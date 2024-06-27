<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/timetable.functions.php';

$body = @file_get_contents('php://input');
// alternative way for IIS if above command fail
// set in php.ini: always_populate_raw_post_data = On
// $body = $HTTP_RAW_POST_DATA;

$season = "";
$serieses = array();
$response1 = "";

$places = explode("|", $body);
$saveErrors = array();
foreach ($places as $placeGameStr) {
  $games = explode(":", $placeGameStr);

  if (intval($games[0]) != 0) {
    ClearReservation($games[0]);
    $resInfo = ReservationInfo($games[0]);
    $firstStart = strtotime($resInfo['starttime']);
    $gameEnd = $firstStart;
    $resEnd = strtotime($resInfo['endtime']);
    for ($i = 1; $i < count($games); $i++) {
      $gameArr = explode("/", $games[$i]);
      $gameInfo = GameInfo($gameArr[0]);
      $season = $gameInfo['season'];
      $serieses[$gameInfo['series']] = 1;
      $time = $firstStart + (60 * $gameArr[1]);
      $duration = gameDuration($gameInfo);
      $gameEnd += $duration * 60;
      if ($gameEnd > $resEnd) {
        $response1 .= "<p>" .
          sprintf(_("Game %s exceeds reserved time %s."), GameName($gameInfo), ShortTimeFormat($resInfo['endtime'])) .
          "</p>";
      }
      $err = ScheduleGame($gameArr[0], $time, $games[0]);
      if ($err) {
        $err['game'] = GameName($gameInfo);
        $saveErrors[] = $err;
      }
    }
  } else {
    for ($i = 1; $i < count($games); $i++) {
      $gameArr = explode("/", $games[$i]);
      $gameInfo = GameInfo($gameArr[0]);
      $season = $gameInfo['season'];
      $serieses[$gameInfo['series']] = 1;
      $err = UnScheduleGame($gameArr[0]);
      if ($err) {
        $err['game'] = GameName($gameInfo);
        $saveErrors[] = $err;
      }
    }
  }
}
if (!empty($saveErrors)) {
  $response1 .= "<p>";
  foreach ($saveErrors as $err) {
    $response1 .= sprintf(_("Game %s could not be scheduled."), $err['game']) . "<br />";
  }
  $response1 .= "</p>";
}

$response2 = '';
if ($season) {
//   $movetimes = TimetableMoveTimes($season);
  foreach ($serieses as $series => $dummy) {
    $conflicts = TimetableIntraPoolConflicts($season, $series);

    foreach ($conflicts as $conflict) {
//       if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
//         if (strtotime($conflict['time1']) + $conflict['slot1'] * 60 +
//           TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'],
//             $conflict['field2']) > strtotime($conflict['time2'])) {
          $game1 = GameInfo($conflict['game1']);
          $game2 = GameInfo($conflict['game2']);
          $response2 .= "<p>" .
            sprintf(_("Warning: Game %s (%d, pool %d) has a scheduling conflict with %s (%d, pool %d).") . "</p>",
              utf8entities(GameName($game2)), (int) $game2['game_id'], (int) $game2['pool'],
              utf8entities(GameName($game1)), (int) $game1['game_id'], (int) $game1['pool']) . "</p>";
          break;
//         }
//       }
    }

    if (empty($response2)) {
      $conflicts = TimetableInterPoolConflicts($season, $series);

      foreach ($conflicts as $conflict) {
//         if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
//           if (strtotime($conflict['time1']) + $conflict['slot1'] * 60 +
//             TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'],
//               $conflict['field2']) > strtotime($conflict['time2'])) {
            $game1 = GameInfo($conflict['game1']);
            $game2 = GameInfo($conflict['game2']);
            $response2 .= "<p>" .
              sprintf(_("Warning: Game %s (%d, pool %d) has a pool scheduling conflict with %s (%d pool %d)."), utf8entities(GameName($game2)), (int) $game2['game_id'], (int) $game2['pool'], 
                utf8entities(GameName($game1)), (int) $game1['game_id'], (int) $game1['pool']) . "</p>";
            break;
//           }
//         }
      }
    }
  }
}

if (!empty($response1) || !empty($response2)) {
  if ($saveErrors) {
    echo "<p>" . _("Some games were not saved! Errors:") . "</p>\n" . $response1 . $response2;
  } else {
    echo "<p>" . _("Schedule saved with errors:") . "</p>\n" . $response1 . $response2;
  }
  responseValue(-1);
} else {
  echo _("Schedule saved and checked.");
  responseValue(1);
}

function responseValue($value) {
  echo "<input type='hidden' id='responseValue' value='$value' />";
}
?>