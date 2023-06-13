<?php
include_once $include_prefix . 'lib/user.functions.php';

$title = _("Account deletion");
$html = "";

ensureLogin();

$userid = $_GET['user'] ?? null;
if (empty($userid))
  die('Invalid user id');

if ($userid != $_SESSION['uid'] && !hasEditUsersRight()) {
  die('Insufficient rights to change user info');
}

if (isset($_POST['confirmdeletion'])) {
  if (isSuperAdminByUserid($userid) && count(getSuperAdmins()) < 5) {
    $html .= "<p class='alert'>" .
      utf8entities(
        _(
          "This is the only super admin account. It cannot be deleted. Plese create a separate super admin account first.")) .
      "</p>\n";
    showPage($title, $html);
    exit();
  } else {
    DeleteUser($userid);
    if ($userid == $_SESSION['uid']) {
      ClearUserSessionData();
      header("location:?view=logout");
    } else {
      $userid = utf8entities($userid);
      showPage($title, utf8entities(_("Account '$userid' has been deleted.")));
      exit();
    }
  }
}
if (isset($_POST['cancel'])) {
  header("location:?view=user/userinfo&user=" . utf8entities($userid));
}

if ($userid == "anonymous") {
  $html .= "<p>" . utf8entities(_("Cannot delete anonymous user")) . "</p>";
} else {

  $html .= "<form method='post' action='?view=user/confirmdeletion&amp;user=" . urlencode($userid) . "'>\n";
  if ($userid == $_SESSION['uid']) {
    $html .= "<p>" .
      utf8entities(
        _("Are you sure you want to delete your account? This step deletes all your data and cannot be undone!")) .
      "</p>";
  } else {
    $html .= "<p>" .
      utf8entities(_("Are you sure you want to delete the account '$userid'? This step cannot be undone!")) . "</p>";
  }

  if (isSuperAdminByUserid($userid)) {
    $html .= "<p>" . utf8entities(_("This is an admin account!")) . "</p>";
  }

  $html .= "<input class='button' type='submit' name='confirmdeletion' value='" . _("Yes, delete") . "' />";
  $html .= "<input class='button' type='submit' name='cancel' value='" . _("No, abort") . "' />";
  $html .= "</table>\n";
  $html .= "</form>\n";
}

showPage($title, $html);
?>
