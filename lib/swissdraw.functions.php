<?php

function DetectTiesInPreviousPool($poolId) {
  // retrieve list of pools contributing to this pool
  $query = sprintf("
		SELECT distinct frompool
		FROM uo_moveteams pmt
		WHERE pmt.topool = '%s'", mysql_adapt_real_escape_string($poolId));

  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  while ($contrPool = mysqli_fetch_assoc($result)) {
    $query = sprintf(
      "
			SELECT count(activerank) AS activeteams,count(activerank)-count(distinct activerank) as ties
			FROM uo_team_pool
			where pool='%s'", mysql_adapt_real_escape_string($contrPool['frompool']));

    $result2 = mysql_adapt_query($query);
    if (!$result2) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $row = mysqli_fetch_assoc($result2);
    
    if ($row['activeteams'] == 0) {
      // no active teams in this pool
      return (2);
    } elseif ($row['ties'] > 0) {
      // ties detected
      return (1);
    }
  }

  // no ties detected, all teams present
  return (0);
}

function AutoResolveTiesInSourcePools($poolId) {
  // retrieve list of pools contributing to this pool
  $query = sprintf("
		SELECT distinct frompool
		FROM uo_moveteams pmt
		WHERE pmt.topool = '%s'", mysql_adapt_real_escape_string($poolId));

  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  while ($contrPool = mysqli_fetch_assoc($result)) {
    AutoResolveTies($contrPool['frompool']);
  }
}

function AutoResolveTies($poolId) {
  // print "Resolving ties in pool".$poolId."<br>";
  $query = sprintf("
		SELECT team,`rank`,activerank
		FROM uo_team_pool
		WHERE pool='%s'
		ORDER BY activerank,team", mysql_adapt_real_escape_string($poolId));

  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  $nbrows = mysqli_num_rows($result);
  // print "Number of rows: ".$nbrows."<br>";

  for ($i = 1; $i <= $nbrows; $i++) {
    $row = mysqli_fetch_assoc($result);
    // print_r($row);
    if ($row['activerank'] < $i && !empty($row['activerank'])) {
      // set this team's activerank to $i
      // print "Adjusting team ".$row['team']."'s `rank` to ".$i."<br>";
      $query = sprintf("
				UPDATE uo_team_pool
				SET activerank=%d
				WHERE pool=%d AND team=%d", intval($i), intval($poolId), intval($row['team']));
      $result2 = mysql_adapt_query($query);
      if (!$result2) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    }
  }
}

function findPairing(&$moves, $games) {
  $forward = true;
  $foundValidArrangement = AdjustForDuplicateGames($moves, $games, $forward);
  $duplicates = 0;
  while (!$foundValidArrangement && $duplicates < $moves) {
    $roundcounter = 0;
    while (!$foundValidArrangement) {
      $forward = !$forward;
      // print "trying the other way round, now forward? ".$forward."<br><br>";
      $foundValidArrangement = AdjustForDuplicateGames($moves, $games, $forward, $duplicates);
      if (!$foundValidArrangement) {
        $roundcounter++;
        if ($roundcounter > count($moves) * 2) {
          break;
        }
      }
    }
    if (!$foundValidArrangement) {
      debug_to_apache("did not find arrangement with $duplicates duplicates.");
      $duplicates++;
    } else if ($duplicates > 0) {
      debug_to_apache("found arrangement with $duplicates duplicates.");
    }
  }
  if (!$foundValidArrangement)
    return -1;
  else
    return $duplicates;
}

function CheckSwissdrawMoves($poolId, &$alteredmoves) {
  // returns -1 if there are ties in previous pool
  // returns -2 if there are no active teams in previous pool
  // returns 1 if everything went fine
  $ties = DetectTiesInPreviousPool($poolId);
  if ($ties > 0)
    return (-$ties);

  // retrieve all moves
  $moves = PoolMovingsToPool($poolId);
  // print "original moves:<br>";
  // PrintMoves($moves);

  // upgrade the moves-data with the actual teams
  for ($i = 0; $i < count($moves); $i++) {
    $team = PoolTeamFromStandings($moves[$i]['frompool'], $moves[$i]['fromplacing']);
    if (empty($team)) {
      debug_to_apache(print_r($moves, true) . $i . " " . $moves[$i]['frompool'] . " " . $moves[$i]['fromplacing']);
      die("This should have been detected earlier ...");
    }
    $moves[$i]['team_id'] = $team['team_id'];
  }

  // retrieve all previously played games
  $games = PoolGetGamesToMove($poolId, 0);

  if (count($moves) % 2 == 1) return -3;

  $duplicates = findPairing($moves, $games);
  if ($duplicates < 0)
    Die("Could not find a valid arrangment of teams");

  // update the moves in the database
  usort($moves,
    create_function('$a,$b',
      'return $a[\'torank\']==$b[\'torank\']?0:($a[\'torank\']<$b[\'torank\']?-1:1);'));
  
  $alteredmoves = $moves;
  // everthing went fine
  return 1 + $duplicates;
}

function AdjustForDuplicateGames(&$moves, $games, $forward, $duplicates = 0) {
  // this function will change the variable $moves
  If ($forward) {
    $sign = 1;
    $startPos = 0;
    $stopPos = count($moves);
  } else {
    $sign = -1;
    $startPos = count($moves) - 1;
    $stopPos = -1;
  }

  // print "Loop from ".$startPos." until ".$stopPos." with steps ".($sign*2)."<br>";
  for ($i = $startPos; $i != $stopPos && $i + $sign != $stopPos; $i = $i + $sign * 2) {
    If (TeamsHavePlayed($moves[$i]['team_id'], $moves[$i + $sign]['team_id'], $games, $duplicates)) {
      // Find the first team in the rest of the list that hasn't played
      $j = FindUnplayedTeam($moves[$i]['team_id'], $i + 2 * $sign, $moves, $games, $forward);
      If ($j > 0) {
        // this means we've found one.
        MoveTeamToNewPosition($j, $i + $sign, $moves);
      } else {
        // This is trouble. There is no team further down that hasn't played
        // the current team.
        // print "unable to find an unplayed team in this direction:".$forward." <br>";
        return (false);
      }
    }
  }

  // print "It all worked out! :-) <br>";
  return (true);
}

function PrintMoves($moves) {
  echo "<table border='1' width='600px'><tr>
	<th>" . _("From pool") . "</th>
	<th>" . _("From pos.") . "</th>
	<th>" . _("Team") . "</th>
	<th>" . _("To pos.") . "</th>
	<th>" . _("To pool") . "</th>
	<th>" . _("Name in Schedule") . "</th></tr>";

  for ($i = 0; $i < count($moves); $i++) {
    $row = $moves[$i];
    echo "<tr>";
    echo "<td style='white-space: nowrap'>" . utf8entities($row['name']) . "</td>";
    $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
    echo "<td class='center'>" . intval($row['fromplacing']) . "</td>";
    echo "<td class='highlight'>" . utf8entities($team['name']) . "</td>";
    echo "<td class='center'>" . intval($row['torank']) . "</td>";
    echo "<td style='white-space: nowrap'>" . $row['scheduling_id'] . "</td>";
    echo "<td>" . utf8entities($row['pteamname']) . "</td>";
    echo "</tr>\n";
  }
  echo "</table>";
}

function MoveTeamToNewPosition($posFrom, $posTo, &$moves) {
  // This routine will move the team in posFrom to the posTo position, and shift
  // everyone in between by one to accomodate.

  // PrintMoves($moves);
  // print "<br>Moving team in position ".$posFrom." to position ".$posTo." <br>";
  If ($posFrom > $posTo) {
    $sign = -1;
  } else {
    $sign = 1;
  }

  $tempfromplacing = $moves[$posFrom]['fromplacing'];
  $tempteam_id = $moves[$posFrom]['team_id'];

  for ($i = $posFrom; $i != $posTo; $i = $i + $sign) {
    // print "in the loop<br>";
    $moves[$i]['fromplacing'] = $moves[$i + $sign]['fromplacing'];
    $moves[$i]['team_id'] = $moves[$i + $sign]['team_id'];
  }
  $moves[$posTo]['fromplacing'] = $tempfromplacing;
  $moves[$posTo]['team_id'] = $tempteam_id;

  // PrintMoves($moves);
}

function FindUnplayedTeam($teamid, $startPos, $moves, $games, $forward) {
  If ($forward) {
    $sign = 1;
    $stopPos = count($moves) - 1;
    if ($startPos > $stopPos)
      return (-1);
  } else {
    $sign = -1;
    $stopPos = 1;
    if ($startPos < $stopPos)
      return (-1);
  }

  for ($i = $startPos; $i != $stopPos; $i = $i + $sign) {
    If (!TeamsHavePlayed($teamid, $moves[$i]['team_id'], $games)) {
      return $i;
    }
  }
  return -1;
}

function TeamsHavePlayed($teamid1, $teamid2, $games, $maxgames = 0) {
  $i = 0;
  $g = 0;

  // now just look down the list and see if these teams have played
  while ($i < count($games)) {
    $game = GameResult($games[$i]);
    if (($game['hometeam'] == $teamid1 && $game['visitorteam'] == $teamid2) ||
      ($game['hometeam'] == $teamid2 && $game['visitorteam'] == $teamid1)) {
      if (++$g > $maxgames)
        return true;
    }
    $i++;
  }
  return false;
}

function FindSwissProblem($moves, $games) {
  $totalmoves = len($moves);
  $problemMove = 0;
  $i = 1;
  while ($i < $rounds && $problemMove == 0) {
    if (HavePlayed($moves($i), $moves($i + 1), $games))
      $problemMove = $i;
    $i = $i + 2;
  }
}

function SwissAllMovesOK($moves, $games) {
  $totalmoves = len($moves);
  $allOK = true;
  $i = 1;
  while ($i < $rounds && $allOK) {
    if (HavePlayed($moves($i), $moves($i + 1), $games))
      $allOK = false;
    $i = $i + 2;
  }
}

function GenerateSwissdrawPools($poolId, $rounds, $generate = true) {
  $poolInfo = PoolInfo($poolId);
  if (hasEditTeamsRight($poolInfo['series'])) {

    $pools = array();

    $query = sprintf(
      "SELECT team.team_id from uo_team_pool as tp left join uo_team team 
				on (tp.team = team.team_id) WHERE tp.pool=%d ORDER BY tp.`rank`", (int) $poolId);
    $moved_result = DBQuery($query);

    $query = sprintf(
      "SELECT pt.scheduling_id AS team_id from uo_scheduling_name pt 
			LEFT JOIN uo_moveteams mt ON(pt.scheduling_id = mt.scheduling_id) 
			WHERE mt.topool=%d ORDER BY mt.torank", (int) $poolId);
      $scheduled_result = DBQuery($query);
    
    if (mysqli_num_rows($moved_result) < mysqli_num_rows($scheduled_result)) {
      $pseudoteams = true;
      $result = $scheduled_result;
    } else {
      $result = $moved_result;
    }
    $teams = mysqli_num_rows($result);
    // echo "<p>rounds to win $rounds</p>";
    $prevpoolId = $poolId;
    $offset = $teams;
    $poolname = $poolInfo['name'];
    
    if ($rounds > 0){ 
      $preflen = (int) log10($rounds) + 1;
      $orderprefix = $poolInfo['ordering'];
      if (strlen($orderprefix) + $preflen+1 > PoolOrderingLength()) {
        // try to find a string greater than orderprefix; this often fails
        $orderprefix = substr($orderprefix, 0, PoolOrderingLength() - $preflen - 1);
        $orderprefix .= 'Z';
      }
    }
    
    // first round is played in master pool
    for ($i = 1; $i < $rounds; $i++) {

      $name = sprintf(_("%s, round %d"), $poolname, ($i + 1));

      if ($generate) {
        // create pool
        $id = PoolFromAnotherPool($poolInfo['series'], $name, sprintf("%s%0{$preflen}d", $orderprefix, ($i + 1)), $poolId);
        // make it a continuation pool
        $query = sprintf("UPDATE uo_pool SET continuingpool=1 WHERE pool_id=%s", (int) $id);
        $result = DBQuery($query);
        
        $query = sprintf("UPDATE uo_pool SET mvgames=0 WHERE pool_id=%s", (int) $id);
        $result = DBQuery($query);
        
        // standard move to next pool
        for ($j = 1; $j <= $teams; $j++) {
          $prevname = sprintf(_("Rnd%d %d"), $i, $j);
          PoolAddMove($prevpoolId, $id, $j, $j, $prevname);
        }

        // create games in new pools as well
        GenerateGames($id, 1, $generate, false);

        $pools[] = PoolInfo($id);
        $prevpoolId = $id;
      } else {
        $pools[] = $poolInfo;
        $pools[$i - 1]['name'] = $name;
      }
    }

    return $pools;
  } else {
    die('Insufficient rights to add games');
  }
}

function PoolTeamFromStandingsNoTies($poolId, $activerank) {
  // does the same as PoolTeamFromStandings above, but never returns an empty team
  // if there are ties, they are broken consistently by the team_id of the tied teams
  $query = sprintf(
    "
		SELECT j.team_id, j.name, js.activerank, c.flagfile
		FROM uo_team AS j 
		LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
		LEFT JOIN uo_country c ON(c.country_id=j.country)
		WHERE js.pool='%s' AND js.activerank='%s'", mysql_adapt_real_escape_string($poolId),
    mysql_adapt_real_escape_string($activerank));

  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  if (mysqli_num_rows($result) == 0) {
    // must be due to ties in previous activeranks
    $searchback = 0;
    while (mysqli_num_rows($result) == 0) {
      $searchback++;
      $query = sprintf(
        "
				SELECT j.team_id, j.name, js.activerank, c.flagfile
				FROM uo_team AS j 
				LEFT JOIN uo_team_pool AS js ON (j.team_id = js.team)
				LEFT JOIN uo_country c ON(c.country_id=j.country)
				WHERE js.pool='%s' AND js.activerank='%s'", mysql_adapt_real_escape_string($poolId),
        mysql_adapt_real_escape_string($activerank - $searchback));

      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    }
    mysqli_data_seek($result, $searchback);
  }

  return mysqli_fetch_assoc($result);
}

function CheckBYESchedule($poolId) {
  // checks if a game with a BYE team as participant has been scheduled
  // and in the same pool, there is a game with real teams that has not been scheduled
  // if this is the case, the slots of the real game gets the slot from the BYE game
  $query = sprintf(
    "
		SELECT game_id,hometeam,visitorteam,reservation,g.time
		FROM uo_game AS g 
		LEFT JOIN uo_team AS tvisit ON (g.visitorteam = tvisit.team_id)
		LEFT JOIN uo_team as thome  ON (g.hometeam = thome.team_id)
		WHERE g.pool='%s' AND ((thome.valid=2 OR tvisit.valid=2 AND g.time is not NULL) OR 
			(g.time is NULL AND thome.valid=1 AND tvisit.valid=1) )
		ORDER BY g.time", mysql_adapt_real_escape_string($poolId));

  $result = DBQuery($query);

  if (mysqli_num_rows($result) == 2) { // swap spots
    $row1 = mysqli_fetch_assoc($result);
    $row2 = mysqli_fetch_assoc($result);
    
    if (!empty($row2['time']) && !empty($row2['reservation']) && empty($row1['reservation']) && empty($row1['time'])) {
      // row2 is a game with time slot and bye team, row1 is the only game without reservation => swap them
      $query = sprintf("
				UPDATE uo_game SET reservation='%s', time='%s' 
				WHERE game_id='%s' ", mysql_adapt_real_escape_string($row2['reservation']),
        mysql_adapt_real_escape_string($row2['time']), mysql_adapt_real_escape_string($row1['game_id']));
      $result = mysql_adapt_query($query);
      if (!$result || mysql_adapt_affected_rows() != 1) {
        die('Invalid query: ' . mysql_adapt_error());
      }

      $query = sprintf("
				UPDATE uo_game SET reservation=NULL, time=NULL 
				WHERE game_id='%s' ", mysql_adapt_real_escape_string($row2['game_id']));
      $result = mysql_adapt_query($query);
      if (!$result || mysql_adapt_affected_rows() != 1) {
        die('Invalid query: ' . mysql_adapt_error());
      }

      return sprintf(_("Time slots swapped! Pool_id %d"),  $poolId);
    }
  }
  return null;
}

function CheckBYE($poolId) {
  // returns the number of games where the standard result has been filled in
  $poolInfo = PoolInfo($poolId);
  $changes = 0;
  if ($poolInfo['type'] == 3) {
    // Swissdraw

    // echo "actually doing it";
    // if the visitor-team is the BYE-team assign the appropriate scores to home and visitor
    $query = sprintf(
      "
				UPDATE uo_game,uo_team SET uo_game.visitorscore='%s', uo_game.homescore='%s', uo_game.hasstarted='2'
				WHERE (uo_game.pool='%s' AND uo_game.visitorteam=uo_team.team_id AND uo_team.valid=2)",
      mysql_adapt_real_escape_string($poolInfo['forfeitagainst']),
      mysql_adapt_real_escape_string($poolInfo['forfeitscore']), mysql_adapt_real_escape_string($poolId));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $changes = mysql_adapt_affected_rows();

    // if the home-team is the BYE-team assign the appropriate scores to home and visitor
    $query = sprintf(
      "
				UPDATE uo_game,uo_team SET uo_game.homescore='%s', uo_game.visitorscore='%s', uo_game.hasstarted='2'
				WHERE (uo_game.pool='%s' AND uo_game.hometeam=uo_team.team_id AND uo_team.valid=2)",
      mysql_adapt_real_escape_string($poolInfo['forfeitagainst']),
      mysql_adapt_real_escape_string($poolInfo['forfeitscore']), mysql_adapt_real_escape_string($poolId));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $changes += mysql_adapt_affected_rows();
  }
  return $changes;
}

/**
 * Returns -1 if the number of teams in the pool is odd, i.e.
 * one team will have a BYE,
 * and at least one team already had a BYE previously.
 *
 * Returns 0 if everything is OK.
 *
 * @param int $poolId
 * @return number
 */
function CheckPlayoffMoves($poolId) {
  $query = sprintf("SELECT COUNT(*) FROM uo_team_pool WHERE pool=%d", (int) $poolId);
  $teams = DBQueryToValue($query);
  if (is_odd($teams) == false) {
    return 0;
  } // there is no problem

  $games = array();
  // retrieve all moves
  $moves = PoolMovingsToPool($poolId);
  foreach ($moves as $row) {
    $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing'], false);
    if (TeamPoolCountBYEs($team['team_id'], $row['frompool']) > 0) {
      return -1;
    }
  }
}

?>
