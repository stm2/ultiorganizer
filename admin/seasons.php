<?php
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/configuration.functions.php';

$title = _("Events");
$html = "";

// process itself on submit
if (!empty($_POST['remove_x']) && !empty($_POST['hiddenDeleteId'])) {
  $id = $_POST['hiddenDeleteId'];
  $ok = true;
  // run some test to for safe deletion
  $series = SeasonSeries($id);
  if (count($series)) {
    $html .= "<p class='warning'>" . sprintf(_("Event has %d Division(s)."), mysqli_num_rows($series)) . " " .
      _("Divisions must be removed before removing the event.") . "</p>";
    $ok = false;
  }
  $cur = CurrentSeason();

  if ($cur == $id) {
    $html .= "<p class='warning'>" . _("You can not remove a current event") . ".</p>";
    $ok = false;
  }
  if ($ok) {
    DeleteSeason($id);
    // remove rights from deleted season
    $propId = getPropId($_SESSION['uid'], 'editseason', $id);
    if ($propId > 0)
      RemoveEditSeason($_SESSION['uid'], $propId);
    $propId = getPropId($_SESSION['uid'], 'userrole', 'seasonadmin:' . $id);
    if ($propId > 0)
      RemoveUserRole($_SESSION['uid'], $propId);
  }
}
// common page

$html .= "<form method='post' action='?view=admin/seasons'>";

$html .= "<h2>" . _("Seasons/Tournaments") . "</h2>\n";

$html .= "<table class='admintable'>\n";

$html .= "<tr>
	<th>" . _("Name") . "</th>
	<th>" . _("Type") . "</th>
	<th>" . _("Starts") . "</th>
	<th>" . _("Ends") . "</th>
	<th>" . _("Enrollment") . "</th>
	<th>" . _("Visible") . "</th>
	<th>" . _("Operations") . "</th>
	<th></th>
	</tr>\n";

$seasons = Seasons();
while ($row = mysqli_fetch_assoc($seasons)) {
  $seasonId = $row['season_id'];
  $info = SeasonInfo($seasonId);

  $html .= "<tr>";
  $html .= "<td><a href='?view=admin/addseasons&amp;season=" . urlencode($seasonId) . "'>" .
    utf8entities(U_($info['name'])) . "</a></td>";

  if (!empty($info['type']))
    $html .= "<td>" . utf8entities(U_($info['type'])) . "</td>";
  else
    $html .= "<td>?</td>";

  if (!empty($info['starttime']))
    $html .= "<td>" . ShortDate($info['starttime']) . "</td>";
  else
    $html .= "<td>-</td>";

  if (!empty($info['endtime']))
    $html .= "<td>" . ShortDate($info['endtime']) . "</td>";
  else
    $html .= "<td>-</td>";

  $enrollment = intval($info['enrollopen']) ? _("open") : _("closed");
  $html .= "<td>" . utf8entities($enrollment) . "</td>";

  $visible = intval($info['iscurrent']) ? _("yes") : _("no");
  $html .= "<td>" . utf8entities($visible) . "</td>";

  $html .= "<td>";
  if (!CanDeleteSeason($seasonId)) {
    if (IsSeasonStatsCalculated($seasonId)) {
      $html .= "<a href='?view=admin/stats&amp;season=" . urlencode($seasonId) . "'>" . utf8entities(_("Re-calc. stats")) . "</a>";
    } else {
      $html .= "<a href='?view=admin/stats&amp;season=" . urlencode($seasonId) . "'><b>" . utf8entities(_("Calc. stats")) . "</b></a>";
    }
  }
  $html .= " | <a href='?view=admin/eventdataexport&amp;season=" . urlencode($seasonId) . "'>" . utf8entities(_("Export")) . "</a>";
  $html .= "</td>\n";

  if (CanDeleteSeason($seasonId)) {
    $html .= "<td class='center'>" .
      getDeleteButton('remove', $seasonId, 'hiddenDeleteId', 'images/remove.png', 'X',
        sprintf(_("Are you sure you want to delete the event '%s'?"), U_($info['name']))) . "</td>";
  } else {
    $html .= "<td></td>";
  }
  $html .= "</tr>\n";
}
$html .= "</table>";

$html .= "<div>";
$html .= "<a href='?view=admin/eventdataimport'>" . utf8entities(_("Import event")) . "</a> | ";
$html .= "<a href='?view=admin/seasonstats'>" . utf8entities(_("All event statistics")) . "</a></div>";
$html .= "<p><input class='button' name='add' type='button' value='" .utf8entities( _("Add")) .
  "' onclick=\"window.location.href='?view=admin/addseasons'\"/></p>";
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>\n";

showPage($title, $html);
?>