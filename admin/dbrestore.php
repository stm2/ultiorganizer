<?php
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/reservation.functions.php';
$html = "";
if (!defined('ENABLE_ADMIN_DB_ACCESS') || ENABLE_ADMIN_DB_ACCESS != "enabled") {
  $html = "<p>" .
    _(
      "Direct database access is disabled. To enable it, define('ENABLE_ADMIN_DB_ACCESS','enabled') in the config.inc.php file.") .
    "</p>";
} else {
  if (isSuperAdmin()) {
    ini_set("post_max_size", "30M");
    ini_set("upload_max_filesize", "30M");
    ini_set("memory_limit", -1);

    $html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/dbrestoring'>\n";

    $html .= "<p><span class='profileheader'>" . _("Select backup to restore") . ": </span></p>\n";

    $html .= "<p><input class='input' type='file' size='80' name='restorefile'/>";
    $html .= "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/></p>";
    $html .= "<p><input class='button' type='submit' name='check' value='" . _("Restore") . "'/>";
    $html .= "<input class='button' type='button' name='return'  value='" . _("Return") .
      "' onclick=\"window.location.href='?view=admin/dbadmin'\"/></p>";
    $html .= "</form>";
  } else {
    $html .= "<p>" . _("User credentials does not match") . "</p>\n";
  }
}
// common page
$title = _("Database restore");

showPage($title, $html);
?>