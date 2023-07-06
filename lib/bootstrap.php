<?php
/**
 * @file
 * This file contains defines a few fariable needed by every script.
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

global $include_prefix;

$serverName = GetServerName();
//include prefix can be used to locate root level of directory tree.
$include_prefix = "";
$depth = 0;
while (!(is_file($include_prefix.'conf/config.inc.php') || is_file($include_prefix.'conf/'.$serverName.".config.inc.php"))) {
  $include_prefix .= "../";
  if (++$depth > 20) {
    die("config.inc.php not found");
  }
}

require_once $include_prefix.'lib/gettext/gettext.inc';
include_once $include_prefix.'lib/common.functions.php';

if (is_file($include_prefix.'conf/'.$serverName.".config.inc.php")) {
  require_once $include_prefix.'conf/'.$serverName.".config.inc.php";
} else {
  require_once $include_prefix.'conf/config.inc.php';
}
