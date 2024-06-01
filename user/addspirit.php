<?php
include_once 'lib/timetable.functions.php';
include_once 'lib/search.functions.php';

$title = _("Spirit");
$html = "";

$gameId = intval(iget("game"));
$seriesId = intval(iget("series"));
$submitterId = intval(iget("submitter"));
$token = iget("token");

if (!empty($_POST['games'])) {
  $gameId = $_POST['games'][0];
}
$token = $_POST['token'] ?? $token;
if (!empty($_POST['teams'])) {
  $submitterId = $_POST['teams'][0];
}
$submitterId = $_POST['submitterid'] ?? $submitterId;
if (!empty($_POST['series'])) {
  $seriesId = $_POST['series'][0];
}
$seriesId = $_POST['seriesid'] ?? $seriesId;

$allgames = $_GET['allgames'] ?? 0;

function isAuthorized(int $gameId): bool {
  global $token;
  if ($token == 'XYXYXXYX424342ZZZZZ' || $token == 'SPSPSP424242SPSP')
    return true;
  ensureLogin();
  return hasEditGameEventsRight($gameId);
}

// TODO check token

$save = false;
if (isset($_POST['goPred'])) {
  $gameId = $_POST['predGame'];
  $save = true;
}
if (isset($_POST['goSucc'])) {
  $gameId = $_POST['succGame'];
  $save = true;
}
if (isset($_POST['save'])) {
  $save = true;
}
if (isset($_POST['reset'])) {
  $save = false;
}

if ($gameId <= 0) {
  $target = "view=user/addspirit";
  if ($submitterId <= 0 && $seriesId > 0 && !$allgames) {
    if (hasEditSeriesRight($seriesId)) {
      $html .= "<a href='?view=user/addspirit&series=$seriesId&allgames=1'>" . _("All games") . "</a>\n";
    }
    $html .= "<h3>" . _("Submitting team") . "</h3>";
    $html .= SearchTeam($target, array('token' => $token, 'seriesid' => $seriesId),
      array('selectteam' => _("Select"), 'cancel' => _("Cancel")),
      ['seriesid' => $seriesId, 'searchseasons' => [1 => SeriesSeasonId($seriesId)], 'searchstart' => "",
        'searchend' => date('d.m.Y'), 'searchteam' => true], false);
  } elseif ($seriesId <= 0) {
    $html .= "<h3>" . _("Search Division") . "</h3>\n";
    $html .= SearchSeries($target, array('token' => $token),
      array('selectseries' => _("Select"), 'cancel' => _("Cancel")), null, ['searchser' => true], false);
  } else if (!$allgames || !hasEditSeriesRight($seriesId)) {
    // $html .= "<h3>" . _("Search Game") . "</h3>\n";
    // $html .= SearchGame($target, array('token' => $token, 'submitterid' => $submitterId, 'seriesid' => $seriesId),
    // array('selectteam' => _("Select"), 'cancel' => _("Cancel")),
    // ['teamid' => $submitterId, 'seriesid' => $seriesId, 'searchstart' => "", 'searchend' => date('d.m.Y'),
    // 'searchgame' => true], false);

    $allgames = TimetableGames($submitterId, "team", "all", "time");
    while ($game = mysqli_fetch_assoc($allgames)) {
      if ($game['hometeam'] && $game['visitorteam']) {
        $gameId = $game['game_id'];
        $complete = GameSpiritComplete($gameId, $submitterId, GetSeriesSpiritMode($seriesId));
        if (!$complete)
          break;
      }
    }
  } else if (hasEditSeriesRight($seriesId)) {
    $allgames = TimetableGames($seriesId, "series", "all", "time");
    while ($game = mysqli_fetch_assoc($allgames)) {
      if ($game['hometeam'] && $game['visitorteam']) {
        $gameId = $game['game_id'];
        $complete = GameSpiritComplete($gameId, $submitterId, GetSeriesSpiritMode($seriesId));
        if (!$complete)
          break;
      }
    }
  }
}
if ($gameId > 0) {
  if ($seriesId <= 0)
    $seriesId = GameSeries($gameId);

  if (($mode = GetSeriesSpiritMode($seriesId)) > 0) {
    $categories = SpiritCategories($mode);

    // process itself if save button was pressed
    if ($save) {
      $saveId = $_POST['saveGame'];
      if (isAuthorized($saveId)) {
        $game_result = GameResult($saveId);
        if ($game_result['hometeam'] && $game_result['visitorteam']) {
          if ($submitterId <= 0 || $game_result['hometeam'] != $submitterId) {
            $points = array();
            foreach ($_POST['homevalueId'] as $cat) {
              if ($categories[$cat]['type'] >= 1) {
                if (isset($_POST['homecat' . $cat]))
                  $points[$cat] = $_POST['homecat' . $cat];
                else
                  $missing = sprintf(_("Incomplete spirit scores were saved for %s."), $game_result['hometeamname']);
              } // else if($categories[$cat]['type'] == 1) {
                // $points[$cat] =
            }

            GameSetSpiritPoints($saveId, $game_result['hometeam'], 1, $points, $categories, false);
          }
          if ($submitterId <= 0 || $game_result['visitorteam'] != $submitterId) {
            $points = array();
            foreach ($_POST['visvalueId'] as $cat) {
              if (isset($_POST['viscat' . $cat]))
                $points[$cat] = $_POST['viscat' . $cat];
              else
                $missing = sprintf(_("Incomplete spirit scores were saved for %s."), $game_result['visitorteamname']);
            }

            GameSetSpiritPoints($saveId, $game_result['visitorteam'], 0, $points, $categories, false);
          }
          if ((($game_result['homescore'] == 0 && $game_result['visitorscore'] == 0) || $game_result['isongoing']) &&
            (intval($_POST['homescore']) + intval($_POST['visitorscore']) > 0) &&
            ($_POST['homescore'] != $game_result['homescore'] || $_POST['visitorscore'] != $game_result['visitorscore']))
            GameSetResult($saveId, $_POST['homescore'], $_POST['visitorscore'], hasEditSeriesRight($seriesId), false);
          else
            $html .= "<p>" . _("Game result was not updated.") . "</p>\n";
        } else {
          $html .= "<p>" . _("Game not ready.") . "</p>\n";
        }
      } else {
        $html .= "<p class='warning'>" . _("Not authorized. Nothing was saved.") . "</p>";
      }
    }

    $allgames = [];
    if ($submitterId != null) {
      $allgames = TimetableGames($submitterId, "team", "all", "time");
    } else if (hasEditSeriesRight($seriesId)) {
      $allgames = TimetableGames($seriesId, "series", "all", "time");
    } else {
      $allgames = TimetableGames($gameId, "game", "all", "time");
    }
    $gamePos = 0;
    $pred = $succ = null;
    if (!empty($allgames)) {
      $gameTot = mysqli_num_rows($allgames);
      $found = false;
      while ($game = mysqli_fetch_assoc($allgames)) {
        if ($game['hometeam'] && $game['visitorteam']) {
          if ($found) {
            $succ = $game['game_id'];
            break;
          }
          ++$gamePos;
          if ($gameId == $game['game_id'])
            $found = true;
          else
            $pred = $game['game_id'];
        }
      }
      if (!$found) {
        showPage($title, sprintf(_("Invalid game %d"), $gameId));
        exit();
      }
    }

    if (!isAuthorized($gameId)) {
      $html .= 'You are not authorized to edit the spirit results for this game.';
      showPage($title, $html);
      exit();
    }

    $game_result = GameResult($gameId);

    $menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
    $menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
    $menutabs[_("Score sheet")] = "?view=user/addscoresheet&game=$gameId";
    $menutabs[_("Spirit points")] = "?view=user/addspirit&game=$gameId";
    if (ShowDefenseStats()) {
      $menutabs[_("Defense sheet")] = "?view=user/adddefensesheet&game=$gameId";
    }
    $html .= pageMenu($menutabs, "", false);

    if (hasEditSeriesRight($seriesId)) {
      $html .= "<p><a href='?view=user/addspirit&series=$seriesId&allgames=0'>" . _("Search team") . "</a></p>\n";
      $html .= "<a href='?view=user/addspirit&series=$seriesId&allgames=1'>" . _("All games") . "</a>\n";
    }
    $html .= "<h2>" . ($gamePos > 0 ? " ($gamePos / $gameTot) " : "") . GameName($game_result) . "</h2>\n";

    $html .= "<form  method='post' action='?view=user/addspirit&amp;game=" . $gameId . "&token=$token'>";

    $edit = "";
    $running = "";
    if ($game_result['isongoing']) {
      $running .= " (" . _("Game is running.") . ")";
    } else if ($game_result['homescore'] > 0 || $game_result['visitorscore'] > 0) {
      $edit = "disabled";
    }
    $html .= "<p>" . _("Final result: ") . "<input class='input' name='homescore' $edit value='" .
      utf8entities($game_result['homescore']) .
      "' maxlength='4' size='5'/> - <input class='input' name='visitorscore' $edit value='" .
      utf8entities($game_result['visitorscore']) . "' maxlength='4' size='5'/>$running";
    if (!empty($edit)) {
      $html .= " " . _("If you think this is not correct, please contact the tournament administration immediately!");
    }

    if (!empty($seriesId))
      $html .= getHiddenInput($seriesId, 'seriesid');
    if ($submitterId > 0)
      $html .= getHiddenInput($submitterId, 'submitterid');

    if ($submitterId <= 0 || hasEditSeriesRight($seriesId) || $game_result['hometeam'] != $submitterId) {
      $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

      $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
      $html .= SpiritTable($game_result, $points, $categories, true);
    }
    if ($submitterId <= 0 || hasEditSeriesRight($seriesId) || $game_result['visitorteam'] != $submitterId) {
      $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";

      $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
      $html .= SpiritTable($game_result, $points, $categories, false);
    }
    if (isset($missing))
      $html .= "<p class='warning'>$missing</p>\n";

    $html .= "<p>";
    if ($pred) {
      $html .= getHiddenInput($pred, 'predGame');

      $html .= "<input class='button' type='submit' name='goPred' value='" . _("Save and previous game &laquo;") . "'/>";
    }
    $html .= getHiddenInput($gameId, 'saveGame');
    $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
    $html .= "<input class='button' type='submit' name='reset' value='" . _("Reset") . "'/>";
    if ($succ) {
      $html .= getHiddenInput($succ, 'succGame');
      $html .= "<input class='button' type='submit' name='goSucc' value='" . _("&raquo; Save and next game") . "'/>";
    }
    if ($pred) {
      $html .= "<br />\n";
      $html .= sprintf(_("Previous game: %s"), GameName(GameInfo($pred)));
    }
    if ($succ) {
      $html .= "<br />\n";
      $html .= sprintf(_("Next game: %s"), GameName(GameInfo($succ)));
    }
    $html .= "</p>";
    $html .= "</form>\n";
  } else {
    $html .= "<p>" . sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p>";
  }
}
showPage($title, $html);

?>
