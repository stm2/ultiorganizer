<?php
if (IsRegistered($_SESSION['uid'])) {
  header("location:?view=frontpage");
}

$title = _("Recover password");
$userId = "";
if (isset($_POST['user']) && $_POST['user'])
  $userId = $_POST['user'];
if (!$userId && isset($_GET['user']) && $_GET['user']) 
  $userId = $_GET['user'];

$html = "";

function getResetForm($userId, $token) {
  $html .= "<p>" . _("Please enter your new password below.") . "</p>\n";
  $html .= "<form method='post' action='?view=login_failed&amp;user=" . utf8entities($userId) . "'>\n";
  $html .= "<table class='formtable'>
		<tr><td class='infocell'>" . _("Username") . ":</td>
			<td>" . $userId . "</td></tr>
		<tr><td class='infocell'>" . _("Password") . ":</td>
			<td><input type='password' class='input' size='40' maxlength='20' id='Password' name='Password' value=''/></td></tr>
		<tr><td class='infocell'>" . _("Repeat password") . ":</td>
			<td><input type='password' class='input' size='40' maxlength='20' id='Password2' name='Password2' value=''/></td></tr>";
  
  $html .= "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='changepw' value='" .
     _("Change password") . "' />
	      <input type='hidden' id='token' name='token' value='" . utf8entities($token) . "'/>
	      </td></tr>\n";
  $html .= "</table>\n";
  $html .= "</form>";
  return $html;
}

if (isset($_POST['recoverpassword'])) {
  // 2. send recover mail
  if (!$userId) {
    if (isset($_POST['email']) && $_POST['email']) {
      $userId = UserIdForMail($_POST['email']);
      $html .= "<p>" . sprintf(_("If the address '%s' is registered, an email with further instructions was sent."),
        utf8entities($_POST['email'])) . "</p>\n";
    } else {
      $warning = "<p class='warning'>" . _("You must enter either a username or an registered email address.") . "</p>\n";
    }
  }
  if ($userId) {
    $ret = UserRecoverPasswordRequest($userId);
  }
  if ($userId && empty($html)) {
    $html .= sprintf(_("If '%s' is a registered user, an email will be sent to the corresponding address."),
      utf8entities($userId));
  }
}

if (!empty($_GET['token']) && !empty($userId)) {
  // 3. prompt for new password
  $token = $_GET['token'];
  if (UserCheckRecoverToken($userId, $token)) {
    $html .= getResetForm($userId, $token);
  } else {
    $html .= "<p class='warning'>" . _("Invalid or expired token.") .
      " <a class='topheaderlink' href='?view=login_failed&user=$userId'>" .
      _("Click this link to try again</a> or, if this problem persists, contact an administrator.") . "</p>";
  }
}

if (!empty($_POST['changepw']) && !empty($userId)) {
  // 4. validate new password
  $token = $_POST['token'];
  if (UserCheckRecoverToken($userId, $token)) {
    $newPassword1 = $_POST['Password'];
    $newPassword2 = $_POST['Password2'];
    $pw = UserValidPassword($newPassword1, $newPassword2);
    if (empty($pw)) {
      UserChangePassword($userId, $newPassword1, $token);
      $html .= "<p>" . _("Your password has been changed. Please login with your new password.") . "</p>";
    } else {
      $html .= $pw;
      $html .= getResetForm($userId, $token);
    }
  } else {
    $html .= "<p>".
      _(
        "Invalid or expired token.
         Please <a href='login_failed&user=$userId'>try again</a> or, if this problem persists, contact an administrator.") .
      "</p>";   
  }
}

if (empty($html)) {
  // 1. recover request
  $html .= "<form method='post' action='?view=login_failed&amp;user=" . urlencode($userId) . "'>\n";
  if (empty($warning)) {
    $html .= "<p class='warning'>" . _("Username/password did not match. Try again or request to recover a lost password.") . "</p>\n";
  } else {
    $html .= $warning;
  }
  $html .= "<p>" .
     _(
      "If you have forgotten your password, enter <em>either</em> your username <em>or</em> your email below. If we have your email address, you will receive an email with further instructions.") .
     "</p>\n";
  $html .= "<table class='formtable'><tr><td class='infocell'>" . _("Username") . ":</td>";
  $html .= "<td><input type='text' class='input' maxlength='20' size='40' name='user' value='" . utf8entities($userId) .
     "'/></td></tr>\n";
  $html .= "<tr><td class='infocell'>" . _("Email") . ":</td>";
  $html .= "<td><input type='text' class='input' maxlength='100' size='40' name='email' size='40' /></td></tr></table>\n";
  $html .= "<p><input class='button' type='submit' name='recoverpassword' value='" . _("Recover password") . "'/></p>\n";
  $html .= "</form>\n";
}
showPage($title, $html);
?>