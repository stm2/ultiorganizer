<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';

$title = _("Users");
$html = "";

ensurePrivileges(hasEditUsersRight(), $title);

if (hasEditUsersRight()) {
	if (isset($_POST['deleteuser'])) {
		if (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				if (isSuperAdmin($userid)) {
					$html .= "<p class='warning'>" . utf8entities(sprintf(_("Did not delete super user account %s."), $userid)) . "</p>\n";
				} elseif (!empty($_POST['registerrequest'])) {
					DeleteRegisterRequest(urldecode($userid));
				} else {
					DeleteUser(urldecode($userid));
				}
			}
		}
	} elseif (isset($_POST['recoverpassword'])) {
		if (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				if (UserResetPassword(urldecode($userid))) {
					$html .= "<p>" . sprintf(_("Recovery request sent to %s."), utf8entities($userid)) . "</p>\n";
				} else {
					$html .= "<p class='warning'>" . sprintf(_("No recovery request sent to %s."), utf8entities($userid)) . "</p>\n";
				}
			}
		}
	}
}

$target = "view=admin/users";
//content

$html .= "<p><a href='?view=admin/adduser'>" . _("Add new user") . "</a></p>";
$html .= "<h2>" . $title . "</h2>";
if (hasEditUsersRight()) {
	$html .= SearchUser($target, array(), array('recoverpassword' => _("Reset password"), 'deleteuser' => _("Delete")));
}

showPage($title, $html);
