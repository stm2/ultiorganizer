<?php
include_once 'lib/search.functions.php';

$html = "";

$gameId = intval(iget("game"));
$seriesId = intval(iget("series"));
$teamId = intval(iget("team"));
$token = intval(iget("token"));

if (!empty($_POST['games'])) {
  $gameId = $_POST['games'][0];
}
$token = $_POST['token'] ?? $token;
if (!empty($_POST['teams'])) {
  $teamId = $_POST['teams'][0];
}
$teamId = $_POST['teamid'] ?? $teamId;
if (!empty($_POST['series'])) {
  $seriesId = $_POST['series'][0];
}
$seriesId = $_POST['seriesid'] ?? $seriesId;

ensureLogin();
if (!hasEditGameEventsRight($gameId)) {
  $html .= 'You are not authorized to edit the spirit results for this game.';
}

// TODO check token

$title = _("Spirit");

if ($gameId <= 0) {
  $target = "view=user/addspirit";
  if ($teamId <= 0 && $seriesId > 0) {
    $html .= "<h3>" . _("Search Team") . "</h3>";
    $html .= SearchTeam($target, array('token' => $token, 'seriesid' => $seriesId),
      array('selectteam' => _("Select"), 'cancel' => _("Cancel")),
      ['seriesid' => $seriesId, 'searchstart' => "", 'searchend' => date('d.m.Y'), 'searchteam' => true], false);
  } elseif ($seriesId <= 0) {
    $html .= "<h3>" . _("Search Division") . "</h3>\n";
    $html .= SearchSeries($target, array('token' => $token),
      array('selectseries' => _("Select"), 'cancel' => _("Cancel")), null,
      [ 'searchser' => true], false);
  } else {
    $html .= "<h3>" . _("Search Game") . "</h3>\n";
    $html .= SearchGame($target, array('token' => $token, 'teamid' => $teamId, 'seriesid' => $seriesId),
      array('selectteam' => _("Select"), 'cancel' => _("Cancel")),
      ['teamid' => $teamId, 'seriesid' => $seriesId, 'searchstart' => "", 'searchend' => date('d.m.Y'),
        'searchgame' => true], false);
  }
} else {
  $season = SeasonInfo(GameSeason($gameId));
  if ($season['spiritmode'] > 0) {
    $game_result = GameResult($gameId);
    $mode = SpiritMode($season['spiritmode']);
    $categories = SpiritCategories($mode['mode']);

    // process itself if save button was pressed
    if (!empty($_POST['save'])) {
      if ($teamId <= 0 || $game_result['hometeam'] == $teamId) {
        $points = array();
        foreach ($_POST['homevalueId'] as $cat) {
          if ($categories[$cat]['type'] >= 1) {
            if (isset($_POST['homecat' . $cat]))
              $points[$cat] = $_POST['homecat' . $cat];
            else
              $missing = sprintf(_("Missing score for %s."), $game_result['hometeamname']);
          } // else if($categories[$cat]['type'] == 1) {
            // $points[$cat] =
        }

        GameSetSpiritPoints($gameId, $game_result['hometeam'], 1, $points, $categories);
      }
      if ($teamId <= 0 || $game_result['visitorteam'] == $teamId) {
        $points = array();
        foreach ($_POST['visvalueId'] as $cat) {
          if (isset($_POST['viscat' . $cat]))
            $points[$cat] = $_POST['viscat' . $cat];
          else
            $missing = sprintf(_("Missing score for %s."), $game_result['visitorteamname']);
        }

        GameSetSpiritPoints($gameId, $game_result['visitorteam'], 0, $points, $categories);

        $game_result = GameResult($gameId);
      }
    }

    $menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
    $menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
    $menutabs[_("Score sheet")] = "?view=user/addscoresheet&game=$gameId";
    $menutabs[_("Spirit points")] = "?view=user/addspirit&game=$gameId";
    if (ShowDefenseStats()) {
      $menutabs[_("Defense sheet")] = "?view=user/adddefensesheet&game=$gameId";
    }
    $html .= pageMenu($menutabs, "", false);

    $html .= "<p>" . ShortDate($game_result['time']) . " " . DefHourFormat($game_result['time']) .
      " <em>{$game_result['hometeamname']} - {$game_result['visitorteamname']}</em>  ${game_result['homescore']} - {$game_result['visitorscore']}</p>\n";

    $html .= "<form  method='post' action='?view=user/addspirit&amp;game=" . $gameId . "'>";
    $html .= getHiddenInput($token, 'token');
    $html .= getHiddenInput($seriesId, 'seriesid');
    $html .= getHiddenInput($teamId, 'teamid');

    if ($teamId <= 0 || $game_result['hometeam'] == $teamId) {
      $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['hometeamname']) . "</h3>\n";

      $points = GameGetSpiritPoints($gameId, $game_result['hometeam']);
      $html .= SpiritTable($game_result, $points, $categories, true);
    }
    if ($teamId <= 0 || $game_result['visitorteam'] == $teamId) {
      $html .= "<h3>" . _("Spirit points given for") . ": " . utf8entities($game_result['visitorteamname']) . "</h3>\n";

      $points = GameGetSpiritPoints($gameId, $game_result['visitorteam']);
      $html .= SpiritTable($game_result, $points, $categories, false);
    }
    $html .= "<p>";
    $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";
    if (isset($missing))
      $html .= " $missing ";
    $html .= "</p>";
    $html .= "</form>\n";
  } else {
    $html .= "<p>" . sprintf(_("Spirit points not given for %s."), utf8entities($season['name'])) . "</p>";
  }
}
showPage($title, $html);

?>
