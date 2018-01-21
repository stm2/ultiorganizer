<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';

$LAYOUT_ID = USERS;
$title = _("Users");

if (hasEditUsersRight()) {
	if (isset($_POST['deleteuser'])) {
		if (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				if(!empty($_POST['registerrequest'])){
					DeleteRegisterRequest(urldecode($userid));
				}else{
					DeleteUser(urldecode($userid));
				}
			}
		}
	}elseif (isset($_POST['resetpassword'])) {
		if (isset($_POST['users'])) {
			foreach ($_POST['users'] as $userid) {
				UserResetPassword(urldecode($userid));
			}
		}
	}
}
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$target = "view=admin/users";
//content
echo "<p><a href='?view=admin/adduser'>"._("Add new user")."</a></p>";
echo "<h2>".$title."</h2>";
if (hasEditUsersRight()) {
	echo SearchUser($target, array(), array('resetpassword' => _("Reset password"),'deleteuser' => _("Delete")));
}

contentEnd();
pageEnd();
?>
