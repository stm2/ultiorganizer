<?php 
include_once $include_prefix.'lib/user.functions.php';

/**
 * Returns reservation info for given reservation.
 * 
 * @param int $id uo_reservation.id 
 */
function ReservationInfo($id) {
	$locale = str_replace(".", "_", getSessionLocale());
	$query = sprintf("SELECT res.id, res.location, res.fieldname, res.reservationgroup, 
		res.date, res.starttime, res.endtime, res.timeslots, loc.name as name, 
		inf.info as info, loc.address as address, res.season  
		FROM uo_reservation as res 
		LEFT JOIN uo_location as loc on (res.location=loc.id)
		LEFT JOIN uo_location_info inf on (loc.id = inf.location_id AND inf.locale='%s' ) 
		WHERE res.id=%d", mysql_adapt_real_escape_string($locale), (int)$id);
	return DBQueryToRow($query);
}
	
function ReservationName($reservationInfo) {
	return utf8entities($reservationInfo['name'])." "._("Field")." ".utf8entities($reservationInfo['fieldname'])." ".ShortDate($reservationInfo['starttime'])." ".DefHourFormat($reservationInfo['starttime']);
}

function ReservationGames($placeId, $seasonId="") {
	$query = sprintf("
		SELECT game_id, hometeam, kj.name as hometeamname, visitorteam, vj.name as visitorteamname, pp.pool as pool,
			time, homescore, visitorscore, pool.timecap, pool.timeslot, pp.timeslot as gametimeslot, pool.series, pool.color, 
			CONCAT(ser.name, ', ', pool.name) as seriespoolname, ser.name AS seriesname, pool.name AS poolname,
			CONCAT(loc.name, ' "._("Field")." ', res.fieldname) AS locationname,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname
		FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (pool.series=ser.series_id)
			left join uo_location loc on (res.location=loc.id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
		WHERE res.id=%d",(int)$placeId);
	
	if(!empty($seasonId))
		$query .= sprintf("	AND ser.season='%s'",mysql_adapt_real_escape_string($seasonId));
	
	$query .= " ORDER BY pp.time ASC";
	
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	
	return  $result;
}


function ReservationGetGame($reservationId, $time="") {
	$query = sprintf("SELECT g.game_id FROM uo_game g 
			LEFT JOIN uo_reservation r on (g.reservation=r.id) 
			WHERE r.id=%d",
	        (int)$reservationId);
	
	if(!empty($time))
		$query .= sprintf("	AND g.time='%s'",mysql_adapt_real_escape_string($time));
	
	$query .= " ORDER BY g.game_id ASC";
	
	return  DBQueryToArray($query);
}

function ReservationGamesByField($locId, $fieldname, $seasonId="") {
	$query = sprintf("
		SELECT game_id, hometeam, kj.name as hometeamname, visitorteam, vj.name as visitorteamname, pp.pool as pool,
			time, homescore, visitorscore, pool.timecap, pool.timeslot, pool.series, pool.color, 
			CONCAT(ser.name, ', ', pool.name) as seriespoolname, ser.name AS seriesname, pool.name AS poolname,
			CONCAT(loc.name, ' "._("Field")." ', res.fieldname) AS locationname,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname
		FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (pool.series=ser.series_id)
			left join uo_location loc on (res.location=loc.id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
		WHERE res.fieldname='%s' AND loc.id=%d",mysql_adapt_real_escape_string($fieldname), intval($locId));
	
	if(!empty($seasonId))
		$query .= sprintf("	AND ser.season='%s'",mysql_adapt_real_escape_string($seasonId));
	
	$query .= " ORDER BY pp.time ASC";
	
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	
	return  $result;
}

function ReservationFields($seasonId) {
	$query = sprintf("
		SELECT loc.id, res.fieldname
			FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (pool.series=ser.series_id)
			left join uo_location loc on (res.location=loc.id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
		WHERE ser.season='%s'
		GROUP BY loc.id, res.fieldname",
			mysql_adapt_real_escape_string($seasonId));
	
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	
	return  $result;
}

function ResponsibleReservationGames($placeId, $gameResponsibilities) {
  $query = "SELECT game_id, hometeam, kj.name as hometeamname, visitorteam,
			vj.name as visitorteamname, pp.pool as pool, time, homescore, visitorscore,
			pool.timecap, pool.timeslot, pool.series, 
			ser.name as seriesname, pool.name as poolname,
			loc.name as placename, res.fieldname,
               pp.hasstarted, pp.isongoing,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pool.color, pgame.name AS gamename
		FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (pool.series=ser.series_id)
			left join uo_location loc on (res.location=loc.id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)";
  if ($placeId)
    $query .= sprintf("WHERE res.id=%d", (int) $placeId);
  else
    $query .= "WHERE res.id IS NULL";
  $query .= " AND game_id IN (" . implode(",", $gameResponsibilities) . ")
		ORDER BY pp.time ASC";
  
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  
  return $result;
}

function ReservationSeasons($reservationId) {
	$query = sprintf("SELECT DISTINCT ser.season FROM uo_game p 
		LEFT JOIN uo_pool pool ON (p.pool=pool.pool_id)
		LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
		WHERE p.reservation=%d", (int)$reservationId);
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	$ret = array();
	while ($row = mysqli_fetch_row($result)) {
		$ret[] = $row[0];
	}
	return $ret;
}

function ReservationGameSeries($reservationId) {
  $query = sprintf("SELECT count(g.game_id) games, ser.series_id series FROM uo_game g
		LEFT JOIN uo_pool pool ON (g.pool=pool.pool_id)
		LEFT JOIN uo_series ser ON (pool.series=ser.series_id)
		WHERE g.reservation=%d GROUP BY ser.series_id", (int)$reservationId);
  return DBQueryToArray($query);
}

/**
 * Set reservation data.
 *
 * Access level: eventadmin
 * 
 * @param Int $id: Reservation id
 * @param array $data: Field data for uo_reservation
 */
function SetReservation($reservationId, $data) {
	if (hasEditSeasonSeriesRight($data['season'])) {
		$query = sprintf("UPDATE uo_reservation SET location=%d, fieldname='%s', reservationgroup='%s', 
			date='%s', starttime='%s', endtime='%s', timeslots='%s', season='%s' WHERE id=%d",
			(int)$data['location'],
			mysql_adapt_real_escape_string($data['fieldname']),
			mysql_adapt_real_escape_string($data['reservationgroup']),
			mysql_adapt_real_escape_string($data['date']),
			mysql_adapt_real_escape_string($data['starttime']),
			mysql_adapt_real_escape_string($data['endtime']),
			mysql_adapt_real_escape_string($data['timeslots']),
			mysql_adapt_real_escape_string($data['season']),
			(int)$reservationId);
		 DBQuery($query);
	} else { die('Insufficient rights to change reservation'); }	
}

/**
 * Add a reservation.
 *
 * Access level: eventadmin
 * 
 * @param array $data: Field data for uo_reservation
 */
function AddReservation($data) {

  if (hasEditSeasonSeriesRight($data['season'])) {
		$query = sprintf("INSERT INTO uo_reservation (location, fieldname, reservationgroup, date, 
			starttime, endtime, timeslots, season) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
			(int)$data['location'],
			mysql_adapt_real_escape_string($data['fieldname']),
			mysql_adapt_real_escape_string($data['reservationgroup']),
			mysql_adapt_real_escape_string($data['date']),
			mysql_adapt_real_escape_string($data['starttime']),
			mysql_adapt_real_escape_string($data['endtime']),
			mysql_adapt_real_escape_string($data['timeslots']),
			mysql_adapt_real_escape_string($data['season'])
			);
		return DBQueryInsert($query);
	} else { die('Insufficient rights to add reservation'); }	
}


function RemoveReservation($id, $season) {
	if (isSuperAdmin() || isSeasonAdmin($season)) {
		$query = sprintf("DELETE FROM uo_reservation WHERE id=%d", (int)$id);
		$result = mysql_adapt_query($query);
		if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	} else { die('Insufficient rights to remove location'); }	
}

function ReservationInfoArray($reservations) {
	if (empty($reservations))
		return array();
	$fetch = array();
	foreach ($reservations as $reservation) {
		$fetch[] = (int)$reservation;
	}
	$fetchStr = implode(",", $fetch);
	$query = "SELECT DATE_FORMAT(starttime, '%Y%m%d') as gameday, id FROM uo_reservation WHERE id IN (".$fetchStr.") 
		ORDER BY DATE(starttime), location, fieldname +0, fieldname, starttime ASC, id";
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	$ret = array();
	while ($row = mysqli_fetch_row($result)) {
		if (!isset($ret[$row[0]])) {
			$ret[$row[0]] = array();
		}
		$next = $ret[$row[0]];
		$nextInfo = ReservationInfo($row[1]);
		$nextGames = array();
		$gameResults = ReservationGames($row[1]);
		while ($gameRow = mysqli_fetch_assoc($gameResults)) {
			$nextGames["".$gameRow['game_id']] = $gameRow;
		}
		$nextInfo['games'] = $nextGames; 
		$next["".$row[1]] = $nextInfo;
		$ret[$row[0]] = $next; 
	}
	return $ret;
}

function UnscheduledTeams() {
	if (isSuperAdmin()) {
		$query = "SELECT team_id FROM uo_team WHERE team_id IN (SELECT hometeam FROM uo_game WHERE reservation IS NULL AND time IS NULL)
			OR team_id IN (SELECT visitorteam FROM uo_game WHERE reservation IS NULL AND time IS NULL)";
	} else {
		$query = "SELECT team_id FROM uo_team WHERE (team_id IN (SELECT hometeam FROM uo_game WHERE reservation IS NULL AND time IS NULL)
			OR team_id IN (SELECT visitorteam FROM uo_game WHERE reservation IS NULL AND time IS NULL)) AND (";
		$criteria = "";
		$first = true;
		if (isset($_SESSION['userproperties']['userrole']['seasonadmin'])) {
			foreach ($_SESSION['userproperties']['userrole']['seasonadmin'] as $season => $propId) {
				if ($first) {
					$first = false;
				} else {
					$criteria .= " OR ";
				}
				$criteria .= sprintf("series IN (SELECT series_id FROM uo_series WHERE season='%s')", mysql_adapt_real_escape_string($season));		
			}
		}
		if (isset($_SESSION['userproperties']['userrole']['seriesadmin'])) {
			$fetch = array();
			foreach ($_SESSION['userproperties']['userrole']['seriesadmin'] as $series => $propId) {
				$fetch[] = (int)$series;
			}
			if (!$first) {
				$criteria .= " OR ";
			}
			$criteria .= "series IN (".implode(",",$fetch).")";		
		}
		if (strlen($criteria) == 0) {
			return array();
		} else {
			$query .= $criteria.")";
		}
	}
	echo "<!--".$query."-->\n";
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	$ret = array();
	while ($row = mysqli_fetch_row($result)) {
		$ret[] = $row[0];
	}
	return  $ret;
}

function CanDeleteReservation($reservationId) {
	$query = sprintf("SELECT count(*) FROM uo_game WHERE reservation=%d",
		(int)$poolId);
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	if (!$row = mysqli_fetch_row($result)) return false;
	return $row[0] == 0;
}

function gameDuration($gameInfo) {
  return empty($gameInfo['gametimeslot']) ? $gameInfo['timeslot'] : $gameInfo['gametimeslot'];
}

function groupSelection($season, $group, $url_params) {
  $groups = SeasonReservationgroups($season);
  $html = "";
  if (count($groups) > 1) {
    $html .= "<p>\n";
    foreach ($groups as $grouptmp) {
      $url = MakeUrl($url_params, ['group' => urlencode($grouptmp['reservationgroup'])]);
      if ($group == $grouptmp['reservationgroup']) {
        $html .= "<a class='groupinglink' href='$url'><span class='selgroupinglink'>" . U_(
          $grouptmp['reservationgroup']) . "</span></a>";
      } else {
        $html .= "<a class='groupinglink' href='$url'>" . U_($grouptmp['reservationgroup']) . "</a>";
      }
      $html .= " ";
    }
    $url = MakeUrl($url_params, ['group' => "__all"]);
    if ($group == "__all") {
      $html .= "<a class='groupinglink' href='$url'><span class='selgroupinglink'>" . _("All") . "</span></a>";
    } else {
      $html .= "<a class='groupinglink' href='$url'>" . _("All") . "</a>";
    }
    $html .= "</p>\n";
  }
  return $html;
}

function ReservationsForLocation($id, $limit, $order = 'date_reversed') {
  $query = sprintf(
    "SELECT MIN(res.date) as date, ss.season_id as season_id, ANY_VALUE(ss.name) as season_name, count(res.id) as count
		FROM uo_reservation as res 
          LEFT JOIN uo_season ss ON (ss.season_id = res.season)
		WHERE res.location = %d
          GROUP BY res.date, ss.season_id
          ORDER BY res.date DESC
          LIMIT 0, %d", (int) $id, (int) $limit);
  return DBQueryToArray($query);
}
?>
