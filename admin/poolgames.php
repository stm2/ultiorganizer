<?php
include_once 'lib/pool.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/reservation.functions.php';


$poolId = $_GET["pool"];
$season = $_GET["season"];
$rounds = 1;
$title = U_(PoolSeriesName($poolId) . ", " . U_(PoolName($poolId))) . ": " . _("Games");
$poolInfo = PoolInfo($poolId);
$usepseudoteams = PseudoTeamsOnly($poolId);
$generatedgames = array();
$nomutual = 0;
$html = "";

// common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu();
contentStart();

$html .= "<h2>" . U_(PoolName($poolId)) . "</h2>";

function askBye($season, $poolId, $poolInfo, $rounds, $homeresp, $nomutual, $action, $generate = false) {
  global $html;
  if (empty($_POST['byename'])) {
    $html .= "<p>" . _("The number of teams in a Swiss-draw pool should be even. We must add a BYE.") . "</p>";
    $seaValue = urlencode($season);
    $html .= "<form method='post' action='?view=admin/poolgames&amp;season=$seaValue&amp;pool=$poolId'>";
    $byeName = sprintf(_("%s BYE"), U_(PoolName($poolId)));
    $html .= "<input type='hidden' name='rounds' value='$rounds'/>\n";
    $html .= "<input type='hidden' name='homeresp' value='$homeresp'/>\n";
    $html .= "<input type='hidden' name='nomutual' value='$nomutual'/>\n";
    $html .= "<p>" . _("Name of BYE team") . ": <input class='input' name='byename' value='$byeName'/></p>\n";
    $html .= "<input type='submit' name='$action' value='" . _("Add BYE") . "'/></p>";
    $html .= "</form>\n";
    return array(-2);
  } else {
    $byeName = $_POST['byename'];
    $query = sprintf("SELECT max(`rank`) FROM uo_team_pool WHERE pool=%d", $poolId);
    if ($poolInfo['continuingpool'] == 0) {
      $byeId = AddTeam(
        array("name" => $byeName, "series" => $poolInfo['series'], "pool" => $poolId, "rank" => "", "valid" => "2"));
      $maxrank = DBQueryToValue($query);
      PoolAddTeam($poolId, $byeId, $maxrank + 1);
    } else {
      $byePool = AddByePool(sprintf(_("%s - invisible, do not delete"), $byeName), 3, $poolInfo['ordering'],
        $poolInfo['series']);
      $byeId = AddTeam(
        array("name" => $byeName, "series" => $poolInfo['series'], "pool" => $byePool, "rank" => "0", "valid" => "2"));
      PoolAddTeam($byePool, $byeId, 1, true);
      $maxrank = PoolMaxMoveToPool($poolId);
      PoolAddMove($byePool, $poolId, 1, $maxrank + 1, $byeName);
      $move = PoolGetMoveToPool($byePool, 1);
      PoolMakeAMove($byePool, 1, true, $poolId, $maxrank + 1, true, $move['scheduling_id']);
    }
    $generatedgames = GenerateGames($poolId, $rounds, $generate, $nomutual, $homeresp);
    return $generatedgames;
  }
}

$hasGames = $poolInfo['type'] == 1 || $poolInfo['type'] == 2 || $poolInfo['type'] == 3 || $poolInfo['type'] == 4;

// process itself on submit
if (!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  $ok = true;

  // run some test to for safe deletion
  $goals = GameAllGoals($id);
  if (mysqli_num_rows($goals)) {
    $html .= "<p class='warning'>" . _("Game has") . " " . mysqli_num_rows($goals) . " " . _("goals") . ". " .
      _("Goals must be removed before removing the team") . ".</p>";
    $ok = false;
  }
  if ($ok)
    DeleteGame($id);
} elseif (!empty($_POST['swap_x'])) {
  $id = $_POST['hiddenSwapId'];
  $goals = GameAllGoals($id);
  if (!mysqli_num_rows($goals)) {
    GameChangeHome($id);
  }
} elseif (!empty($_POST['removemoved'])) {
  $id = $_POST['hiddenDeleteId'];
  DeleteMovedGame($id, $poolId);
} elseif (!empty($_POST['removeall'])) {
  PoolDeleteAllGames($poolId);
} elseif (!empty($_POST['fakegenerate'])) {
  if (!empty($_POST['rounds'])) {
    $rounds = $_POST['rounds'];
  }
  $nomutual = isset($_POST["nomutual"]);
  $homeresp = isset($_POST["homeresp"]);
  $fakegames = "";
  $generatedgames = GenerateGames($poolId, $rounds, false, $nomutual, $homeresp);

  if ($poolInfo['type'] == 1) {
    foreach ($generatedgames as $game) {
      if ($usepseudoteams) {
        $fakegames .= "<p>" . TeamPseudoName($game['home']) . " - " . TeamPseudoName($game['away']) . "</p>";
      } else {
        $fakegames .= "<p>" . TeamName($game['home']) . " - " . TeamName($game['away']) . "</p>";
      }
    }
  } elseif ($poolInfo['type'] == 2) {
    $generatedpools = GeneratePlayoffPools($poolId, false);
    $fakegames .= "<p><b>" . $poolInfo['name'] . "</b></p>";
    foreach ($generatedgames as $game) {
      if ($usepseudoteams) {
        $fakegames .= "<p>" . TeamPseudoName($game['home']) . " - " . TeamPseudoName($game['away']) . "</p>";
      } else {
        $fakegames .= "<p>" . TeamName($game['home']) . " - " . TeamName($game['away']) . "</p>";
      }
    }
    foreach ($generatedpools as $gpool) {
      $fakegames .= "<p><b>" . $gpool['name'] . "</b></p>";
      $generatedgames = GenerateGames($poolId, $rounds, false);
      $fakegames .= "<p>" . count($generatedgames) . " " . _("games") . ". " .
        _("Previous round winner vs. winners and losers vs. losers") . "</p>";
      // debugVar($gpool);
      if ($gpool['specialmoves']) {
        $fakegames .= "<p>" . _("playoff layout with moves found, using special moves.") . "</p>";
      }
    }
  } elseif ($poolInfo['type'] == 3) {
    // Swiss-draw:
    if ($generatedgames[0] === -2) {
      $generatedgames = askBye($season, $poolId, $poolInfo, $rounds, $homeresp, $nomutual, "fakegenerate", false);
      $fakegames .= "<p>" . _("The number of teams in a Swiss-draw pool should be even.") . "</p>";
    } else if ($generatedgames[0] === -1) {
      $html .= "<p>" . _("There must be at least two teams. Add more teams to the pool.") . "</p>";
    }

    if ($rounds <= 0) {
      $html .= "<p>" . _("Must generate at least one round") . "</p>";
    } elseif (gettype($generatedgames[0]) === "array") {
      // generate pools (with games) and moves
      $generatedpools = GenerateSwissdrawPools($poolId, $rounds, false);
      $fakegames .= "<p><b>" . $poolInfo['name'] . "</b></p>";
      foreach ($generatedgames as $game) {
        if ($usepseudoteams) {
          $fakegames .= "<p>" . TeamPseudoName($game['home']) . " - " . TeamPseudoName($game['away']) . "</p>";
        } else {
          $fakegames .= "<p>" . TeamName($game['home']) . " - " . TeamName($game['away']) . "</p>";
        }
      }

      if ($rounds > 2) {
        $generatedgames = GenerateGames($poolId, $rounds, false);
        $fakegames .= "<p><b> " .
          sprintf(_("and %d extra swiss draw pools with %d games each"), ($rounds - 1), count($generatedgames)) .
          "</b></p>";
      } elseif ($rounds == 2) {
        $generatedgames = GenerateGames($poolId, $rounds, false);
        $fakegames .= "<p><b> " .
          sprintf(_("and one extra swiss draw pool with %d games each"), ($rounds - 1), count($generatedgames)) .
          "</b></p>";
      }
      foreach ($generatedpools as $fakepool) {
        $fakegames .= "<p>" . $fakepool['name'] . "</p>";
      }
    }
  } elseif ($poolInfo['type'] == 4) {
    foreach ($generatedgames as $game) {
      if ($usepseudoteams) {
        $fakegames .= "<p>" . TeamPseudoName($game['home']) . " - " . TeamPseudoName($game['away']) . "</p>";
      } else {
        $fakegames .= "<p>" . TeamName($game['home']) . " - " . TeamName($game['away']) . "</p>";
      }
    }
  } else {
    $fakegames = "<p>" . _("Not applicable for this pool type") . "</p>\n";
  }
} elseif (!empty($_POST['generate'])) {
  if (!empty($_POST['rounds'])) {
    $rounds = $_POST['rounds'];
  }
  $homeresp = isset($_POST["homeresp"]);
  $nomutual = isset($_POST["nomutual"]);
  
  if ($hasGames) {
    $generatedgames = GenerateGames($poolId, $rounds, true, $nomutual, $homeresp);
  } else {
    $html .= "<p>" . _("Not applicable for this pool type") . "</p>\n";
  }

  // in case of playoff pool create all pools and games for playoffs
  if ($poolInfo['type'] == 2) {
    // generate pools needed to solve standings
    $generatedpools = GeneratePlayoffPools($poolId, true);

    // generate games into generated pools
    foreach ($generatedpools as $gpool) {
      // echo "<p>Generate games for ".$gpool['pool_id']."</p>";
      GenerateGames($gpool['pool_id'], $rounds, true);
    }
  } elseif ($poolInfo['type'] == 3) { // in case of Swissdraw, create pools and moves
    if ($generatedgames[0] === -2) {
      $generatedgames = askBye($season, $poolId, $poolInfo, $rounds, $homeresp, $nomutual, "generate", true);
    } else if ($generatedgames[0] === -1) {
      $html .= "<p>" . _("There must be at least two teams. Add more teams to the pool.") . "</p>";
    }

    if ($rounds <= 0) {
      $html .= "<p>" . _("Must generate at least one round") . "</p>";
    } elseif (gettype($generatedgames[0]) === "array") {
      // generate pools (with games) and moves
      $generatedpools = GenerateSwissdrawPools($poolId, $rounds, true);
    }
  }
} elseif (!empty($_POST['addnew'])) {
  if (!$hasGames) throw new Exception("pool type has no games"); 
  $home = $_POST['newhome'];
  $away = $_POST['newaway'];
  $homeresp = isset($_POST["homeresp"]);
  PoolAddGame($poolId, $home, $away, $usepseudoteams, $homeresp);
}

$seaValue = urlencode($season);
$html .= "<form method='post' action='?view=admin/poolgames&amp;season=$seaValue&amp;pool=$poolId'>";

if ($hasGames) {
  if (CanGenerateGames($poolId)) {
  $html .= "<h2>" . _("Creation of pool games") . "</h2>\n";

  if ($poolInfo['type'] == "1") {
    $html .= "<p>" . _("Round Robin pool") . "</p>\n";
    $html .= "<p>" . _("Game rounds") . ": <input class='input' size='2' name='rounds' value='$rounds'/></p>\n";
    $html .= "<p>" . _("Home team has rights to edit game score sheet") .
      ":<input class='input' type='checkbox' name='homeresp'";
    if (isRespTeamHomeTeam()) {
      $html .= "checked='checked'";
    }
    $html .= "/></p>";

    if ($poolInfo['mvgames'] == 2) {
      $html .= "<p>" . _("Do not generate mutual games for teams moved from same pool") .
        ":<input class='input' type='checkbox' name='nomutual'";
      if ($nomutual) {
        $html .= "checked='checked'";
      }
      $html .= "/></p>";
    }
  } elseif ($poolInfo['type'] == "2") {
    $html .= "<p>" . _("Play-off pool") . "</p>\n";
    $html .= "<p>" .
      sprintf(utf8entities(_("best of %s games")), ": <input class='input' size='2' name='rounds' value='$rounds'/>") .
      "</p>\n";
    $html .= "<p>" . _("Home team has rights to edit game score sheet") .
      ":<input class='input' type='checkbox' name='homeresp'";
    if (isRespTeamHomeTeam()) {
      $html .= "checked='checked'";
    }
    $html .= "/></p>";
  } elseif ($poolInfo['type'] == "3") {
    $html .= "<p>" . _("Swissdraw pool: ") . "<input class='input' size='2' name='rounds' value='$rounds'/> " .
      _("rounds") . "</p>\n";
    $html .= "<p>" . _("Home team has rights to edit game score sheet") .
      ":<input class='input' type='checkbox' name='homeresp'";
    if (isRespTeamHomeTeam()) {
      $html .= "checked='checked'";
    }
    $html .= "/></p>";
  } elseif ($poolInfo['type'] == "4") {
    $html .= "<p>" . _("Crossmatch pool") . "</p>\n";
    $html .= "<p>" .
      sprintf(utf8entities(_("best of %s games")), ": <input class='input' size='2' name='rounds' value='$rounds'/>") .
      "</p>\n";
    $html .= "<p>" . _("Home team has rights to edit game score sheet") .
      ":<input class='input' type='checkbox' name='homeresp'";
    if (isRespTeamHomeTeam()) {
      $html .= "checked='checked'";
    }
    $html .= "/></p>";
  } else {
    $html .= "<p>" . _("Not applicable for this pool type") . "</p>\n";
  }

  $html .= "<p><input type='submit' name='fakegenerate' value='" . _("Show games") . "'/>";
  $html .= "<input type='submit' name='generate' value='" . _("Generate all games") . "'/></p>";
} else {
  $html .= "<p><a href='?view=admin/reservations&amp;season=$seaValue'>" . _("Scheduling and Reservation management") .
    "</a></p>";
}
} else {
  $html .= "<p>" . _("Not applicable for this pool type") . "</p>\n";
}
if (!empty($fakegames)) {
  $html .= "<h2>" . _("Games to generate") . "</h2>\n";
  $html .= $fakegames;
}

$mutualgames = array();

// if mutual games moved, mark games played between teams moved from same pool
if ($poolInfo['mvgames'] == 2) {
  $allgames = PoolGames($poolInfo['pool_id']);
  foreach ($allgames as $game) {
    $gameInfo = GameInfo($game['game_id']);
    if (!empty($gameInfo['hometeam']) && !empty($gameInfo['visitorteam'])) {
      $homepool = PoolGetFromPoolByTeamId($poolInfo['pool_id'], $gameInfo['hometeam']);
      $awaypool = PoolGetFromPoolByTeamId($poolInfo['pool_id'], $gameInfo['visitorteam']);
    } else {
      $homepool = PoolGetFromPoolBySchedulingId($gameInfo['scheduling_name_home']);
      $awaypool = PoolGetFromPoolBySchedulingId($gameInfo['scheduling_name_visitor']);
    }
    if ($homepool == $awaypool) {
      $mutualgames[] = $game['game_id'];
    }
  }
}

$reservations = SeasonReservations($season);
$tour = "";
$totalgames = 0;
foreach ($reservations as $res) {
  $games = PoolGames($poolId, $res['id']);
  $location = LocationInfo($res['location']);
  if (count($games)) {
    if ($tour != $res['reservationgroup']) {
      $html .= "<h2>" . utf8entities($res['reservationgroup']) . "</h2>";
      $tour = $res['reservationgroup'];
    }
    $html .= "<table border='0'>\n";
    $html .= "<tr><th colspan='4'>" . utf8entities($location['name']) . " ";
    $html .= " " . DefWeekDateFormat($res['starttime']) . " " . DefHourFormat($res['starttime']) . "-";
    $html .= DefHourFormat($res['endtime']) . "</th>";
    $html .= "<th colspan='6' class='right'><a class='thlink' href='?view=admin/schedule&amp;season=$seaValue&amp;series=" .
      $poolInfo['series'] . "&amp;pool=$poolId&amp;reservations=" . $res['id'] . "'>" . _("Add games") . "</a></th>";
    $html .= "</tr>";

    foreach ($games as $row) {
      ++$totalgames;
      if (in_array($row['game_id'], $mutualgames)) {
        $html .= "<tr class='highlight'>";
      } else {
        $html .= "<tr>";
      }

      $html .= "<td style='width:10%'>" . DefHourFormat($row['time']) . "</td>";
      if ($usepseudoteams) {
        $html .= "<td style='width:30%'>" . utf8entities($row['phometeamname']) . "</td>";
        $html .= "<td>-</td>";
        $html .= "<td style='width:30%'>" . utf8entities($row['pvisitorteamname']) . "</td>";
      } else {
        $html .= "<td style='width:30%'>" . utf8entities($row['hometeamname']) . "</td>";
        $html .= "<td>-</td>";
        $html .= "<td style='width:30%'>" . utf8entities($row['visitorteamname']) . "</td>";
      }
      $html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$seaValue&amp;game=" . $row['game_id'] . "'>" .
        _("edit") . "</a></td>";
      $html .= "<td style='width:5%'>" . intval($row['homescore']) .
        "</td><td style='width:2%'>-</td><td style='width:5%'>" . intval($row['visitorscore']) . "</td>";
      $html .= "<td class='center'> " .
        getDeleteButton('swap', $row['game_id'], 'hiddenSwapId', 'images/swap.png', "<->") . "</td>";
      $html .= "<td class='center'>" . getDeleteButton('remove', $row['game_id']) . "</td>";
      $html .= "</tr>\n";
    }

    $html .= "</table>";
  }
}

$games = PoolGamesNotScheduled($poolId);
if (count($games)) {
  $html .= "<h2>" . _("No schedule") . "</h2>\n";
  $html .= "<table border='0'>\n";

  foreach ($games as $row) {
    ++$totalgames;
    if (in_array($row['game_id'], $mutualgames)) {
      $html .= "<tr class='highlight'>";
    } else {
      $html .= "<tr>";
    }
    if ($row['hometeam']) {
      $html .= "<td style='width:30%'>" . utf8entities($row['hometeamname']) . "</td>";
    } else {
      $html .= "<td style='width:30%'>" . utf8entities(U_($row['phometeamname'])) . "</td>";
    }

    $html .= "<td>-</td>";
    if ($row['visitorteam']) {
      $html .= "<td style='width:30%'>" . utf8entities($row['visitorteamname']) . "</td>";
    } else {
      $html .= "<td style='width:30%'>" . utf8entities(U_($row['pvisitorteamname'])) . "</td>";
    }
    $html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$seaValue&amp;game=" . $row['game_id'] . "'>" .
      _("edit") . "</a></td>";
    $html .= "<td class='center'>" . getDeleteButton('swap', $row['game_id'], 'hiddenSwapId', 'images/swap.png', '<->') .
      "</td>";
    $html .= "<td class='center'>" . getDeleteButton('remove', $row['game_id']) . "</td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>";
}

$games = PoolMovedGames($poolId);
if (count($games)) {
  $html .= "<h2>" . _("Moved games") . "</h2>\n";
  $html .= "<table border='0'>\n";
  foreach ($games as $row) {
    ++$totalgames;
    $html .= "<tr>";
    $html .= "<td style='width:30%'>" . utf8entities($row['hometeamname']) . "</td>";
    $html .= "<td>-</td>";
    $html .= "<td style='width:30%'>" . utf8entities($row['visitorteamname']) . "</td>";
    $html .= "<td style='width:5%'>" . intval($row['homescore']) .
      "</td><td style='width:2%'>-</td><td style='width:5%'>" . intval($row['visitorscore']) . "</td>";
    $html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$seaValue&amp;game=" . $row['game_id'] . "'>" .
      _("edit") . "</a></td>";
    $html .= "<td class='center'>" . getDeleteButton('swap', $row['game_id'], 'hiddenSwapId', 'images/swap.png', '<->') .
      "</td>";
    $html .= "<td class='center'>" . getDeleteButton('removemoved', $row['game_id']) . "</td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>";
}

if ($totalgames > 0) {
  $html .= "<p><input class='button' type='submit' value='" . _("Remove all games") .
    "' name='removeall' onclick='return confirm(\"" . _("This will remove all games from this pool.") . "\");'/></p>";
}

if (!$poolInfo['played'] && $hasGames) {
  $html .= "<h2>" . _("Creation of single game") . "</h2>\n";
  $html .= "<p>" . _("Home team has rights to edit game score sheet") .
    ":<input class='input' type='checkbox' name='homeresp'";
  if (isRespTeamHomeTeam()) {
    $html .= "checked='checked'";
  }
  $html .= "/></p>";

  $html .= "<table border='0'>\n";
  $html .= "<tr>";
  $html .= "<td style='width:30%'><select class='dropdown' style='width:100%' name='newhome'>";
  $pseudoteams = false;
  $teams = PoolTeams($poolId);
  if (count($teams) == 0) {
    $teams = PoolSchedulingTeams($poolId);
    $pseudoteams = true;
  }
  $teamlist = "";
  foreach ($teams as $row) {
    if ($pseudoteams) {
      $teamlist .= "<option class='dropdown' value='" . utf8entities($row['scheduling_id']) . "'>" .
        utf8entities($row['name']) . "</option>";
    } else {
      $teamlist .= "<option class='dropdown' value='" . utf8entities($row['team_id']) . "'>" . utf8entities(
        $row['name']) . "</option>";
    }
  }
  $html .= $teamlist;
  $html .= "</select></td>";
  $html .= "<td>-</td>";
  $html .= "<td style='width:30%'><select class='dropdown' style='width:100%' name='newaway'>";
  $html .= $teamlist;
  $html .= "</select></td>";
  $html .= "<td class='center'><input class='button' type='submit' value='" . _("Create") . "' name='addnew'/></td>";
  $html .= "</tr>\n";
  $html .= "</table>";
}

// stores id to delete
$html .= "<p>" . getHiddenInput(null, 'hiddenDeleteId', 'hiddenDeleteId') . "</p>";
$html .= "<p>" . getHiddenInput(null, 'hiddenSwapId', 'hiddenSwapId') . "</p>";
$html .= "</form>\n";

$html .= "<hr><p><a href='?view=admin/seasonpools&amp;season=$seaValue'&series=" . $poolInfo['series'] . ">" . _("Pools") . "</a></p>";

echo $html;
contentEnd();
pageEnd();
?>
