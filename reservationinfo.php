<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/configuration.functions.php';

$reservationId = intval(iget("reservation"));
$place = ReservationInfo($reservationId);
$location = LocationInfo($place['location']);
$title = _("Reservation") . ": " . utf8entities($place['name']) . " " . _("Field") . " " .
  utf8entities($place['fieldname']);

// common page
pageTopHeadOpen($title);
echo MapScript('map', $location['lat'], $location['lng']);
?>


<?php
pageTopHeadClose($title, false);
leftMenu();
contentStart();

echo "<h1>" . utf8entities($place['name']) . " " . _("Field") . " " . utf8entities($place['fieldname']) . "</h1>\n";
echo "<p>" . DefTimeFormat($place['starttime']) . " - " . DefHourFormat($place['endtime']) . "</p>\n";
echo "<p>" . utf8entities($place['address']) . "</p>\n";
echo "<p>" . $place['info'] . "</p>\n";
echo "<p>&nbsp;</p>";
?>
<div id="map"></div>
<?php
contentEnd();
pageEnd();
?>
