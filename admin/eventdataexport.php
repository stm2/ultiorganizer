<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';

$html = "";
$title = _("Event data export");
$seasonId = iget("season");

if (empty($seasonId)) {
  $seasonId = CurrentSeason();
}

$data = "";

if (!empty($_POST['season'])) {
  $seasonId = $_POST['season'];

  $filename = $seasonId . ".xml";

  $eventdatahandler = new EventDataXMLHandler();
  try {
    $data = $eventdatahandler->EventToXML($seasonId, $_POST["searchseries"],
      isset($_POST["template"]) && $_POST["template"] === "on");
    header("Pragma: public");
    header("Expires: -1");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=$filename;");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . strlen($data));
    echo $data;
  } catch (Exception $e) {
    $html .= "<p id='statusMessage' class='warning' >" . utf8entities($e->getMessage()) ."</p>\n";
    $html .= "<script>setTimeout(\"document.getElementById('statusMessage').style.display='none';\",2000);</script>";
  }
}

$html .= "<h2>" . sprintf(_("Export season %s."), utf8entities(SeasonName($seasonId))) . "</h2>\n";

$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataexport&amp;season=" .
  utf8entities($seasonId) . "'>\n";

$html .= "<input type='hidden' name='season' value='" . utf8entities($seasonId) . "'/>\n";

$html .= "<table class='formtable'><tr><td class='infocell'>" . _("Exported divisions:") . "</td>\n";

$html .= "<td><select multiple='multiple' name='searchseries[]' id='searchseries' style='height:200px'>\n";

$series = SeasonSeriesMult(array($seasonId => 'selected'));

while ($seriesRow = mysqli_fetch_assoc($series)) {
  $html .= "<option value='" . urlencode($seriesRow['series']) . "' selected='selected' >";
  $html .= utf8entities($seriesRow['series_name']) . "</option>\n";
}
$html .= "</select></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Export as template, without results") . "</td>\n";
$html .= "<td><input class='input' type='checkbox' id='template' name='template' /></td></tr>\n";

$html .= "<tr><td><input class='button' type='submit' name='export' value='" . _("Export") . "'/></td></tr>\n";

$html .= "</table></form>";

if (empty($data))
  showPage($title, $html);

?>