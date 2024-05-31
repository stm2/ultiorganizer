<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$seasonId = $_GET["season"];
$single = 0;
$series_id = -1;
CurrentSeries($seasonId, $series_id, $single, _("Teams"));

$title = SeasonName($seasonId) . ": " . _("Teams");
$html = "";

ensureEditSeriesRight($series_id);

$seasonInfo = SeasonInfo($seasonId);

if (!empty($_POST['save'])) {
  // TODO
  $html .= "TODO. Nothing saved";
}
$focusId = null;

$get_link = function ($seasonId, $seriesId, $single = 0, $htmlEntities = false) {
  $single = $single == 0 ? "" : "&single=1";
  $seaLink = urlencode($seasonId);
  $link = "?view=admin/seasonspirit&season=$seaLink&series={$seriesId}$single";
  return $htmlEntities ? utf8entities($link) : $link;
};

$url_here = $get_link($seasonId, $series_id, $single, true);

$html .= "Global settings";

$html .= SeriesPageMenu($seasonId, $series_id, $single, $get_link, "?view=admin/seasonseries&season=$seasonId");

$html .= "<form method='post' action='$url_here'>";


$html .= "</form>\n";

if (!empty($focusId))
  setFocus($focusId);

  $html .= "Hello World";

showPage($title, $html);
?>