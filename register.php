<?php
include_once $include_prefix . 'lib/common.functions.php';

$html = "";
$message = "";
$title = _("Register");
$html .= file_get_contents('script/disable_enter.js.inc');

$mailsent = false;
if (!empty($_POST['save'])) {
  $newUsername = trim($_POST['UserName']);
  $newPassword = $_POST['Password'];
  $newName = trim($_POST['Name']);
  $newEmail = trim($_POST['Email']);
  $error = 0;
  if (empty($_POST['privacy'])) {
    $message .= "<p class='warning'>" . _("You must accept the privacy policy by checking the appropriate box below.") .
      "<p>\n";
    $error = 1;
  } else {
    $message = UserValid($newUsername, $newPassword, $_POST['Password2'], $newName, $newEmail, true);
    if (!empty($message)) {
      $error = 1;
    } else if (AddRegisterRequest($newUsername, $newPassword, $newName, $newEmail)) {
      $message .= "<p>" .
        _(
          "The confirmation e-mail has been sent to the e-mail address provided. You have to follow the link in the e-mail to finalize registration, before you can use the account.") .
        "</p>\n";
      $mailsent = true;
    } else {
      $message .= "<p class='warning'>" .
        _(
          "The confirmation e-mail could not be sent. There may be a problem with your e-mail address or this might be a temporary error. In the latter case you can try again later.") .
        "<p>\n";
      $error = 2;
    }
  }

  if ($error > 0) {
    $message .= "<p>" . _("Please correct the errors and try again") . ".</p>\n";
  }
}

$confirmed = false;
if (!empty($_GET['token'])) {
  $userid = RegisterUIDByToken($_GET['token']);
  if (ConfirmRegister($_GET['token'])) {
    SetUserSessionData($userid);
    AddEditSeason($userid, CurrentSeason());
    $message = "<p>" . _("Your registration was confirmed successfully.") . "</p>\n";
    $confirmed = true;
  } else {
    $message = "<p class='warning'>" . _("Confirming your registration failed.") . "</p>\n";
  }
}

// help
$help = "<p>" .
  _(
    "Registration is only needed for event organizers, team contact persons, and players needing to create or change data in system.") .
  " ";
$help .= _("Registration process:") . "</p>
	<ol>
		<li> " . _("Fill your registration information in fields below.") . "</li>
		<li> " .
  _(
    "A confirmation e-mail will be sent immediately to the e-mail address provided. (Note that confirmation e-mail can be incorrectly filterd as spam by your e-mail client and in this case you can find it in the spam folder instead of your inbox.)") .
  "</li>
		<li> " . _("Follow the link in the e-mail to confirm your registration.") . "</li>
	</ol>";

$help .= "<hr/>";

// content

if (empty($message)) {
  $html .= $help;
} else {
  $html .= $message;
}

if (!$confirmed && !$mailsent) {
  $html .= "<form method='post' action='?view=register";
  $html .= "'>\n";
  $html .= "<table class='formtable'>
		<tr><td class='infocell'><label for='Name'>" . _("Name") .
    "</label>:</td>
			<td><input type='text' class='input' maxlength='256' id='Name' name='Name' value='";
  if (isset($_POST['Name']))
    $html .= utf8entities($_POST['Name']);
  $html .= "'/></td></tr>
		<tr><td class='infocell'><label for='UserName'>" . _("Username") .
    "</label>:</td>
			<td><input type='text' class='input' maxlength='20' id='UserName' name='UserName' value='";
  if (isset($_POST['UserName']))
    $html .= utf8entities($_POST['UserName']);
  $html .= "'/></td></tr>
		<tr><td class='infocell'><label for='Password'>" . _("Password") .
    "</label>:</td>
			<td><input type='password' class='input' maxlength='20' id='Password' name='Password' value='";
  if (isset($_POST['Password']))
    $html .= utf8entities($_POST['Password']);
  $html .= "'/></td></tr>
		<tr><td class='infocell'><label for='Password2'>" . _("Repeat password") .
    "</label>:</td>
			<td><input type='password' class='input' maxlength='20' id='Password2' name='Password2' value='";
  if (isset($_POST['Password']))
    $html .= utf8entities($_POST['Password']);
  $html .= "'/></td></tr>
		<tr><td class='infocell'><label for='Email'>" . _("E-mail") .
    "</label>:</td>
			<td><input type='text' class='input' maxlength='100' id='Email' name='Email' size='30' value='";
  if (isset($_POST['Email']))
    $html .= utf8entities($_POST['Email']);
  $html .= "'/></td>\n";

  $html .= "<tr><td colspan='2'>" . utf8entities(_("You can read about our privacy policy by following this link:")) .
    " <a target='_blank' href='?view=privacy'>" . utf8entities(_("Privacy Policy")) . "</a></td></tr>\n";
  $html .= "<tr><td><input type='checkbox' id='privacy' name='privacy' /></td>";
  $html .= "<td class='infocell'><label for='confirm_privacy'>" .
    utf8entities(
      _(
        "I accept the privacy policy. I agree that this site stores my personal data (including name and e-mail given above) as declared in the privacy policy.")) .
    "</label></td></tr>\n";

  $html .= "";

  $html .= "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='save' value='" . _("Register") . "' />
	      </td></tr>\n";

  $html .= "</table>\n";
  $html .= "</form>";
}

showPage($title, $html);

?>
