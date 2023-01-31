<?php
if (is_file('install.php')) {
  die("Delete install.php file from server!");
}

$bootstrapfile = 'lib/bootstrap.php';
if (is_file($bootstrapfile))
  include_once $bootstrapfile;
else
  die("$bootstrapfile not found.");

include_once $bootstrapfile;

if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE > 0) {
  include 'maintenance.php';
  exit();
}

$view = iget("view");
if (!$view) {
  header("location:?view=frontpage");
  exit();
} else if (!include_exists($view . ".php")) {
  header("location:?view=frontpage");
  exit();
}

include_once $include_prefix . 'lib/database.php';
OpenConnection();
include_once $include_prefix . 'menufunctions.php';
include_once $include_prefix . 'view_ids.inc.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/facebook.functions.php';
include_once $include_prefix . 'lib/logging.functions.php';

include_once $include_prefix . 'lib/debug.functions.php';

session_name("UO_SESSID");
session_start();
if (!isset($_SESSION['VISIT_COUNTER'])) {
  LogVisitor($_SERVER['REMOTE_ADDR']);
  $_SESSION['VISIT_COUNTER'] = true;
}

if (!isset($_SESSION['uid']) || UserId($_SESSION['uid']) <= 0) {
  $_SESSION['uid'] = "anonymous";
  SetUserSessionData("anonymous");
} else {
  if (UserSettingsValidationToken() != GetSettingsValidationToken()) {
    // user properties may have changed
    SetUserSessionData($_SESSION['uid']);
  }
  if ($_SESSION['uid'] === "anonymous") {
  // loadUserProperties("anonymous");
  }
}

require_once $include_prefix . 'lib/configuration.functions.php';

include_once 'localization.php';
setSessionLocale();

if (isset($_POST['myusername'])) {
  if (strpos($view, "mobile") === false)
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirect");
  else
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirectMobile");
}

LogPageLoad($view);

global $serverConf;
if (IsFacebookEnabled() && !empty($serverConf['FacebookAppId']) && !empty($serverConf['FacebookAppSecret'])) {
  // include_once 'lib/facebook/facebook.php';
  $fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);
  if ($_SESSION['uid'] == "anonymous" && $fb_cookie) {
    $_SESSION['uid'] = MapFBUserId($fb_cookie);
    SetUserSessionData($_SESSION['uid']);
  }
}

$user = $_SESSION['uid'];

setSelectedSeason();

if(iget("print")) {
  $_SESSION['print'] = 1;
} else {
  $_SESSION['print'] = 0;
}


include $view . ".php";

CloseConnection();
?>
