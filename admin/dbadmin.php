<?php
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/plugin.functions.php';
$html = "";

// common page
$title = _("Database administration");

ensureSuperAdmin($title);

if (isSuperAdmin()) {

  $html .= "<p><span class='profileheader'>" . _("Database administration") . ": </span><br/>\n";
  $html .= "<a href='?view=admin/executesql'>&raquo; " . _("Run SQL") . "</a><br/>\n";
  $html .= "<a href='?view=admin/dbbackup'>&raquo; " . _("Backup") . "</a><br/>\n";
  $html .= "<a href='?view=admin/dbrestore'>&raquo; " . _("Restore") . "</a><br/>\n";
  $html .= "<a href='?view=admin/dbequalize'>&raquo; " . _("Equalization") . "</a><br/>\n";
  $html .= "<a href='?view=admin/dbanonymous'>&raquo; " . _("Restore anonymous user settings") . "</a><br/>\n";
  $html .= "<a href='?view=admin/purge'>&raquo; " . _("Delete old stuff") . "</a><br/>\n";
  $html .= "</p>\n";

  $types = array("import", "updater", "simulator", "generator");

  foreach ($types as $type) {
    $plugins = GetPluginList("database", $type);
    if (count($plugins)) {
      $html .= "<p><span class='profileheader'>" . _("Plugins") . " ($type): </span><br/>\n";
      foreach ($plugins as $plugin) {
        $html .= "<a href='?view=" . $plugin['file'] . "'>&raquo; " . $plugin['title'] . "</a><br/>\n";
      }
      $html .= "</p>\n";
    }
  }

  $total_size = 0;
  $result = mysql_adapt_query("SHOW TABLE STATUS");
  $html .= "<p><span class='profileheader'>" . _("Tables") . ": </span></p>\n";
  $html .= "<table class='infotable'>";
  $html .= "<tr><th>" . _("Name") . "</th>";
  $html .= "<th>" . _("Rows") . "</th>";
  $html .= "<th>" . _("avg. row length") . "</th>";
  $html .= "<th>" . _("Data") . "</th>";
  $html .= "<th>" . _("Index") . "</th>";
  $html .= "<th>" . _("Auto Increment") . "</th>";
  $html .= "<th>" . _("Updated") . "</th>";
  $html .= "</tr>\n";
  while ($row = mysqli_fetch_assoc($result)) {
    if (mb_substr($row['Name'], 0, 3) == 'uo_') {
      $sql = urlencode("SELECT * FROM " . $row['Name']);
      $html .= "<tr>";
      $html .= "<td><a href='?view=admin/executesql&amp;sql=$sql'>" . $row['Name'] . "</a></td>";
      $html .= "<td>" . $row['Rows'] . "</td>";
      $html .= "<td>" . $row['Avg_row_length'] . "</td>";
      $html .= "<td>" . $row['Data_length'] . "</td>";
      $html .= "<td>" . $row['Index_length'] . "</td>";
      $html .= "<td>" . $row['Auto_increment'] . "</td>";
      $html .= "<td>" . $row['Update_time'] . "</td>";
      $html .= "</tr>\n";
      $total_size += intval($row['Data_length']) + intval($row['Index_length']);
    }
  }
  $sql = urlencode("SHOW TABLE STATUS");
  $html .= "<tr><td colspan='5'>" . _("Execute") . ": <a href='?view=admin/executesql&amp;sql=$sql'>" .
    "SHOW TABLE STATUS" . "</a></td></tr>";

  $html .= "</table>";
  $html .= "<p>" . _("Database size") . ": " . $total_size . " " . _("bytes") . "</p>\n";

  $html .= "<p><span class='profileheader'>" . _("Statistics") . ": </span><br/>\n";
  $mysql_stat = mysql_adapt_stat();
  $tot_count = preg_match_all('/([a-z ]+):\s*([0-9.]+)/i', $mysql_stat, $matches);
  for ($i = 0; $i < $tot_count; $i++) {
    $info1 = trim($matches[1][$i]);
    $info2 = trim($matches[2][$i]);
    $html .= "&nbsp;" . $info1 . ": " . $info2 . "<br/>\n";
  }
  $sql = urlencode("SHOW GLOBAL STATUS");
  $html .= "&nbsp;" . _("Execute") . ": <a href='?view=admin/executesql&amp;sql=$sql'>" . "SHOW GLOBAL STATUS" . "</a>";
  $html .= "</p>\n";

  $html .= "<p><span class='profileheader'>" . _("Client library version") . ": </span>" .
    mysqli_get_client_info(DBLink()) . "<br/>\n";
  $html .= "<span class='profileheader'>" . _("Type of connection in use") . ": </span>" . mysqli_get_host_info(
    DBLink()) . "<br/>\n";
  $html .= "<span class='profileheader'>" . _("Protocol version") . ": </span>" . mysqli_get_proto_info(DBLink()) .
    "<br/>\n";
  $html .= "<span class='profileheader'>" . _("Server version") . ": </span>" . mysqli_get_server_info(DBLink()) .
    "</p>\n";

  $html .= "<p><span class='profileheader'>" . _("Character set and collation") . ": </span><br/>\n";
  $result = mysql_adapt_query("SHOW VARIABLES LIKE 'character_set\_%';");
  while ($row = mysqli_fetch_assoc($result)) {
    $html .= "&nbsp;" . $row['Variable_name'] . ": " . $row['Value'] . "<br/>\n";
  }
  $result = mysql_adapt_query("SHOW VARIABLES LIKE 'collation\_%';");
  while ($row = mysqli_fetch_assoc($result)) {
    $html .= "&nbsp;" . $row['Variable_name'] . ": " . $row['Value'] . "<br/>\n";
  }
  $html .= "</p>\n";
  $html .= "<p><span class='profileheader'>" . _("PHP version") . ": </span>" . phpversion() . "</p>\n";
}

showPage($title, $html);
?>