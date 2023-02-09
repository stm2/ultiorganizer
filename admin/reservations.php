<?php
include_once 'menufunctions.php';
include_once 'lib/search.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/timetable.functions.php';
$urlparams = ['view' => 'admin/reservations'];
$season = "";
$html = "";
$group = "__all";
if (!empty($_GET["group"])) {
  $group = $_GET["group"];
}

if (!empty($_GET["series"])) {
  $urlparams['series'] = intval($_GET["series"]);
} elseif (!empty($_GET["pool"])) {
  $urlparams['pool'] = intval($_GET["pool"]);
} elseif (!empty($_GET["season"])) {
  $urlparams['season'] = $_GET["season"];
  $season = $_GET["season"];
}

if (!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  RemoveReservation($id, $season);
  $_POST['searchreservation'] = "1"; // do not hide search results
}
if (isset($_POST['schedule']) && isset($_POST['reservations'])) {
  $url = "location:";
  $extra_params = ['view' => 'admin/schedule', 'reservations' => implode(",", $_POST['reservations'])];
  if (!empty($urlparams)) {
    $url .= MakeUrl($urlparams, $extra_params);
  } else {
    $url .= MakeUrl($extra_params);
  }
  header($url);
  exit();
}
if (isset($_POST['moving']) && isset($_POST['reservations'])) {
  $url = "location:";
  $extra_params = ['view' => 'admin/movingtimes', 'reservations' => implode(",", $_POST['reservations'])];
  if (!empty($urlparams)) {
    $url .= MakeUrl($urlparams, $extra_params);
  } else {
    $url .= MakeUrl($extra_params);
  }
  header($url);
  exit();
}
// common page
$title = _("Fields");

include_once 'lib/yui.functions.php';

addHeaderCallback(
  function () {
    echo yuiLoad(array("calendar"));

    echo getCalendarScript(['searchstart', 'searchend']);
  });

$searchItems = array();
$searchItems[] = 'searchstart';
$searchItems[] = 'searchend';
$searchItems[] = 'searchgroup';
$searchItems[] = 'searchlocation';

$hidden = array();
foreach ($searchItems as $name) {
  if (isset($_POST[$name])) {
    $hidden[$name] = $_POST[$name];
  }
}

$url = MakeUrl($urlparams);

if (empty($season)) {
  $html .= SearchReservation(substr($url, 1), $hidden, array('schedule' => _("Schedule selected")));
  $html .= "<hr />";
  $html .= "<p><a href='?view=admin/addreservation&amp;season=" . $season . "'>" . _("Add reservation") . "</a> | ";
  $html .= "<a href='?view=admin/locations&amp;season=" . $season . "'>" . _("Add location") . "</a></p>";
} else {
  $html .= "<p><a href='?view=admin/addreservation&amp;season=" . $season . "'>" . _("Add reservation") . "</a> | ";
  $html .= "<a href='?view=admin/locations&amp;season=" . $season . "'>" . _("Add location") . "</a> | ";
  $html .= "<a href='?view=admin/reservations'>" . _("Search") . "</a></p>\n";
  $html .= "<hr />";

  $html .= groupSelection($season, $group, ['view' => 'admin/reservations', 'season' => $season, 'group' => urlencode($group)]);

  $reservations = SeasonReservations($season, $group);
  if (count($reservations) > 0) {
    $allGamesOther = 0;
    $html .= "<form method='post' id='reservations' action='$url'>\n";
    $html .= "<table class='admintable'><tr><th>" . checkAllCheckbox('reservations') . "</th>";
    $html .= "<th>" . _("Group") . "</th><th>" . _("Location") . "</th><th>" . _("Date") . "</th>";
    $html .= "<th>" . _("Starts") . "</th><th>" . _("Ends") . "</th><th>" . _("Games") . "</th>";
    $html .= "<th>" . _("Scoresheets") . "</th><th></th></tr>\n";
    foreach ($reservations as $reservation) {
      $row = ReservationInfo($reservation['id']);
      
      $rseries = ReservationGameSeries($reservation['id']);
      $gamesResponsible =  0;
      $gamesOther = 0;
      foreach ($rseries as $ser) {
        if (hasEditSeriesRight($ser['series'])) {
          $gamesResponsible += $ser['games'];
        } else {
          $gamesOther += $ser['games'];
        }
      }
      $allGamesOther += $gamesOther;

      $disabled = ($gamesResponsible == 0 && !$gamesOther == 0) ? " disabled" : "";
      $html .= "<tr class='admintablerow'><td><input type='checkbox'$disabled name='reservations[]' value='" .
        utf8entities($row['id']) . "'/></td>";
      $html .= "<td>" . utf8entities(U_($row['reservationgroup'])) . "</td>";
      $html .= "<td><a href='?view=admin/addreservation&amp;reservation=" . $row['id'] . "&amp;season=" . $row['season'] .
        "'>" . utf8entities(U_($row['name'])) . " " . _("Field") . " " . utf8entities(U_($row['fieldname'])) .
        "</a></td>";
      $html .= "<td>" . DefWeekDateFormat($row['starttime']) . "</td>";
      $html .= "<td>" . DefHourFormat($row['starttime']) . "</td>";
      $html .= "<td>" . DefHourFormat($row['endtime']) . "</td>";
      $html .= "<td class='center'>" . ($gamesOther>0?($gamesResponsible." / "):"") . ($gamesOther+$gamesResponsible) . "</td>";
      $html .= "<td class='center'><a href='?view=user/pdfscoresheet&amp;reservation=" . $row['id'] .
        "&amp;season=$season'>" . _("PDF") . "</a></td>";
      if ($gamesOther + $gamesResponsible == 0) {
        $html .= "<td class='center'>" . getDeleteButton('remove', $row['id']) . "</td>";
      }

      $html .= "</tr>\n";
    }
    $html .= "</table>\n";

    if ($allGamesOther > 0) {
      $html .= "<p>" . _("Reservations where you don't have series edit rights for all games have been disabled.") . "</p>";
    }

    $html .= "<p>";
    $html .= "<input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>\n";
    $html .= "<input type='submit' name='schedule' value='" . utf8entities(_("Schedule selected")) . "'/>\n";
    $html .= "<input type='submit' name='moving' value='" . utf8entities(_("Manage transfer times")) . "'/></p>\n";
  } else {
    $html .= "<p>" . _("No reservations.") . "</p>";
  }
}

showPage($title, $html);
?>
