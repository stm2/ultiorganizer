<?php
include_once 'lib/timetable.functions.php';

$season = $_GET['season'] ?? CurrentSeason();

$title = _("Transfer times");
$html = "";

if (!empty($_POST['change_reservations'])) {
  header('location:' . urldecode($_POST['reservation_url']));
  exit();
}
if (!empty($_POST['schedule_reservations'])) {
  header('location:' . urldecode($_POST['schedule_url']));
  exit();
}

$group = "__all";
if (!empty($_GET["group"])) {
  $group = $_GET["group"];
}

$reservations = null;
if (!empty($_GET['reservations'])) {
  $reservations = explode(",", $_GET['reservations']);
}

$symmetric = $_GET['symmetric'] ?? 0;

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
$html .= "<p>" .
  _(
    "Enter the minimum times (in minutes) required for a team to move from field A to B. This information will be used when checking a schedule after saving it.") .
  "</p>\n";

$url_params = ['view' => 'admin/movingtimes', 'season' => $season, 'group' => urlencode($group),
  'reservations' => implode(",", $reservations), 'symmetric' => $symmetric];

$html .= "<form method='post' action='" . MakeUrl($url_params) . "'>\n";

$html .= groupSelection($season, $group, $url_params);

$html .= "<hr/>";

function filterLocations($locations, $reservations, $group) {
  $allowed = array();
  foreach ($reservations as $r) {
    $info = ReservationInfo($r);
    if (!isset($allowed[$info['location']]))
      $allowed[$info['location']] = [];
    $allowed[$info['location']][$info['fieldname']] = 1;
  }
  $locations = array_filter($locations,
    function ($loc) use ($allowed) {
      return isset($allowed[$loc['location']]) && isset($allowed[$loc['location']][$loc['fieldname']]);
    });
  if ($group === "_all") {
    foreach ($locations as $loc) {
      $loc_known[$loc['location']] = $loc['fieldname'];
    }
    foreach ($reservations as $r) {
      $info = ReservationInfo($r);
      if (!isset($loc_known[$info['location']]))
        $locations[] = array('location' => $info['location'], 'name' => $info['name'], 'fieldname' => $info['fieldname']);
    }
  }

  return $locations;
}

if ($reservations)
  $locations = filterLocations($locations, $reservations, $group);

$location_names = array();

$legend = "<div id='legend'>";
if (empty($locations)) {
  $legend .= _("No locations match the selected reservations and reservation group.");
} else {
  $legend .= "<h3>" . _("Selected locations") . "</h3><ol>";

  $i = 0;
  foreach ($locations as $location) {
    $location_name = utf8entities($location['name'] . " " . _("Field") . " " . $location['fieldname']);
    $legend .= "<li><input type='hidden' id='loc$i' name='loc[]' value='" . utf8entities($location['location']) . "'/>";
    $legend .= "<input type='hidden' id='field$i' name='field[]' value='" . utf8entities($location['fieldname']) . "'/>";
    $legend .= "$location_name</li>\n";
    $location_names[$i] = $location_name;
    $i++;
  }
  "</ol>\n";
}

if ($reservations) {
  $legend .= "<h3>" . _("Selected reservations") . "</h3>";
  $legend .= "<ul>";
  foreach ($reservations as $res) {
    $legend .= "<li>" . ReservationName(ReservationInfo($res)) . "</li>\n";
  }
  $legend .= "</ul>\n";
}
$legend .= "</div>";

if (count($locations) > 25) {
  $html .= _(
    "Too many locations! You should restrict your selection (by selecting a reservation group) before entering transfer times.");
  $i = 0;
  foreach ($locations as $location) {
    $location_name = utf8entities($location['name'] . " " . _("Field") . " " . $location['fieldname']);
    $location_names[$i] = $location_name;
    $i++;
  }
} else if (empty($locations)) {
  //
} else {

  $html .= "<p><input type='text' size='3' maxlength='5' value='0' id='setalltime' name='setalltime'/> ";
  $html .= "<input type='submit' name='reset_all' value='" . utf8entities(_("Set all times to this value.")) . "'/> ";
  $html .= "<input type='submit' name='reset_diagonal' value='" . utf8entities(_("Set diagonals to this value.")) .
    "'/></p>";

  if ($symmetric) {
    $invert_text = utf8entities(_("Allow asymmetric transfer times."));
  } else {
    $invert_text = utf8entities(_("Make symmetric."));
  }

  $html .= "<p><input type='submit' name='invert_symmetric' value='$invert_text'/></p>";

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
        $movetime = (TimeTableMoveTime($movetimes, $location1['location'], $location1['fieldname'],
          $location2['location'], $location2['fieldname']) / 60);
      }

      $times[$i][$j] = $movetime;

      $html .= "<td><input type='text'$disabled size='3' maxlength='5' value='$movetime' id='move" . $i . "_" . $j .
        "' name='move[$i][$j]'/></td>";
      $j++;
    }
    $html .= "</tr>\n";

    $i++;
  }
  $html .= "</table>";

  $html .= "<input type='submit' name='change_times' value='" . utf8entities(_("Save times")) . "'/>\n";
}
$html .= "<hr />";
$html .= $legend;

$html .= "<p><input type='hidden' name='reservation_url' value='" .
  urlencode(
    MakeUrl(['view' => 'admin/reservations', 'season' => $season, 'reservations' => implode(",", $reservations)])) .
  "'/>\n" . "<input type='submit' name='change_reservations' value='" . _("Select reservations") . "' />\n";
$html .= "<input type='hidden' name='schedule_url' value='" .
  urlencode(MakeUrl(['view' => 'admin/schedule', 'season' => $season, 'reservations' => implode(",", $reservations)])) .
  "'>\n" . "<input type='submit' name='schedule_reservations' value='" . _("Schedule") . "'/></p>";
$html .= "</form>";

showPage($title, $html);
?>