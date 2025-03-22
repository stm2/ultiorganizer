<?php
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/url.functions.php';
include_once $include_prefix . 'lib/yui.functions.php';

$title = _("Event users");
$html = "";
$seasonId = $_GET["season"];
$seaLink = urlencode($seasonId);

ensureSeasonAdmin($seasonId, $title);

$html .= yuiLoad(array("utilities", "datasource", "autocomplete"));

if (!empty($_POST['add'])) {
  switch ($_GET['access']) {
    case 'eventadmin':
      $userids = $_POST['seasonusers'];
      break;
    case 'seriesadmin':
      $userids = $_POST['seriesusers'];
      break;
    case 'teamadmin':
      $userids = $_POST['teamusers'];
      break;
    case 'resgameadmin':
      $userids = $_POST['gameusers'];
      break;
    case 'gameadmin':
      $userids = $_POST['gameusers'];
      break;
    case 'accradmin':
      $userids = $_POST['accrusers'];
      break;
    default:
      $userids = isset($_POST['userids']) ? $_POST['userids'] : '';
  }
  if (empty($userids)) {
    $userids = array();
    $mails = $_POST['emails'];
    foreach (preg_split('/\s*[,;]\s*/', $mails) as $email) {
      $id = UserIdForMail($email);
      if (empty($id) || $id < 0)
        $userids[] = array('type' => 'invalid', 'value' => $email);
      else
        $userids[] = array('type' => 'email', 'value' => $id);
    }
  } else {
    $useridss = $userids;
    $userids = array();
    foreach (preg_split('/\s*[,;]\s*/', $useridss) as $userid)
      $userids[] = array('type' => 'id', 'value' => $userid);
  }

  foreach ($userids as $userid) {
    if ($userid['type'] != 'invalid' && IsRegistered($userid['value'])) {
      $userid = $userid['value'];
      if ($_GET["access"] == "eventadmin") {
        AddSeasonUserRole($userid, "seasonadmin:" . $seasonId, $seasonId);
      } elseif ($_GET["access"] == "seriesadmin") {
        if ($seasonId == SeriesSeasonId($_POST['series'])) // check series belongs to season to avoid privilege escalation
          AddSeasonUserRole($userid, "seriesadmin:" . $_POST["series"], $seasonId);
      } elseif ($_GET["access"] == "teamadmin") {
        if ($seasonId == TeamSeason($_POST['team']))
          AddSeasonUserRole($userid, "teamadmin:" . $_POST["team"], $seasonId);
      } elseif ($_GET['access'] == 'resgameadmin') {
        $reservations = $_POST['reservations'];
        foreach ($reservations as $res) {
          AddSeasonUserRole($userid, 'resgameadmin:' . $res, $seasonId);
        }
      } elseif ($_GET["access"] == "gameadmin") {
        $reservations = $_POST["reservations"];
        foreach ($reservations as $res) {
          $games = ReservationGames($res);
          while ($game = mysqli_fetch_assoc($games)) {
            if ($seasonId == GameSeason($game['game_id']))
              AddSeasonUserRole($userid, 'gameadmin:' . $game['game_id'], $seasonId);
          }
        }
      } elseif ($_GET["access"] == "accradmin") {
        $teams = $_POST["teams"];
        foreach ($teams as $teamId) {
          if ($seasonId == TeamSeason($teamId))
            AddSeasonUserRole($userid, 'accradmin:' . $teamId, $seasonId);
        }
      }
      $html .= "<p>" . sprintf(_("User rights added for %s."), utf8entities($userid)) . "</p>";
    } else {
      $html .= "<p class='warning'>" . _("Invalid user") . " " . utf8entities($userid['value']) . "</p>";
    }
  }
} elseif (!empty($_POST['remove_x'])) {
  if ($_GET["access"] == "eventadmin") {
    RemoveSeasonUserRole($_POST['delId'], "seasonadmin:" . $seasonId, $seasonId);
  } elseif ($_GET["access"] == "seriesadmin") {
    RemoveSeasonUserRole($_POST['delId'], "seriesadmin:" . $_POST['seriesId'], $seasonId);
  } elseif ($_GET["access"] == "teamadmin") {
    RemoveSeasonUserRole($_POST['delId'], "teamadmin:" . $_POST['teamId'], $seasonId);
  } elseif ($_GET["access"] == "accradmin") {
    RemoveSeasonUserRole($_POST['delId'], "accradmin:" . $_POST['teamId'], $seasonId);
  }
  $_GET["access"] = "";
}

//common page

addHeaderScript('script/disable_enter.js.inc');

function adminHeader($title, $formId) {
  global $seasonId;
  $html = "<h3>{$title}:</h3>\n";
  $seaLink = urlencode($seasonId);
  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink&amp;access=$formId' name='$formId'>\n";
  return $html;
}

function userLink($userid) {
  return "<a href='?view=user/userinfo&amp;user=" . urlencode($userid) . "'>"
    . utf8entities($userid) . "</a>";
}

function  adminTable($admins, $formId, $groupTag, $delIds, $inputId, $adminGroups = null, $dropdownId = null, $groupValueTag = null, $nameTag = null, $selectedId = null) {
  global $seasonId;
  $seaLink = urlencode($seasonId);
  $html = "<table class='admintable'>";
  $heading = null;
  foreach ($admins as $user) {
    $userlink = userLink($user['userid']);
    $html .= "<tr>";
    if (! empty($groupTag)) {
      $heading = U_($user[$groupTag]);
      $html .= "<td style='width:175px'>" . utf8entities($heading) . "</td>\n";
    }
    $html .= "<td style='width:75px'>" . $userlink . "</td><td>" . utf8entities($user['name']) . " ("
      . mailto_link($user['email'], $user['name'], $user['email'], $heading) . ")</td>";
    $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' ";
    if (! empty($delIds)) {
      $html .= "onclick=\"";
      foreach ($delIds as $id => $tag) {
        $html .= "document.$formId.$id.value='" . utf8entities($user[$tag]) . "';";
      }
      $html .= "\"/></td>";
    }
    $html .= "</tr>\n";
  }
  $html .= "</table>\n";
  // TODO refactor function grantTable($accesstype, $selections = array(val => name)), $class, $multiple)
  if (! empty($_GET["access"]) && $_GET["access"] == $formId) {
    $html .= "<table class='formtable'>\n";
    $html .= "<tr>";

    if (count($adminGroups) == 1) {
      $html .= "<td><input type='hidden' name='$dropdownId' value='" . utf8entities($adminGroups[0][$groupValueTag]) . "'/>" . utf8entities(U_($adminGroups[0][$nameTag])) . "</td>\n";
      $html .= "<td>" . _("User Id(s)") . "</td><td>";
      $html .= UserInput($inputId, '');
      $html .= "</td><td>&nbsp;" . _("or") . "&nbsp;</td>\n";
      $html .= "<td>" . _("E-Mail(s)") . "</td><td><input class='input' size='20' name='emails'/></td>\n";
      $html .= "<td>" . _("or") . " <a href='?view=admin/adduser&amp;season=" . $seaLink . "' target='_blank'>" .
        _("Add new user") . "</a></td></tr>\n";
      $html .= "</table>\n";
      $html .= "<p><input class='button' name='add' type='submit' value='" . _("Grant rights") . "'/></p>\n";
    } else if (count($adminGroups) > 1) {
      $html .= "<td colspan='4'><select class='dropdown' name='$dropdownId'>\n";
      foreach ($adminGroups as $group) {
        $selected = '';
        if (!empty($selectedId) && $group[$groupValueTag] == $selectedId)
          $selected = " selected='selected'";
        $html .= "<option class='dropdown' value='" . utf8entities($group[$groupValueTag]) . "'" . $selected . ">" . utf8entities(U_($group[$nameTag])) . "</option>\n";
      }
      $html .= "</select>\n";
      $html .= "</td></tr><tr><td>" . _("User Id(s)") . "</td><td>";
      $html .= UserInput($inputId, '');
      $html .= "</td><td>&nbsp;" . _("or") . "&nbsp;</td>\n";
      $html .= "<td>" . _("E-Mail(s)") . "</td><td><input class='input' size='20' name='emails'/></td>\n";
      $html .= "<td>" . _("or") . " <a href='?view=admin/adduser&amp;season=" . $seaLink . "' target='_blank'>" .
        _("Add new user") . "</a></td></tr>\n";
      $html .= "</table>\n";
      $html .= "<p><input class='button' name='add' type='submit' value='" . _("Grant rights") . "'/></p>\n";
    } else {
      $html .= "</tr></table>\n";
    }
  } else {
    $html .= "<p><a href='?view=admin/addseasonusers&amp;season=" . $seaLink . "&amp;access=$formId'>" . _("Add more ...") . "</a></p>\n";
  }
  //
  foreach ($delIds as $id => $field) {
    $html .= "<div><input type='hidden' name='$id'/></div>\n";
  }
  $html .= "</form>\n";

  if (! empty($_GET["access"]) && $_GET["access"] == $formId) {
    $html .= UserScript($inputId);
  }

  return $html;
}

function emptyAdmins($message) {
  return "<p>" . utf8entities($message) . "</p>\n</form>\n";
}

$seasonName = SeasonName($seasonId);
if (!empty($_POST['confirm_remove_all_season'])) {
  $admins = SeasonAdmins($seasonId);
  foreach ($admins as $admin) {
    RemoveSeasonUserRole($admin['userid'], "seasonadmin:" . $seasonId, $seasonId);
    // $html .= "<p>" . sprintf(_("Event admin rights removed for %s."), utf8entities($admin['userid'])) . "</p>";
  }
  $html .= "<p>" . _("All season admins have been removed.") . "</p>";
} elseif (!empty($_POST['remove_all_season'])) {
  $admins = SeasonAdmins($seasonId);
  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
  $html .= "<p>" . sprintf(_("Are you sure you want to remove all %s season admins?"), count($admins)) . "</p>";
  $html .= "<p><input class='button' name='confirm_remove_all_season' type='submit' value='" . _("Yes") . "'/>";
  $html .= "<input class='button' name='cancel' type='submit' value='" . _("No") . "'/></p>";
  $html .= "</form>";
} else if (!empty($_POST['confirm_remove_all_series'])) {
  $seriesAdmins = SeasonSeriesAdmins($seasonId);
  $userRights = array();

  foreach ($seriesAdmins as $admin) {
    RemoveSeasonUserRole($admin['userid'], "seriesadmin:" . $admin['series_id'], $seasonId);
    // $html .= "<p>" . sprintf(_("Division admin rights series %s removed for %s."), $admin['name'], utf8entities($admin['userid'])) . "</p>";
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed division admin rights for %s on %d series."), utf8entities($userid), $count) . "</p>";
  }

  $html .= "<p>" . _("All division admins have been removed.") . "</p>";
} elseif (!empty($_POST['remove_all_series'])) {
  $seriesAdmins = SeasonSeriesAdmins($seasonId);
  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
  $html .= "<p>" . sprintf(_("Are you sure you want to remove all %s division admins?"), count($seriesAdmins)) . "</p>";
  $html .= "<p><input class='button' name='confirm_remove_all_series' type='submit' value='" . _("Yes") . "'/>";
  $html .= "<input class='button' name='cancel' type='submit' value='" . _("No") . "'/></p>";
  $html .= "</form>";
} else if (!empty($_POST['remove_all_teams'])) {
  $teamAdmins = SeasonTeamAdmins($seasonId, false);
  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
  $html .= "<p>" . sprintf(_("Are you sure you want to remove all %s team admins?"), count($teamAdmins)) . "</p>";
  $html .= "<p><input class='button' name='confirm_remove_all_teams' type='submit' value='" . _("Yes") . "'/>";
  $html .= "<input class='button' name='cancel' type='submit' value='" . _("No") . "'/></p>";
  $html .= "</form>";
} else if (!empty($_POST['confirm_remove_all_teams'])) {
  $teamAdmins = SeasonTeamAdmins($seasonId, false);
  $userRights = array();

  foreach ($teamAdmins as $admin) {
    RemoveSeasonUserRole($admin['userid'], "teamadmin:" . $admin['team_id'], $seasonId);
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed team admin rights for %s on %d teams."), utf8entities($userid), $count) . "</p>";
  }

  $html .= "<p>" . _("All team admins have been removed.") . "</p>";
} else if (!empty($_POST['remove_all_scorekeepers'])) {
  $scorekeepers =  count(SeasonReservationGameAdmins($seasonId, true)) + count(SeasonGameAdmins($seasonId, true));
  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
  $html .= "<p>" . sprintf(_("Are you sure you want to remove all %s scorekeepers?"), $scorekeepers) . "</p>";
  $html .= "<p><input class='button' name='confirm_remove_all_scorekeepers' type='submit' value='" . _("Yes") . "'/>";
  $html .= "<input class='button' name='cancel' type='submit' value='" . _("No") . "'/></p>";
  $html .= "</form>";
} else if (!empty($_POST['confirm_remove_all_scorekeepers'])) {
  $scorekeepers = SeasonReservationGameAdmins($seasonId, false);
  $userRights = array();

  foreach ($scorekeepers as $admin) {
    RemoveSeasonUserRole($admin['userid'], "resgameadmin:" . $admin['reservation_id'], $seasonId);
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed scorekeeper rights for user %s on %d reservations."), utf8entities($userid), $count) . "</p>";
  }
  $userRights = array();

  $scorekeepers = SeasonGameAdmins($seasonId, false);
  foreach ($scorekeepers as $admin) {
    RemoveSeasonUserRole($admin['userid'], "gameadmin:" . $admin['game_id'], $seasonId);
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed scorekeeper rights for %s on %d games."), utf8entities($userid), $count) . "</p>";
  }

  $html .= "<p>" . _("All scorekeepers have been removed.") . "</p>";
} else if (!empty($_POST['remove_all_accradmins'])) {
  $accradmins = SeasonAccreditationAdmins($seasonId, false);
  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
  $html .= "<p>" . sprintf(_("Are you sure you want to remove all %s accreditation admins?"), count($accradmins)) . "</p>";
  $html .= "<p><input class='button' name='confirm_remove_all_accradmins' type='submit' value='" . _("Yes") . "'/>";
  $html .= "<input class='button' name='cancel' type='submit' value='" . _("No") . "'/></p>";
  $html .= "</form>";
} else if (!empty($_POST['confirm_remove_all_accradmins'])) {
  $accradmins = SeasonAccreditationAdmins($seasonId, false);
  $userRights = array();

  foreach ($accradmins as $admin) {
    RemoveSeasonUserRole($admin['userid'], "accradmin:" . $admin['team_id'], $seasonId);
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed accreditation rights for %s on %d teams."), utf8entities($userid), $count) . "</p>";
  }

  $html .= "<p>" . _("All accreditation rights have been removed.") . "</p>";
} else if (!empty($_POST['remove_all_scorekeepers'])) {
  $scorekeepers = SeasonReservationGameAdmins($seasonId, false);
  $userRights = array();

  foreach ($scorekeepers as $admin) {
    RemoveSeasonUserRole($admin['userid'], "resgameadmin:" . $admin['reservation_id'], $seasonId);
    // $html .= "<p>" . sprintf(_("Scorekeeper rights for reservation %s removed for %s."), $admin['reservation_id'], utf8entities($admin['userid'])) . "</p>";
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed scorekeeper rights for user %s on %d reservations."), utf8entities($userid), $count) . "</p>";
  }
  $userRights = array();

  $scorekeepers = SeasonGameAdmins($seasonId, false);
  foreach ($scorekeepers as $admin) {
    RemoveSeasonUserRole($admin['userid'], "gameadmin:" . $admin['game_id'], $seasonId);
    // $html .= "<p>" . sprintf(_("Scorekeeper rights for game %s removed for %s."), $admin['game_id'], utf8entities($admin['userid'])) . "</p>";
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed scorekeeper rights for %s on %d games."), utf8entities($userid), $count) . "</p>";
  }

  $html .= "<p>" . _("All scorekeepers have been removed.") . "</p>";
} else if (!empty($_POST['remove_all_accradmins'])) {
  $accradmins = SeasonAccreditationAdmins($seasonId, false);
  $userRights = array();

  foreach ($accradmins as $admin) {
    RemoveSeasonUserRole($admin['userid'], "accradmin:" . $admin['team_id'], $seasonId);
    // $html .= "<p>" . sprintf(_("Accreditation rights for team %s removed for %s."), $admin['team_id'], utf8entities($admin['userid'])) . "</p>";
    if (!isset($userRights[$admin['userid']])) {
      $userRights[$admin['userid']] = 0;
    }
    $userRights[$admin['userid']]++;
  }

  foreach ($userRights as $userid => $count) {
    $html .= "<p>" . sprintf(_("Removed accreditation rights for %s on %d teams."), utf8entities($userid), $count) . "</p>";
  }

  $html .= "<p>" . _("All accreditation rights have been removed.") . "</p>";
}

$html .= adminHeader(_("Event admins"), 'eventadmin');
$admins = SeasonAdmins($seasonId);
$html .= adminTable($admins, 'eventadmin', null, array(
  'delId' => 'userid'
), 'seasonusers', [['name' => $seasonName, 'season_id' => $seasonId]], 'season', 'season_id', 'name');

$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
$html .= "<p><input class='button' name='remove_all_season' type='submit' value='" . _("Remove all season admins") . "'/></p>";
$html .= "</form>";


$html .= adminHeader(_("Division admins"), 'seriesadmin');
$series = SeasonSeries($seasonId);
$admins = SeasonSeriesAdmins($seasonId, false, 'series');
if (empty($series)) {
  $html .= emptyAdmins(_("No divisions defined."));
} else {
  $html .= adminTable($admins, 'seriesadmin', 'seriesname', array(
    'delId' => 'userid',
    'seriesId' => 'series_id'
  ), 'seriesusers', $series, 'series', 'series_id', 'name', empty($_POST['series']) ? null : $_POST['series']);

  $html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
  $html .= "<p><input class='button' name='remove_all_series' type='submit' value='" . _("Remove all division admins") . "'/></p>";
  $html .= "</form>";
}


$html .= adminHeader(_("Team admins"), 'teamadmin');
$admins = SeasonTeamAdmins($seasonId);
foreach ($admins as &$user) {
  $user['teamname'] = $user['seriesname'] . ", " . $user['teamname'];
}
unset($user);
$teams = SeasonTeams($seasonId);
if (empty($teams)) {
  $html .= emptyAdmins(_("No teams defined."));
} else {
  foreach ($teams as &$team) {
    $team['name'] = $team['seriesname'] . ", " . $team['name'];
  }
  unset($team);
  $html .= adminTable(
    $admins,
    'teamadmin',
    'teamname',
    array('delId' => 'userid', 'teamId' => 'team_id'),
    'teamusers',
    $teams,
    'team',
    'team_id',
    'name'
  );
}

$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
$html .= "<p><input class='button' name='remove_all_teams' type='submit' value='" . _("Remove all team admins") . "'/></p>";
$html .= "</form>";

$html .= "<h3>" . _("Scorekeepers") . ":</h3>\n";
$seasongames = SeasonAllGames($seasonId);
$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink&amp;access=resgameadmin' name='resgameadmin'>\n";
$html .= "<table class='formtable'>\n";
//all event admins have score keeping rights
$admins = SeasonAdmins($seasonId);
foreach ($admins as $user) {
  $html .= "<tr>";
  $html .= "<td style='width:75px'>" . userLink($user['userid']) . "</td><td>"
    . utf8entities($user['name']) . " (" . mailto_link($user['email'], $user['name'], $user['email'], $seasonName) . ")</td>";
  $html .= "<td>" . _("All games") . "</td>";
  $html .= "<td>" . _("In role of event admin") . "</td>";
  $html .= "</tr>\n";;
}

$admins = SeasonReservationGameAdmins($seasonId);

foreach ($admins as $user) {
  $html .= "<tr>";
  $html .= "<td style='width:75px'>" . userLink($user['userid']) . "</td><td>"
    . utf8entities($user['name']) . " (" . mailto_link($user['email'], $user['name'], $user['email'], $seasonName) . ")</td>";
  $html .= "<td colspan='2'>" . sprintf(_("%s reservations"), $user['reservations']) . "</td>";
  $html .= "</tr>\n";;
}

$admins = SeasonGameAdmins($seasonId);

foreach ($admins as $user) {
  $html .= "<tr>";
  $html .= "<td style='width:75px'>" . userLink($user['userid']) . "</td><td>"
    . utf8entities($user['name']) . " (" . mailto_link($user['email'], $user['name'], $user['email'], $seasonName) . ")</td>";
  if ($user['games'] == count($seasongames)) {
    $html .= "<td>" . sprintf(_("All %s games"), count($seasongames)) . "</td>";
  } else {
    $html .= "<td colspan='2'>" . sprintf(_("%s games"), $user['games']) . "</td>";
  }
  $html .= "</tr>\n";;
}

$teamresp = 0;
foreach ($seasongames as $game) {
  if (!empty($game['respteam'])) {
    $teamresp++;
  }
}

if ($teamresp) {
  $html .= "<tr>";
  $html .= "<td colspan='2'><i>" . _("All team admins have scorekeeping rights for teams' games.") . "</i></td>";
  $html .= "</tr>\n";;
}

$html .= "</table>";
if (!empty($_GET["access"]) && $_GET["access"] == "gameadmin") {
  $html .= "<table class='formtable'>\n";
  $html .= "<tr><td>" . _("User Id (s)") . "</td><td>";
  $html .= UserInput('gameusers', '');
  $html .= "</td><td>" . _("or") . "</td>\n";
  $html .= "<td>" . _("E-Mail(s)") . "</td><td><input class='input' size='20' name='emails'/></td>\n";
  $html .= "<td>" . _("or") . " <a href='?view=admin/adduser&amp;season=$seaLink' target='_blank'>" . _("Add new user") . "</a></td></tr>\n";

  $reservations = SeasonReservations($seasonId);
  $html .= "<tr><td colspan='5'><select class='series_select' multiple='multiple' size='" . count($reservations) . "' name='reservations[]'>";
  foreach ($reservations as $row) {
    $html .= "<option value='" . utf8entities($row['id']) . "'>";
    $html .= utf8entities($row['reservationgroup']) . " " . utf8entities($row['name']) . ", " . _("Field") . " " . utf8entities($row['fieldname']) . " (" . JustDate($row['starttime']) . ")";
    $html .= "</option>";
  }
  $html .= "</select></td></tr>";
  $html .= "</table>";

  $html .= UserScript('gameusers');

  $html .= "<p><input class='button' name='add' type='submit' value='" . _("Grant rights") . "'/></p>\n";
} else {
  $html .= "<p><a href='?view=admin/addseasonusers&amp;season=" . $seasonId . "&amp;access=gameadmin'>" . _("Add more ...") . "</a></p>";
}
$html .= "</form>";

$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
$html .= "<p><input class='button' name='remove_all_scorekeepers' type='submit' value='" . _("Remove all scorekeepers") . "'/></p>";
$html .= "</form>";

$html .= "<h3>" . _("Roster accreditation rights") . ":</h3>";
$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink&amp;access=accradmin' name='accradmin'>";
$html .= "<table  class='formtable'>";
//all event admins have score keeping rights
$admins = SeasonAdmins($seasonId);
foreach ($admins as $user) {
  $html .= "<tr>";
  $html .= "<td style='width:175px'>" . _("All teams") . "</td>";
  $html .= "<td style='width:75px'>" . userLink($user['userid']) . "</td><td>"
    . utf8entities($user['name']) . " (" . mailto_link($user['email'], $user['name'], $user['email'], $seasonName) . ")</td>";
  $html .= "<td>" . _("In role of event admin") . "</td>";
  $html .= "</tr>\n";;
}

$admins = SeasonAccreditationAdmins($seasonId);
foreach ($admins as $user) {
  $html .= "<tr>";
  $teaminfo = TeamInfo($user['team_id']);
  $html .= "<td style='width:175px'>" . utf8entities(U_($teaminfo['seriesname'])) . ", " . utf8entities(U_($teaminfo['name'])) . "</td>\n";
  $html .= "<td style='width:75px'>" . userLink($user['userid']) . "</td><td>"
    . utf8entities($user['name']) . " (" . mailto_link($user['email'], $user['name'], $user['email'], $seasonName) . ")</td>";
  $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"document.accradmin.delId.value='" . utf8entities($user['userid']) . "';document.accradmin.teamId.value='" . utf8entities($user['team_id']) . "';\"/></td>";
  $html .= "</tr>\n";;
}
$html .= "</table>";
if (!empty($_GET["access"]) && $_GET["access"] == "accradmin") {
  $html .= "<table class='formtable'>\n";
  $html .= "<tr><td>" . _("User Id(s)") . "</td><td>";
  $html .= UserInput('accrusers', '');
  $html .= "</td><td>" . _("or") . "</td>\n";
  $html .= "<td>" . _("E-Mail(s)") . "</td><td><input class='input' size='20' name='emails'/></td>\n";
  $html .= "<td>" . _("or") . " <a href='?view=admin/adduser&amp;season=" . $seasonId . "' target='_blank'>" . _("Add new user") . "</a></td></tr>\n";

  $teams = SeasonTeams($seasonId);
  $html .= "<tr><td colspan='5'><select class='series_select' multiple='multiple' size='" . count($teams) . "' name='teams[]'>";
  foreach ($teams as $team) {
    $html .= "<option value='" . utf8entities($team['team_id']) . "'>";
    $html .= utf8entities(U_($team['seriesname'])) . ", " . utf8entities(U_($team['name']));
    $html .= "</option>";
  }
  $html .= "</select></td></tr>";
  $html .= "</table>";

  $html .= UserScript('accrusers');

  $html .= "<p><input class='button' name='add' type='submit' value='" . _("Grant rights") . "'/></p>\n";
} else {
  $html .= "<p><a href='?view=admin/addseasonusers&amp;season=" . $seasonId . "&amp;access=accradmin'>" . _("Add more ...") . "</a></p>";
}

$html .= "<div><input type='hidden' name='delId'/></div>";
$html .= "<div><input type='hidden' name='teamId'/></div>";
$html .= "</form>";

$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=$seaLink'>";
$html .= "<p><input class='button' name='remove_all_accradmins' type='submit' value='" . _("Remove all accreditation rights") . "'/></p>";
$html .= "</form>";

showPage($title, $html);
