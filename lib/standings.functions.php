<?php 
include_once $include_prefix . 'lib/class.Glicko2Player.php';

function ResolvePoolStandings($poolId){
	$poolinfo = PoolInfo($poolId);
	if($poolinfo['type']==1){
		ResolveSeriesPoolStandings($poolId);
	}elseif($poolinfo['type']==2){
		ResolvePlayoffPoolStandings($poolId);
	}elseif($poolinfo['type']==3){
		ResolveSwissdrawPoolStandings($poolId);
	}elseif($poolinfo['type']==4){
		ResolveCrossMatchPoolStandings($poolId);
	}
}

function ResolvePlayoffPoolStandings($poolId){
	
	//query pool teams
	$query = sprintf("
		SELECT j.team_id, js.activerank 
		FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
		WHERE js.pool=%d 
		ORDER BY js.`rank` ASC",
		(int)$poolId);
	
	$teams = DBQueryToArray($query);
	$steams = PoolSchedulingTeams($poolId);
	
	if(count($teams)<=1 || count($teams) < count($steams)){
		return;
	}
	
	for($i=0;$i<(count($teams)-1);$i=$i+2){
		//loop team in pairs, but also be aware if there is odd number of teams
		$teamId1 = $teams[$i]['team_id'];
		$teamId2 = $teams[$i+1]['team_id'];
		$query = sprintf("SELECT 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team1wins, 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team2wins
				FROM uo_game 
				WHERE (homescore != visitorscore) AND ((hometeam=%d AND visitorteam=%d) OR (hometeam=%d AND visitorteam=%d)) 
					AND isongoing=0
					AND game_id IN (SELECT game FROM uo_game_pool WHERE pool=%d)",
				(int)$teamId1,(int)$teamId1,
				(int)$teamId2,(int)$teamId2,
				(int)$teamId1,(int)$teamId2,(int)$teamId2,(int)$teamId1,
				(int)$poolId);
		$games = DBQueryToRow($query);
		
		if($games['team1wins']>$games['team2wins']){
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+1)." WHERE pool=".intval($poolId)." AND team=$teamId1");
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+2)." WHERE pool=".intval($poolId)." AND team=$teamId2");
		}elseif($games['team1wins']<$games['team2wins']){
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+1)." WHERE pool=".intval($poolId)." AND team=$teamId2");
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+2)." WHERE pool=".intval($poolId)." AND team=$teamId1");
		}else{
		//keep current positions
		}
		//check if teams can be moved to next round
		$gamesleft1 = TeamPoolGamesLeft($teamId1, $poolId);
		$gamesleft2 = TeamPoolGamesLeft($teamId2, $poolId);
		if(mysqli_num_rows($gamesleft1)+mysqli_num_rows($gamesleft2)==0){
			TeamMove($teamId1, $poolId, true);
			TeamMove($teamId2, $poolId, true);
		}
	}
	// if odd number of teams
	if (count($teams)%2==1 ) {
		$byeTeamId=$teams[count($teams)-1]['team_id'];
		// set activerank to the last position in pool
		DBQuery("UPDATE uo_team_pool SET activerank=".(count($teams))." WHERE pool=".intval($poolId)." AND team=$byeTeamId");
		// and attempt to move
		TeamMove($byeTeamId, $poolId, true);
	}
	
	//check if there are special ranking rules and apply them 
	CheckSpecialRanking($poolId);
}

function CheckSpecialRanking($poolId) {
	//check if there are special ranking rules for this pool and apply them 
	$query = sprintf("		
			SELECT team,pool,activerank as oldrank,torank as newrank
			FROM uo_specialranking r 
			LEFT JOIN uo_team_pool tp ON (tp.pool = r.frompool AND tp.activerank = r.fromplacing)
			WHERE tp.pool='%s'",
			(int)$poolId);		
	$specialranking=DBQueryToArray($query);
	foreach($specialranking as $row) {
//		print_r($row);
		DBQuery("UPDATE uo_team_pool SET activerank=".$row['newrank']." WHERE pool=".intval($row['pool'])." AND team=".$row['team']);
	}
	
}

function ResolveCrossMatchPoolStandings($poolId){
	
	//query pool teams
	$query = sprintf("
		SELECT j.team_id, js.activerank 
		FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
		WHERE js.pool=%d 
		ORDER BY js.activerank ASC, js.`rank` ASC",
		(int)$poolId);
	
	$teams = DBQueryToArray($query);

	if(count($teams)<=1){
		return;
	}
	
	for($i=0;$i<(count($teams)-1);$i=$i+2){
		//loop team in pairs, but also be aware if there is odd number of teams
		$teamId1 = $teams[$i]['team_id'];
		$teamId2 = $teams[$i+1]['team_id'];
		$query = sprintf("SELECT 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team1wins, 
				COUNT((hometeam=%d AND (homescore>visitorscore)) OR (visitorteam=%d AND (homescore<visitorscore)) OR NULL) AS team2wins 
				FROM uo_game 
				WHERE (homescore != visitorscore) AND ((hometeam=%d AND visitorteam=%d) OR (hometeam=%d AND visitorteam=%d)) 
					AND isongoing=0
					AND game_id IN (SELECT game FROM uo_game_pool WHERE pool=%d)",
				(int)$teamId1,(int)$teamId1,
				(int)$teamId2,(int)$teamId2,
				(int)$teamId1,(int)$teamId2,(int)$teamId2,(int)$teamId1,
				(int)$poolId);
		$games = DBQueryToRow($query);
		
		if($games['team1wins']>$games['team2wins']){
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+1)." WHERE pool=".intval($poolId)." AND team=$teamId1");
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+2)." WHERE pool=".intval($poolId)." AND team=$teamId2");
		}elseif($games['team1wins']<$games['team2wins']){
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+1)." WHERE pool=".intval($poolId)." AND team=$teamId2");
			DBQuery("UPDATE uo_team_pool SET activerank=".($i+2)." WHERE pool=".intval($poolId)." AND team=$teamId1");
		}else{
		//keep current positions
		}
		//check if teams can be moved to next round
		$gamesleft1 = TeamPoolGamesLeft($teamId1, $poolId);
		$gamesleft2 = TeamPoolGamesLeft($teamId2, $poolId);

		if(mysqli_num_rows($gamesleft1)+mysqli_num_rows($gamesleft2)==0){
			TeamMove($teamId1, $poolId);
			TeamMove($teamId2, $poolId);
		}
	}
}

function CompareTeamsSwissdraw($a, $b) { // distinguish between first round and the rest
  $vpa =  $a['games'] == 0? 0:($a['vp'] / $a['games']);
  $vpb =  $b['games'] == 0? 0:($b['vp'] / $b['games']);
  $ova =  $a['games'] == 0? 0:($a['oppvp'] / $a['oppgames']);
  $ovb =  $b['games'] == 0? 0:($b['oppvp'] / $b['oppgames']);
  $mga =  $a['games'] == 0? 0:($a['margin'] / $a['games']);
  $mgb =  $b['games'] == 0? 0:($b['margin'] / $b['games']);
  $sca =  $a['games'] == 0? 0:($a['score'] / $a['games']);
  $scb =  $b['games'] == 0? 0:($b['score'] / $b['games']);
  
  
  if ($a['games'] == 1 && $b['games'] == 1) {
    // sort according to
    // 1. victory points
    // 2. margin
    // 3. total points scored
    // 4. spirit score <-- REMOVED!
    if ($vpa != $vpa) {
      return $vpa > $vpb ? -1 : 1;
    } else {
      if ($mga != $mgb) {
        return $mga > $mgb ? -1 : 1;
      } else {
        if ($sca != $scb) {
          return $sca > $scb ? -1 : 1;
        } else {
          return 0;
        }
      }
    }
  } else {
    // sort according to
    // 1. victory points
    // 2. opponent's victory points
    // 3. total points scored
    // 4. number of games
    
    if ($vpa != $vpb) {
      return $vpa > $vpb ? -1 : 1;
    } else {
      if ($ova != $ovb) {
        return $ova > $ovb ? -1 : 1;
      } else {
        if ($sca != $scb) {
          return $sca > $scb ? -1 : 1;
        } else {
          if ($a['games'] != $b['games']) {
            return ($a['games'] > $b['games']) ? -1 : 1;
          } else {
            return 0;
          }
        }
      }
    }
  }
}

function SolveStandingsAccordingSwissdraw($points){
	//sort according victorypoints
	usort($points, "CompareTeamsSwissdraw");
	
	//update active `rank`
	$stand=1;
	$points[0]['arank']=1;
	
	for($i=1; $i < count($points); $i++){
		if (CompareTeamsSwissdraw($points[$i-1],$points[$i])!=0) {
			$stand=$i+1;
		}
		$points[$i]['arank']=$stand;		
	}
	return $points;
}


function ResolveSwissdrawPoolStandings($poolId)
	{
	//query pool teams
	$query = sprintf("
		SELECT j.team_id, js.activerank 
		FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
		WHERE js.pool='%s' 
		ORDER BY js.activerank ASC, js.`rank` ASC",
		mysql_adapt_real_escape_string($poolId));
		
	$standings = mysql_adapt_query($query);
	
	$points=array();
	$i=0;
	
	if(mysqli_num_rows($standings)<=1){
		return;
	}
	
	while($row = mysqli_fetch_assoc($standings))	{
		// retrieve nr of games, victory points, average opponent's victory points
		$stats1=TeamVictoryPointsByPool($poolId,$row['team_id']);
		
		$points[$i]['team'] = $row['team_id'];
		$points[$i]['games'] = $stats1['games'];
		$points[$i]['vp'] = $stats1['victorypoints'];	
		$points[$i]['oppvp'] = $stats1['oppvp'];	
		$points[$i]['oppgames'] = $stats1['oppgames'];
		$points[$i]['margin'] = $stats1['margin'];	
		$points[$i]['score'] = $stats1['score'];
		$i++;
	}
	
//	echo "before sorting acc to games:"
//	PrintStandingsSwissdraw($points);
	usort($points, uo_create_key_comparator('games', false));
	  // create_function('$a,$b','return $a[\'games\']==$b[\'games\']?0:($a[\'games\']>$b[\'games\']?-1:1);'));

	//initial sort according games

//	echo "before sorting acc to points:";
//	PrintStandingsSwissdraw($points);
	
	$points = SolveStandingsAccordingSwissdraw($points);
//	echo "after sorting acc to points:";
//	PrintStandingsSwissdraw($points);

	
	//update results
	for ($i=0; $i < mysqli_num_rows($standings) && !empty($points[$i]['team']); $i++) 
		{	
		//echo "<p>win t".$points[$i]['team']." v".$points[$i]['wins']." s".$points[$i]['arank']."</p>";
		$query = sprintf("UPDATE uo_team_pool 
				SET activerank=%d WHERE pool=%d AND team=%d",
			intval($points[$i]['arank']),
			intval($poolId),
			intval($points[$i]['team']));
		
		mysql_adapt_query($query);
		}
		
	}


function ResolveSeriesPoolStandings($poolId){
  $poolId = intval($poolId);

  //query pool teams
  $query = sprintf("
	SELECT j.team_id, js.activerank 
	FROM uo_team AS j INNER JOIN uo_team_pool AS js ON (j.team_id = js.team) 
	WHERE js.pool='%s' 
	ORDER BY js.activerank ASC, js.`rank` ASC",
  mysql_adapt_real_escape_string($poolId));

  $standings = mysql_adapt_query($query);

  $points=array();
  $i=0;

  if(mysqli_num_rows($standings)<=1){
    return;
  }

  while($row = mysqli_fetch_assoc($standings))	{
    $points[$i]['team'] = $row['team_id'];
    $points[$i]['arank'] = 1;
    $i++;
  }
  $points = getMatchesWins($points, $poolId);
  
  //initial sort according games
  usort($points, uo_create_key_comparator('games', false));
    // create_function('$a,$b','return $a[\'games\']==$b[\'games\']?0:($a[\'games\']>$b[\'games\']?-1:1);'));

  //sort according to score (wins*winscore+draws*drawscore)
  $points = SolveStandings($points, 'cmp_score');
  $offset = 1;

  //if team sharing same standing
  $samerank = FindSameRank($points, $offset);
  
  //check in order
  //1st condition: check matches played against teams sharing same standing
  //2nd condition: check goal difference from matches played against teams sharing same standing
  //3rd condition: all matches goal difference
  //4th condition: made  goals in matches played against teams sharing same standing
  //5th condition: made goals in all matches
  //whenever one of these condtions solve one or more team standings start checking on begin for teams still sharing same standings
  while(count($samerank)) {
  	$solved=false;
    $offset = $samerank[0]['arank'];

    //PrintStandings($samerank);
    //1st condition: check matches played against teams sharing same standing
    $samerank = SolveStandings(getMatchesWins($samerank, $poolId, true), 'cmp_score');
    
    //PrintStandings($samerank);
    //continue to 2nd condition if all teams are still sharing the same standing
    if(IsSameRank($samerank)){
    	//2nd condition: check goal difference from matches played against teams sharing same standing
//       $samerank = SolveStandingsSharedMatchesGoalsDiff($samerank, $poolId);
      $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, true), 'cmp_goalsdiff');
    }else{
      $solved=true;
    }

    //PrintStandings($samerank);
    //continue to 3rd condition if standings not solved
    if(!$solved && IsSameRank($samerank)){
      //3rd condition: all matches goal difference
//       $samerank = SolveStandingsAllMatchesGoalsDiff($samerank, $poolId);
      $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, false), 'cmp_goalsdiff');
    }else{
      $solved=true;
    }
    
    //PrintStandings($samerank);
    //continue to 4th condition if standings not solved
    if(!$solved && IsSameRank($samerank)){
    	//4th condition: made  goals in matches played against teams sharing same standing
//       $samerank = SolveStandingsSharedMatchesGoalsMade($samerank, $poolId);
       $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, true), 'cmp_goalsmade');
    }else{
      $solved=true;
    }

    //PrintStandings($samerank);
    //continue to 5th condition if standings not solved
    if(!$solved && IsSameRank($samerank)){
    	//5th condition: made goals in all matches
//       $samerank = SolveStandingsAllMatchesGoalsMade($samerank, $poolId);
       $samerank = SolveStandings(getMatchesGoals($samerank, $poolId, false), 'cmp_goalsmade');
    }else{
      $solved=true;
    }

    if (!$solved && !IsSameRank($samerank)) {
      $solved = true;
    }
    
    //PrintStandings($samerank);
    if($solved){
    	//update standings and check remaining standings in same pool
      $points = UpdateStandings($points, $samerank);
    }else{
      //cannot solve standings with current conditions. Leave teams to shared stands and check remaining standings in same pool
      //echo "<p>count: ".$offset." ".count($samerank)."</p>";
      $offset += count($samerank);
    }

    $samerank = FindSameRank($points,$offset);
  }

  //update results
  for ($i=0; $i < mysqli_num_rows($standings) && !empty($points[$i]['team']); $i++)  {
    //echo "<p>win t".$points[$i]['team']." v".$points[$i]['wins']." s".$points[$i]['arank']."</p>";
    $query = sprintf("UPDATE uo_team_pool
			SET activerank=%d WHERE pool=%d AND team=%d",
      intval($points[$i]['arank']),
      intval($poolId),
      intval($points[$i]['team']));

    mysql_adapt_query($query);
  }

  //test if pool is played
  $games = DBQueryRowCount("SELECT game_id
		FROM uo_game game
		LEFT JOIN uo_pool p ON (p.pool_id=game.pool)
		WHERE p.pool_id=$poolId");
  $played = DBQueryRowCount("SELECT game_id
		FROM uo_game game
		LEFT JOIN uo_pool p ON (p.pool_id=game.pool)
		WHERE p.pool_id=$poolId AND (game.hasstarted>0) AND game.isongoing=0");
  if($games == $played){
    
    //test that standings are not shared
    $query = sprintf("SELECT activerank, COUNT(activerank) AS num
			FROM uo_team_pool WHERE pool=%d 
			GROUP BY activerank HAVING ( COUNT(activerank) > 1 )",
        (int)$poolId);

    $duplicates = DBQueryRowCount($query);
    if(!$duplicates){
      $topools = PoolMovingsFromPool($poolId);
      
      foreach($topools as $pool){
        $poolinfo = PoolInfo($pool['topool']);
        if($poolinfo['mvgames']==1){
          PoolMakeMove($pool['frompool'],$pool['fromplacing'], false);
          //set pool visible
          $query = sprintf("UPDATE uo_pool SET visible='1' WHERE pool_id=%d",(int)$pool['topool']);
          DBQuery($query);            
        }

      }
    }
  }
}

function Score($point) {
	return $point['wins']*2 + ($point['games']-$point['wins']-$point['losses'])*1;
}

function cmp_score($pointa, $pointb) {
	return (Score($pointa)>Score($pointb))?-1:((Score($pointa)<Score($pointb))?1:0);
}

function cmp_goalsdiff($pointa, $pointb) {
	return ($pointa['goalsdiff']>$pointb['goalsdiff'])?-1:(($pointa['goalsdiff']<$pointb['goalsdiff'])?1:0);
}

function cmp_goalsmade($pointa, $pointb) {
	return ($pointa['goalsmade']>$pointb['goalsmade'])?-1:(($pointa['goalsmade']<$pointb['goalsmade'])?1:0);
}

function SolveStandings($points, $cmpf){
	if (count($points)==0)
		return $points;
	//sort according wins
	usort($points, $cmpf); 
	
	//update active `rank`
	$offset=1;
	
	for($i=1; $i < count($points); $i++){
		if ($cmpf($points[$i], $points[$i-1])!=0) {
			$points[$i]['arank'] = $points[$i-1]['arank']+$offset;
			$offset=1;
		}else{
			$points[$i]['arank'] = $points[$i-1]['arank'];
			$offset++;
		}
	}	
	
	return $points;
}
	
function FindSameRank($points, $offset)
	{
	usort($points, uo_create_key_comparator('arank'));
	// create_function('$a,$b','return $a[\'arank\']==$b[\'arank\']?0:($a[\'arank\']<$b[\'arank\']?-1:1);'));
	$samerank=array();
	$total=0;

	for ($i=$offset; $i < count($points) && !empty($points[$i]['team']); $i++) 
		{
		if($points[$i]['arank']==$points[$i-1]['arank']){
			//if first found, then previous team was with same `rank`
			if(!$total)
				{
				$samerank[$total]['team'] = $points[$i-1]['team'];
				$samerank[$total]['wins'] = 0;
				$samerank[$total]['arank'] = $points[$i-1]['arank'];
				$total++;
				}
			$samerank[$total]['team'] = $points[$i]['team'];
			$samerank[$total]['wins'] = 0;
			$samerank[$total]['arank'] = $points[$i]['arank'];
			$total++;
		}elseif($total){
			break;
		}
		}
	return $samerank;
	}

function IsSameRank($points) {
	for($i=1; $i < count($points); $i++){
		if($points[$i]['arank']!=$points[$i-1]['arank']){
			return false;
		}
	}
return true;
}

function PrintStandings($points){
	for ($i=0; $i < count($points); $i++) 
		{	
		echo "<p>t".$points[$i]['team']." w".$points[$i]['wins']." #".$points[$i]['arank']."</p>";
		}
}

function PrintStandingsSwissdraw($points){
	for ($i=0; $i < count($points); $i++) 
		{	
		echo "<p>".$points[$i]['team']." g".$points[$i]['games']." vp".$points[$i]['vp']." oppvp".$points[$i]['oppvp']." sc".$points[$i]['score']." #".$points[$i]['arank']."</p>";
		}
}


function UpdateStandings($to, $from)
	{
	foreach($from as $newrank) 
		{
		for($i=0;$i<count($to);$i++) 
			{
			if($newrank['team']==$to[$i]['team'])
				{
				$to[$i]['arank'] = $newrank['arank'];
				break;
				}
			}
		}
	//for ($i=0; $i < count($to); $i++) 
	//	{	
	//	echo "<p>update t".$to[$i]['team']." v".$to[$i]['wins']." s".$to[$i]['arank']."</p>";
	//	}
		
	return $to;
}

function getMatchesWins($points, $poolId, $shared=false) {
	$sameteams = mysql_adapt_real_escape_string($points[0]['team']);
	for ($i=1; $i<count($points); $i++) {
		$sameteams .= ",".mysql_adapt_real_escape_string($points[$i]['team']);
	}
	for ($i=0; $i<count($points); $i++) {
		$team = mysql_adapt_real_escape_string($points[$i]['team']);
		$query = sprintf("
		SELECT COUNT(*) AS games,
    		COUNT((hometeam='%s' AND (homescore>visitorscore)) OR (visitorteam='%s' AND (homescore<visitorscore)) OR NULL) AS wins,
    		COUNT((hometeam='%s' AND (homescore<visitorscore)) OR (visitorteam='%s' AND (homescore>visitorscore)) OR NULL) AS losses
		FROM uo_game
		WHERE (hasStarted) AND (hometeam='%s' OR visitorteam='%s') AND isongoing=0
			AND game_id IN (SELECT game FROM uo_game_pool WHERE pool='%s')",
				$team, $team, $team, $team, $team, $team,
				mysql_adapt_real_escape_string($poolId));
		if ($shared)
			$query .= sprintf(" AND hometeam IN (%s) AND visitorteam IN (%s)", $sameteams, $sameteams);

		$result = mysql_adapt_query($query);
		$stats1 = mysqli_fetch_assoc($result);

		$points[$i]['games'] = $stats1['games'];
		$points[$i]['wins'] = $stats1['wins'];
		$points[$i]['losses'] = $stats1['losses'];
	}
	return $points;
}

function getMatchesGoals($points, $poolId, $shared=false) {
	$sameteams = mysql_adapt_real_escape_string($points[0]['team']);
	for ($i=1; $i<count($points); $i++) {
		$sameteams .= ",".mysql_adapt_real_escape_string($points[$i]['team']);
	}
	//reset counters
	for ($i=0; $i < count($points); $i++)
	{
		$points[$i]['goalsmade'] = 0;
		$points[$i]['goalsagainst'] = 0;
		$points[$i]['goalsdiff'] = 0;
	}

	// 	foreach ($points as $point) {
	for ($i=0; $i<count($points); $i++) {
		$team = mysql_adapt_real_escape_string($points[$i]['team']);

		$query = sprintf("
			SELECT hometeam,visitorteam,homescore,visitorscore
			  FROM uo_game
			  WHERE (hometeam='%s' OR visitorteam='%s') AND hasstarted AND isongoing=0
			  AND game_id IN (SELECT game FROM uo_game_pool WHERE pool='%s')",
				$team, $team, mysql_adapt_real_escape_string($poolId));
		if ($shared)
			$query .= sprintf(" AND hometeam IN (%s) AND visitorteam IN (%s)", $sameteams, $sameteams);

		$result = mysql_adapt_query($query);
		while($stats = mysqli_fetch_assoc($result))
		{
			if($stats['hometeam']==$points[$i]['team'])
			{
				$points[$i]['goalsmade']+=$stats['homescore'];
				$points[$i]['goalsagainst']+=$stats['visitorscore'];
			}
			elseif($stats['visitorteam']==$points[$i]['team'])
			{
				$points[$i]['goalsmade']+=$stats['visitorscore'];
				$points[$i]['goalsagainst']+=$stats['homescore'];
			}
		}
		$points[$i]['goalsdiff'] = $points[$i]['goalsmade'] - $points[$i]['goalsagainst'];
	}
	return $points;
}

function TeamPoolStanding($teamId, $poolId){
	$query = sprintf("SELECT u.activerank FROM uo_team_pool u WHERE pool=%d AND team=%d",
		(int)$poolId,
		(int)$teamId);
	return DBQueryToValue($query);
}

function TeamSeriesStanding($teamId){
	
	$team_info = TeamInfo($teamId);
	$ppools = SeriesPlacementPoolIds($team_info['series']);
	$standing = 1;

	$found = false;
	
	//loop all placement pools
	foreach ($ppools as $ppool){
		$teams = PoolTeams($ppool['pool_id']);
		$i=0;
		//loop all teams
		foreach($teams as $team){
			$i++;	
			$moved = PoolMoveExist($ppool['pool_id'], $i);
			//if not moved and team searched exit loop
			if(!$moved && $team['team_id']==$teamId){
				$found=true;
				break;
			}elseif(!$moved){
				$standing++;
			}
		}
		if($found){break;}
	}
		
	//if not found then return best guess
	if(!$found){
		$standing = TeamPoolStanding($teamId,$team_info['pool']);
	}
	
	return intval($standing);
}

function VictoryPoints() {
  $query = "SELECT * FROM uo_victorypoints ORDER BY pointdiff ASC";
  return DBQueryToArray($query);
}

/* Solves Ax = y for x */
function gauss_seidel($A, $y, $x = null, $tol = 0.000000001) {
  $n = count($y);
  if ($x == null) {
    $x = array();
    for ($i = 0; $i < $n; ++$i) {
      $x[$i] = 0;
    }
  }
  
  // debug_lr($n, $n, $y, $A);
  
  $maxIterations = 10000;
  for ($i = 0; $i < $n; ++$i) {
    $xprev[$i] = 0.0;
  }
  
  for ($it = 0; $it < $maxIterations; ++$it) {
    for ($j = 0; $j < $n; ++$j) {
      $xprev[$j] = $x[$j];
    }
    for ($j = 0; $j < $n; ++$j) {
      $summ = 0.0;
      
      for ($k = 0; $k < $n; ++$k) {
        if ($k != $j) {
          $summ = $summ + $A[$j][$k] * $x[$k];
        }
        $x[$j] = ($y[$j][0] - $summ) / $A[$j][$j];
      }
    }
    $diff1norm = 0.0;
    $oldnorm = 0.0;
    for ($j = 0; $j < $n; ++$j) {
      $diff1norm += abs($x[$j] - $xprev[$j]);
      $oldnorm += abs($xprev[$j]);
    }
    if ($oldnorm == 0.0) {
      $oldnorm = 1.0;
    }
    $norm = $diff1norm / $oldnorm;
    if ($norm < $tol && $i != 0) {
      return $x;
    }
  }
  return null;
}

function debug_matrix($A) {
  $msg = "";
  for ($i = 0; $i < count($A); ++$i) {
    for ($j = 0; $j < count($A[$i]); ++$j) {
      $msg .= sprintf("%3d ", $A[$i][$j]);
    }
    $msg .= "\n";
  }
  $msg .= "\n";
  return $msg;
}

function debug_lr($m, $n, $y, $A) {
  $msg = "m, n = " . $m . ", " . $n;
  $msg .= "\ny = ";
  for ($i = 0; $i < count($y); ++$i) {
    $msg .= $y[$i] . " ";
  }
  $msg .= "\nA = \n";
  
  $msg .= debug_matrix($A);
  
  debug_to_apache($msg);
}

function mmult($A, $B, $rowsA, $colsA, $rowsB, $colsB, $transposeA = false, $transposeB = false) {
  $AB = array();
  
  $m = $transposeA ? $colsA : $rowsA;
  $n = $transposeB ? $rowsB : $colsB;
  $p = $transposeA ? $rowsA : $colsA;
  
  for ($i = 0; $i < $m; ++$i) {
    $AB[$i] = array();
    for ($j = 0; $j < $n; ++$j) {
      $AB[$i][$j] = 0;
      for ($k = 0; $k < $p; ++$k) {
        $a = $transposeA ? $A[$k][$i] : $A[$i][$k];
        $b = $transposeB ? $B[$j][$k] : $B[$k][$j];
        $AB[$i][$j] += $a * $b;
      }
    }
  }
  return $AB;
}

function LRSolve($m, $n, $A, $y) {
  // FIXME
  // debug_lr($m, $n, $y, $A);
  $AtA = mmult($A, $A, $m, $n, $m, $n, true);
  
  $y0 = array( 0 => $y); // y is an array, y0 is a row vector
  $Aty = mmult($A, $y0, $m, $n, 1, $m, true, true);
  
  $ranking = gauss_seidel($AtA, $Aty);
  
  if ($ranking == null) {
    debug_to_apache("no solution found");
    $ranking = array();
    for ($i = 0; $i < $n; ++$i) {
      $ranking[$i] = -1;
    }
  }
  
  return $ranking;
}

function find_components($A, $m, $n) {
  $comps = array();
  
  $teams = array();
  $games = array();
  for ($t = 0; $t < $n; ++$t) {
    if (isset($teams[$t]))
      continue;
      $comp = array();
      $bag = array($t);
      $teams[$t] = 1;
      while (!empty($bag)) {
        $current_team = array_pop($bag);
        $comp[$current_team] = 1;
        //       debug_to_apache("checking team " . $current_team . "\n");
        
        $game_to_check = array();
        for ($g = 0; $g < $m; ++$g) {
          if (!isset($games[$g])) {
            if ($A[$g][$current_team] != 0) {
              //             debug_to_apache("adding game " . $g . "\n");
              $game_to_check[] = $g;
            }
          }
        }
        foreach ($game_to_check as $game) {
          //         debug_to_apache("checking game " . $game . "\n");
          if (!isset($games[$game])) {
            $games[$game] = 1;
            for ($tt = 0; $tt < $n; ++$tt) {
              if ($A[$game][$tt] != 0) {
                //               debug_to_apache("adding team " . $tt . "\n");
                $comp[$tt] = 2;
                if (!isset($teams[$tt])) {
                  array_push($bag, $tt);
                  $teams[$tt] = 2;
                }
              }
            }
          }
        }
      }
      //     debug_to_apache("done\n");
      //     debug_to_apache(print_r($comp, true));
      $comps[] = $comp;
  }
  
  return $comps;
}

function LRRanking($t, $games) {
  if ($t == 0)
    return array();
    
    if ($t == 1)
      return array(0 => 0);
      
      $ranking = array();
      
      $y = array();
      $game_matrix = array();
      $row = 0;
      foreach ($games as $game) {
        for ($i = 0; $i < $t; ++$i) {
          $game_matrix[$row][$i] = 0;
        }
        $game_matrix[$row][$game['home']] = 1;
        $game_matrix[$row][$game['visitor']] = -1;
        $y[$row] = $game['hscore'] - $game['vscore'];
        ++$row;
      }
      
      //   $A = array(
      //     array(0, 1, -1, 0, 0, 0, 0),
      //     array(0, 0, 1, -1, 0, 0, 0),
      //     array(0, 0, 0, 0, 1, 1, 0),
      //     array(0, 0, 0, 0, 0, 1, 1),
      //     array(0, 0, 0, 0, 1, 0, 1),
      //     array(0, 0, 0, 0, 0, 1, 1),
      //     array(0, 0, 0, 0, 0, 0, 0)
      //   );
      //   debug_to_apache(debug_matrix($A));
      //   $comps = find_components($A, count($A), 7);
      //   debug_to_apache(print_r($comps, true));
      
      //   $A = array(array(1,-1,0,0));
      //   $comps = find_components($A, count($A), 4);
      //   debug_to_apache(debug_matrix($A));
      //   debug_to_apache(print_r($comps, true));
      
      $comps = find_components($game_matrix, count($games), $t);
      //   debug_to_apache("comps\n" . print_r($comps, true));
      
      foreach ($comps as $comp) {
        for ($i = 0; $i < $t; ++$i) {
          $game_matrix[$row][$i] = 0;
        }
        foreach ($comp as $index => $v) {
          $game_matrix[$row][$index] = 1;
        }
        $y[$row] = 0;
        ++$row;
      }
      
      $ranking = LRSolve($row, $t, $game_matrix, $y);
      
      return $ranking;
}

function PowerRanking($teams, $games, $win_reward = 0) {
  // TODO special for bye team??
  $teami = 0;
  $teamindex = array();
  foreach ($teams as $team) {
    $teamindex[$team['team_id']] = $teami++;
  }
  
  $results = array();
  foreach ($games as $game) {
    if (!isset($teamindex[$game['hometeam']])) {
      $teamindex[$game['hometeam']] = $teami++;
    }
    if (!isset($teamindex[$game['visitorteam']])) {
      $teamindex[$game['visitorteam']] = $teami++;
    }
    $home_adv = $visitor_adv = 0;
    if ($game['homescore'] > $game['visitorscore']) {
      $home_adv = $win_reward;
    } else {
      $visitor_adv = $win_reward;
    }
    $results[] = array('home' => $teamindex[$game['hometeam']], 'visitor' => $teamindex[$game['visitorteam']],
      'hscore' => ($game['homescore'] + $home_adv) / ($win_reward + 1), 'vscore' => ($game['visitorscore'] + $visitor_adv)  / ($win_reward + 1));
  }
  
  $ranking = LRRanking($teami, $results);
  //   debug_to_apache(print_r($ranking, true));
  
  $scores = array();
  
  $i = 0;
  foreach ($teams as $team) {
    $scores[$team['team_id']] = $ranking[$i++];
  }
  
  return $scores;
}

function series_games($seriesId) {
  $query = sprintf(
    "SELECT g.hometeam, g.visitorteam,
            g.homescore, g.visitorscore
            FROM uo_game g
            LEFT JOIN uo_pool p on (g.pool=p.pool_id)
            WHERE p.series=%d AND g.hometeam IS NOT NULL AND g.visitorteam IS NOT NULL AND hasstarted>0 AND isongoing=0",
    intval($seriesId));

  return DBQueryToArray($query);
}

function SeriesPowerRanking($teams, $seriesId) {
  $games = series_games($seriesId);
  
  return PowerRanking($teams, $games);
}

function PoolPowerRanking($poolId, $win_reward = 0) {
  $query = sprintf(
    "SELECT p.hometeam, p.visitorteam,
            p.homescore, p.visitorscore
            FROM uo_game p
            LEFT JOIN uo_game_pool ps ON (p.game_id=ps.game)
            WHERE ps.pool = %d ORDER BY p.hometeam, p.visitorteam", (int) $poolId);
  
  $games = DBQueryToArray($query);
  
  $query = sprintf(
    "SELECT uo_team.team_id, uo_team.valid
        FROM uo_team
        RIGHT JOIN uo_team_pool ON (uo_team.team_id=uo_team_pool.team)
        WHERE uo_team_pool.pool = '%s' ORDER BY team_id", (int) $poolId);
  
  $teams = DBQueryToArray($query);
  
  return PowerRanking($teams, $games, $win_reward);
}

function Elos($teams, $games, $max_it = 1000) {
  $scale = 400;
  $k = 80;
  $kappa = 1;

  $wins = [];
  $diffs = [];
  $n = [];
  foreach ($teams as $team) {
    $wins[$team['team_id']] = 0;
    $n[$team['team_id']] = 0;
    $diffs[$team['team_id']] = 0;
  }
  $adv = [];

  foreach ($games as $game) {
    if (isset($n[$game['hometeam']]) && isset($n[$game['visitorteam']])) {
      $win = $game['homescore'] - $game['visitorscore'];
      $hwin = $wins[$game['hometeam']];
      $vwin = $wins[$game['visitorteam']];
      if ($win > 0)
        ++$hwin;
      else if ($win < 0)
        ++$vwin;
      else {
        $hwin += .5;
        $vwin += .5;
      }
      $wins[$game['hometeam']] = $hwin;
      $wins[$game['visitorteam']] = $vwin;
      $n[$game['hometeam']] += 1;
      $n[$game['visitorteam']] += 1;
      $diffs[$game['hometeam']] += $win;
      $diffs[$game['visitorteam']] -= $win;
      if (!isset($adv[$game['hometeam']]))
        $adv[$game['hometeam']] = [];
      if (!isset($adv[$game['visitorteam']]))
        $adv[$game['visitorteam']] = [];
      $adv[$game['hometeam']][$game['visitorteam']] = ($adv[$game['hometeam']][$game['visitorteam']] ?? 0) + 1;
      $adv[$game['visitorteam']][$game['hometeam']] = ($adv[$game['visitorteam']][$game['hometeam']] ?? 0) + 1;
    }
  }

  $perf0 = [];
  foreach ($teams as $team) {
    $teamId = $team['team_id'];
    if (empty($wins[$teamId]))
      $wins[$teamId] = 0;
    if (empty($n[$teamId]))
      $n[$teamId] = 0;
    $perf0[$teamId] = [$scale, $scale, $scale, $scale];
  }

  // debug_to_apache(print_r($perf0, true));

  $elos = [];
  $elos["ELOp1"] = [];
  $elos["ELOpX"] = [];
  $elos["ELOpp1"] = [];
  $elos["ELOppX"] = [];
  $elos["ELO1"] = [];
  $elos["ELOX"] = [];
  $elos["ELOd1"] = [];
  $elos["ELOdX"] = [];

  for ($it = 1; $it <= $max_it; ++$it) {
    $perf1 = []; // approx elo performance, approx. elo diff performance, elo updates, elo-davidson updates
    if ($it == 2) {
      foreach ($teams as $team) {
        $teamId = $team['team_id'];
        // scale perfect scores
        if ($n[$teamId] > 0) {
          $dist = $n[$teamId] > 10 ? .5 : .02;
          $wins[$teamId] = .5 * $dist + $wins[$teamId] * ($n[$teamId] - $dist) / $n[$teamId];
        }
        // $damp = max(.95, ($n[$teamId] - 1) / $n[$teamId]);
        // $wins[$teamId] = .5 * (1 - $damp) + $wins[$teamId] * $damp;
      }
    }
    foreach ($teams as $team) {
      $teamId = $team['team_id'];
      $oppStrength = [0, 0];
      $nopp = 0;
      if (isset($adv[$teamId])) {
        foreach ($adv[$teamId] as $opp => $ng) {
          $oppStrength[0] += $ng * $perf0[$opp][0];
          $oppStrength[1] += $ng * $perf0[$opp][1];
          $nopp += $ng;
        }
        $oppStrength[0] /= $nopp;
        $oppStrength[1] /= $nopp;
      }

      if ($n[$teamId] == 0)
        $perf1[$teamId] = [$scale, $scale, $scale, $scale];
      else {
        /* P = R_G + 800 * (S/N - 0.5) */
        $perf1[$teamId][0] = $oppStrength[0] + 2 * $scale * //
        ($wins[$teamId] / $n[$teamId] - .5);
        /* P = R_G + 800 * (Pdiff) */
        $perf1[$teamId][1] = $oppStrength[1] + 2 * $scale * //
        ($diffs[$teamId]);

        /* R' = R + k * (S-E) */
        $perf = $wins[$teamId];
        foreach ($adv[$teamId] as $opp => $ng) {
          $perf -= $ng * (1 / (1 + 10 ** (($perf0[$opp][2] - $perf0[$teamId][2]) / $scale)));
        }
        $perf1[$teamId][2] = $perf0[$teamId][2] + $k * $perf;

        /* R' = R + k * (S - E), E = (10**(r/s) + kappa/2) / (10**(-r/s) + kappa + 10**(r/s)) */
        $perf = $wins[$teamId];
        foreach ($adv[$teamId] as $opp => $ng) {
          $r_ab = $perf0[$opp][3] - $perf0[$teamId][3];
          $r_ab /= -$scale;
          if ($r_ab > 10)
            $r_ab = 10;
          if ($r_ab < -10)
            $r_ab = 10;
          $perf -= $ng * (10 ** ($r_ab) + $kappa / 2) / (10 ** (-$r_ab) + $kappa + 10 ** ($r_ab));
        }
        $perf1[$teamId][3] = $perf0[$teamId][3] + $k * $perf;
      }

      if ($it == 1) {
        $elos["ELOp1"][$teamId] = $perf1[$teamId][0];
        $elos["ELOpp1"][$teamId] = $perf1[$teamId][1];
        $elos["ELO1"][$teamId] = $perf1[$teamId][2];
        $elos["ELOd1"][$teamId] = $perf1[$teamId][3];
      } else if ($it == $max_it) {
        $elos["ELOpX"][$teamId] = $perf1[$teamId][0];
        $elos["ELOppX"][$teamId] = $perf1[$teamId][1];
        $elos["ELOX"][$teamId] = $perf1[$teamId][2];
        $elos["ELOdX"][$teamId] = $perf1[$teamId][3];
      }
    }
    // debug_to_apache(print_r($perf1, true));
    $perf0 = $perf1;
  }
  return $elos;
}

function Glickos($teams, $games) {
  $players = [];
  foreach ($teams as $team) {
    $players[$team['team_id']] = new Glicko2Player();
  }
  foreach ($players as $id => $player) {
    $player->Update();
    $glickos[$id] = ['rating' => $player->rating, 'rd' => $player->rd, 'sigma' => $player->sigma];
  }
  
  $it = 1;
  for ($i = 1; $i <= $it; ++$i) {
    foreach ($games as $game) {
      $ht = $game['hometeam'];
      $vt = $game['visitorteam'];
      if (isset($players[$ht]) && isset($players[$vt])) {
        $win = $game['homescore'] - $game['visitorscore'];
        if ($i > 0) {
          $win += normalvariate(0, 1);
        }
        if ($i < 0) {
          $prediction = $glickos[$ht]['rating'] - $glickos[$vt]['rating'];
          $rh = $glickos[$ht]['rating'];
          $rv = $glickos[$vt]['rating'];
          if ($prediction < -200)
            $prob = .1;
          if ($prediction > -200)
            $prob = .2;
          if ($prediction > -100)
            $prob = .3;
          if ($prediction > -50)
            $prob = .5;
          if ($prediction > 50)
            $prob = .7;
          if ($prediction > 100)
            $prob = .8;
          if ($prediction > 200)
            $prob = .9;
          $win0 = $win;
          if (rand() / getrandmax() < $prob)
            $win = 1;
          else
            $win = 0;
          // debug_to_apache("rh $rh rv $rv r $win0 p $prob w $win");
        }
        if ($win > 0)
          $players[$ht]->AddWin($players[$vt]);
        else if ($win > 0)
          $players[$ht]->AddLoss($players[$vt]);
        else
          $players[$ht]->AddDraw($players[$vt]);
      }
    }

    $glickos = [];
    foreach ($players as $id => $player) {
      $player->Update();
      $glickos[$id] = ['rating' => $player->rating, 'rd' => $player->rd, 'sigma' => $player->sigma];
    }
  }

  return $glickos;
}

function simpleLR($points) {
  // b_1 = sum(xi-x)(yi-y) / sum(xi-x)**2, b0 = y-b1x;
  if (empty($points))
    throw new Exception("empty matrix");
  
  $xavg = 0;
  $yavg = 0;
  foreach ($points as $p) {
    $xavg += $p[0];
    $yavg += $p[1];
  }
  $xavg /= count($points);
  $yavg /= count($points);
  $sum1 = 0;
  $sum2 = 0;

  foreach ($points as $p) {
    $sum1 += ($p[0] - $xavg) * ($p[1] - $yavg);
    $sum2 += ($p[0] - $xavg) * ($p[0] - $xavg);
  }
  if ($sum2 > 0)
    $b1 = $sum1 / $sum2;
  else
    $b1 = $sum1;
  $b2 = $yavg - $b1 * $xavg;
  return [$b1, $b2];
}

function WinPredictionAccuracy($rating, $teams, $games, $debug = false) {
  $accuracies = [];
  foreach ($teams as $team) {
    $teamId = $team['team_id'];
    $accuracies[$teamId] = teamPredictionAccuracy($rating, $teamId, $games, $debug);
  }
  return $accuracies;
}

function ScorePredictionAccuracy($rating, $teams, $games, $debug = false) {
  $hasTeam = [];
  $diff = [];
  $n = [];
  foreach ($teams as $team) {
    $hasTeam[$team['team_id']] = 1;
    $diff[$team['team_id']] = 0;
    $n[$team['team_id']] = 0;
  }
  $xy = [];
  foreach ($games as $game) {
    if (isset($hasTeam[$game['hometeam']]) && isset($hasTeam[$game['visitorteam']])) {
      $xy[] = [$rating[$game['hometeam']] - $rating[$game['visitorteam']], $game['homescore'] - $game['visitorscore']];
    }
  }

  if (empty($xy))
    return $diff;

  if ($debug)
    debug_to_apache($xy);
  
  $linr = simpleLR($xy);
  if ($debug) {
    foreach ($xy as $p) {
      debug_to_apache("$p[0]\t$p[1]");
    }
    debug_to_apache("b " . $linr[0] . " a " . $linr[1]);
  }
  // $linr = [1,0];

  foreach ($games as $game) {
    if (isset($hasTeam[$game['hometeam']]) && isset($hasTeam[$game['visitorteam']])) {
      $rd = $rating[$game['hometeam']] - $rating[$game['visitorteam']];
      $prediction = $linr[0] * $rd + $linr[1];
      $result = $game['homescore'] - $game['visitorscore'];
      $dd = abs($prediction - $result);
      $diff[$game['hometeam']] += $dd;
      $diff[$game['visitorteam']] += $dd;
      $n[$game['hometeam']] += 1;
      $n[$game['visitorteam']] += 1;

      // $ht = $game['hometeam']; $vt = $game['visitorteam']; $hs = $game['homescore']; $vs = $game['visitorscore']; $dh = $diff[$ht]; $dv = $diff[$vt];
      // $a = $linr[0]; $b = $linr[1];
      // debug_to_apache("h $ht v $vt $hs : $vs = $result p $prediction d $rd a $a b $b e $dd $dh $dv");
    }
  }
  foreach ($teams as $team) {
    if ($n[$team['team_id']] > 0)
      $diff[$team['team_id']] /= $n[$team['team_id']];
  }
  return $diff;
}

function teamPredictionAccuracy($rating, $teamId, $games, $debug = false) {
  if (empty($rating[$teamId]))
    return 0;
  $score = 0;
  $n = 0;
  foreach ($games as $game) {
    $opp = $game['hometeam'] == $teamId ? $game['visitorteam'] : ($game['visitorteam'] == $teamId ? $game['hometeam'] : null);
    $ht = $game['hometeam']; $vt = $game['visitorteam'];
    $hs = $game['homescore']; $vs = $game['visitorscore'];
    if ($opp != null && isset($rating[$opp])) {
      $prediction = $rating[$opp] > $rating[$teamId] ? -1 : ($rating[$opp] < $rating[$teamId] ? 1 : 0);
      $rh = $rating[$ht]; $rv = $rating[$vt];
      $result = $game['homescore'] - $game['visitorscore'];
      if ($teamId == $game['visitorteam'])
        $result *= -1;
      if (($result > 0 && $prediction > 0) || ($result < 0 && $prediction < 0) || ($result == 0 && $prediction == 0))
        ++$score;
      if ($debug)
      debug_to_apache("h $ht v $vt o $opp $hs : $vs r $result rh $rh rv $rv p $prediction s $score");
      ++$n;
    }
  }
  return 1 - $score / $n;
}

?>
