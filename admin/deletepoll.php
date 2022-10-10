<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['poll'])) {
  die(_("Series and poll mandatory"));
}
$pollId = $_GET['poll'];
$poll = PollInfo($pollId);
$seriesName = SeriesName($poll['series_id']);

$title = _("Delete poll");
$html = '';

$backurl = isset($_SERVER['HTTP_REFERER']) ? utf8entities($_SERVER['HTTP_REFERER']) : '';
if (isset($_POST['backurl'])) {
  $backurl = $_POST['backurl'];
}

if (isset($_POST['confirm'])) {
  DeletePoll($pollId);

  
  header("location:$backurl");
} else if (isset($_POST['abort'])) {
  header("location:$backurl");
}

$html .= "<h2>$title";

$html .= " " . utf8entities($seriesName);

if (!empty($poll['name'])) {
  $html .= " - " . utf8entities($poll['name']);
}
$html .= "</h2>\n";

$html .= "<form method='post' action='?view=admin/deletepoll&poll=$pollId'>";

$html .= "<input type='hidden' name='backurl' value='$backurl'/>";

$voters = PollVoters($pollId);
$options = PollOptions($pollId);
$html .= "<p>" . htmlentities($poll['description']) . "</p>\n";

$html .= "<p>" . _("Options") . ": " . count($options) . "<br />\n";
$html .= "<p>" . _("Voters") . ": " . $voters . "</p>\n";



$html .= "<br /><p>" . _("Do you want to delete the poll and all votes?") . "</p>";

$html .= "<p><input class='button' name='abort' type='submit' value='" . _("No") . "'/>&nbsp;";
$html .= "<input class='button' name='confirm' type='submit' value='" . _("Delete") . "'/></p>\n";
$html .= "</form>\n";

showPage($title, $html);

?>
