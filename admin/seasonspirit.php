<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once $include_prefix . 'lib/yui.functions.php';
addHeaderCallback(
  function () {
    echo yuiLoad(array("utilities", "calendar", "datasource", "autocomplete"));

    echo getCalendarScript(['lockdate']);
  });

$seasonId = $_GET["season"];
$single = 0;
$seriesId = -1;
CurrentSeries($seasonId, $seriesId, $single, _("Teams"));

$title = SeasonName($seasonId) . ": " . _("Teams");
$html = "";

ensureEditSeriesRight($seriesId);

$seasonInfo = SeasonInfo($seasonId);

$globaltoken = "SPSPSP424242SPSP";
$resultstoken = "SPSPSP424242SPSP";

$sp = ['globalspiritmode' => $seasonInfo['spiritmode'], 'lockdate' => '', 'globaltoken' => $globaltoken,
  'teamtoken' => true, 'locked' => false, 'public' => false, 'displaytoken' => false, 'spiritmode' => 0];

if (!empty($_POST['save'])) {
  // TODO
  $html .= "<div style='white-space: pre-wrap;'>" . utf8entities(print_r($_POST, true)) . "</div>";
  $html .= "TODO. Nothing saved";
}

$get_link = function ($seasonId, $seriesId, $single = 0, $htmlEntities = false) {
  $single = $single == 0 ? "" : "&single=1";
  $seaLink = urlencode($seasonId);
  $link = "?view=admin/seasonspirit&season=$seaLink&series={$seriesId}$single";
  return $htmlEntities ? utf8entities($link) : $link;
};

$url_here = $get_link($seasonId, $seriesId, $single, true);

$html .= "<h2>" . _("Global settings") . "</h2>\n";

$spiritmodes = SpiritModes();

$html .= "<form method='post' action='$url_here'>";
$html .= "<table class='formtable'>";
$html .= "<tr><td><label for='globalspiritmode'>" . _("Default Spirit Mode") . "</label></td>";
$html .= "<td><select class='dropdown' id='globalspiritmode' name='globalspiritmode'>\n";
$html .= "<option value='0'></option>\n";
foreach ($spiritmodes as $mode) {
  $selected = ($sp['globalspiritmode'] == $mode['mode']) ? " selected='selected'" : "";
  $html .= "<option $selected value='" . utf8entities($mode['mode']) . "'>" . utf8entities(_($mode['name'])) .
    "</option>\n";
}
$html .= "</select></td></tr>\n";

$html .= "<tr><td><label for='lock'>" . _("Lock submission for non-admins") . "</label></td>";
$html .= "<td><input class='input' type='checkbox' name='lock' id='lock' ";
if ($sp['locked']) {
  $html .= "checked='checked'";
}
$html .= "/></td>";

$html .= "<tr><td><label for='public'>" . _("Display results to public") . "</label></td>";
$html .= "<td><input class='input' type='checkbox' name='public' id='public' ";
if ($sp['public']) {
  $html .= "checked='checked'";
}
$html .= "/></td>";

$html .= "<td><a href='?view=seriesstatus&series=$seriesId'>" . _("public (or division admin) results") . "</a></td>";

$html .= "<tr><td><label for='displaytoken'>" . _("Display to anyone with link") . "</label></td>";
$html .= "<td><input class='input' type='checkbox' name='displaytoken' id='displaytoken' ";
if ($sp['public']) {
  $html .= "checked='checked'";
}
$html .= "/></td>";
$html .= "<td><a href='?view=seriesstatus&series=$seriesId&token=$resultstoken'>" . _("semi-secret results") .
  "</a></td>";
$html .= "<td><input type='submit' name='change_public_link' value='" . utf8entities(_("Change link")) .
  "'/></td></tr>\n";

$html .= "</table>";
$html .= "</form>\n";

$html .= "<br /><br />";

$html .= SeriesPageMenu($seasonId, $seriesId, $single, $get_link, "?view=admin/seasonseries&season=$seasonId");

$html .= "<form method='post' action='$url_here'>";

$html .= "<table class='formtable'>";
$html .= "<tr><td><label for='spiritmode'>" . _("Spirit Mode") . "</label></td>";
$html .= "<td><select class='dropdown' id='spiritmode' name='spiritmode'>\n";
$html .= "<option value='0'></option>\n";

// TODO
$seriesMode = $sp['globalspiritmode'];

foreach ($spiritmodes as $mode) {
  $selected = ($seriesMode == $mode['mode']) ? " selected='selected'" : "";
  $html .= "<option $selected value='" . utf8entities($mode['mode']) . "'>" . utf8entities(_($mode['name'])) .
    "</option>\n";
}
$html .= "</select></td></tr>\n";

$html .= "<tr><td><label for='globaltoken'>" . _("Allow everyone to edit all results with link") . "</label></td>";
$html .= "<td><input class='input' type='checkbox' name='globaltoken' id='globaltoken' ";
if ($sp['globaltoken']) {
  $html .= "checked='checked'";
}
$html .= "/></td>";

$teams = SeriesTeams($seriesId, true);
$admins = [];
foreach ($teams as $team) {
  $admins = array_merge($admins, GetTeamAdmins($team['team_id']));
}
$link = dm_link($admins, _("Global Spirit Link"), "Use this link for submitting spirit results: TODO",
  _("send link to teams"));

$html .= "<td><a href='?view=user/addspirit&series=$seriesId&token=$globaltoken'>" . _("global edit link") . "</a>";
$html .= " | $link</td>";
$html .= "</tr>\n";

$html .= "<tr><td><label for='teamtoken'>" . _("Allow teams to edit their results with link") . "</label></td>";
$html .= "<td><input class='input' type='checkbox' name='teamtoken' id='teamtoken' ";
if ($sp['teamtoken']) {
  $html .= "checked='checked'";
}
$html .= "/></td>";

if ($sp['teamtoken']) {
  $link = dm_link($admins, _("Spirit link"), "TODO", _("send link to teams"));
  $html .= "<td>$link</td>";
}
$html .= "</tr>\n";

$html .= "<tr><td><label for='lockdate'>" . _("Lock submission for non-admins after") . "</label></td>";
$html .= "<td>" . getCalendarInput('lockdate', ShortDate($sp['lockdate'])) . "</td></tr>";

$html .= "</table>\n";

$html .= "<table class='admintable'>\n";

$html .= "<tr><th>" . utf8entities(_("Name")) . "</th>";
$html .= "<th>" . utf8entities(_("played")) . "</th>";
$html .= "<th>" . utf8entities(_("subm.")) . "</th>";
$html .= "<th>" . utf8entities(_("rcv.")) . "</th>";
$html .= "<th>" . utf8entities(_("avg.")) . "</th>";
$html .= "<th></th>";
$html .= "</tr>\n";

$total = 0;

$spiritAvg = SeriesSpiritBoard($seriesId);

foreach ($teams as $team) {
  $teamId = $team['team_id'];
  $teamToken = "XYXYXXYX424342ZZZZZ";
  $played = TeamStats($teamId)['games'];
  $submitted = SpiritSubmitted($teamId, $seriesMode);
  $html .= "<tr class='admintablerow'>";
  $html .= "<td><a href='?view=teamcard&team=$teamId'>{$team['name']}</a></td>";
  $total = $spiritAvg[$teamId]['total'] ?? '-';
  if (intval($total) > 0)
    $total = numf($total, 1);
  $complete = $played <= $submitted['submitted'];
  $sub = !$complete ? "<b>{$submitted['submitted']}</b>" : "{$submitted['submitted']}";
  $html .= "<td>$played</td><td>$sub</td><td>{$submitted['received']}</td><td>$total</td>";
  $html .= "<td><a href='?view=user/addspirit&series=$seriesId&submitter=$teamId&token=$teamToken'>" .
    _("submission link") . "</a>";
  $html .= " | <a href='?view=user/spiritresults&series=$seriesId&team=$teamId&token=$teamToken'>" . _("results link") .
    "</a>";
  $admins = GetTeamAdmins($teamId);
  if (empty($admins)) {
    $html .= " | <a href='?view=admin/addteamadmins&series=1643'>" . _("add team admin") . "</a>";
  } else {
    if (!$complete) {
      $link = dm_link($admins, _("missing spirit results"), null, _("send reminder"));
      $html .= " | $link";
    }
  }
  $html .= "</td>";
  $html .= "</tr>\n";
}

$html .= "</table>\n";

$html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "'/>";

showPage($title, $html);
?>