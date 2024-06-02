<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'lib/logging.functions.php';
include_once $include_prefix . 'lib/common.functions.php';

// include_once $include_prefix.'lib/configuration.functions.php';
function FailRedirect($user, $query = '') {
  SetUserSessionData('anonymous');
  $query = urlencode($query);
  header("location:?view=login&failed=1&query=$query&user=" . urlencode($user));
  exit();
}

function FailRedirectMobile($user, $query = '') {
  SetUserSessionData('anonymous');
  $query = urlencode($query);
  header("location:?view=mobile/login&failed=1&query=$query&user=" . urlencode($user));
  exit();
}

function FailUnauthorized($user) {
  header('WWW-Authenticate: Basic realm="ultiorganizer"');
  if (strpos("Microsoft", $_SERVER["SERVER_SOFTWARE"])) {
    header("Status: 401 Unauthorized");
  } else {
    header("HTTP/1.0 401 Unauthorized");
  }
  echo "<html><head><title>Login failed</title></head><body><h1>Login failed for " . $user . "</h1></body></html>\n";
  exit();
}

function Forbidden($user) {
  if (strpos("Microsoft", $_SERVER["SERVER_SOFTWARE"])) {
    header("Status: 403 Forbidden");
  } else {
    header("HTTP/1.0 403 Forbidden");
  }
  echo "<html><head><title>Operation not allowed.</title></head><body><h1>Operation not allowed for " . $user .
    "</h1></body></html>\n";
  exit();
}

function asyncLogin($id) {
  global $include_prefix;
  include_once $include_prefix . 'lib/yui.functions.php';
  $html = yuiLoad(array("utilities"));

  $html .= <<<EOS

<script type="text/javascript">
//<![CDATA[

var Dom = YAHOO.util.Dom;

(function() {
  var Event = YAHOO.util.Event;

  YAHOO.example.ScheduleApp = {
    init: function() {
      Dom.get("loginwrapper$id").style.display = "block";
      Event.on("myloginbutton$id", "click", this.requestString);
    },
    
    requestString: function() {
      var responseDiv = Dom.get("responseStatus");
      Dom.setStyle(responseDiv,"background-image","url('images/indicator.gif')");
      Dom.setStyle(responseDiv,"background-repeat","no-repeat");
      Dom.setStyle(responseDiv,"background-position", "top right");
      Dom.setStyle(responseDiv,"class", "inprogress");
      responseDiv.innerHTML = '&nbsp;';
      var request = Dom.get("myusername$id").value + "|" + Dom.get("mypassword$id").value;
      var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=user/login_request', callback, request);         
    },
  };

  var callback = {
    success: function(o) {
      const answer = JSON.parse(o.responseText);
      var responseDiv = Dom.get("responseStatus");
      Dom.setStyle(responseDiv,"background-image","");

      if (!answer.status) {
        responseDiv.innerHTML = "<p>" + answer.msg + "</p>";
      } else if (answer.authenticated) {
        responseDiv.innerHTML = "<p>Authenticated</p>";
      } else {
        responseDiv.innerHTML = "<p>Authentication failed</p>";
      }
      if (answer.authenticated) {
        YAHOO.util.Dom.removeClass(responseDiv,"attention");
        YAHOO.util.Dom.addClass(responseDiv,"highlight");
        window.history.go(0);
      } else {
        YAHOO.util.Dom.removeClass(responseDiv,"highlight");
        YAHOO.util.Dom.addClass(responseDiv,"attention");
      }
    },

    failure: function(o) {
      var responseDiv = Dom.get("responseStatus");
      YAHOO.util.Dom.removeClass(responseDiv,"highlight");
      YAHOO.util.Dom.addClass(responseDiv,"attention");
      Dom.setStyle(responseDiv,"background-image","");
      responseDiv.innerHTML = o.responseText;
    }
  }


  Event.onDOMReady(YAHOO.example.ScheduleApp.init, YAHOO.example.ScheduleApp, true);

})();
</script>
EOS;

  return $html;
}

function ensureLogin() {
  $view = iget("view");
  $query = urlencode($_SERVER['QUERY_STRING']);
  $mobile = "";
  if (strpos($view, "mobile") === 0) {
    $mobile = "mobile/";
  }

  if (empty($_SESSION['uid']) || $_SESSION['uid'] === "anonymous") {
    if (isset($_POST) && !empty($_POST)) {
      // we must login, then reload this page
      $html = "<p class='warning'>" . _("You must login to access this page.") . "</p>\n";

      // non-JS solution: login in other window, then reload manually
      $html .= "<noscript><p><a href='?view={$mobile}login' target='_blank'>" .
        _("Please open this login link in a new window, then reload this page.") . "</a></noscript>";

      // JS solution: login asynchronosly, then reload
      $html .= "<div id='loginwrapperpopup' style='display:none'>";
      $html .= loginForm("", "", "popup");
      $html .= "<p><div id='responseStatus'></div></p></div>";
      $html .= asyncLogin("popup");
      showPage(_("Please login"), $html);
      exit();
    }

    // login and reload with GET
    header("location:?view=${mobile}login&query=$query&privileged=1");
  }
}

function ensurePrivileges($check, $title = null, $message = null, $type = null, $options = null) {
  ensureLogin();

  if (gettype($check) == 'object') {
    if ($check()) {
      return true;
    }
  } else {
    if ($check)
      return true;
  }

  showUnprivileged($title, $message, $type, $options);
}

function showUnprivileged($title, $message, $type = null, $options = null) {
  if ($title === null) {
    $title = _("Insufficient rights");
  }

  if ($message === null) {
    $message = "<p>" . _("Insufficient rights!") . "</p>\n";
    switch ($type) {
    case "season":
      $message .= "<p>" . sprintf(_("You are not a season admin for %s."), $options) . "</p>";
      break;
    case "season":
      $message .= "<p>" . sprintf(_("You cannot change series for season %s."), $options) . "</p>";
      break;
    case "season_series":
      $message .= "<p>" . sprintf(_("You cannot change any series for season %s."), $options) . "</p>";
      break;
    case "series":
      $message .= "<p>" . sprintf(_("You cannot edit division %s."), SeriesName($options)) . "</p>";
      break;
    default:
      $message .= "<p>" . _("You are not allowed to do this.") . "</p>";
    }
  }

  $backlink = utf8entities($_SERVER['HTTP_REFERER'] ?? "");

  if ($backlink)
    $backlink = "<a href='$backlink'>" . _("Return") . "</a><br />";

  $message .= "<p>$backlink<a href='?view=frontpage'>" . _("Go to front page") . "</p>\n";

  $message = "<h1>$title</h1>\n" . $message;

  showPage($title, $message);
  exit();
}

function UserExpireTokens($userId) {
  $query = "DELETE FROM `uo_extraemailrequest` WHERE TIMESTAMPDIFF(MINUTE, time, NOW()) > 60  OR TIME is NULL";
  DBQuery($query);
  $query = "DELETE FROM `uo_recoverrequest` WHERE TIMESTAMPDIFF(MINUTE, time, NOW()) > 60  OR TIME is NULL";
  DBQuery($query);
  $query = "DELETE FROM `uo_registerrequest` WHERE TIMESTAMPDIFF(HOUR, last_login, NOW()) > 24";
  DBQuery($query);
}

function UserAuthenticate($user, $passwd, $failcallback) {
  $query = sprintf("SELECT * FROM uo_users WHERE UserID='%s' AND Password=MD5('%s')",
    mysql_adapt_real_escape_string($user), mysql_adapt_real_escape_string($passwd));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $count = mysqli_num_rows($result);
  if ($count == 1) {
    LogUserAuthentication($user, "success");
    SetUserSessionData($user);
    $row = mysqli_fetch_assoc($result);
    mysql_adapt_query(
      "UPDATE uo_users SET last_login=NOW() WHERE userid='" . mysql_adapt_real_escape_string($user) . "'");

    UserExpireTokens($user);

    // first logging
    if (empty($row['last_login']) && $user == "admin") {
      header("location:?view=admin/serverconf");
      exit();
    }

    if (empty($row['last_login'])) {
      header("location:?view=user/userinfo");
      exit();
    }
    return true;
  } else {
    LogUserAuthentication($user, "failed");
    if (!empty($failcallback)) {
      $query = $_SERVER['QUERY_STRING'];
      $failcallback($user, $query);
      exit();
    } else {
      return false;
    }
  }
  return false;
}

function UserInfo($user_id) {
  if ($user_id == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("SELECT * FROM uo_users WHERE userid='%s'", mysql_adapt_real_escape_string($user_id));
    return DBQueryToRow($query, true);
  } else {
    die('Insufficient rights to get user info');
  }
}

function UserId($username) {
  if ($username == 'anonymous')
    return -1;
  $query = sprintf("SELECT id FROM uo_users WHERE userid='%s'", mysql_adapt_real_escape_string($username));
  return DBQueryToValue($query);
}

function UserName($id) {
  $query = sprintf("SELECT userid FROM uo_users WHERE id='%d'", (int) $id);
  return DBQueryToValue($query);
}

function UserIdForMail($mail) {
  $query = sprintf("SELECT userid FROM uo_users WHERE email='%s'", mysql_adapt_real_escape_string($mail));
  return DBQueryToValue($query);
}

function UserExtraEmails($user_id) {
  if ($user_id == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("SELECT email FROM uo_extraemail WHERE userid='%s'", mysql_adapt_real_escape_string($user_id));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $ret = array();
    while ($row = mysqli_fetch_row($result)) {
      $ret[] = $row[0];
    }
    if (count($ret) > 0) {
      return $ret;
    } else
      return false;
  } else {
    die('Insufficient rights to get user info');
  }
}

function IsRegistered($user_id) {
  if ($user_id == "anonymous") {
    return false;
  }

  $query = sprintf("SELECT userid FROM uo_users WHERE userid='%s'", mysql_adapt_real_escape_string($user_id));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    return true;
  } else {
    $query = sprintf("SELECT userid FROM uo_registerrequest WHERE userid='%s'", mysql_adapt_real_escape_string($user_id));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    if ($row = mysqli_fetch_assoc($result)) {
      return true;
    }
    return false;
  }
}

function UserUpdateInfo($user_id, $olduser, $user, $name) {
  if ($olduser == $_SESSION['uid'] || hasEditUsersRight()) {

    $query = sprintf("UPDATE uo_users SET UserID='%s', name='%s' WHERE ID=%d", mysql_adapt_real_escape_string($user),
      mysql_adapt_real_escape_string($name), (int) $user_id);

    DBQuery($query);

    if ($olduser != $user) {
      $query = sprintf("UPDATE uo_userproperties SET userid='%s' WHERE userid='%s'",
        mysql_adapt_real_escape_string($user), mysql_adapt_real_escape_string($olduser));

      DBQuery($query);
    }
    invalidateSessions();
    // update session data only if user is current use
    if ($olduser == $_SESSION['uid']) {
      SetUserSessionData($user);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

function UserValidPassword($newPassword1, $newPassword2) {
  $message = "";
  if (empty($newPassword1)) {
    $message .= "<p class='warning'>" . _("Password cannot be empty.") . "</p>";
  }

  if (!empty($newPassword1) && (strlen($newPassword1) < 8 || strlen($newPassword1) > 20)) {
    $message .= "<p class='warning'>" .
      sprintf(_("Password is too short or too long (between %d and %d letters)."), 8, 20) . "</p>";
  }

  if (!empty($newPassword1) && ($newPassword1 != $newPassword2)) {
    $message .= "<p class='warning'>" . _("Passwords do not match.") . "</p>";
  }

  return $message;
}

function UserValid($newUsername, $newPassword1, $newPassword2, $newName, $newEmail, $checkDuplicate = false,
  $checkPw = true) {
  $html = "";
  if (empty($newUsername) || strlen($newUsername) < 3 || strlen($newUsername) > 30) {
    $html .= "<p class='warning'>" . sprintf(_("Username is too short or too long (between %d and %d letters.)"), 3, 30) .
      ".</p>";
  }

  $uidcheck = mysql_adapt_real_escape_string($newUsername);

  if ($uidcheck != $newUsername || preg_match('/[ ]/', $newUsername) || preg_match('/[^a-z0-9._]/i', $newUsername)) {
    $html .= "<p class='warning'>" . _("User id may not have spaces or special characters") . ".</p>";
  }

  if ($checkDuplicate && (IsRegistered($newUsername) || $newUsername === "anonymous")) {
    $html .= "<p class='warning'>" . _("The username is already in use") . ".</p>";
  }

  if ($checkPw) {
    $pw = UserValidPassword($newPassword1, $newPassword2);
    if (!empty($pw)) {
      $html .= $pw;
    }

    $pswcheck = mysql_adapt_real_escape_string($newPassword1);

    if ($pswcheck != $newPassword1) {
      $html .= "<p class='warning'>" . _("Illegal characters in the password") . ".</p>";
    }
  }
  if (empty($newName)) {
    $html .= "<p class='warning'>" . _("Name can not be empty") . ".</p>";
  }

  if (empty($newEmail)) {
    $html .= "<p class='warning'>" . _("Email can not be empty") . ".</p>";
  }

  if (!validEmail($newEmail)) {
    $html .= "<p class='warning'>" . _("Invalid email address") . ".</p>";
  }
  if (UserIdForMail($newEmail) !== -1) {
    $html .= "<p class='warning'>" .
      _(
        "Email address already in use. If you have forgotten your login details, please follow this link: <a href='?view=login&recover=1'>Recover lost password / user name</a>") .
      ".</p>";
  }

  return $html;
}

function UserChangePassword($user_id, $passwd, $token = null) {
  Log1("user", "change", $user_id, "", "set password");

  if ($user_id == $_SESSION['uid'] || hasEditUsersRight($user_id) || UserCheckRecoverToken($user_id, $token)) {
    $query = sprintf("UPDATE uo_users SET password=MD5('%s') WHERE userid='%s'", mysql_adapt_real_escape_string($passwd),
      mysql_adapt_real_escape_string($user_id));

    DBQuery($query);
    $changed = mysql_adapt_affected_rows();
    $query = sprintf("DELETE FROM uo_recoverrequest WHERE userid='%s'", mysql_adapt_real_escape_string($user_id));
    DBQuery($query);
    return $changed;
  } else {
    die('Insufficient rights to change user info');
  }
}

function invalidateSessions() {
  IncreaseSettingsValidationToken();
}

function UserSettingsValidationToken() {
  if (!isset($_SESSION['SETTINGS_VALIDATION_TOKEN'])) {
    $_SESSION['SETTINGS_VALIDATION_TOKEN'] = GetSettingsValidationToken();
  }
  return $_SESSION['SETTINGS_VALIDATION_TOKEN'];
}

function SetUserSessionData($user_id) {
  unset($_SESSION['userproperties']);
  unset($_SESSION['navigation']);
  unset($_SESSION['dbversion']);
  $_SESSION['uid'] = $user_id;

  loadUserProperties($user_id);
  $_SESSION['SETTINGS_VALIDATION_TOKEN'] = GetSettingsValidationToken();
}

function loadUserProperties($user_id) {
  $query = sprintf("SELECT prop_id, name, value FROM uo_userproperties WHERE userid='%s'",
    mysql_adapt_real_escape_string($user_id));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  if (!isset($_SESSION['userproperties'])) {
    $_SESSION['userproperties'] = array();
  }

  while ($property = mysqli_fetch_assoc($result)) {
    $propname = $property['name'];
    $propvalue = explode(":", $property['value']);
    $propid = $property['prop_id'];
    if (!isset($_SESSION['userproperties'][$propname])) {
      $_SESSION['userproperties'][$propname] = array();
    }
    if (count($propvalue) == 1) {
      $_SESSION['userproperties'][$propname][$propvalue[0]] = $propid;
    } else {
      if (isset($_SESSION['userproperties'][$propname][$propvalue[0]])) {
        $nextVal = $_SESSION['userproperties'][$propname][$propvalue[0]];
        $nextVal[$propvalue[1]] = $propid;
      } else {
        $nextVal = array($propvalue[1] => $propid);
      }
      $_SESSION['userproperties'][$propname][$propvalue[0]] = $nextVal;
    }
  }
}

function getEditSeasons($userid) {
  $editSeasons = getUserpropertyArray($userid, 'editseason');
  return SortEditSeasons($editSeasons);
}

function SortEditSeasons($editSeasons) {
  if (count($editSeasons) == 0)
    return $editSeasons;
  else {
    $first = true;
    $seasons = "'";
    foreach ($editSeasons as $season => $propId) {
      if ($first) {
        $first = false;
      } else {
        $seasons .= ", '";
      }
      $seasons .= mysql_adapt_real_escape_string($season) . "'";
    }
    $query = "SELECT season_id FROM uo_season WHERE season_id IN (" . $seasons . ") ORDER BY starttime ASC";
    $result = mysql_adapt_query($query);

    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $ret = array();
    while ($row = mysqli_fetch_row($result)) {
      $ret[$row[0]] = $editSeasons[$row[0]];
    }
    return $ret;
  }
}

function getPoolselectors($userid) {
  return getUserpropertyArray($userid, 'poolselector');
}

function getUserroles($userid) {
  return getUserpropertyArray($userid, 'userrole');
}

function getUserLocale($userid) {
  $localearr = getUserpropertyArray($userid, 'locale');
  if (count($localearr) > 0) {
    $tmparr = array_keys($localearr);
    return $tmparr[0];
  } else {
    return GetDefaultLocale();
  }
}

function SetUserLocale($userid, $locale) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    global $locales;
    if (isset($locales[$locale])) {
      $localearr = getUserpropertyArray($userid, 'locale');
      if (count($localearr) > 0) {
        $query = sprintf("UPDATE uo_userproperties SET value='%s' WHERE userid='%s' AND name='locale'",
          mysql_adapt_real_escape_string($locale), mysql_adapt_real_escape_string($userid));
      } else {
        $query = sprintf("INSERT INTO uo_userproperties (name, value, userid) VALUES ('locale', '%s', '%s')",
          mysql_adapt_real_escape_string($locale), mysql_adapt_real_escape_string($userid));
      }
      $result = mysql_adapt_query($query);

      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    } else {
      die('Invalid locale: ' . $locale);
    }
  } else {
    die('Insufficient rights to set user locale');
  }
}

function getPropId($userid, $name, $value) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("SELECT prop_id FROM uo_userproperties WHERE userid='%s' and name='%s'
							and value='%s'", mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($name),
      mysql_adapt_real_escape_string($value));
    $result = mysql_adapt_query($query);

    return DBQueryToValue($query);
  } else {
    die('Insufficient rights to get user info');
  }
}

function getUserpropertyArray($userid, $propertyname) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("SELECT prop_id, value FROM uo_userproperties WHERE userid='%s' and name='%s'",
      mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($propertyname));
    $result = mysql_adapt_query($query);

    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $ret = array();
    while ($property = mysqli_fetch_assoc($result)) {
      $propvalue = explode(":", $property['value']);
      $propid = $property['prop_id'];
      if (count($propvalue) == 1) {
        $ret[$propvalue[0]] = $propid;
      } else {
        if (isset($ret[$propvalue[0]])) {
          $nextVal = $ret[$propvalue[0]];
          $nextVal[$propvalue[1]] = $propid;
        } else {
          $nextVal = array($propvalue[1] => $propid);
        }
        $ret[$propvalue[0]] = $nextVal;
      }
    }
    return $ret;
  } else {
    die('Insufficient rights to get user info');
  }
}

function setSelectedSeason() {
  // season selection changed
  if (!empty($_GET["selseason"])) {
    $seasonId = $_GET["selseason"];
    if (!empty(SeasonInfo($seasonId)))
      $_SESSION['userproperties']['selseason'] = $seasonId;
  }
}

function and_or($counter) {
  if ($counter == 0) {
    return " AND (";
  } else {
    return " OR ";
  }
}

function getViewPools($selSeasonId) {
  $numselectors = 0;
  $query = "SELECT seas.season_id as season, seas.name as season_name, ser.series_id as series, ser.name as series_name, pool.pool_id as pool, pool.name as pool_name ";
  $query .= "FROM uo_pool pool
		left outer join uo_series ser on (pool.series = ser.series_id)
		left outer join uo_season seas on (ser.season = seas.season_id) ";
  $query .= "WHERE pool.visible=1";

  if (isset($_SESSION['userproperties']['poolselector'])) {
    foreach ($_SESSION['userproperties']['poolselector'] as $selector => $param) {
      if ($selector == 'currentseason') {
        $query .= and_or($numselectors++);
        $query .= sprintf("seas.season_id='%s'", mysql_adapt_real_escape_string($selSeasonId));
      } else {
        foreach ($param as $subject => $prop_id) {
          $query .= and_or($numselectors++);
          if ($selector == 'team') {
            $query .= sprintf("pool.pool_id in (SELECT pool FROM uo_team WHERE team_id=%d)", (int) $subject);
            $query .= sprintf("OR pool.pool_id in (SELECT pool FROM uo_team_pool WHERE team=%d)", (int) $subject);
          } elseif ($selector == 'season') {
            $query .= sprintf("seas.season_id='%s'", mysql_adapt_real_escape_string($subject));
          } elseif ($selector == 'series') {
            $query .= sprintf("ser.series_id=%d", (int) $subject);
          } elseif ($selector == 'pool') {
            $query .= sprintf("pool.pool_id=%d", (int) $subject);
          }
        }
      }
    }
  }

  if ($numselectors > 0) {
    $query .= ")";
  }
  $query .= " ORDER BY seas.endtime > NOW() DESC, seas.starttime DESC, ser.season ASC, ser.ordering ASC, series ASC, pool.ordering ASC, pool ASC";

  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  return $result;
}

function ClearUserSessionData() {
  if (IsFacebookEnabled()) {
    global $serverConf;
    setcookie('fbs_' . $serverConf['FacebookAppId'], "", 1, "/");
    unset($_COOKIE['fbs_' . $serverConf['FacebookAppId']]);
  }
  SetUserSessionData("anonymous");
}

function setSuperAdmin($userid, $value) {
  if (isSuperAdmin()) {
    if ($value && !isSuperAdminByUserid($userid)) {
      $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'superadmin')",
        mysql_adapt_real_escape_string($userid));
      $result = mysql_adapt_query($query);
      Log1("security", "add", $userid, "", "superadmin acceess granted");
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    } else if (!$value) {
      $query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='superadmin'",
        mysql_adapt_real_escape_string($userid));
      $result = mysql_adapt_query($query);
      Log1("security", "add", $userid, "", "superadmin acceess removed");
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    }
  } else {
    die('Insufficient rights to change superadmin userrole');
  }
}

function getSuperAdmins() {
  if (isSuperAdmin()) {
    $query = "SELECT us.id, us.userid FROM uo_userproperties up 
        LEFT JOIN uo_users us on (us.userid = up.userid) 
        WHERE up.name='userrole' AND up.value='superadmin' AND us.id is not null GROUP BY userid";
    return DBQueryToArray($query);
  } else {
    die('Insufficient rights to change superadmin userrole');
  }
}

function setTranslationAdmin($userid, $value) {
  if (hasEditUsersRight()) {
    if ($value && !isTranslationAdminByUserid($userid)) {
      $query = sprintf(
        "INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'translationadmin')",
        mysql_adapt_real_escape_string($userid));
      $result = mysql_adapt_query($query);
      Log1("security", "add", $userid, "", "translationadmin acceess granted");
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    } else if (!$value) {
      $query = sprintf(
        "DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='translationadmin'",
        mysql_adapt_real_escape_string($userid));
      $result = mysql_adapt_query($query);
      Log1("security", "add", $userid, "", "translationadmin acceess removed");
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    }
  } else {
    die('Insufficient rights to change superadmin userrole');
  }
}

function isSuperAdminByUserid($userid) {
  if (hasEditUsersRight()) {
    $query = sprintf("SELECT * FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='superadmin'",
      mysql_adapt_real_escape_string($userid));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }

    if ($row = mysqli_fetch_assoc($result)) {
      return true;
    } else {
      return false;
    }
  }
}

function isTranslationAdminByUserid($userid) {
  if (hasEditUsersRight()) {
    $query = sprintf(
      "SELECT * FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='translationadmin'",
      mysql_adapt_real_escape_string($userid));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }

    if ($row = mysqli_fetch_assoc($result)) {
      return true;
    } else {
      return false;
    }
  }
}

function isSuperAdmin() {
  return isset($_SESSION['userproperties']['userrole']['superadmin']);
}

function ensureSuperAdmin($title = null, $message = null) {
  ensurePrivileges(isSuperAdmin(), $title, $message);
}

function isTranslationAdmin() {
  return isset($_SESSION['userproperties']['userrole']['translationadmin']);
}

function isPlayerAdmin($profile_id) {
  return isset($_SESSION['userproperties']['userrole']['playeradmin'][$profile_id]);
}

function hasPlayerAdminRights() {
  return isset($_SESSION['userproperties']['userrole']['playeradmin']);
}

function hasScheduleRights() {
  return isset($_SESSION['userproperties']['userrole']['resadmin']);
}

function hasViewUsersRight() {
  return isset($_SESSION['userproperties']['userrole']['superadmin']);
}

function hasEditUsersRight($userid = null) {
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    (($userid == null || !isSuperAdminByUserid($userid)) && isset($_SESSION['userproperties']['userrole']['useradmin']));
}

function hasChangeCurrentSeasonRight() {
  return isset($_SESSION['userproperties']['userrole']['superadmin']);
}

function hasCurrentSeasonsEditRight() {
  $seasons = EnrollSeasons();
  $seasons[] = CurrentSeason();
  $ret = false;
  foreach ($seasons as $season) {
    $ret = $ret || isSeasonAdmin($season);
    if ($ret)
      return true;
  }
  return false;
}

function isSeasonAdmin($season) {
  if (empty($season))
    return false;
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]);
}

function ensureSeasonAdmin($season, $title = null, $super = false, $message = null) {
  return ensurePrivileges(
    function () use ($super, $season) {
      return ($super && isSuperAdmin()) || isSeasonAdmin($season);
    }, $title, $message, 'season', $season);
}

function hasEditSeasonSeriesRight($season) {
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]);
}

function ensureSeasonSeriesAdmin($season, $title = null, $message = null) {
  return ensurePrivileges(hasEditSeasonSeriesRight($season), $title, $message, "season_series", $season);
}

function hasEditSeriesRight($seriesId) {
  $season = SeriesSeasonId($seriesId);
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
    isset($_SESSION['userproperties']['userrole']['seriesadmin'][$seriesId]);
}

function ensureEditSeriesRight($seriesId, $title = null, $message = null) {
  return ensurePrivileges(hasEditSeriesRight($seriesId), $title, $message, "series", $seriesId);
}

function hasEditSpiritRight(int $gameId) {
  return hasEditSeriesRight(GameSeries($gameId));
}

function hasEditPlacesRight($season) {
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]);
}

function hasEditTeamsRight($series) {
  return hasEditSeriesRight($series);
}

function hasEditGamesRight($series) {
  return hasEditSeriesRight($series);
}

function hasEditPlayerProfileRight($playerId) {
  $playerInfo = PlayerInfo($playerId);
  $team = $playerInfo['team'];
  $series = getTeamSeries($team);
  $season = SeriesSeasonId($series);
  return isPlayerAdmin($playerInfo['profile_id']) || isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
    isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
    isset($_SESSION['userproperties']['userrole']['teamadmin'][$team]);
}

function hasEditPlayersRight($team) {
  $series = getTeamSeries($team);
  $season = SeriesSeasonId($series);
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
    isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
    isset($_SESSION['userproperties']['userrole']['teamadmin'][$team]);
}

function hasEditGamePlayersRight($game) {
  $team = GameRespTeam($game);
  $series = GameSeries($game);
  $season = SeriesSeasonId($series);
  $reservation = GameReservation($game);
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
    isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
    (!empty($team) && isset($_SESSION['userproperties']['userrole']['teamadmin'][$team])) ||
    (!empty($reservation) && isset($_SESSION['userproperties']['userrole']['resgameadmin'][$reservation])) ||
    isset($_SESSION['userproperties']['userrole']['gameadmin'][$game]);
}

function hasEditGameEventsRight($game) {
  $team = GameRespTeam($game);
  $series = GameSeries($game);
  $season = SeriesSeasonId($series);
  $reservation = GameReservation($game);
  return isset($_SESSION['userproperties']['userrole']['superadmin']) ||
    isset($_SESSION['userproperties']['userrole']['seasonadmin'][$season]) ||
    isset($_SESSION['userproperties']['userrole']['seriesadmin'][$series]) ||
    (!empty($team) && isset($_SESSION['userproperties']['userrole']['teamadmin'][$team])) ||
    (!empty($reservation) && isset($_SESSION['userproperties']['userrole']['resgameadmin'][$reservation])) ||
    isset($_SESSION['userproperties']['userrole']['gameadmin'][$game]);
}

function hasAccredidationRight($team) {
  return hasEditTeamsRight(getTeamSeries($team)) || isset($_SESSION['userproperties']['userrole']['accradmin'][$team]);
}

function hasTranslationRight() {
  return isSuperAdmin() || isset($_SESSION['userproperties']['userrole']['translationadmin']);
}

function hasAddMediaRight() {
  return isset($_SESSION['uid']) && ($_SESSION['uid'] != 'anonymous');
}

/*
 * function getSeriesSeason($series) {
 * $query = sprintf("SELECT ser.season FROM uo_series ser
 * LEFT JOIN uo_series ser ON (pool.series=ser.series_id) WHERE ser.series_id=%d", (int)$series);
 * $result = mysql_adapt_query($query);
 * if (!$result) { die('Invalid query: ' . mysql_adapt_error()); }
 * if ($row = mysqli_fetch_row($result)) {
 * return $row[0];
 * } else return "";
 * }
 */
function UserListRightsHtml($userId) {
  $query = sprintf("SELECT value FROM uo_userproperties WHERE userid='%s'", mysql_adapt_real_escape_string($userId));
  $result = DBQuery($query);
  $rights = "";
  while ($row = mysqli_fetch_row($result)) {
    $value = preg_split('/:/', $row[0]);
    switch ($value[0]) {
    case "superadmin":
      $rights .= "<span style='color:#ff0000; font-weight:bold'>" . $value[0] . "</span><br/>";
      break;
    case "seasonadmin":
      $rights .= "<span style='color:#ff00ff;'>" . $value[0] . ": ";
      $rights .= utf8entities(SeasonName($value[1]));
      $rights .= "</span><br/>";
      break;
    case "teamadmin":
      $rights .= "<span'>" . $value[0] . ": ";
      if (empty($value[1]) || empty(TeamName($value[1])))
        $rights .= "???";
      else
        $rights .= utf8entities(TeamName($value[1]));
      $rights .= "</span><br/>";
      break;
    }
  }

  return $rights;
}

function getSeriesName($series) {
  $query = sprintf("SELECT name FROM uo_series WHERE series_id=%d", (int) $series);
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    return $row['name'];
  } else
    return "";
}

function getTeamSeries($team) {
  $query = sprintf("SELECT series FROM uo_team WHERE team_id=%d", (int) $team);
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    return $row['series'];
  } else
    return "";
}

function getTeamSeason($team) {
  $query = sprintf(
    "SELECT ser.season as season FROM uo_team as team left join uo_series as ser on (team.series = ser.series_id)  WHERE team_id=%d",
    (int) $team);
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    return $row['season'];
  } else
    return "";
}

function getTeamName($team) {
  $query = sprintf("SELECT name FROM uo_team WHERE team_id=%d", (int) $team);
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    return $row['name'];
  } else
    return "";
}

function RemovePoolSelector($userid, $propid) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("DELETE FROM uo_userproperties WHERE prop_id=%d AND userid='%s' AND name='poolselector'",
      (int) $propid, mysql_adapt_real_escape_string($userid));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    Log1("security", "delete", $userid, $propid, "poolselector");
    invalidateSessions();
    if ($userid == $_SESSION['uid']) {
      SetUserSessionData($userid);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

function RemoveExtraEmail($userid, $extraEmail) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("DELETE FROM uo_extraemail WHERE userid='%s' AND email='%s'",
      mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($extraEmail));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    Log1("security", "delete", $userid, $extraEmail, "extraemail");
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

function ToPrimaryEmail($userid, $extraEmail) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("SELECT * FROM uo_extraemail WHERE userid='%s' AND email='%s'",
      mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($extraEmail));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    if ($row = mysqli_fetch_row($result)) {
      $userInfo = UserInfo($userid);
      $oldPrimary = $userInfo['email'];
      if ($oldPrimary != $extraEmail) {
        $query = sprintf("UPDATE uo_extraemail SET email='%s' WHERE userid='%s' and email='%s'",
          mysql_adapt_real_escape_string($oldPrimary), mysql_adapt_real_escape_string($userid),
          mysql_adapt_real_escape_string($extraEmail));
        $result = mysql_adapt_query($query);
        if (!$result) {
          die('Invalid query: ' . mysql_adapt_error());
        }
        $query = sprintf("UPDATE uo_users SET email='%s' WHERE userid='%s'", mysql_adapt_real_escape_string($extraEmail),
          mysql_adapt_real_escape_string($userid));
        $result = mysql_adapt_query($query);
        if (!$result) {
          die('Invalid query: ' . mysql_adapt_error());
        }
      }
    }
  } else {
    die('Insufficient rights to change user info');
  }
}

function AddPoolSelector(int $userid, string $selector) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='poolselector' AND value='%s'",
      mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($selector));
    $result = DBQuery($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }

    $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'poolselector', '%s')",
      mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($selector));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    Log1("security", "add", $userid, $selector, "poolselector");
    invalidateSessions();
    if ($userid == $_SESSION['uid']) {
      SetUserSessionData($userid);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

function RemoveEditSeason(int $userid, int $propid) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight()) {
    $query = sprintf("DELETE FROM uo_userproperties WHERE prop_id=%d AND userid='%s' AND name='editseason'",
      (int) $propid, mysql_adapt_real_escape_string($userid));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    Log1("security", "delete", $userid, $propid, "editseason");
    invalidateSessions();
    if ($userid == $_SESSION['uid']) {
      SetUserSessionData($userid);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

function AddEditSeason(int $userid, string $season) {
  if ($userid == $_SESSION['uid'] || hasEditUsersRight() || isSeasonAdmin($season)) {
    $query = sprintf("SELECT COUNT(*) FROM uo_userproperties 
			WHERE userid='%s' AND name='editseason' AND value='%s'", mysql_adapt_real_escape_string($userid),
      mysql_adapt_real_escape_string($season));
    $exist = DBQueryToValue($query);

    if ($exist == 0) {
      $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'editseason', '%s')",
        mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($season));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysql_adapt_error());
      }
      Log1("security", "add", $userid, $season, "editseason");
    }

    invalidateSessions();
    if ($userid == $_SESSION['uid']) {
      SetUserSessionData($userid);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

function RemoveUserRole(int $userid, int $propid) {
  if (hasEditUsersRight() || $_SESSION['uid'] == $userid) {
    $query = sprintf("DELETE FROM uo_userproperties WHERE prop_id=%d AND userid='%s' AND name='userrole'", (int) $propid,
      mysql_adapt_real_escape_string($userid));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    Log1("security", "delete", $userid, $propid, "userrole");
    invalidateSessions();
    if ($userid == $_SESSION['uid']) {
      SetUserSessionData($userid);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

/**
 *
 * @param string $userid
 * @param string $role
 * @return boolean
 */
function AddUserRole(int $userid, string $role) {
  if (isSuperAdmin() || ($role != 'superadmin' && hasEditUsersRight())) {
    $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', '%s')",
      mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($role));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysql_adapt_error());
    }
    Log1("security", "add", $userid, $role, "userrole");

    invalidateSessions();
    if ($userid == $_SESSION['uid']) {
      SetUserSessionData($userid);
    }
    return true;
  } else {
    die('Insufficient rights to change user info');
  }
}

/**
 * Attention: Use this function only if season admin rights for $seasonId are sufficient to grant the role.
 *
 * @param string $userId
 * @param string $role
 * @param string $seasonId
 * @return boolean
 */
function AddSeasonUserRole(int $userId, string $role, int $seasonId) {
  if (hasEditUsersRight() || isSeasonAdmin($seasonId)) {

    $query = sprintf("SELECT COUNT(*) FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='%s'",
      mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($role));
    $result = DBQueryToValue($query);

    if ($result <= 0) {
      $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', '%s')",
        mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($role));
      $result = DBQuery($query);
      Log1("security", "add", $userId, $seasonId, $role);
      AddEditSeason($userId, $seasonId);

      invalidateSessions();
      if ($userId == $_SESSION['uid']) {
        SetUserSessionData($userId);
      }
      return true;
    } else {
      return false;
    }
  } else {
    die('Insufficient rights to change user info');
  }
}

function RemoveSeasonUserRole(int $userId, string $role, int $seasonId) {
  if (hasEditUsersRight() || isSeasonAdmin($seasonId)) {
    $query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='%s'",
      mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($role));
    $result = DBQuery($query);
    return !empty($result);
  } else {
    die('Insufficient rights to change user info');
  }
}

function GetTeamAdmins(int $teamId) {
  $season = TeamSeason($teamId);

  if (isSeasonAdmin($season) || hasEditSeriesRight(TeamSeries($teamId))) {
    $query = sprintf(
      "SELECT pu.userid, pu.name, pu.email FROM uo_userproperties pup
				LEFT JOIN uo_users pu ON(pup.userid=pu.userid)
				WHERE pup.value='%s' ORDER BY pu.name ASC", mysql_adapt_real_escape_string('teamadmin:' . $teamId));
    return DBQueryToArray($query);
  } else {
    die('Insufficient rights to access user info');
  }
}

/**
 * 
 *
 * @param string $userId
 * @param string $teamId
 * @return boolean
 */
function AddTeamAdmin(int $userId, int $teamId) : bool {
  $seriesId = getTeamSeries($teamId);
  if (hasEditUsersRight() || hasEditSeriesRight($seriesId)) {
    $role = "teamadmin:$teamId";
    $query = sprintf("SELECT COUNT(*) FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='%s'",
      mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($role));
    $result = DBQueryToValue($query);
    
    if ($result <= 0) {
      $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', '%s')",
        mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($role));
      $result = DBQuery($query);
      Log1("security", "add", $userId, $teamId, $role);
      AddEditSeason($userId, SeriesSeasonId($seriesId));
      
      invalidateSessions();
      if ($userId == $_SESSION['uid']) {
        SetUserSessionData($userId);
      }
      return true;
    } else {
      return false;
    }
  } else {
    die('Insufficient rights to change user info');
  }
}

function RemoveTeamAdmin(int $userId, int $teamId) : bool {
  $seriesId = getTeamSeries($teamId);
  if (hasEditUsersRight() || hasEditSeriesRight($seriesId)) {
    $role = "teamadmin:$teamId";
    $query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s' AND name='userrole' AND value='%s'",
      mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($role));
    $result = DBQuery($query);
    return !empty($result);
  } else {
    die('Insufficient rights to change user info');
  }
}

function DeleteUser(int $userid) {
  if ($userid != "anonymous") {
    if (hasEditUsersRight($userid) || $userid = $_SESSION['uid']) {
      $query = sprintf("DELETE FROM uo_userproperties WHERE userid='%s'", mysql_adapt_real_escape_string($userid));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
      $query = sprintf("DELETE FROM uo_users WHERE userid='%s'", mysql_adapt_real_escape_string($userid));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
      Log1("security", "delete", $userid, "", "user");
    } else {
      die('Insufficient rights to delete user');
    }
  } else {
    die('Can not delete anonymous user');
  }
}

function DeleteRegisterRequest(int $userId) {
  if ($userId != "anonymous") {
    if (hasEditUsersRight()) {
      Log1("security", "delete", $userId, "", "RegisterRequest");
      $query = sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'", mysql_adapt_real_escape_string($userId));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    } else {
      die('Insufficient rights to delete user');
    }
  } else {
    die('Can not delete anonymous user');
  }
}

function AddRegisterRequest($newUsername, $newPassword, $newName, $newEmail, $message = 'register.txt') {
  Log1("user", "add", $newUsername, "", "register request");
  $token = uuidSecure();
  $query = sprintf(
    "INSERT INTO uo_registerrequest (userid, password, name, email, token, last_login) VALUES ('%s', MD5('%s'), '%s', '%s', '%s', NOW())",
    mysql_adapt_real_escape_string($newUsername), mysql_adapt_real_escape_string($newPassword),
    mysql_adapt_real_escape_string($newName), mysql_adapt_real_escape_string($newEmail),
    mysql_adapt_real_escape_string($token));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $message = file_get_contents('locale/' . GetSessionLocale() . '/LC_MESSAGES/' . $message);

  // for IIS
  $url = GetURLBase() . "?view=register&token=" . urlencode($token);

  $message = str_replace(array('$url', '$ultiorganizer'), array($url, _("Ultiorganizer")), $message);
  $headers = "MIME-Version: 1.0" . "\r\n";
  $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";

  global $serverConf;
  $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";

  if (!mail($newEmail, _("Confirm your account to ultiorganizer"), $message, $headers)) {
    $query = sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'", mysql_adapt_real_escape_string($newUsername));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    return false;
  } else {
    return true;
  }
}

function emailUsed($email) {
  $query = sprintf(
    "select email from uo_users where LOWER(email)='%s' 
		union all select email from uo_extraemail where LOWER(email)='%s' 
		union all select email from uo_extraemailrequest where LOWER(email)='%s'",
    mysql_adapt_real_escape_string(strtolower($email)), mysql_adapt_real_escape_string(strtolower($email)),
    mysql_adapt_real_escape_string(strtolower($email)));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if (mysqli_fetch_row($result)) {
    return true;
  } else {
    return false;
  }
}

function AddExtraEmailRequest($userid, $extraEmail, $message = 'verify_email.txt') {
  Log1("user", "add", $userid, "", "extra email request");
  $token = uuidSecure();
  $query = sprintf("INSERT INTO uo_extraemailrequest (userid, email, token, time) VALUES ('%s', '%s', '%s', NOW())",
    mysql_adapt_real_escape_string($userid), mysql_adapt_real_escape_string($extraEmail),
    mysql_adapt_real_escape_string($token));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $message = file_get_contents('locale/' . GetSessionLocale() . '/LC_MESSAGES/' . $message);

  // for IIS
  $url = GetURLBase() . "?view=user/addextraemail&token=" . urlencode($token);

  $message = str_replace(array('$url', '$ultiorganizer'), array($url, _("Ultiorganizer")), $message);
  $headers = "MIME-Version: 1.0" . "\r\n";
  $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";

  global $serverConf;
  $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";

  if (!mail($extraEmail, _("Confirm extra email address for ultiorganizer"), $message, $headers)) {
    $query = sprintf("DELETE FROM uo_extraemailrequest WHERE token='%s'", mysql_adapt_real_escape_string($token));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    return false;
  } else {
    return true;
  }
}

function RegisterUIDByToken($token) {
  $query = sprintf("SELECT userid FROM uo_registerrequest WHERE token='%s'", mysql_adapt_real_escape_string($token));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    return $row['userid'];
  }
  return false;
}

function ConfirmRegister($token) {
  $query = sprintf("SELECT userid, password, name, email FROM uo_registerrequest WHERE token='%s'",
    mysql_adapt_real_escape_string($token));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    $query = sprintf("INSERT INTO uo_users (name, userid, password, email) VALUES ('%s', '%s', '%s', '%s')",
      mysql_adapt_real_escape_string($row['name']), mysql_adapt_real_escape_string($row['userid']),
      mysql_adapt_real_escape_string($row['password']), mysql_adapt_real_escape_string($row['email']));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $query = sprintf("DELETE FROM uo_registerrequest WHERE token='%s'", mysql_adapt_real_escape_string($token));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    FinalizeNewUser($row['userid'], $row['email']);
    Log1("user", "add", $row['userid'], "", "confirm register request");
    return true;
  } else
    return false;
}

/**
 * Adds a new user.
 * Attention! This does not require any user rights!
 *
 * @param string $newUsername
 * @param string $newPassword
 * @param string $newName
 * @param string $newEmail
 * @param string $creator
 *          The user that created the new user
 * @return string|boolean
 */
function AddUser($newUsername, $newPassword, $newName, $newEmail, $creator) {
  if (hasEditUsersRight()) {
    $message = UserValid($newUsername, $newPassword, $newPassword, $newName, $newEmail, true);
    if (empty($message)) {
      $query = sprintf("INSERT INTO uo_users (userid, password, name, email) VALUES ('%s', MD5('%s'), '%s', '%s')",
        mysql_adapt_real_escape_string($newUsername), mysql_adapt_real_escape_string($newPassword),
        mysql_adapt_real_escape_string($newName), mysql_adapt_real_escape_string($newEmail));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
      $query = sprintf("DELETE FROM uo_registerrequest WHERE userid='%s'", mysql_adapt_real_escape_string($newUsername));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
      FinalizeNewUser($newUsername, $newEmail);
      Log1("user", "add", $newUsername, $creator, "added by administrator");
    }
    return $message;
  } else
    die('insufficient rights to add users');
}

function FinalizeNewUser($userid, $email) {
  $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'poolselector', 'currentseason')",
    mysql_adapt_real_escape_string($userid));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }

  $query = sprintf("SELECT DISTINCT profile_id FROM uo_player_profile WHERE LOWER(email)='%s'",
    mysql_adapt_real_escape_string(strtolower($email)));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  while ($profile = mysqli_fetch_row($result)) {
    $query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'playeradmin:%d')",
      mysql_adapt_real_escape_string($userid), intval($profile[0]));
    $result1 = mysql_adapt_query($query);
    if (!$result1) {
      die('Invalid query: ' . mysql_adapt_error());
    }
  }
}

function ConfirmEmail($token) {
  $query = sprintf("SELECT userid, email FROM uo_extraemailrequest WHERE token='%s'",
    mysql_adapt_real_escape_string($token));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  if ($row = mysqli_fetch_assoc($result)) {
    $query = sprintf("INSERT INTO uo_extraemail (userid, email) VALUES ('%s', '%s')",
      mysql_adapt_real_escape_string($row['userid']), mysql_adapt_real_escape_string($row['email']));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $query = sprintf("DELETE FROM uo_extraemailrequest WHERE token='%s'", mysql_adapt_real_escape_string($token));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }

    $query = sprintf("SELECT DISTINCT profile_id FROM uo_player_profile WHERE LOWER(email)='%s'",
      mysql_adapt_real_escape_string(strtolower($row['email'])));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    while ($profile = mysqli_fetch_row($result)) {
      $query = sprintf(
        "INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'userrole', 'playeradmin:%s')",
        mysql_adapt_real_escape_string($row['userid']), intval($profile[0]));
      $result1 = mysql_adapt_query($query);
      if (!$result1) {
        die('Invalid query: ' . mysql_adapt_error());
      }
    }

    Log1("user", "add", $row['userid'], "", "confirm extra email");
    return true;
  } else
    return false;
}

function uuidSecure() {
  $pr_bits = null;
  $fp = @fopen('/dev/urandom', 'rb');
  if ($fp !== false) {
    $pr_bits .= @fread($fp, 16);
    @fclose($fp);
  } else {
    // If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
    $pr_bits = "";
    for ($cnt = 0; $cnt < 16; $cnt++) {
      $pr_bits .= chr(mt_rand(0, 255));
    }
  }

  $time_low = bin2hex(substr($pr_bits, 0, 4));
  $time_mid = bin2hex(substr($pr_bits, 4, 2));
  $time_hi_and_version = bin2hex(substr($pr_bits, 6, 2));
  $clock_seq_hi_and_reserved = bin2hex(substr($pr_bits, 8, 2));
  $node = bin2hex(substr($pr_bits, 10, 6));

  /**
   * Set the four most significant bits (bits 12 through 15) of the
   * time_hi_and_version field to the 4-bit version number from
   * Section 4.1.3.
   *
   * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
   */
  $time_hi_and_version = hexdec($time_hi_and_version);
  $time_hi_and_version = $time_hi_and_version >> 4;
  $time_hi_and_version = $time_hi_and_version | 0x4000;

  /**
   * Set the two most significant bits (bits 6 and 7) of the
   * clock_seq_hi_and_reserved to zero and one, respectively.
   */
  $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
  $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
  $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

  return sprintf('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved,
    $node);
}

function TeamResponsibilities($userid, $season) {
  $teams = SeasonTeams($season);
  $seasonTeamAdmin = array();
  foreach ($teams as $team) {
    if (isset($_SESSION['userproperties']['userrole']['teamadmin'][$team['team_id']])) {
      $seasonTeamAdmin[] = $team['team_id'];
    }
  }
  return $seasonTeamAdmin;
}

function GameResponsibilities($season) {
  $query = sprintf("SELECT DISTINCT game_id FROM uo_game WHERE ");
  $criteria = "";
  if (isSeasonAdmin($season)) {
    $criteria = sprintf(
      " pool IN 
		(SELECT pool_id FROM uo_pool WHERE series IN 
			(SELECT series_id FROM uo_series WHERE season='%s'))", mysql_adapt_real_escape_string($season));
  } else {
    // SeriesAdmin
    $seriesResult = SeasonSeries($season);
    $seasonSeriesAdmin = array();
    foreach ($seriesResult as $row) {
      if (isset($_SESSION['userproperties']['userrole']['seriesadmin'][$row['series_id']])) {
        $seasonSeriesAdmin[] = $row['series_id'];
      }
    }
    if (count($seasonSeriesAdmin) > 0) {
      $criteria = "(pool IN (SELECT pool_id FROM uo_pool WHERE series IN (" . implode(",", $seasonSeriesAdmin) . ")))";
    }

    // TeamAdmin
    $teams = SeasonTeams($season);
    $seasonTeamAdmin = array();
    foreach ($teams as $team) {
      if (isset($_SESSION['userproperties']['userrole']['teamadmin'][$team['team_id']])) {
        $seasonTeamAdmin[] = $team['team_id'];
      }
    }
    if (count($seasonTeamAdmin) > 0) {
      if (strlen($criteria) > 0) {
        $criteria .= " OR ";
      }
      $criteria .= "(respteam IN (" . implode(",", $seasonTeamAdmin) . "))";
    }
    if (isset($_SESSION['userproperties']['userrole']['gameadmin'])) {
      // GameAdmin
      $respGames = $_SESSION['userproperties']['userrole']['gameadmin'];
      $seasonGames = array();
      foreach ($respGames as $gameId => $propId) {
        if (GameSeason($gameId) == $season) {
          $seasonGames[] = $gameId;
        }
      }
      if (count($seasonGames) > 0) {
        if (strlen($criteria) > 0) {
          $criteria .= " OR ";
        }
        $criteria .= "(game_id IN (" . implode(",", $seasonGames) . "))";
      }
    }
    if (isset($_SESSION['userproperties']['userrole']['resgameadmin'])) {
      // ResGameAdmin
      $respResvs = $_SESSION['userproperties']['userrole']['resgameadmin'];
      $seasonResvs = array();
      foreach ($respResvs as $resId => $propId) {
        foreach (ReservationSeasons($resId) as $resSeason) {
          if ($resSeason == $season) {
            $seasonResvs[] = $resId;
            break;
          }
        }
      }
      if (count($seasonResvs) > 0) {
        if (strlen($criteria) > 0) {
          $criteria .= " OR ";
        }
        $criteria .= "(reservation IN (" . implode(",", $seasonResvs) . "))";
      }
    }
  }
  if (strlen($criteria) == 0) {
    return array();
  } else {
    $ret = array();
    $query .= $criteria;
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    while ($row = mysqli_fetch_row($result)) {
      $ret[] = $row[0];
    }
    return $ret;
  }
}

function GameResponsibilityArray($season, $series = null) {
  $gameResponsibilities = GameResponsibilities($season);
  if (!$gameResponsibilities) {
    return array();
  }
  $query = sprintf(
    "SELECT game_id, hometeam, kj.name as hometeamname, visitorteam,
			vj.name as visitorteamname, pp.pool as pool, time, homescore, visitorscore,
			pool.timecap, pool.timeslot, pool.series, res.reservationgroup,
			ser.name, pool.name as poolname, res.id as res_id, res.starttime,
			loc.name AS locationname, res.fieldname AS fieldname, res.location,
			COALESCE(m.goals,0) AS goals, phome.name AS phometeamname, pvisitor.name AS pvisitorteamname,
	        pp.isongoing, pp.hasstarted
		FROM uo_game pp left join uo_reservation res on (pp.reservation=res.id) 
			left join uo_pool pool on (pp.pool=pool.pool_id)
			left join uo_series ser on (pool.series=ser.series_id)
			left join uo_location loc on (res.location=loc.id)
			left join uo_team kj on (pp.hometeam=kj.team_id)
			left join uo_team vj on (pp.visitorteam=vj.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			left join (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS m ON (pp.game_id=m.game)
		WHERE game_id IN (" . implode(",", $gameResponsibilities) . ")" . ($series ? " AND pool.series=%d" : "") .
    "
		ORDER BY res.starttime ASC, res.reservationgroup ASC, res.fieldname+0,pp.time ASC", $series ? (int) $series : 0);

  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $ret = array();
  while ($row = mysqli_fetch_assoc($result)) {
    if (!isset($ret[$row['reservationgroup']])) {
      $ret[$row['reservationgroup']] = array();
    }
    if (!isset($ret[$row['reservationgroup']][$row['res_id']])) {
      $ret[$row['reservationgroup']][$row['res_id']] = array();
    }
    $gamesArray = $ret[$row['reservationgroup']][$row['res_id']];
    $gamesArray['starttime'] = $row['starttime'];
    $gamesArray['locationname'] = utf8entities($row['locationname']) . " " . _("Field") . " " .
      utf8entities($row['fieldname']);
    $gamesArray[$row['game_id']] = $row;
    $ret[$row['reservationgroup']][$row['res_id']] = $gamesArray;
  }
  return $ret;
}

function UserCheckRecoverToken($userId, $token) {
  if (empty($token))
    return false;
  $query = sprintf(
    "SELECT COUNT(*) FROM uo_recoverrequest WHERE userid='%s' AND token='%s' AND TIMESTAMPDIFF(MINUTE, time, NOW()) < 60",
    mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($token));
  return DBQueryToValue($query) == 1;
}

function UserRecoverPasswordRequest($userId) {
  $query = sprintf(
    "SELECT MAX(time) as last FROM uo_recoverrequest WHERE userid='%s' AND TIMESTAMPDIFF(MINUTE, time, NOW()) < 6 GROUP BY time",
    mysql_adapt_real_escape_string($userId));
  $lastRequest = DBQueryToArray($query);
  if (!empty($lastRequest)) {
    // too many requests
    return false;
  }

  $query = sprintf("SELECT email FROM uo_users WHERE userid='%s'", mysql_adapt_real_escape_string($userId));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $row = mysqli_fetch_assoc($result);

  if (!empty($row['email'])) {
    $email = $row['email'];
    Log1("user", "change", $userId, "", "recover mail");
    $token = uuidSecure();
    $url = GetURLBase() . "?view=login&user=" . urlencode($userId) . "&token=" . urlencode($token);
    $query = sprintf("INSERT INTO uo_recoverrequest (userid, email, token, time) 
       VALUES ('%s', '%s', '%s', NOW())", mysql_adapt_real_escape_string($userId),
      mysql_adapt_real_escape_string($email), mysql_adapt_real_escape_string($token));
    $result = mysql_adapt_query($query);
    if (!$result) {
      die('Invalid query: ' . mysql_adapt_error());
    }
    $query = sprintf("DELETE FROM uo_recoverrequest WHERE userid='%s' AND token != '%s'",
      mysql_adapt_real_escape_string($userId), mysql_adapt_real_escape_string($token));
    DBQuery($query);

    $locale = getSessionLocale();
    $message = file_get_contents('locale/' . $locale . '/LC_MESSAGES/pwd_recover.txt');
    $message = str_replace(array('$url', '$ultiorganizer', '$username'), array($url, _("Ultiorganizer"), $userId),
      $message);

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";

    global $serverConf;
    $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";

    if (mail($email, _("Password recovery request"), $message, $headers)) {
      return true;
    } else {
      return false;
    }
  } else {
    return false;
  }
}

function UserResetPassword($userId) {
  Log1("user", "change", $userId, "", "reset password");

  $query = sprintf("SELECT email FROM uo_users WHERE userid='%s'", mysql_adapt_real_escape_string($userId));
  $result = mysql_adapt_query($query);
  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  $row = mysqli_fetch_assoc($result);

  $email = $row['email'];
  if (!empty($email)) {
    $password = UserCreateRandomPassword();

    $url = GetURLBase();
    $locale = getSessionLocale();
    $message = file_get_contents('locale/' . $locale . '/LC_MESSAGES/pwd_reset.txt');
    $message = str_replace('$url', $url, $message);
    $message = str_replace('$username', $userId, $message);
    $message = str_replace('$password', $password, $message);

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";

    global $serverConf;
    $headers .= "From: " . $serverConf['EmailSource'] . "\r\n";

    if (mail($email, _("New password to ultiorganizer"), $message, $headers)) {
      $query = sprintf("UPDATE uo_users SET password=MD5('%s') WHERE userid='%s'",
        mysql_adapt_real_escape_string($password), mysql_adapt_real_escape_string($userId));
      $result = mysql_adapt_query($query);
      if (!$result) {
        die('Invalid query: ' . mysql_adapt_error());
      }
      return true;
    } else {
      return false;
    }
  } else {
    return false;
  }
}

function CreateNewUsername($firstname, $lastname, $email) {
  $firstname = strtolower($firstname);
  $lastname = strtolower($lastname);
  $emailSplitted = explode("@", strtolower($email));
  $emailStart = $emailSplitted[0];
  $try = mb_substr($firstname, 0, 1) . $lastname;
  if (!isRegistered($try))
    return $try;
  if (!isRegistered($emailStart))
    return $emailStart;
  if (!isRegistered($firstname . "." . $lastname))
    return $firstname . "." . $lastname;
  $extra = 0;
  while (true) {
    $extra++;
    if (!isRegistered($try . $extra))
      return $try . $extra;
    if (!isRegistered($emailStart . $extra))
      return $emailStart . $extra;
    if (!isRegistered($firstname . "." . $lastname . $extra))
      return $firstname . "." . $lastname . $extra;
  }
}

function rchar($alnum = false) {
  $chars = "abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ._-:#+*~!%=?023456789";
  $num = almostSecureRandom(0, strlen($chars) - 1);
  return substr($chars, $num, 1);
}

function UserCreateRandomPassword() {
  $i = 0;
  $pass = '' . rchar(true);
  while ($i <= 7) {
    $pass = $pass . rchar();
    $i++;
  }
  $pass .= rchar(true);
  return $pass;
}

function restore_db_error($log, $result) {
  if ($result == -1)
    $log[] = sprintf(_("Error running query, error '%s'"), mysql_adapt_error());

  return array('result' => $result, 'log' => $log);
}

function RestoreAnonymousUser($override = 0) {
  $log = array();

  $query = sprintf("SELECT id FROM uo_users WHERE userid='anonymous'");
  $log[] = $query;

  $result = mysql_adapt_query($query);
  if (!$result) {
    return restore_db_error($log, -1);
  }
  $count = mysqli_num_rows($result);

  if ($count == 1) {
    $row = mysqli_fetch_row($result);
    $userId = $row[0];
  } else {
    $list = implode(", ", DBResourceToArray($result, true));
    $log[] = sprintf(_("No or too many anonymous users (%s). Additional users will be deleted."), $list);

    if ($override < 101)
      return restore_db_error($log, 101);

    if ($count > 0) {
      $query = sprintf("DELETE FROM uo_users WHERE userid='anonymous'");
      $log[] = $query;
      $result = DBQuery($query);
      if (!$result) {
        return restore_db_error($log, -1);
      }
    }
    $query = "INSERT INTO `uo_users` (`userid`, `password`, `name`, `email`, `last_login`) " .
      "VALUES ('anonymous', NULL, NULL, NULL, NULL)";

    $log[] = $query;
    $result = mysql_adapt_query($query);
    if (!$result) {
      return restore_db_error($log, -1);
    }
    $userId = mysql_adapt_insert_id();
  }

  if ($userId != 1) {
    $log[] = sprintf(_("Anonymous userid is %d instead of 1. This may be okay."), $userId);
    if ($override < 102)
      return restore_db_error($log, 102);
  }

  $query = sprintf("SELECT 1 FROM uo_userproperties WHERE userid='anonymous'");
  $log[] = $query;
  $result = mysql_adapt_query($query);
  if (!$result) {
    return restore_db_error($log, -1);
  }
  $count = mysqli_num_rows($result);
  if ($count > 1) {
    $log[] = _("Found $count properties in uo_userproperties, expected 1. Additional entries will be deleted.");
    if ($override < 103)
      return restore_db_error($log, 103);
  }

  $query = "DELETE FROM `uo_userproperties` WHERE userid='anonymous'";
  $log[] = $query;
  $result = mysql_adapt_query($query);
  if (!$result) {
    return restore_db_error($log, -1);
  }

  $query = "INSERT INTO `uo_userproperties` (`prop_id`, `userid`, `name`, `value`) VALUES (1, 'anonymous', 'poolselector', 'currentseason')";
  $log[] = $query;
  $result = mysql_adapt_query($query);
  if (!$result) {
    return restore_db_error($log, -1);
  }

  return restore_db_error($log, 1);
}

function SelectionInput($id, $value, $valueId = null) {
  $html = "<div id='${id}Autocomplete' class='yui-skin-sam'>\n";
  if ($valueId !== null) {
    $html .= "<input type='hidden'  name='${id}' id='${id}' value='" . utf8entities($valueId) . "'/>\n";
    $html .= "<input class='input' id='${id}Name' style='position:relative;' type='text' name='${id}Name' value='";
    $html .= utf8entities($value);
  } else {
    $html .= "<input class='input' id='${id}' style='position:relative;' type='text' name='${id}' value='";
    $html .= utf8entities($value);
  }
  $html .= "'/><div id='${id}Container'></div></div>";

  return $html;
}

function UserInput($id, $value) {
  return SelectionInput($id, $value);
}

function SelectionScript($id, $type, $hiddenId = false, $minQueryLength = 1) {
  $idOffset = $hiddenId ? '1' : '0';
  $trigger = $hiddenId ? "{$id}Name" : $id;
  $script = "<script type=\"text/javascript\">
//<![CDATA[
  var idOffset = $idOffset;
";
  if ($hiddenId) {
    $script .= "
  var ${id}SelectHandler = function(sType, aArgs) {
    var oData = aArgs[2];
    document.getElementById(\"${id}\").value = oData[1];
    var x = document.getElementById(\"${id}Name\").className;
    document.getElementById(\"${id}Name\").className = x.replaceAll(' highlight','');
  };

  var ${id}SelectHandler2 = function() {
    var x = document.getElementById(\"${id}Name\").className;
    if (!x || !x.includes(' highlight'))
      document.getElementById(\"${id}Name\").className+=' highlight';
  };

";
  }
  $script .= "
  Fetch${id} = function(){
    var ${type}Source = new YAHOO.util.XHRDataSource(\"ext/${type}txt.php\");
    ${type}Source.responseSchema = {
      recordDelim: \"\\n\",
      fieldDelim: \"\\t\"
    };
    ${type}Source.responseType = YAHOO.util.XHRDataSource.TYPE_TEXT;
    ${type}Source.maxCacheEntries = 60;

    // First AutoComplete
    var ${type}AutoComp = new YAHOO.widget.AutoComplete(\"${trigger}\",\"${id}Container\", ${type}Source);
    ${type}AutoComp.formatResult = function(oResultData, sQuery, sResultMatch) {

      format = `<div class='${type}CustomResult'><span style='font-weight:bold'>\${sResultMatch}<\/span>`;
      if (oResultData.length > 1 + idOffset) {
        format += ` / \${oResultData[1 + idOffset]}`;
      }
      if (oResultData.length > 2 + idOffset) {
        format += '<br /> (';
        for(i=2; i < oResultData.length; ++i) {
          if (i > 2) format += ', ';
          format += `\${oResultData[i + idOffset]}`;
        }
        format += ')';
      }
      format += '</div>';
      return format;
    };
    ${type}AutoComp.minQueryLength = $minQueryLength;
";
  if ($hiddenId) {
    $script .= "
      userAutoComp.itemSelectEvent.subscribe(${id}SelectHandler);
      userAutoComp.textboxFocusEvent.subscribe(${id}SelectHandler2);
";
  }

  $script .= "
    return {
      oDS: ${type}Source,
      oAC: ${type}AutoComp
    }
  }();
//]]>
</script>\n";
  return $script;
}

function UserScript($id) {
  return SelectionScript($id, 'user');
}

function GetUsers($mode, $search) {
  $query = sprintf("SELECT id, userid, name from uo_users WHERE uo_users.userid LIKE '%%%s%%' OR name LIKE '%%%s%%'",
    mysql_adapt_real_escape_string($search), mysql_adapt_real_escape_string($search));
  $result = mysql_adapt_query($query);

  if (!$result) {
    die('Invalid query: ' . mysql_adapt_error());
  }
  return $result;
}

function GetSearchUsers() {
  if (isset($_GET['search']) || isset($_GET['query']) || isset($_GET['q'])) {
    if (isset($_GET['search']))
      $search = $_GET['search'];
    elseif (isset($_GET['query']))
      $search = $_GET['query'];
    else
      $search = $_GET['q'];
    return GetUsers('search', $search);
  } elseif (isset($_GET['id'])) {
    return GetUsers('id', (int) $_GET['id']);
  } else {
    return GetUsers('all', null);
  }
}

function GetUserYears($admin = false) {
  $query = "SELECT count(*) as count, YEAR(last_login) as year FROM uo_users u";
  if (!$admin) {
    $query .= " WHERE NOT EXISTS(SELECT * FROM uo_userproperties up WHERE u.userid = up.userid AND up.name = 'userrole' AND up.value = 'superadmin')";
  }
  $query .= " GROUP BY YEAR(last_login) ORDER BY year ASC";
  return DBQueryToArray($query);
}

function DeleteUsersBefore(int | null $year = 0, bool $confirmed) {
  if (!isSuperAdmin())
    die('insufficient rights to delete users');

  $query = "SELECT count(*) FROM uo_userproperties WHERE userid = ''";
  $count = DBQueryToValue($query);

  if ($confirmed == true) {
    $query = "DELETE FROM uo_userproperties WHERE userid = ''";
    DBQuery($query);
    Log1("database", "delete", "", "", "delete $count orphaned user properties", "user");
  }

  $notexists = "u.userid != 'anonymous' AND NOT EXISTS (SELECT prop_id FROM (SELECT prop_id FROM uo_userproperties up2 
    WHERE u.userid = up2.userid AND up2.name = 'userrole' AND up2.value = 'superadmin') AS b)";

  if ($year === NULL) {
    $selector = "u.last_login IS NULL";
  } else {
    $year = intval($year);
    $selector = "year(u.last_login) <= $year";
  }

  if ($confirmed == true) {
    $query = "DELETE u, up FROM uo_users as u
      LEFT JOIN uo_userproperties as up ON (u.userid = up.userid) WHERE $selector AND $notexists";
    return DBQuery($query);
  } else {
    $query = "SELECT userid, name, email, last_login FROM uo_users as u WHERE $selector AND $notexists";
    return DBQueryToArray($query);
    // return $count + DBQueryToValue("SELECT count(*) FROM uo_users as u WHERE $selector AND $notexists");
  }
}
