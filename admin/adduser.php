<?php
include_once $include_prefix . 'lib/common.functions.php';

$seasonId = $_GET['season'];
if (!$seasonId)
  $seasonId = CurrentSeason();

if ((!$seasonId || !isSeasonAdmin($seasonId)) && !isSuperAdmin()) {
  die("Insufficient user rights");
}

$html = "";
$mailsent = false;
if (!empty($_POST['save'])) {
  $newUsername = $_POST['UserName'];
  $newPassword = $_POST['Password'];
  $newName = $_POST['Name'];
  $newEmail = $_POST['Email'];
  
  $message = AddUser($newUsername, $newPassword, $newName, $newEmail, $_SESSION['uid']);
  if (empty($message)) {
    $html .= "<p>" . _("Added new user") . "<br/>\n";
    $html .= _("Username") . ": " . $newUsername . "<br/>\n";
    $html .= _("Password") . ": " . $newPassword . "<br/>\n";
    if (UserRecoverPasswordRequest($newUsername)) {
      $html .= "<p>" . _("User has been notified to change password.") . "</p>\n";
    } else {
      $html .= "<p class='warning'>" . _("Could not send password recovery mail.") . "</p>\n";
    }
    AddEditSeason($newUsername, $seasonId);
    if (!empty($_POST['team'])) {
      $teamId = $_POST['team'];
      if (isSuperAdmin() || $seasonId == TeamSeason($_POST['team'])) {
        AddSeasonUserRole($newUsername, "teamadmin:" . $_POST['team'], $seasonId);
      }
    }
  } else {
    $html .= $message;
    $html .= "<p>" . _("Correct the errors and try again") . ".</p>\n";
  }
}

$title = _("Add new user");
// common page
addHeaderScript('script/disable_enter.js.inc');

$html .= "<form method='post' action='?view=admin/adduser";
$html .= "'>\n";
$html .= "<table class='formtable'>
		<tr><td class='infocell'>" . _("Name") . ":</td>
			<td><input type='text' class='input' maxlength='256' id='Name' name='Name' value='";
if (isset($_POST['Name']))
  $html .= $_POST['Name'];
$html .= "'/></td></tr>
		<tr><td class='infocell'>" . _("Username") . ":</td>
			<td><input type='text' class='input' maxlength='50' id='UserName' name='UserName' value='";
if (isset($_POST['UserName']))
  $html .= $_POST['UserName'];
$html .= "'/></td></tr>
		<tr><td class='infocell'>" . _("Password") . ":</td>
			<td><input type='text' class='input' maxlength='20' id='Password' name='Password' value='";
if (isset($_POST['Password']))
  $html .= $_POST['Password'];
else
  $html .= UserCreateRandomPassword();
$html .= "'/></td></tr>
		<tr><td class='infocell'>" . _("Email") . ":</td>
			<td><input type='text' class='input' maxlength='100' id='Email' name='Email' size='40' value='";
if (isset($_POST['Email']))
  $html .= $_POST['Email'];
$html .= "'/></td></tr>";

$html .= "<tr><td class='infocell'>" . _("Responsible team") . ":</td>";
$teams = SeasonTeams(CurrentSeason());
$html .= "<td><select class='dropdown' name='team'>";
if (isset($_POST['team']))
  $html .= "<option class='dropdown' value='0'></option>";
else
  $html .= "<option class='dropdown' selected='selected' value='0'></option>";

foreach ($teams as $team) {
  if (isset($_POST['team']) && $team['team_id'] == $_POST['team'])
    $html .= "<option class='dropdown' selected='selected' value='" . utf8entities($team['team_id']) . "'>" .
       utf8entities(U_($team['seriesname'])) . " " . utf8entities($team['name']) . "</option>";
  else
    $html .= "<option class='dropdown' value='" . utf8entities($team['team_id']) . "'>" .
       utf8entities(U_($team['seriesname'])) . " " . utf8entities($team['name']) . "</option>";
}

$html .= "</select></td></tr>";

$html .= "<tr><td colspan = '2'><br/>
	      <input class='button' type='submit' name='save' value='" . _("Add") . "' />
	      </td></tr>\n";

$html .= "</table>\n";
$html .= "</form>";

showPage($title, $html);
?>
