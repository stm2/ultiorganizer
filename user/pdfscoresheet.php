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

if (is_file('cust/' . CUSTOMIZATIONS . '/pdfprinter.php')) {
  include_once 'cust/' . CUSTOMIZATIONS . '/pdfprinter.php';
} else {
  include_once 'cust/default/pdfprinter.php';
}
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
  foreach ($source['reservations'] as $rr) {
    $games = array_merge($games,
      DBResourceToArray(ResponsibleReservationGames($rr == "none" ? null : $rr, $gameResponsibilities)));

    $reservationname = html_entity_decode(ReservationName(ReservationInfo($source['reservation'])));
    if (empty($subject))
      $subject .= $reservationname;
    else if (mb_strlen($subject) < 80)
      $subject .= ", " . $reservationname;
  }
  $subject = _("Reservations") . " " . $subject;
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
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=team'>" .
    utf8entities(_("Roster for a team")) . "</a></li>\n";
  $html .= "<li><a href='?view=user/pdfscoresheet&amp;season=$seasonRef&amp;search=division'>" .
    utf8entities(_("Rosters for a division")) . "</a></li>\n";
  $html .= "</ul>";
  return $html;
}

if (isset($_POST['create'])) {

  $pdf = new PDF();
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
    $seasonname = SeasonName($season);

    $printlist = $_POST['roster'] == 'force_roster';
    if ($_POST['roster'] != 'no_roster') {
      foreach ($games as &$gameRow) {
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

    // FIXME!!
    if (false) {
      foreach ($games as $gameRow) {
        $printed = true;

        $sGid = $gameRow['game_id'];
        // $sGid .= getChkNum($sGid);

        $home = empty($gameRow["hometeamname"]) ? U_($gameRow["phometeamname"]) : $gameRow["hometeamname"];
        $visitor = empty($gameRow["visitorteamname"]) ? U_($gameRow["pvisitorteamname"]) : $gameRow["visitorteamname"];

        $pdf->PrintScoreSheet(U_($seasonname), $sGid, $home, $visitor,
          U_($gameRow['seriesname']) . ", " . U_($gameRow['poolname']), $gameRow["time"],
          U_($gameRow["placename"]) . " " . _("Field") . " " . U_($gameRow['fieldname']));

        if ($printlist)
          $pdf->PrintPlayerList($gameRow['homeplayers'], $gameRow['visitorplayers']);
      }
    } else {
      $printed = true;

      $sGid = $gameRow['game_id'];
      // $sGid .= getChkNum($sGid);

      $home = empty($gameRow["hometeamname"]) ? U_($gameRow["phometeamname"]) : $gameRow["hometeamname"];
      $visitor = empty($gameRow["visitorteamname"]) ? U_($gameRow["pvisitorteamname"]) : $gameRow["visitorteamname"];

      $pdf->PrintScoreSheet(U_($seasonname), $sGid, $home, $visitor,
        U_($gameRow['seriesname']) . ", " . U_($gameRow['poolname']), $gameRow["time"],
        U_($gameRow["placename"]) . " " . _("Field") . " " . U_($gameRow['fieldname']));

      if ($printlist)
        $pdf->PrintPlayerList($gameRow['homeplayers'], $gameRow['visitorplayers']);
    }
  }

  if ($printed)
    $pdf->Output();
  else {
    $html = "<p>" . utf8entities(_("No games selected")) . "</p>\n";
    $html .= searchModes();
    showPage($title, $html);
  }
} else if (isset($_POST['submit']) || !isset($_GET['search'])) {
  $html .= "<p>" . utf8entities(SeasonName($season)) . "<br/>" . utf8entities($subject) . "</p>";
  $html .= "<form method='post' action='?view=user/pdfscoresheet'>\n";
  if (!empty($_GET)) {
    $html .= getHiddenInput($_GET);
  }
  if (!empty($_POST)) {
    $html .= getHiddenInput($_POST);
  }

  $html .= "<fieldset>";
  $html .= "<p><input type='radio' checked id='default_roster' name='roster' value='default_roster' />";
  $html .= "<label for='default_roster'>" . _("Print rosters if teams have rosters set.") . "</label></p>\n";
  $html .= "<p><input type='radio' id='force_roster' name='roster' value='force_roster' />";
  $html .= "<label for='force_roster'>" . _("Always print rosters.") . "</label></p>\n";
  $html .= "<p><input type='radio' id='no_roster' name='roster' value='no_roster' />";
  $html .= "<label for='no_roster'>" . _("Never print rosters.") . "</label></p>\n";
  $html . "</fieldset>\n";

  $html .= "<input type='submit' name='create' value='" . utf8entities($create) . "'/>\n";
  $html .= "</form>";

  $html .= searchModes();

  showPage($title, $html);
} else {

  $mode = $_GET['search'];
  if (empty($source))
    $source = [];

  if ($mode == 'pool') {
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
    $html .= SearchTeam('view=user/pdfscoresheet', $source, ['submit' => $create]);
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
