<?php
global $serverConf;
$serverConf = GetSimpleServerConf();
global $locales;
$locales = getAvailableLocalizations();

function IsTwitterEnabled() {
	return false;
}

function GetPageTitle() {
	global $serverConf;
	return utf8entities($serverConf['PageTitle']);
}

function GetDefaultLocale() {
	global $serverConf;
	return $serverConf['DefaultLocale'];
}

function GetDefTimeZone() {
	global $serverConf;
	return $serverConf['DefaultTimezone'];
}

function IsFacebookEnabled() {
	return false;
}

function IsGameRSSEnabled() {
	global $serverConf;
	return ($serverConf['GameRSSEnabled'] == "true");
}

function ShowDefenseStats() {
	global $serverConf;
	return ($serverConf['ShowDefenseStats'] == "true");
}

function GetSettingsValidationToken() {
  $query = "SELECT `value` FROM `uo_setting` WHERE `name`='SettingsToken'";
  $result = (int) DBQueryToValue($query);
  if ($result > 0)
    return $result;
  DBQueryInsert("INSERT INTO `uo_setting` (`name`, `value`) VALUES ('SettingsToken', '1')");
  return 1;
}

function IncreaseSettingsValidationToken() {
  $token = GetSettingsValidationToken();
  $token += 1;
  DBQueryInsert("UPDATE `uo_setting` SET `value` = '$token' WHERE `name`='SettingsToken'");
}

function GetServerConf() {
	$query = "SELECT * FROM uo_setting ORDER BY setting_id";
	return DBQueryToArray($query);
}

function GetSimpleServerConf() {
	$query = "SELECT * FROM uo_setting ORDER BY setting_id";
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }
	
	$retarray = array();
	while ($row = mysqli_fetch_assoc($result)) {
		$retarray[$row['name']] = $row['value'];
	}
	return $retarray;
}

function SetServerConf($settings) {
	if(isSuperAdmin()){
		foreach($settings as $setting){
			$query = sprintf("SELECT setting_id FROM uo_setting WHERE name='%s'",
				mysql_adapt_real_escape_string($setting['name']));
			$result = mysql_adapt_query($query);
			if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
			if ($row = mysqli_fetch_row($result)) {
				$query = sprintf("UPDATE uo_setting SET value='%s' WHERE setting_id=%d",
			 		mysql_adapt_real_escape_string($setting['value']),
					(int)$row[0]);
				$result = mysql_adapt_query($query);
				if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
			} else {
				$query = sprintf("INSERT INTO uo_setting (name, value) VALUES ('%s', '%s')",
					mysql_adapt_real_escape_string($setting['name']),
					mysql_adapt_real_escape_string($setting['value']));
				$result = mysql_adapt_query($query);
				if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
			}
		}
	} else { die('Insufficient rights to configure server'); }
}

function GetGoogleMapsAPIKey() {
	global $serverConf;
	return $serverConf['GoogleMapsAPIKey'];
}

function isRespTeamHomeTeam() {
	$query = "SELECT value FROM uo_setting WHERE name = 'HomeTeamResponsible'";
	$result = mysql_adapt_query($query);
	if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
	
	if (!$row = mysqli_fetch_row($result)) {
		return false;
	} else {
		return $row[0] == 'yes';
	} 
}

/**
 * Scans directory /cust/* and returns list of customizations avialable.
 * 
 */
function getAvailableCustomizations(){
  global $include_prefix;  
  $customizations=array();
    $temp = scandir($include_prefix."cust/");

    foreach($temp as $fh){
      if(is_dir($include_prefix."cust/$fh") && $fh!='.' && $fh!='..'){
        $customizations[]=$fh;
      }
    }
    
    return $customizations;
}

/**
 * Scans directory /locale/* and returns list of localizations avialable.
 * 
 */
function getAvailableLocalizations(){
    global $include_prefix;
    $localizations=array();
    $temp = scandir($include_prefix."locale/");

    foreach($temp as $fh){
      if(is_dir($include_prefix."locale/$fh") && $fh!='.' && $fh!='..'){
         $localizations[$fh]=$fh;
      }
    }
    
    return $localizations;
}
?>
