<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';

$title = _("Users");
$html = "";

ensurePrivileges(hasEditUsersRight(), $title);

if (hasEditUsersRight()) {
	if (isset($_POST['confirmdeleteuser'])) {
		// Actually delete users after confirmation
		if (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				if (isSuperAdminByUserid($userid)) {
					$html .= "<p class='warning'>" . utf8entities(sprintf(_("Did not delete super user account %s."), $userid)) . "</p>\n";
				} elseif (!empty($_POST['registerrequest'])) {
					DeleteRegisterRequest(urldecode($userid));
					$html .= "<p class='warning'>" . utf8entities(sprintf(_("Deleted registration request for user %s."), $userid)) . "</p>\n";
				} else {
					DeleteUser(urldecode($userid));
					$html .= "<p class='warning'>" . utf8entities(sprintf(_("Deleted user account %s."), $userid)) . "</p>\n";
				}
			}
		}
	} elseif (isset($_POST['deleteuser'])) {
		// Confirmation step
		if (isset($_POST['users'])) {
			$html .= "<h2>" . _("Confirm deletion") . "</h2>";
			$html .= "<form method='post' action='?view=admin/users'>";
			foreach ($_POST['users'] as $userid) {
				$html .= "<input type='hidden' name='users[]' value='" . htmlspecialchars($userid, ENT_QUOTES) . "' />";
			}
			if (!empty($_POST['registerrequest'])) {
				$html .= "<input type='hidden' name='registerrequest' value='1' />";
			}
			$html .= "<ul>";
			foreach ($_POST['users'] as $userid) {
				$html .= "<li>" . utf8entities($userid) . "</li>";
			}
			$html .= "</ul>";
			$html .= "<p>" . _("Are you sure you want to delete the selected users or registration requests? This cannot be undone!") . "</p>";
			$html .= "<p><input class='button' type='submit' name='confirmdeleteuser' value='" . _("Yes, delete") . "' />";
			$html .= " <input class='button' type='submit' name='cancel' value='" . _("Cancel") . "' /></p>";
			$html .= "</form>";
			showPage($title, $html);
			exit;
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
