<?php
include_once 'lib/season.functions.php';
include_once 'lib/statistical.functions.php';

$html = "";

$season_stats = AllSeasonStatistics();
$series_stats = ALLSeriesStatistics();
//common page
$title = _("Event statistics");
pageTop($title);
leftMenu();
contentStart();

$html .= "<h3>"._("Events")."</h3>\n";
$html .= "<table class='admintable'><tr>
	<th>"._("Name")."</th><th>"._("Type")."</th><th>"._("Teams")."</th><th>"._("Players")."</th><th>"._("Games")."</th></tr>\n";
foreach($season_stats as $ss){
	$html .= "<tr>";
	$html .= "<td>".utf8entities(U_($ss['seasonname']))."</td>";
	$html .= "<td>".utf8entities(U_($ss['seasontype']))."</td>";
	$html .= "<td>".$ss['teams']."</td>";
	$html .= "<td>".$ss['players']."</td>";	
	$html .= "<td>".$ss['games']."</td>";
	$html .= "</tr>\n";
}	
$html .= "</table>";

$html .= "<h3>"._("Division")."</h3>\n";
$html .= "<table class='admintable'><tr>
	<th>"._("Name")."</th><th>"._("Type")."</th><th>"._("Event")."</th><th>"._("Type")."</th><th>"._("Teams")."</th><th>"._("Players")."</th><th>"._("Games")."</th></tr>\n";
foreach($series_stats as $ss){
	$html .= "<tr>";
	$html .= "<td>".utf8entities(U_($ss['seriesname']))."</td>";
	$html .= "<td>".utf8entities(U_($ss['seriestype']))."</td>";
	$html .= "<td>".utf8entities(U_($ss['seasonname']))."</td>";
	$html .= "<td>".utf8entities(U_($ss['seasontype']))."</td>";
	$html .= "<td>".$ss['teams']."</td>";
	$html .= "<td>".$ss['players']."</td>";	
	$html .= "<td>".$ss['games']."</td>";
	$html .= "</tr>\n";
}	
$html .= "</table>";

echo $html;
contentEnd();
pageEnd();
?>