<?php
include_once 'lib/timetable.functions.php';

$season = $_GET['season'] ?? CurrentSeason();

$title = _("Transfer times");
$html = "";

$group = "__all";
if (!empty($_GET["group"])) {
  $group = $_GET["group"];
}

$symmetric = $_POST['symmetric'] ?? 0;

if (!empty($_POST['invert_symmetric'])) {
  $symmetric = 1 - $symmetric;
}

if (isset($_POST['loc'])) {
  if (!empty($_POST['reset_all'])) {
    $times = array();
    foreach ($_POST['loc'] as $i => $loc) {
      $times[$i]['location'] = $loc;
    }
    foreach ($_POST['field'] as $i => $field) {
      $times[$i]['field'] = $field;
    }
    foreach ($_POST['move'] as $from => $row) {
      foreach ($_POST['move'] as $to => $row) {
        $times[$from][$to] = $_POST['setalltime'];
      }
    }
    TimeTableSetMoveTimes($season, $times);
  } else if (!empty($_POST['reset_diagonal'])) {
    $times = array();
    foreach ($_POST['loc'] as $i => $loc) {
      $times[$i]['location'] = $loc;
    }
    foreach ($_POST['field'] as $i => $field) {
      $times[$i]['field'] = $field;
    }
    foreach ($_POST['move'] as $from => $row) {
      for ($j = 0; $j < $from; ++$j)
        $times[$from][$j] = $times[$j][$from];
      foreach ($row as $to => $time) {
        if ($from == $to)
          $times[$from][$to] = $_POST['setalltime'];
        else
          $times[$from][$to] = $time;
      }
    }
    TimeTableSetMoveTimes($season, $times);
  } else if (!empty($_POST['change_times'])) {
    $times = array();
    foreach ($_POST['loc'] as $i => $loc) {
      $times[$i]['location'] = $loc;
    }
    foreach ($_POST['field'] as $i => $field) {
      $times[$i]['field'] = $field;
    }
    foreach ($_POST['move'] as $from => $row) {
      for ($j = 0; $j < $from; ++$j)
        $times[$from][$j] = $times[$j][$from];
      foreach ($row as $to => $time) {
        $times[$from][$to] = $time;
      }
    }
    TimeTableSetMoveTimes($season, $times);
  }
}

$locations = SeasonReservationLocations($season, $group);

$html .= "<h2>" . _("Transfer times") . "</h2>";
$html .= "<p>" . _("Minimum times (in minutes) to move between fields") . "</p>\n";

$html .= "<p><a href='?view=admin/reservations&amp;season=" . $season . "'>" . _("Reservations") . "</a></p><hr />";

$html .= "<form method='post' action='?view=admin/movingtimes&amp;season=$season&amp;group=" . urlencode($group) . "'>\n";

$html .= groupSelection($season, $group, '?view=admin/movingtimes');

$location_names = array();

$legend = "<div id='legend'";
$i = 0;
foreach ($locations as $location) {
  $location_name = utf8entities($location['name'] . " " . _("Field") . " " . $location['fieldname']);
  $legend .= "<input type='hidden' id='loc$i' name='loc[]' value='" . utf8entities($location['location']) . "'/>";
  $legend .= "<input type='hidden' id='field$i' name='field[]' value='" . utf8entities($location['fieldname']) . "'/>";
  $legend .= "<p>" . ($i + 1) . ": $location_name</p>\n";
  $location_names[$i] = $location_name;
  $i++;
}
$legend .= "</div>";

$html .= "<p><input type='text' size='3' maxlength='5' value='0' id='setalltime' name='setalltime'/> ";
$html .= "<input type='submit' name='reset_all' value='" . utf8entities(_("Set all times to this value.")) . "'/> ";
$html .= "<input type='submit' name='reset_diagonal' value='" . utf8entities(_("Set diagonals to this value.")) .
  "'/></p>";

if ($symmetric) {
  $invert_text = utf8entities(_("Allow asymmetric values."));
} else {
  $invert_text = utf8entities(_("Make symmetric."));
}

$html .= "<p><input type='hidden' name='symmetric' value='$symmetric' />" .
  "<input type='submit' name='invert_symmetric' value='$invert_text'/></p>";

$html .= "<table class='admintable transfertable'><tr><th>" . _("from\\to") . "</th>";
$i = 0;
foreach ($locations as $location) {
  $html .= "<th title='{$location_names[$i]}'>" . ($i + 1) . "</th>";
  ++$i;
}
$html .= "</tr>\n<tr>";
$i = 0;
$movetimes = TimetableMoveTimes($season);

$times = array();

foreach ($locations as $location1) {
  $times[$i] = array();
  $html .= "<td title='{$location_names[$i]}'>" . ($i + 1) . "</td>";
  $j = 0;
  foreach ($locations as $location2) {
    if ($symmetric && $j < $i) {
      $disabled = " disabled";
      $movetime = $times[$j][$i];
    } else {
      $disabled = '';
      $movetime = (TimeTableMoveTime($movetimes, $location1['location'], $location1['fieldname'], $location2['location'],
        $location2['fieldname']) / 60);
    }

    $times[$i][$j] = $movetime;

    $html .= "<td><input type='text'$disabled size='3' maxlength='5' value='$movetime' id='move" . $i . "_" . $j .
      "' name='move[$i][$j]'/></td>";
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

$html .= $legend;

$html .= "</form>";

showPage($title, $html);

?>