<?php
$body = @file_get_contents('php://input');

$parts = explode("|", $body);

$user = $parts[0];
$passwd = $parts[1];

$error = array();
$resp = array();

if (empty($user)) {
  $error[] = _("User name is required");
}

if (empty($passwd)) {
  $error[] = _("Password is required");
}

if (count($error) > 0) {
  $resp['msg'] = implode($error, ", ");
  $resp['status'] = false;
  echo json_encode($resp);
} else {

  $authenticated = UserAuthenticate($user, $passwd, null);
  $authenticated = $authenticated ? 1 : 0;

  $resp['status'] = true;
  $resp['authenticated'] = $authenticated;

  echo json_encode($resp);
}
?>