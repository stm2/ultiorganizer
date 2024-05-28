<?php 

function EventCategories(){
	return array("security","user","enrolment","club","team","player","season","series","pool","game","media");
	}
	
function LogEvent($event){
		if(empty($event['id1']))
			$event['id1']="";
			
		if(empty($event['id2']))
			$event['id2']="";

		if(empty($event['source']))
			$event['source']="";
			
		if(empty($event['description']))
			$event['description']="";
		
		if(strlen($event['description'])>50)
			$event['description']=mb_strcut($event['description'],0,50);
		
		if(strlen($event['id1'])>20)
			$event['id1']=mb_strcut($event['id1'],0,20);
		
		if(strlen($event['id2'])>20)
			$event['id2']=mb_strcut($event['id2'],0,20);
			
		if(empty($event['user_id'])){
			if(!empty($_SESSION['uid']))
				$event['user_id'] = $_SESSION['uid'];
			else
				$event['user_id'] = "unknown";
		}
		
		$event['ip'] = "";		
		if(!empty($_SERVER['REMOTE_ADDR']))
			$event['ip'] = anonymize_ip($_SERVER['REMOTE_ADDR']);
		if (getDBVersion() < 85)
			$event['ip'] = substr($event['ip'], 0, 15);
	     $event['ip'] = anonymize_ip($event['ip']);
		
		$query = sprintf("INSERT INTO uo_event_log (user_id, ip, category, type, source,
			id1, id2, description)
				VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
		mysql_adapt_real_escape_string($event['user_id']),
		mysql_adapt_real_escape_string($event['ip']),
		mysql_adapt_real_escape_string($event['category']),
		mysql_adapt_real_escape_string($event['type']),
		mysql_adapt_real_escape_string($event['source']),
		mysql_adapt_real_escape_string($event['id1']),
		mysql_adapt_real_escape_string($event['id2']),
		mysql_adapt_real_escape_string($event['description']));
	$result = mysql_adapt_query($query);
	if (!$result) { 
	 die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysql_adapt_error());
	}
	return mysql_adapt_insert_id();
	}	

function EventList($categoryfilter, $userfilter, $offset, $limit){
	if(isSuperAdmin()){
		if(count($categoryfilter)==0){
			return false;
		}
		$query = "SELECT * FROM uo_event_log WHERE ";
		
		$i=0;	
		foreach($categoryfilter as $cat){
			if($i==0){$query .= "(";}
			if($i>0){$query .= " OR ";}
		
			$query .= sprintf("category='%s'", mysql_adapt_real_escape_string($cat));
			$i++;
			if($i==count($categoryfilter)){	$query .= ")";}
		}
		
		if(!empty($userfilter)){
			$query .= sprintf("AND user_id='%s'", mysql_adapt_real_escape_string($userfilter));
		}
		$query .= sprintf(" ORDER BY time DESC LIMIT %d, %d", (int) $offset, (int) $limit);
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . $query . "\n" . mysql_adapt_error()); }
		return $result;
	}
}

function ClearEventList($ids){
	if(isSuperAdmin()){
		$query = sprintf("DELETE FROM uo_event_log WHERE event_id IN (%s)", mysql_adapt_real_escape_string($ids));
				
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
		return $result;
	}
}
	
function Log1($category, $type, $id1="", $id2="", $description="", $source=""){
	$event['category'] = $category;
	$event['type'] = $type;
	$event['id1'] = $id1;
	$event['id2'] = $id2;
	$event['description'] = $description;
	$event['source'] = $source;	
	return LogEvent($event);
	}	

function Log2($category, $type, $description="", $source=""){
	$event['category'] = $category;
	$event['type'] = $type;
	$event['description'] = $description;
	$event['source'] = $source;	
	return LogEvent($event);
	}
	
function LogPlayerProfileUpdate($playerId, $source=""){
	$event['category'] = "player";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $playerId;
	$event['description'] = "profile updated";
	return LogEvent($event);
	}
	
function LogTeamProfileUpdate($teamId, $source=""){
	$event['category'] = "team";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $teamId;
	$event['description'] = "profile updated";
	return LogEvent($event);
	}
	
function LogUserAuthentication($userId, $result, $source=""){
	$event['user_id'] = $userId;
	$event['category'] = "security";
	$event['type'] = "authenticate";
	$event['source'] = $source;
	$event['description'] = $result;
	return LogEvent($event);
	}

function LogGameResult($gameId, $result, $source=""){
	$event['category'] = "game";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $result;
	return LogEvent($event);
	}	

function LogDefenseResult($gameId, $result, $source=""){
	$event['category'] = "defense";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $result;
	return LogEvent($event);
	}	

function LogGameUpdate($gameId, $details, $source=""){
	$event['category'] = "game";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $details;
	return LogEvent($event);
	}

function LogDefenseUpdate($gameId, $details, $source=""){
	$event['category'] = "defense";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $details;
	return LogEvent($event);
	}

function GetLastGameUpdateEntry($gameId, $source) {
	$query = sprintf("SELECT * FROM uo_event_log WHERE id1=%d AND source='%s' ORDER BY TIME DESC",
		(int)$gameId, mysql_adapt_real_escape_string($source));	
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	return mysqli_fetch_assoc($result);
}

function LogPoolUpdate($poolId, $details, $source=""){
	$event['category'] = "pool";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $poolId;
	$event['description'] = $details;
	return LogEvent($event);
	}

function LogDbUpgrade($version, $end = false, $source = "") {
  $event['category'] = "database";
  $event['type'] = "change";
  $event['source'] = $source;
  $event['id1'] = $version;
  $event['description'] = $end ? "finished" : "started";
  return LogEvent($event);
}

/**
 * Log page load into database for usage statistics.
 *
 * @param string $page
 *          - loaded page
 */
function LogPageLoad($page){

  $query=sprintf("SELECT loads FROM uo_pageload_counter WHERE page='%s'",
		mysql_adapt_real_escape_string($page));
  $loads = DBQueryToValue($query);
  
  if($loads<0){
    $query=sprintf("INSERT INTO uo_pageload_counter (page, loads) VALUES ('%s',%d)",
        mysql_adapt_real_escape_string($page),1);
    DBQuery($query);
    
  }else{
    $loads++;
    $query=sprintf("UPDATE uo_pageload_counter SET loads=%d WHERE page='%s'",
	  $loads,
      mysql_adapt_real_escape_string($page));
    DBQuery($query);
  }
}

function anonymize_ip($ip) {
  return preg_replace(['/\.\d*$/', '/[\da-f]*:[\da-f]*$/'], ['.0', '0:0'], $ip);
}

/**
 * Log visitors visit into database for usage statistics.
 * 
 * @param string $ip - ip address
 */
function LogVisitor($ip){
  $ip = anonymize_ip($ip);

  $query=sprintf("SELECT visits FROM uo_visitor_counter WHERE ip='%s'",
		mysql_adapt_real_escape_string($ip));
  $visits = DBQueryToValue($query);
 
  if($visits<0){
    $query=sprintf("INSERT INTO uo_visitor_counter (ip, visits) VALUES ('%s',%d)",
        mysql_adapt_real_escape_string($ip),1);
    DBQuery($query);
  }else{
    $visits++;
    $query=sprintf("UPDATE uo_visitor_counter SET visits=%d WHERE ip='%s'",
	  $visits,
      mysql_adapt_real_escape_string($ip));
    DBQuery($query);
  }
}

/**
 * Get visitor count.
 */
function LogGetVisitorCount(){
  $query=sprintf("SELECT SUM(visits) AS visits, COUNT(ip) AS visitors FROM uo_visitor_counter");
  return DBQueryToRow($query);
}

/**
 * Get page loads.
 */
function LogGetPageLoads(){
  $query=sprintf("SELECT page, loads FROM uo_pageload_counter ORDER BY loads DESC");
  return DBQueryToArray($query);
}

function PageLoadStats() {
  $query = sprintf("SELECT count(*) as count, loads FROM uo_pageload_counter GROUP BY loads ORDER BY loads ASC");
  $stats = DBQueryToArray($query);

  $hist = [];

  $clade = 0;
  $cladesize = 1;
  $sum = 0;
  foreach ($stats as $row) {
    if ($row['loads'] >= $clade) {
      while ($row['loads'] >= $clade) {
        if ($row['loads'] <= $clade)
          $sum += $row['count'];
        $hist[$clade] = $sum;
        increaseClade($clade, $cladesize);
        $sum = 0;
      }
    } else if ($row['loads'] <= $clade)
      $sum += $row['count'];
  }

  return $hist;
}

function InvalidPageLoads() {
  $query = "SELECT id, page FROM uo_pageload_counter";
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $valid = $invalid = 0;
  while ($row = mysqli_fetch_assoc($result)) {
    $view = $row['page'];
    if (!include_exists($view . ".php")) {
      ++$invalid;
    } else {
      ++$valid;
    }
  }
  return $invalid;
}

function DeleteLoads(float $freq, bool $confirmed) {
  if (!isSuperAdmin())
    die('insufficient rights to delete stats');

  $freq = intval($freq);
  $select = "loads <= $freq";

  if ($confirmed === true) {
    $query = "DELETE FROM uo_pageload_counter WHERE $select";
    Log1("database", "delete", "", "", "delete loads under $freq", "purge");
    return DBQuery($query);
  } else {
    return DBQueryToValue("SELECT count(*) FROM uo_pageload_counter WHERE $select");
  }
}

function ReduceLoads(float $fact, bool $confirmed) {
  if (!isSuperAdmin())
    die('insufficient rights to delete stats');

  $fact = MIN(1, floatval($fact));

  if ($confirmed === true) {
    Log1("database", "change", "", "", "reduce loads by $fact", "purge");
    $query = "UPDATE uo_pageload_counter SET loads = loads - 1 WHERE loads > 10 AND loads <= 20";
    DBQuery($query);
    $query = "UPDATE uo_pageload_counter SET loads = CEIL(loads * $fact) WHERE loads > 20";

    return DBQuery($query);
  } else {
    return DBQueryToValue("SELECT count(*) FROM uo_pageload_counter WHERE loads > 10");
  }
}

function DeleteInvalidLoads(bool $confirmed) {
  if (!isSuperAdmin())
    die('insufficient rights to delete stats');

  $query = "SELECT id, page FROM uo_pageload_counter";
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $valid = $invalid = 0;
  Log1("database", "change", "", "", "delete invalid loads", "purge");
  while ($row = mysqli_fetch_assoc($result)) {
    $view = $row['page'];
    if (!include_exists($view . ".php")) {
      if ($confirmed == true)
        DBQuery("DELETE FROM uo_pageload_counter WHERE id = " . $row['id']);
      ++$invalid;
    } else {
      ++$valid;
    }
  }
  return $invalid;
}

function LogStats() {
  $query = sprintf("SELECT YEAR(time) as year, count(*) as count FROM uo_event_log GROUP BY YEAR(time)");
  return DBQueryToArray($query);
}

function DeleteLogs(int $maxyear, bool $confirmed = true) {
  if (!isSuperAdmin())
    die('insufficient rights to delete stats');

  $year = intval($maxyear);
  $select = "YEAR(time) <= $year";

  if ($confirmed === true) {
    Log1("database", "delete", "", "", "delete logs before $year", "purge");
    $query = "DELETE FROM uo_event_log WHERE $select";
    return DBQuery($query);
  } else {
    return DBQueryToValue("SELECT count(*) FROM uo_event_log WHERE $select");
  }
}

function increaseClade(&$clade, int &$cladesize) {
  if ($clade < 5) {
    ++$clade;
  } else if ($clade == 5) {
    $clade = 10;
    $cladesize = 10;
  } else {
    $c = $cladesize;
    while ($c % 10 == 0)
      $c /= 10;
    if ($c == 5)
      $cladesize *= 2;
    else
      $cladesize *= 5;
    $clade = $cladesize;
  }
}

function VisitorStats() {
  $query = sprintf("SELECT count(*) as count, visits FROM uo_visitor_counter GROUP BY visits ORDER BY visits ASC");
  $stats = DBQueryToArray($query);

  $hist = [];

  $clade = 0;
  $cladesize = 1;
  $sum = 0;
  foreach ($stats as $row) {
    if ($row['visits'] >= $clade) {
      while ($row['visits'] >= $clade) {
        if ($row['visits'] <= $clade)
          $sum += $row['count'];
        $hist[$clade] = $sum;
        increaseClade($clade, $cladesize);
        $sum = 0;
      }
    } else if ($row['visits'] <= $clade)
      $sum += $row['count'];
  }

  return $hist;
}

function DeleteVisitors(int $freq, bool $confirmed = false) {
  if (!isSuperAdmin())
    die('insufficient rights to delete stats');

  $freq = intval($freq);
  $select = "visits <= $freq";

  if ($confirmed === true) {
    Log1("database", "delete", "", "", "delete visitors under $freq", "purge");
    $query = "DELETE FROM uo_visitor_counter WHERE $select";
    return DBQuery($query);
  } else {
    $query = "SELECT count(*) FROM uo_visitor_counter WHERE $select";
    return DBQueryToValue($query);
  }
}

function ReduceVisitors(float $fact, bool $confirmed = false) {
  if (!isSuperAdmin())
    die('insufficient rights to delete stats');

  $fact = MIN(1, floatval($fact));
  if ($confirmed === true) {
    Log1("database", "change", "", "", "reduce vistors by $fact", "purge");
    $query = "UPDATE uo_visitor_counter SET visits = visits - 1 WHERE visits > 10 AND visits <= 20";
    $query = "UPDATE uo_visitor_counter SET visits = CEIL(visits * $fact) WHERE visits > 20";

    return DBQuery($query);
  } else {
    return DBQueryToValue("SELECT count(*) FROM uo_visitor_counter WHERE visits > 10");
  }
}

function SeasonStats() {
  $query = "SELECT year(endtime) as end, uo_season.season_id, uo_series.series_id FROM uo_season LEFT JOIN uo_series ON (uo_season.season_id = uo_series.season ) ORDER BY end ASC, season_id ASC, series_id ASC";
  return DBQueryToArray($query);
}
