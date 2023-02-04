<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/timetable.functions.php';


$body = @file_get_contents('php://input');
//alternative way for IIS if above command fail
//set in php.ini: always_populate_raw_post_data = On
//$body = $HTTP_RAW_POST_DATA; 

$season = "";
$serieses = array();
$response = "";

$places = explode("|", $body);
foreach ($places as $placeGameStr) {
	$games = explode(":", $placeGameStr);
	if (intval($games[0]) != 0) {
		
		ClearReservation($games[0]);
		$resInfo = ReservationInfo($games[0]);
		$firstStart = strtotime($resInfo['starttime']);
		$gameEnd = $firstStart;
		$resEnd = strtotime($resInfo['endtime']);
		for ($i=1; $i < count($games); $i++) {
			$gameArr = explode("/", $games[$i]);
			$gameInfo = GameInfo($gameArr[0]);
			$season = $gameInfo['season'];
			$serieses[$gameInfo['series']] = 1;
			$time = $firstStart + (60 * $gameArr[1]);
			$duration = gameDuration($gameInfo);
			$gameEnd += $duration * 60;
			if ($gameEnd > $resEnd) {
			  $response .= "<p>" .sprintf(_("Game %s exceeds reserved time %s."), GameName($gameInfo), ShortTimeFormat($resInfo['endtime'])) ."</p>";
			}
			ScheduleGame($gameArr[0], $time, $games[0]);
		} 
	} else {
		for ($i=1; $i < count($games); $i++) {
			$gameArr = explode("/", $games[$i]);
			$gameInfo = GameInfo($gameArr[0]);
			$season = $gameInfo['season'];
			$serieses[$gameInfo['series']] = 1;
			UnScheduleGame($gameArr[0]);	
		}
	} 
	
}

if ($season) {
  
  $movetimes = TimetableMoveTimes($season);
  foreach ($serieses as $series => $dummy) {
    $conflicts = TimetableIntraPoolConflicts($season, $series);
    
    foreach ($conflicts as $conflict) {
      if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
        if (strtotime($conflict['time1']) + $conflict['slot1'] * 60 +
             TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'], 
                $conflict['field2']) > strtotime($conflict['time2'])) {
          $game1 = GameInfo($conflict['game1']);
          $game2 = GameInfo($conflict['game2']);
          $response .= "<p>" . sprintf(
              _("Warning: Game %s (%d, pool %d) has a scheduling conflict with %s (%d, pool %d).") . "</p>", 
              utf8entities(GameName($game2)), (int) $game2['game_id'], (int) $game2['pool'], 
              utf8entities(GameName($game1)), (int) $game1['game_id'], (int) $game1['pool']) . "</p>";
          break;
        }
      }
    }
    
    if (empty($response)) {
      $conflicts = TimetableInterPoolConflicts($season, $series);
      
      foreach ($conflicts as $conflict) {
        if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
          if (strtotime($conflict['time1']) + $conflict['slot1'] * 60 +
               TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'], 
                  $conflict['field2']) > strtotime($conflict['time2'])) {
            $game1 = GameInfo($conflict['game1']);
            $game2 = GameInfo($conflict['game2']);
            $response .= "<p>" . sprintf(_("Warning: Game %s has a scheduling conflict with %s."), 
                utf8entities(GameName($game2)), utf8entities(GameName($game1))) . "</p>";
            break;
          }
        }
      }
    }
  }
}

if (!empty($response)) {
  echo "<p>"._("Schedule saved with errors:")."</p>\n". $response;
  responseValue(-1);
} else {
  echo _("Schedule saved and checked.");
  responseValue(1);
}

function responseValue($value) {
  echo "<input type='hidden' id='responseValue' value='$value' />";
}
?>
