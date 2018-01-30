<?php
/**
 * @file
 * This file contains general functions to access and query database.
 *
 */

function GetServerName() {
	if(isset($_SERVER['SERVER_NAME'])) {
		return $_SERVER['SERVER_NAME'];
	}elseif(isset($_SERVER['HTTP_HOST'])) {
		return $_SERVER['HTTP_HOST'];
	}else{
		die("Cannot find server address");
	}
}

$serverName = GetServerName();
//include prefix can be used to locate root level of directory tree.
$include_prefix = "";
while (!(is_file($include_prefix.'conf/config.inc.php') || is_file($include_prefix.'conf/'.$serverName.".config.inc.php"))) {
  $include_prefix .= "../";
}

require_once $include_prefix.'lib/gettext/gettext.inc';
include_once $include_prefix.'lib/common.functions.php';

if (is_file($include_prefix.'conf/'.$serverName.".config.inc.php")) {
	require_once $include_prefix.'conf/'.$serverName.".config.inc.php";
} else {
	require_once $include_prefix.'conf/config.inc.php';
}

include_once $include_prefix.'sql/upgrade_db.php';

//When adding new update function into upgrade_db.php change this number
//Also when you change the database, please add a database definition into
// 'lib/table-definition-cache' with the database version in the file name.
// You can get it by getting ext/restful/show_tables.php
define('DB_VERSION', 78); //Database version matching to upgrade functions.

$mysqlconnectionref = 0;

/**
 * Open database connection.
 */
function OpenConnection() {
  
  global $mysqlconnectionref;
  
  //connect to database
  $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
  if(!$mysqlconnectionref) {
    die('Failed to connect to server: ' . mysql_adapt_error());
  }

  //select schema
  $db = ((bool)mysqli_query($mysqlconnectionref, "USE `" . DB_DATABASE ."`"));
  mysqli_set_charset($mysqlconnectionref, 'utf8');

  if(!$db) {
      die("Unable to select database " . mysql_adapt_error());
  }
  
  //check if database is up-to-date
  if (!isset($_SESSION['dbversion'])) {
    CheckDB();
    $_SESSION['dbversion'] = getDBVersion();
  }
}

/**
 * Closes database connection.
 */
function CloseConnection() {
  global $mysqlconnectionref;
  mysqli_close($mysqlconnectionref);
  $mysqlconnectionref = 0;
}

/**
 * Checks if there is need to update database and execute upgrade functions.
 */
function CheckDB() {
  $installedDb = getDBVersion();
  for ($i = $installedDb; $i <= DB_VERSION; $i++) {
    $upgradeFunc = 'upgrade'.$i;
    LogDbUpgrade($i);
    $upgradeFunc();
    $query = sprintf("insert into uo_database (version, updated) values (%d, now())", $i + 1);
    runQuery($query);
    LogDbUpgrade($i, true);
  }
}

/**
 * Returns ultiorganizer database internal version number.
 *
 * @return integer version number
 */
function getDBVersion() {
  $query = "SELECT max(version) as version FROM uo_database";
  $result = mysql_adapt_query($query);
  if (!$result) {
    $query = "SELECT max(version) as version FROM pelik_database";
    $result = mysql_adapt_query($query);
  }
  if (!$result) return 0;
  if (!$row = mysqli_fetch_assoc($result)) {
    return 0;
  } else return $row['version'];
}

/**
 * Executes sql query and  returns result as an mysql array.
 *
 * @param string $query database query
 * @return mixed Array of rows
 */
function DBQuery($query) {
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }
  return $result;
}

/**
 * Executes sql query and returns the ID generated the query.
 *
 * @param string $query database query
 * @return int id
 */
function DBQueryInsert($query) {
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }
  return mysql_adapt_insert_id();
}

/**
 * Executes sql query and  returns result as an value.
 *
 * @param string $query database query
 * @return mixed Value of first cell on first row
 */
function DBQueryToValue($query, $docasting=false) {
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }

  if(mysqli_num_rows($result)){
    $row = mysqli_fetch_row($result);
    if ($docasting) {
      $row = DBCastArray($result, $row);
    }
    return $row[0];
  }else{
    return -1;
  }
}

/**
 * Executes sql query and returns number of rows in resultset
 *
 * @param string $query database query
 * @return int number of rows
 */
function DBQueryRowCount($query) {
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }

  return mysqli_num_rows($result);
}
/**
 * Executes sql query and copy returns to php array.
 *
 * @param string $query database query
 * @return Array of rows
 */
function DBQueryToArray($query, $docasting=false) {
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }
  return DBResourceToArray($result,$docasting);
}


/**
 * Converts a db resource to an array
 *
 * @param $result mixed The database resource returned from mysql_query
 * @return array of rows
 */
function DBResourceToArray($result, $docasting=false) {
  $retarray = array();
  while ($row = mysqli_fetch_assoc($result)) {
    if ($docasting) {$row = DBCastArray($result, $row);}
    $retarray[] = $row;
  }
  return $retarray;
}

/**
 * Executes sql query and copy returns to php array of first row.
 *
 * @param string $query database query
 * @return array first row in array
 */
function DBQueryToRow($query, $docasting=false) {
  $result = mysql_adapt_query($query);
  if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_adapt_error()); }
  $ret = mysqli_fetch_assoc($result);
  if ($docasting && $ret) {$ret = DBCastArray($result, $ret);}
  return $ret;
}

  /**
   * Set data into database by updating existing row.
   * @param string $name Name of the table to update
   * @param array $row Data to insert: key=>field, value=>data
   */
 function DBSetRow($name, $data, $cond){

    $values = array_values($data);
    $fields = array_keys($data);

    $query = "UPDATE ".mysql_adapt_real_escape_string($name)." SET ";

    for($i=0;$i<count($fields);$i++){
      $query .= mysql_adapt_real_escape_string($fields[$i]) ."='".$values[$i]."', ";
    }
    $query = rtrim($query,', ');
    $query .= " WHERE ";
    $query .= $cond;
    return DBQuery($query);
  }
  
/**
 * Copy mysql_associative array row to regular php array.
 *
 * @param $result array return value of mysql_query
 * @param $row array mysql_associative array row
 * @return array php array of $row
 */
function DBCastArray($result, $row) {
  $ret = array();
  $i=0;
  foreach ($row as $key => $value) {
    if (mysql_adapt_field_type($result, $i) == "int") {
      $ret[$key] = (int)$value;
    } else {
      $ret[$key] = $value;
    }
    $i++;
  }
  return $ret;
}

if (function_exists('mysqli_set_charset') === false) {
  /**
   * Sets the client character set.
   *
   * Note: This function requires MySQL 5.0.7 or later.
   *
   * @see http://www.php.net/mysql-set-charset
   * @param string $charset A valid character set name
   * @param resource $link_identifier The MySQL connection
   * @return TRUE on success or FALSE on failure
   */
  function mysqli_set_charset($charset, $link_identifier = null){
    if ($link_identifier == null) {
      return mysql_adapt_query('SET CHARACTER SET "'.$charset.'"');
    } else {
      return mysql_adapt_query('SET CHARACTER SET "'.$charset.'"', $link_identifier);
    }
  }
}

/***********************************************************/
/* mysql to mysqli conversion */

function DBLink() {
  global $mysqlconnectionref;
  
  return $mysqlconnectionref;
}

function mysql_adapt_real_escape_string($string, $link_identifier = NULL) {
  if (is_null($string)) return NULL;
  if (is_null($link_identifier))
    return mysqli_real_escape_string(DBLink(), $string);
  else
    return mysqli_real_escape_string($link_identifier, $string);
}

function mysql_adapt_error($link_identifier = NULL) {
  return mysqli_error(is_null($link_identifier) ? DBLink() : $link_identifier);
}

function mysql_adapt_query($query, $link_identifier = NULL) {
  return mysqli_query(is_null($link_identifier) ? DBLink() : $link_identifier, $query);
}

function mysql_adapt_free_result($result) {
  return mysqli_free_result($result);
}

function mysql_adapt_affected_rows($link_identifier = NULL) {
  return mysqli_affected_rows(is_null($link_identifier) ? DBLink() : $link_identifier);
}

function mysql_adapt_stat($link_identifier = NULL) {
  return mysqli_stat(is_null($link_identifier) ? DBLink() : $link_identifier);
}

function mysql_adapt_insert_id($link_identifier = NULL) {
  return mysqli_insert_id(is_null($link_identifier) ? DBLink() : $link_identifier);
}

function mysql_adapt_field_type($result, $field_offset) {
  return ((is_object($___mysqli_tmp = mysqli_fetch_field_direct($result, $field_offset)) && ! is_null($___mysqli_tmp = $___mysqli_tmp->type)) ? ((($___mysqli_tmp = (string) (substr(((($___mysqli_tmp == MYSQLI_TYPE_STRING) || ($___mysqli_tmp == MYSQLI_TYPE_VAR_STRING)) ? "string " : "") . ((in_array($___mysqli_tmp, array(
    MYSQLI_TYPE_TINY,
    MYSQLI_TYPE_SHORT,
    MYSQLI_TYPE_LONG,
    MYSQLI_TYPE_LONGLONG,
    MYSQLI_TYPE_INT24
  ))) ? "int " : "") . ((in_array($___mysqli_tmp, array(
    MYSQLI_TYPE_FLOAT,
    MYSQLI_TYPE_DOUBLE,
    MYSQLI_TYPE_DECIMAL,
    ((defined("MYSQLI_TYPE_NEWDECIMAL")) ? constant("MYSQLI_TYPE_NEWDECIMAL") : - 1)
  ))) ? "real " : "") . (($___mysqli_tmp == MYSQLI_TYPE_TIMESTAMP) ? "timestamp " : "") . (($___mysqli_tmp == MYSQLI_TYPE_YEAR) ? "year " : "") . ((($___mysqli_tmp == MYSQLI_TYPE_DATE) || ($___mysqli_tmp == MYSQLI_TYPE_NEWDATE)) ? "date " : "") . (($___mysqli_tmp == MYSQLI_TYPE_TIME) ? "time " : "") . (($___mysqli_tmp == MYSQLI_TYPE_SET) ? "set " : "") . (($___mysqli_tmp == MYSQLI_TYPE_ENUM) ? "enum " : "") . (($___mysqli_tmp == MYSQLI_TYPE_GEOMETRY) ? "geometry " : "") . (($___mysqli_tmp == MYSQLI_TYPE_DATETIME) ? "datetime " : "") . ((in_array($___mysqli_tmp, array(
    MYSQLI_TYPE_TINY_BLOB,
    MYSQLI_TYPE_BLOB,
    MYSQLI_TYPE_MEDIUM_BLOB,
    MYSQLI_TYPE_LONG_BLOB
  ))) ? "blob " : "") . (($___mysqli_tmp == MYSQLI_TYPE_NULL) ? "null " : ""), 0, - 1))) == "") ? "unknown" : $___mysqli_tmp) : false);
}

function mysql_adapt_is_blob($result, $field_offset) {
  return mysql_adapt_field_type($result, $field_offset) === 'blob';
}

function mysql_adapt_is_int($result, $field_offset) {
  return mysql_adapt_field_type($result, $field_offset) === 'int';
}

?>
