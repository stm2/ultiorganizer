<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/reservation.functions.php';

$html = "";
$message = "";
$error = 0;

ensureLogin();

if (!empty($_GET['user'])) {
  if (($_GET['user']) != $_SESSION['uid'] && !hasEditUsersRight()) {
    die('Insufficient rights to change user info');
  } else {
    $userid = $_GET['user'];
  }
} else {
  $userid = $_SESSION['uid'];
}

if (IsFacebookEnabled() && $_SESSION['uid'] == $userid) {
  global $serverConf;
  $fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);

  if ($fb_cookie) {
    if ((!empty($_GET['linkfacebook'])) && ($_GET['linkfacebook'] == "true")) {
      ReMapFBUserId($fb_cookie, $userid);
    }
    if ((!empty($_GET['unlinkfacebook'])) && ($_GET['unlinkfacebook'] == "true")) {
      UnMapFBUserId($fb_cookie, $userid);
    }
  }
  $fb_props = getFacebookUserProperties($userid);
  if (FBLoggedIn($fb_cookie, $fb_props)) {
    if (!empty($_POST['linkfbplayer'])) {
      LinkFBPlayer($userid, $_POST['fbPlayerId'], array("won"));
      $fb_props = getFacebookUserProperties($userid);
    }
    if (!empty($_POST['unlinkfbplayer'])) {
      UnLinkFBPlayer($userid, $_POST['fbPlayerId']);
      $fb_props = getFacebookUserProperties($userid);
    }
  }
}

if ($_SESSION['uid'] === "anonymous") {
  showPage(_("User information"), "<p>" . _("You are not logged in.") . "</p>");
  exit();
}

$userinfo = UserInfo($userid);
if ($userinfo == null || $userid === "anonymous") {
  showPage(_("User information"), "<p>" . sprintf(_("Unknown user '%s'."), utf8entities($userid)) . "</p>");
  exit();
} 

// if ($userid != "anonymous") 
{
  // process itself if submit was pressed
  if (!empty($_POST['save'])) {
    $newUsername = $_POST['UserName'];
    $newName = $_POST['Name'];
    $newLocale = $_POST['userlocale'];

    $message = UserValid($newUsername, null, null, $newName, "abc@example.com", $userid != $newUsername, false);

    global $locales;
    if (!isset($locales[$newLocale])) {
      $message .= "<p class='warning'>" . _("Unsupported language:") . " " . $newLocale . "</p>";
    }

    if (!empty($message)) {
      $message .= "<p class='warning'><b>" . _("Changes were NOT saved") . "</b></p><hr/>";
    } else {
      $message = "<p>" . _("Changes were saved") . "</p><hr/>";

      $success = false;
      $oldLocale = getUserLocale($userid);
      if ($oldLocale != $newLocale) {
        SetUserLocale($userid, $newLocale);
        if ($_SESSION['uid'] == $userid) {
          $_SESSION['userproperties']['locale'] = array($newLocale => 0);
          setSessionLocale();
          loadDBTranslations($newLocale);
        }
      }
      $success = UserUpdateInfo($userinfo['id'], $userid, $newUsername, $newName);
      if ($success) {
        if ($newUsername != $_SESSION['uid'] && $userid != $newUsername) {
          header('location:?view=user/userinfo&user=' . urlencode($newUsername));
          exit();
        } else {
          $userid = $newUsername;
        }
      }
    }
  }
  
  if(!empty($_POST['changepsw'])) {
    $newPassword1=$_POST['Password1'];
    $newPassword2=$_POST['Password2'];
    $pw = UserValidPassword($newPassword1, $newPassword2);
    if (!empty($pw)) {
      $message .= $pw;
      $error = 1;
    }

    if (!$error) {
      $message .= "<p>" . _("Changes were saved.") . "</p><hr/>";
    } else {
      $message .= "<p class='warning'><b>" . _("Changes were NOT saved.") . "</b></p><hr/>";
    }

    if (!$error) {
      if (UserChangePassword($userid, $newPassword1) == 0)
        $message .= "<p>" . _("Nothing was changed.") . "</p><hr/>";
    }
    
  }

  if (!empty($_POST['addeditseasons']) && !empty($_POST['addeditseasonslist'])) {
    foreach($_POST['addeditseasonslist'] as $seasonid) {
      AddEditSeason($userid, $seasonid);
    }
  }
  if (!empty($_POST['remeditseasons']) && !empty($_POST['remeditseasonslist'])) {
    foreach($_POST['remeditseasonslist'] as $propid) {
      RemoveEditSeason($userid, $propid);
    }
  }
  if (!empty($_POST['rempoolselector_x'])) {
    RemovePoolSelector($userid, $_POST['deleteSelectorId']);
  }
  if (!empty($_POST['remuserrole_x'])) {
    RemoveUserRole($userid, $_POST['deleteRoleId']);
  }
  if (!empty($_POST['remextraemail_x'])) {
    RemoveExtraEmail($userid, $_POST['deleteExtraEmail']);
  }

  if (!empty($_POST['toprimaryemail'])) {
    ToPrimaryEmail($userid, $_POST['toPrimaryEmailVal']);
  }

  if (!empty($_POST['selectpoolselector'])) {
    if ($_POST['selectortype'] == 'currentseason') {
      $selector = 'currentseason';
      AddPoolSelector($userid, $selector);
    } elseif ($_POST['selectortype'] == 'team') {
      foreach ($_POST['teams'] as $teamid) {
        AddPoolSelector($userid, 'team:'.$teamid);
      }
    } elseif ($_POST['selectortype'] == 'season') {
      foreach ($_POST['searchseasons'] as $seasonid) {
        AddPoolSelector($userid, 'season:'.$seasonid);
      }
    } elseif ($_POST['selectortype'] == 'series') {
      foreach ($_POST['series'] as $seriesid) {
        AddPoolSelector($userid, 'series:'.$seriesid);
      }
    } elseif ($_POST['selectortype'] == 'pool') {
      foreach ($_POST['pools'] as $poolid) {
        AddPoolSelector($userid, 'pool:'.$poolid);
      }
    }
  }

  if (!empty($_POST['selectuserrole']) && hasEditUsersRight()) {
    if ($_POST['userrole'] == 'superadmin') {
      $selector = 'superadmin';
      AddUserRole($userid, $selector);
    } elseif ($_POST['userrole'] == 'translationadmin') {
      $selector = 'translationadmin';
      AddUserRole($userid, $selector);
    } elseif ($_POST['userrole'] == 'useradmin') {
      $selector = 'useradmin';
      AddUserRole($userid, $selector);
    } elseif ($_POST['userrole'] == 'teamadmin') {
      if (isset($_POST['teams']))
      foreach ($_POST['teams'] as $teamid) {
        AddUserRole($userid, 'teamadmin:'.$teamid);
      }
    } elseif ($_POST['userrole'] == 'accradmin') {
      if (isset($_POST['teams']))
      foreach ($_POST['teams'] as $teamid) {
        AddUserRole($userid, 'accradmin:'.$teamid);
      }
    } elseif ($_POST['userrole'] == 'seasonadmin') {
      if (isset($_POST['searchseasons']))
      foreach ($_POST['searchseasons'] as $seasonid) {
        AddUserRole($userid, 'seasonadmin:'.$seasonid);
      }
    } elseif ($_POST['userrole'] == 'seriesadmin') {
      if (isset($_POST['series']))
      foreach ($_POST['series'] as $seriesid) {
        AddUserRole($userid, 'seriesadmin:'.$seriesid);
      }
    } elseif ($_POST['userrole'] == 'resadmin') {
      if (isset($_POST['reservations']))
      foreach ($_POST['reservations'] as $reservationId) {
        AddUserRole($userid, 'resadmin:'.$reservationId);
      }
    } elseif ($_POST['userrole'] == 'resgameadmin') {
      if (isset($_POST['reservations']))
      foreach ($_POST['reservations'] as $reservationId) {
        AddUserRole($userid, 'resgameadmin:'.$reservationId);
      }
    } elseif ($_POST['userrole'] == 'gameadmin') {
      if (isset($_POST['games']))
      foreach ($_POST['games'] as $gameId) {
        AddUserRole($userid, 'gameadmin:'.$gameId);
      }
    } elseif ($_POST['userrole'] == 'playeradmin') {
      if (isset($_POST['players']))
      foreach ($_POST['players'] as $playerId) {
        AddUserRole($userid, 'playeradmin:'.$playerId);
      }
    }
  }

}

$title = _("User information") . ": " . utf8entities($userinfo['name']);
$html .= file_get_contents('script/disable_enter.js.inc');

{
  $html .= $message;

  $html .= "<form method='post' action='?view=user/userinfo";
  if (!empty($_GET['user'])) {
    $html .= "&amp;user=" . urlencode($_GET['user']);
  }
  $html .= "'>\n";
  $html .= "<table cellpadding='8'>
		<tr><td class='infocell'>" . _("Name") .
    ":</td>
			<td><input class='input' maxlength='256' id='Name' name='Name' value='" . utf8entities($userinfo['name']) .
    "'/></td></tr>
		<tr><td class='infocell'>" . _("Username") .
    ":</td>
			<td><input class='input' maxlength='20' id='UserName' name='UserName' value='" . utf8entities($userinfo['userid']) .
    "'/></td></tr>
		<tr><td class='infocell'>" . _("Primary email") . ":</td>
			<td>" . mailto_link($userinfo['email'], $userinfo['name'], $userinfo['email']) .
    "&nbsp;
			<a href='?view=user/addextraemail&amp;user=" . utf8entities($userid) . "'>" . _("Add extra address") .
    "</a></td></tr>\n";
  $extraEmails = UserExtraEmails($userid);
  if ($extraEmails) {
    $html .= "<tr><td rowspan='" . count($extraEmails) . "' class='infocell'>" . _("Extra emails") . ":</td>\n";
    $first = true;
    foreach ($extraEmails as $extraEmail) {
      if ($first) {
        $first = false;
      } else {
        $html .= "<tr>\n";
      }
      $html .= "<td><a href='mailto:" . utf8entities($extraEmail) . "'>" . utf8entities($extraEmail) . "</a>" .
        getDeleteButton('remextraemail', $extraEmail, 'deleteExtraEmail') .
        "<input class='button' type='submit' name='toprimaryemail' value='" . utf8entities(_("Set as primary")) .
        "' onclick='setId1(\"toPrimaryEmailVal\", \"" . utf8entities($extraEmail) . "\");'/>" . "</td></tr>\n";
    }
  }
  if (IsFacebookEnabled() && $_SESSION['uid'] == $userid) {
    global $serverConf;
    $fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);
    $fb_props = getFacebookUserProperties($userid);
    if (!$fb_cookie) {
      // Login button
      $html .= "<tr><td class='infocell'>" . _("Login via Facebook") .
        ":</td>
			<td><fb:login-button perms='email,publish_stream,offline_access'/></td></tr>\n";
    } elseif ($fb_cookie && !isset($fb_props['facebookuid'])) {
      if (ExistingFBUserId($fb_cookie['uid'])) {
        // Offer to change facebook linkage
        $html .= "<tr><td class='infocell'>" . _("Login via Facebook") .
          ":</td>
				<td><a href='?view=user/userinfo&amp;linkfacebook=true'>" .
          _("Change link from this account to my current Facebook account") . "</a></td></tr>\n";
      } else {
        // Offer to link account
        $html .= "<tr><td class='infocell'>" . _("Login via Facebook") .
          ":</td>
				<td><a href='?view=user/userinfo&amp;linkfacebook=true'>" . _("Link this account to my Facebook account") .
          "</a></td></tr>\n";
      }
    } elseif ($fb_cookie['uid'] == $fb_props['facebookuid']) {
      // Offer to unlink account
      $html .= "<tr><td class='infocell'>" . _("Login via Facebook") .
        ":</td>
			<td><a href='?view=user/userinfo&amp;unlinkfacebook=true'>" .
        _("Remove link from this account to my Facebook account") . "</a></td></tr>\n";
    } else {
      // Offer to change facebook linkage
      $html .= "<tr><td class='infocell'>" . _("Login via Facebook") .
        ":</td>
			<td><a href='?view=user/userinfo&amp;linkfacebook=true'>" .
        _("Change link from this account to my current Facebook account") . "</a></td></tr>\n";
    }
  }

  $html .= "		<tr><td class='infocell'>" . _("Language") . ":</td>
			<td><select class='dropdown' name='userlocale'>";
  global $locales;

  $userlocale = getUserLocale($userinfo['userid']);

  foreach ($locales as $localestr => $localename) {
    $html .= "<option value='" . utf8entities($localestr) . "'";
    if ($localestr == $userlocale) {
      $html .= " selected='selected'";
    }
    $html .= ">" . utf8entities($localename) . "</option>\n";
  }

  $html .= "</select></td></tr>";

  $html .= "<tr><td colspan = '2'><br/>
		  <input type='hidden' id='deleteExtraEmail' name='deleteExtraEmail'/>
		  <input type='hidden' id='toPrimaryEmailVal' name='toPrimaryEmailVal'/>
		  <input class='button' type='submit' name='save' value='" . _("Save") .
    "' />
	      <input class='button' type='submit' name='cancel' value='" . _("Cancel") . "' />
	      </td></tr>\n";

  $html .= "</table>\n";

  $html .= "</form>";

  $html .= "<hr />\n";

  $html .= "<h2>" . _("Show administration menus") . "</h2>\n";
  $html .= "<form method='post' action='?view=user/userinfo";
  if (!empty($_GET['user'])) {
    $html .= "&amp;user=" . urlencode($_GET['user']);
  }
  $html .= "'>\n";
  $editseasons = getEditSeasons($userid);
  $html .= "<div class='addremove'><div class='arleft'><select multiple='multiple' name='remeditseasonslist[]' id='remeditseasonslist'>\n";
  foreach ($editseasons as $season => $id) {
    $html .= "<option value='" . utf8entities($id) . "'>" . utf8entities(SeasonName($season)) . "</option>";
  }
  $html .= "</select><p>" . _("These tournaments are visible in your menu.") . "</p></div>\n";
  $html .= "<div class='arbuttons'><input class='button' type='submit' name='remeditseasons' value='" . _("Hide") .
    " &raquo;' /><br />
	      <input class='button' type='submit' name='addeditseasons' value='&laquo; " . _("Show") . "' /></div>\n";

  $html .= "<div class='arright'><select multiple='multiple' name='addeditseasonslist[]' id='addeditseasonslist'>\n";
  $seasons = Seasons();
  while ($season = mysqli_fetch_assoc($seasons)) {
    if (empty($editseasons[$season['season_id']])) {
      $html .= "<option value='" . utf8entities($season['season_id']) . "'>" . utf8entities($season['name']) . "</option>";
    }
  }
  $html .= "</select><p>" . _("These tournaments are hidden.") . "</p></div></div>\n";

  $html .= "</form><hr />\n";

  $html .= "<h2>" . _("Show pools") . "</h2>\n";
  $poolselectors = getPoolselectors($userid);
  if (!empty($poolselectors)) {
    $html .= "<form method='post' action='?view=user/userinfo";
    if (!empty($_GET['user'])) {
      $html .= "&amp;user=" . urlencode($_GET['user']);
    }
    $html .= "'>\n<table cellpadding='2'>\n";
    foreach ($poolselectors as $selector => $param) {
      if ($selector == 'currentseason') {
        $html .= "<tr><td>" . _("Current event");
        $html .= "</td><td>" . getDeleteButton('rempoolselector', $param, 'deleteSelectorId') . "</td></tr>\n";
      } else {
        foreach ($param as $subject => $propertyId) {
          $html .= "<tr><td>";
          if ($selector == 'team') {
            $html .= _("Team pools");
            $html .= " (" . utf8entities(getTeamName($subject)) . ")";
          } elseif ($selector == 'season') {
            $html .= _("Event");
            $html .= " (" . utf8entities(SeasonName($subject)) . ")";
          } elseif ($selector == 'series') {
            $html .= _("Division");
            $html .= " (" . utf8entities(getSeriesName($subject)) . ")";
          } elseif ($selector == 'pool') {
            $html .= _("Pool");
            $html .= " (" . utf8entities(U_(PoolSeriesName($subject)) . ", " . U_(PoolName($subject))) . ")";
          }
          $html .= "</td><td>" . getDeleteButton('rempoolselector', $propertyId, 'deleteSelectorId') . "</td></tr>\n";
        }
      }
    }
    $html .= "<tr><td><input type='hidden' id='deleteSelectorId' name='deleteSelectorId'/></td><td></td></tr>";
    $html .= "</table></form>";
  }
}

$html .= "<form method='get' action='?view=user/select_poolselector";
if (!empty($_GET['user'])) {
  $html .= "&amp;user=" . urlencode($_GET['user']);
}
$html .= "'>";
$html .= "<p><select class='dropdown' name='selectortype'>\n";
$html .= "<option value='currentseason'>" . _("Current event") . "</option>\n";
$html .= "<option value='team'>" . _("Team pools") . "</option>\n";
$html .= "<option value='season'>" . _("Event") . "</option>\n";
$html .= "<option value='series'>" . _("Division") . "</option>\n";
$html .= "<option value='pool'>" . _("Pool") . "</option>\n";
$html .= "</select>\n";
$html .= "<input type='hidden' name='view' value='user/select_poolselector'/>\n";
if (!empty($_GET['user'])) {
  $html .= "<input type='hidden' name='user' value='" . urlencode($_GET['user']) . "'/>\n";
}
$html .= "<input class='button' type='submit' name='addpoolselector' value='" . _("Add") . "...' /></p>\n";
$html .= "</form>\n";

if (hasEditUsersRight() || $_SESSION['uid'] == $userid) {
  $html .= "<hr />\n";

  $html .= "<h2>" . _("User roles") . "</h2>\n";
  $userroles = getUserroles($userid);
  if (!empty($userroles)) {
    $html .= "<form method='post' action='?view=user/userinfo";
    if (!empty($_GET['user'])) {
      $html .= "&amp;user=" . urlencode($_GET['user']);
    }
    $html .= "'>\n<table>\n";
    foreach ($userroles as $role => $param) {
      if ($role == 'superadmin') {
        $html .= "<tr><td>";
        $html .= _("Administrator");
        $html .= "</td><td>" . getDeleteButton('remuserrole', $param, 'deleteRoleId') . "</td></tr>\n";
      } elseif ($role == 'translationadmin') {
        $html .= "<tr><td>";
        $html .= _("Translation administrator");
        $html .= "</td><td>" . getDeleteButton('remuserrole', $param, 'deleteRoleId') . "</td></tr>\n";
      } elseif ($role == 'useradmin') {
        $html .= "<tr><td>";
        $html .= _("User administrator");
        $html .= "</td><td>" . getDeleteButton('remuserrole', $param, 'deleteRoleId') . "</td></tr>\n";
      } elseif ($role == 'teamadmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Team contact person");
          $html .= " (" . utf8entities(getTeamName($akey)) . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'seasonadmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Event responsible");
          $html .= " (" . utf8entities(SeasonName($akey)) . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'seriesadmin' || $role == 'series') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Division organizer");
          $html .= " (" . utf8entities(getSeriesName($akey)) . ", ". SeasonName(SeriesSeasonId($akey)) . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'accradmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Accreditation official");
          $html .= " (" . utf8entities(getTeamName($akey)) . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'resadmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Scheduling right");
          $reservationInfo = ReservationInfo($akey);
          $resName = ReservationName($reservationInfo);
          $html .= " (" . $resName . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'resgameadmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Reservation game input responsible");
          $reservationInfo = ReservationInfo($akey);
          $resName = ReservationName($reservationInfo);
          $html .= " (" . $resName . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'gameadmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Game input responsibility");
          $gameInfo = GameInfo($akey);
          $gameName = GameName($gameInfo);
          $html .= " (" . utf8entities($gameName) . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
        }
      } elseif ($role == 'playeradmin') {
        foreach ($param as $akey => $prop_id) {
          $html .= "<tr><td>";
          $html .= _("Player profile administrator");
          $playerInfo = PlayerProfile($akey);
          $html .= " (" . utf8entities($playerInfo['firstname'] . " " . $playerInfo['lastname']) . ")";
          $html .= "</td><td>" . getDeleteButton('remuserrole', $prop_id, 'deleteRoleId') . "</td></tr>\n";
          if (IsFacebookEnabled() && $_SESSION['uid'] == $userid) {
            if (FBLoggedIn($fb_cookie, $fb_props)) {
              if (isset($fb_props['facebookplayer'][$akey])) {
                $html .= "<tr><td>&raquo; " . _("Do not publish the game events of this player on my Facebook feed");
                $html .= "</td><td><input class='button' type='submit' name='unlinkfbplayer' value='" . _("Unpublish") .
                  "' onclick='setId1(\"fbPlayerId\", " . $akey . ");'/><br/>\n";
                $html .= "<a href='?view=user/facebookpublishing&amp;player=" . $akey . "'>" . _("Options") .
                  "...</a></td></tr>\n";
              } else {
                $html .= "<tr><td>&raquo; " . _("Publish the game events of this player on my Facebook feed");
                $html .= "</td><td><input class='button' type='submit' name='linkfbplayer' value='" . _("Publish") .
                  "' onclick='setId1(\"fbPlayerId\", " . $akey . ");'/></td></tr>\n";
              }
            }
          }
        }
      }
    }
    $html .= "</table>\n";
    $html .= "<div><input type='hidden' id='deleteRoleId' name='deleteRoleId'/>";
    $html .= "<input type='hidden' id='fbPlayerId' name='fbPlayerId'/></div>";
    $html .= "</form>\n";
  }
}

if (hasEditUsersRight()) {
  $html .= "<form method='get' action='?view=admin/select_userrole";
  if (!empty($_GET['user'])) {
    $html .= "&amp;user=" . urlencode($_GET['user']);
  }
  $html .= "'>";
  $html .= "<p>\n";
  $html .= "<select class='dropdown' name='userrole'>\n";
  $html .= "<option value='superadmin'>" . _("Administrator") . "</option>\n";
  $html .= "<option value='translationadmin'>" . _("Translation administrator") . "</option>\n";
  $html .= "<option value='useradmin'>" . _("User administrator") . "</option>\n";
  $html .= "<option value='teamadmin'>" . _("Team contact person") . "</option>\n";
  $html .= "<option value='seasonadmin'>" . _("Event responsible") . "</option>\n";
  $html .= "<option value='seriesadmin'>" . _("Division organizer") . "</option>\n";
  $html .= "<option value='accradmin'>" . _("Accreditation official") . "</option>\n";
  $html .= "<option value='resadmin'>" . _("Scheduling right") . "</option>\n";
  $html .= "<option value='resgameadmin'>" . _("Reservation game input responsible") . "</option>\n";
  $html .= "<option value='gameadmin'>" . _("Game input responsibility") . "</option>\n";
  $html .= "<option value='playeradmin'>" . _("Player profile administrator") . "</option>\n";
  $html .= "</select>\n";
  $html .= "<input type='hidden' name='view' value='admin/select_userrole'/>\n";
  if (!empty($_GET['user'])) {
    $html .= "<input type='hidden' name='user' value='" . urlencode($_GET['user']) . "'/>\n";
  }
  $html .= "<input class='button' type='submit' name='addpoolselector' value='" . _("Add") . "...' />\n";
  $html .= "</p>\n";
  $html .= "</form>\n";
}
$html .= "<hr/>\n";
$html .= "<form method='post' action='?view=user/userinfo";
if (!empty($_GET['user'])) {
  $html .= "&amp;user=" . urlencode($_GET['user']);
}
$html .= "'>\n";
$html .= "<table cellpadding='8'>";
$html .= "<tr><td class='infocell'>" . _("New  password") . ":</td>";
$html .= "<td><input class='input' type='password' maxlength='20' id='Password1' name='Password1' /></td></tr>";
$html .= "<tr><td class='infocell'>" . _("Repeat password") . ":</td>";
$html .= "<td><input class='input' type='password' maxlength='20' id='Password2' name='Password2' /></td></tr>";
$html .= "<tr><td colspan = '2' align='right'>";
$html .= "<input class='button' type='submit' name='changepsw' value='" . _("Change Password") . "' />";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>\n";

showPage($title, $html);
?>
