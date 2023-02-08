<?php
include_once 'lib/timetable.functions.php';

$season = $_GET['season'] ?? CurrentSeason();

$title = _("Transfer times");
$html = "";

$group = "__all";
if (!empty($_GET["group"])) {
  $group = $_GET["group"];
}

if (!empty($_POST['change_times'])) {
  $times = array();
  foreach ($_POST['loc'] as $i => $loc) {
    $times[$i]['location'] = $loc;
  }
  foreach ($_POST['field'] as $i => $field) {
    $times[$i]['field'] = $field;
  }
  foreach ($_POST['move'] as $from => $row) {
    foreach ($row as $to => $time) {
      $times[$from][$to] = $time;
    }
  }
  debug_to_apache(print_r($times, true));  
  TimeTableSetMoveTimes($season, $times);
}

$locations = SeasonReservationLocations($season, $group);

$html .= "<h2>" . _("Transfer times") . "</h2>";
$html .= "<p>" . _("Minimum times (in minutes) to move between fields") . "</p>\n";
$html .= "<form method='post' action='?view=admin/movingtimes&amp;season=$season&amp;group=" . urlencode($group) .
  "'>\n";

$html .= groupSelection($season, $group, '?view=admin/movingtimes');

// $html .= "<p>Hallo Welt Hallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo WeltHallo Welt</p>";

$location_names = array();

$i = 0;
foreach ($locations as $location) {
  $location_name = utf8entities($location['name'] . " " . _("Field") . " " . $location['fieldname']);
  $html .= "<input type='hidden' id='loc$i' name='loc[]' value='" . utf8entities($location['location']) . "'/>";
  $html .= "<input type='hidden' id='field$i' name='field[]' value='" . utf8entities($location['fieldname']) . "'/>";
  $html .= "<p>" . ($i + 1) . ": $location_name</p>\n";
  $location_names[$i] = $location_name;
  $i++;
}

$html .= "<table class='admintable transfertable'><tr><th>" . _("from\\to") . "</th>";
$i = 0;
foreach ($locations as $location) {
  $html .= "<th title='{$location_names[$i]}'>" . ($i + 1) . "</th>";
  ++$i;
}
$html .= "</tr>\n<tr>";
$i = 0;
$movetimes = TimetableMoveTimes($season);

$symmetric = true;

foreach ($locations as $location1) {
  $html .= "<td title='{$location_names[$i]}'>" . ($i + 1) . "</td>";
  $j = 0;
  foreach ($locations as $location2) {

    $html .= "<td><input type='text' size='3' maxlength='5' value='" .
      (TimeTableMoveTime($movetimes, $location1['location'], $location1['fieldname'], $location2['location'],
        $location2['fieldname']) / 60) . "' id='move" . $i . "_" . $j . "' name='move[$i][$j]'/></td>";
    $j++;
  }
  $html .= "</tr>\n";

  $i++;
}
/*
 * $html .= "<input type='text' size='4' maxlength='5' value='0' id='setallvalue' name='setallvalue' />";
 * $html .= "<input type='submit' name='setallbutton' value='" . utf8entities(_("Set all to this value")) . "'onkeypress='setTimes()'/>";
 */

$html .= "</table>";

$html .= "<input type='submit' name='change_times' value='" . utf8entities(_("Save times")) . "'/>\n";

$html .= "</form>";

showPage($title, $html);

?>