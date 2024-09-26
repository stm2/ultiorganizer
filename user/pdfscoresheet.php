<?php
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/timetable.functions.php';

include_once $include_prefix . 'lib/search.functions.php';

$title = _("Score sheets");
$html = "<h1>" . utf8entities($title) . "</h2>\n";
$create = _("Create");
$subject = "";

include_once 'cust/default/pdfprinter.php';

$season = "";
$filter1 = "";
$filter2 = "";
$gameId = 0;
$teamId = 0;
$seriesId = 0;

if (!empty($_POST)) {
  $source = &$_POST;
} else {
  $source = &$_GET;
}

/**
 * Modes:
 * <li>team=teamid: print only team roster
 * <li>game=gameid : print game roster and score sheets
 * <li>series=seriesid: print series games rosters (if available) and score sheets
 * <li>pool=poolid: print pool games rosters (if available) and score sheets
 * <li>reservation=res: print responsible games in season at reservation
 * <li>group=group: print games of grouping
 * <br>
 * Optional Parameters:
 * <li>season=seasonid (default: current)
 * <li>filter1=coming (only group: upcoming games only)
 * <li>filter2=teams: exclude teams with scheduling names
 */

if (!empty($source["game"])) {
  $gameId = $source["game"];
  $games = DBResourceToArray(TimetableGames($source["game"], "game", "all", "place"));
  $subject = sprintf(_("Game %s"), GameName(GameInfo($gameId)));
}

if (!empty($source["games"])) {
  $gameId = implode(',', $source["games"]);
  $games = [];
  $subject = " ";
  foreach ($source['games'] as $gg) {
    $games[] = DBResourceToArray(TimetableGames($gg, "game", "all", "place"))[0];

    $gamename = GameName(GameInfo($gg));
    if (empty($subject))
      $subject .= $gamename;
    else if (mb_strlen($subject) < 80)
      $subject .= ", " . $gamename;
  }
  $subject = _("Game") . " " . $subject;
}

if (!empty($source["season"])) {
  $season = $source["season"];
} else {
  $season = CurrentSeason();
}

if (!empty($source["series"])) {
  $seriesId = $source["series"];
  $subject = "";
  if (gettype($seriesId) == 'array') {
    foreach ($seriesId as $sr) {
      if (empty($subject))
        $subject .= SeriesName($sr);
      else if (mb_strlen($subject) < 80)
        $subject .= ", " . SeriesName($sr);
    }
    $subject = _("Series") . " " . $subject;
  } else {
    $subject = sprintf(_("Series %s"), SeriesName($seriesId));
  }
}

if (!empty($source["pool"])) {
  $poolId = $source["pool"];
  $subject = sprintf(_("Pool %s"), PoolName($poolId));
  $games = DBResourceToArray(TimetableGames($poolId, "pool", "all", "time", ""));
}

if (!empty($source["pools"])) {
  $poolId = $source["pools"];

  $subject = "";
  $games = [];
  foreach ($poolId as $pp) {
    $games = array_merge($games, DBResourceToArray(TimetableGames($pp, "pool", "all", "time", "")));
    if (empty($subject))
      $subject .= PoolName($pp);
    else if (mb_strlen($subject) < 80)
      $subject .= ", " . PoolName($pp);
  }

  $subject = _("Pool") . " " . $subject;
}

if (!empty($source["filter1"])) {
  $filter1 = $source["filter1"];
}

if (!empty($source["filter2"])) {
  $filter2 = $source["filter2"];
}

if (!empty($source["reservation"])) {
  if ($source['reservation'] == "none") {
    $subject = _("Without reservation");
  } else {
    $reservationname = html_entity_decode(ReservationName(ReservationInfo($source['reservation'])));
    $subject = sprintf(_("Reservation %s"), $reservationname);
  }
  $gameResponsibilities = GameResponsibilities($season);
  $games = DBResourceToArray(
    ResponsibleReservationGames($source["reservation"] == "none" ? null : $source["reservation"], $gameResponsibilities));
}

if (!empty($source["reservations"])) {
  $gameResponsibilities = GameResponsibilities($season);
  $games = [];
  $subject = "";
  if (!empty($gameResponsibilities)) {
    foreach ($source['reservations'] as $rr) {
      $games = array_merge($games,
        DBResourceToArray(ResponsibleReservationGames($rr == "none" ? null : $rr, $gameResponsibilities)));

      $reservationname = html_entity_decode(ReservationName(ReservationInfo($rr)));
      if (empty($subject))
        $subject .= $reservationname;
      else if (mb_strlen($subject) < 80)
        $subject .= ", " . $reservationname;
    }
    mergesort($games,
      uo_create_multi_key_comparator([['placename', true, true], ['fieldname', true, true], ['time', true, true]])); 
    $subject = _("Reservations") . " " . $subject;
  } else {
    $subject = _("No reservations for this user!");
  }
}

if (!empty($source["group"])) {
  $subject = sprintf(_("Group %s"), $source['group']);
  if ($filter1 == "coming") {
    $games = TimetableGames($season, "season", "coming", "places", $source["group"]);
    $subject .= ", " . _("upcoming");
  } else {
    $games = TimetableGames($season, "season", "all", "places", $source["group"]);
  }
  $games = DBResourceToArray($games);
}

if (!empty($source["team"])) {
  $teamId = $source["team"];
  $subject = sprintf(_("Team %s"), TeamName($teamId));
}

if (!empty($source["teams"])) {
  $teamId = $source["teams"];

  $subject = "";
  foreach ($teamId as $tt)
    if (empty($subject))
      $subject .= TeamName($tt);
    else if (mb_strlen($subject) < 80)
      $subject .= ", " . TeamName($tt);

  $subject = _("Teams") . " " . $subject;
}

if (!empty($source['empty'])) {
  $num = (int) $source['num_games'];
  $games = [];
  for ($i = 0; $i < $num; $i++) {
    $games[] = null; // DBResourceToArray(TimetableGames(null, null, 'all'))[0];
  }
}

function modeSelect($default) {
  $html = "<select class='dropdown' name='roster'>";
  if ($default)
    $html .= "<option value='default_roster'>" . utf8entities(_("print rosters if teams have rosters set")) .
      "</option>\n";
  $html .= "<option value='force_roster'>" . utf8entities(_("always print rosters")) . "</option>\n";
  $html .= "<option value='no_roster'>" . utf8entities(_("never print rosters")) . "</option>\n";
  $html .= "</select><br />\n";

  $html .= "<select class='dropdown' name='sheet_type'>";
  $html .= "<option value='short'>" . utf8entities(_("short sheets (multiple games per page)")) . "</option>\n";
  $html .= "<option value='long'>" . utf8entities(_("long sheets (one game per page)")) . "</option>\n";
  $html .= "</select><br />\n";

  $html .= "<p><label for='split_fields'>" . _("Fields on separate pages") . "</label>";
  $html .= "<input class='input' type='checkbox' id='split_fields' name='split_fields' checked='checked'/></p>\n";
  
  return $html;
}

function searchModes() {
  global $season;
  $html = "";
  $seasonRef = utf8entities($season);
  $html .= "<hr /><ul>";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=pool'>" .
    utf8entities(_("Score sheets for a pool ")) . "</a></li>\n";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=reservation'>" .
    utf8entities(_("Score sheets for a reservation")) . "</a></li>\n";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=game'>" .
    utf8entities(_("Score sheets for a game")) . "</a></li>\n";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=empty'>" .
    utf8entities(_("Blank score sheets")) . "</a></li>\n";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=team'>" .
    utf8entities(_("Roster for a team")) . "</a></li>\n";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=division'>" .
    utf8entities(_("Rosters for a division")) . "</a></li>\n";
  $html .= "</ul>";

  return $html;
}

if (isset($_POST['create'])) {

  $pdf = new UltiPDF();
  $printed = false;
  if ($teamId) {

    function print_team_roster($teamId) {
      global $pdf;
      $teaminfo = TeamInfo($teamId);
      $players = array();
      if ($result = TeamPlayerList($teamId)) {
        while ($row = mysqli_fetch_assoc($result)) {
          $players[] = $row;
        }
      }
      $pdf->PrintRoster($teaminfo['name'], $teaminfo['seriesname'], $teaminfo['poolname'], $players);
    }
    if (gettype($teamId) == 'array') {
      foreach ($teamId as $tt) {
        print_team_roster($tt);
        $printed = true;
      }
    } else {
      print_team_roster($teamId);
      $printed = true;
    }
  } elseif ($seriesId) {

    function print_series_rosters($seriesId) {
      global $pdf;
      $teams = SeriesTeams($seriesId, true);
      if (empty($teams))
        return false;
      foreach ($teams as $team) {
        $teaminfo = TeamInfo($team['team_id']);
        $players = array();
        if ($result = TeamPlayerList($team['team_id'])) {
          while ($row = mysqli_fetch_assoc($result)) {
            $players[] = $row;
          }
        }
        $pdf->PrintRoster($teaminfo['name'], $teaminfo['seriesname'], $teaminfo['poolname'], $players);
      }

      return true;
    }
    if (gettype($seriesId) == "array") {
      foreach ($seriesId as $sr)
        $printed |= print_series_rosters($sr);
    } else {
      $printed |= print_series_rosters($seriesId);
    }
  } else {
    $seasonname = $_POST['season_name'] ?? SeasonName($season);

    $printlist = $_POST['roster'] == 'force_roster';
    if ($_POST['roster'] != 'no_roster') {
      foreach ($games as &$gameRow) {
        if ($gameRow == null)
          continue;
        $homeplayers = array();

        $playerlist = TeamPlayerList($gameRow["hometeam"]);
        $i = 0;
        while ($player = mysqli_fetch_assoc($playerlist)) {
          $homeplayers[$i]['name'] = $player['firstname'] . " " . $player['lastname'];
          $homeplayers[$i]['accredited'] = $player['accredited'];
          $homeplayers[$i]['num'] = $player['num'];
          $i++;
        }
        $printlist |= $i > 0;
        $gameRow['homeplayers'] = $homeplayers;

        $visitorplayers = array();
        $playerlist = TeamPlayerList($gameRow["visitorteam"]);
        $i = 0;
        while ($player = mysqli_fetch_assoc($playerlist)) {
          $visitorplayers[$i]['name'] = $player['firstname'] . " " . $player['lastname'];
          $visitorplayers[$i]['accredited'] = $player['accredited'];
          $visitorplayers[$i]['num'] = $player['num'];
          $i++;
        }
        $printlist |= $i > 0;
        $gameRow['visitorplayers'] = $visitorplayers;
      }
      unset($gameRow);
    }

    if ($filter2 == "teams") {
      foreach ($games as $key => $gameRow) {
        if (!$gameRow['hometeam'] || !$gameRow['visitorteam']) {
          unset($games[$key]);
        }
      }
    }

    if ($_POST['sheet_type'] == 'long') {
      foreach ($games as $gameRow) {
        $printed = true;
        if ($gameRow == null) {
          $sGid = '';
          $home = $visitor = $poolname = $time = $placename = null;
        } else {
          $sGid = $gameRow['game_id'];
          // $sGid .= getChkNum($sGid);

          $home = empty($gameRow["hometeamname"]) ? U_($gameRow["phometeamname"]) : $gameRow["hometeamname"];
          $visitor = empty($gameRow["visitorteamname"]) ? U_($gameRow["pvisitorteamname"]) : $gameRow["visitorteamname"];
          $poolname = U_($gameRow['seriesname']) . ", " . U_($gameRow['poolname']);
          $time = $gameRow["time"];
          $placename = U_($gameRow["placename"]) . " " . _("Field") . " " . U_($gameRow['fieldname']);
        }
        $pdf->PrintScoreSheet(U_($seasonname), $sGid, $home, $visitor, $poolname, $time, $placename);

        if ($printlist)
          $pdf->PrintPlayerList($gameRow['homeplayers'] ?? [], $gameRow['visitorplayers'] ?? []);
      }
    } else {
      $printed = true;
      $pdf->PrintShortScoreSheets(U_($seasonname), $games, isset($_POST['split_fields']));
    }
  }

  if ($printed)
    $pdf->Output();
  else {
    $html = "<p>" . utf8entities(_("No games selected")) . "</p>\n";
    $html .= searchModes();
    showPage($title, $html);
  }
} else if ((isset($_POST['submit']) || !isset($_GET['search']))) {
  $html .= "<p>" . utf8entities(SeasonName($season)) . "<br/>";

  if (!empty($subject)) {
    $html .= utf8entities($subject) . "</p>";

    $html .= "<form method='post' action='?view=user/pdfscoresheet'>\n";
    if (!empty($_GET)) {
      $html .= getHiddenInput($_GET);
    }
    if (!empty($_POST)) {
      $html .= getHiddenInput($_POST);
    }

    $html .= modeSelect(true);

    $html .= "<input type='submit' name='create' value='" . utf8entities($create) . "'/>\n";
    $html .= "</form>";
  } else {
    $html .= utf8entities(_("No games selected")) . "</p>\n";
  }

  $html .= searchModes();

  showPage($title, $html);
} else {

  $mode = $_GET['search'];
  if (empty($source))
    $source = [];

  if ($mode == 'empty') {
    $html .= "<h2>" . _("Empty Sheets") . "</h2>";
    $html .= "<form method='post' action='?view=user/pdfscoresheet'>\n";
    // if (!empty($_GET)) {
    // $html .= getHiddenInput($_GET);
    // }
    // if (!empty($_POST)) {
    // $html .= getHiddenInput($_POST);
    // }

    $html .= getHiddenInput('1', 'empty');
    $html .= "<input name='season_name' value='" . utf8entities(SeasonName($season)) . "'/><br />\n";
    $html .= "<input type='number' min=0 name='num_games' value='4' /><br />\n";

    $html .= modeSelect(false);

    $html .= "<input type='submit' name='create' value='" . utf8entities($create) . "'/>\n";
    $html .= "</form>";
  } else if ($mode == 'pool') {
    $html .= "<h2>" . _("Search Pools") . "</h2>";
    $html .= SearchPool('view=user/pdfscoresheet', $source, ['submit' => $create]);
  } else if ($mode == 'reservation') {
    $html .= "<h2>" . _("Search Reservations") . "</h2>";
    $html .= SearchReservation('view=user/pdfscoresheet', $source, ['submit' => $create], $season);
  } else if ($mode == 'game') {
    $html .= "<h2>" . _("Search Games") . "</h2>";
    $html .= SearchGame('view=user/pdfscoresheet', $source, ['submit' => $create]);
  } else if ($mode == 'team') {
    $html .= "<h2>" . _("Search Teams") . "</h2>";
    $html .= SearchTeam('view=user/pdfscoresheet', $source, ['submit' => $create], []);
  } else if ($mode == 'division') {
    $html .= "<h2>" . _("Search Divisions") . "</h2>";
    $html .= SearchSeries('view=user/pdfscoresheet', $source, ['submit' => $create], $season);
  } else {
    die("invalid parameter");
  }

  $html .= searchModes();

  showPage($title, $html);
}
?>
