<?php
include_once 'localization.php';
include $include_prefix . 'lib/user.functions.php';

session_name("UO_SESSID");
session_start();

if (!isSuperAdmin())
  die ('unprivileged access');

OpenConnection();

header("Content-type: text/plain; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetSearchUsers();
// Iterate through the rows, adding XML nodes for each
while ($row = @mysqli_fetch_assoc($result)){
  echo U_($row['userid'])."\t".$row['name']."\n"; // ."\t".$row['id']
}
CloseConnection();
?>