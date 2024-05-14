<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=updater
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Pool color updater"
description = "Automatically updates pool colors based on predefined list."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';

$html = "";
$title = _("Pool color updater");
$seasonId = "";

$pal = 3;

$offset = $_POST['offset'] ?? 0;
$distance = $_POST['distance'] ?? 5;

if(!empty($_POST['season'])){
	$seasonId = $_POST['season'];

	$order = array();
	$colors = PoolColors(intval($offset), $distance, $pal, $order);
	$max = count($colors) -1;
}

if (isset($_POST['change']) && !empty($_POST['pools'])) {
	$assignment = array();
	foreach($_POST['colors'] as $i => $color) {
		$assignment[$_POST['ids'][$i]] = $color;
	}
	$pools = $_POST["pools"];
	foreach($pools as $poolId){
		$color = $assignment[$poolId];
		$query = "UPDATE uo_pool SET color='" . $color . "' WHERE pool_id=" . $poolId;
		DBQuery($query);
	}
}

//season selection
$html .= "<form method='post' id='tables' action='?view=plugins/update_pool_colors'>\n";

if(empty($seasonId)){
	$html .= "<p>"._("Select event").": <select class='dropdown' name='season'>\n";

	$seasons = Seasons();
			
	while($row = mysqli_fetch_assoc($seasons)){
		$html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
	}

	$html .= "</select></p>\n";
	$html .= "<p><input class='button' type='submit' name='select' value='"._("Select")."'/></p>";
}else{
	
	$html .= "<p>"._("Select pools to change color").":</p>\n";
	$html .= "<table><tr>";
	$html .= "<th>"._("Pool")."</th>";
	$html .= "<th>"._("Division")."</th>";
	$html .= "<th>" . checkAllCheckbox('pools') . "</th>";
	$html .= "<th>"._("New color")."</th>";
	$html .= "<th>#</th>";
	$html .= "</tr>\n";
	
	$series = SeasonSeries($seasonId);
	$i = 0;
	foreach($series as $row){

		$pools = SeriesPools($row['series_id']);
		foreach($pools as $pool){
			$poolinfo = PoolInfo($pool['pool_id']);
			$html .= "<tr>";
			$html .= "<td style='background-color:#".$poolinfo['color']."'>". $pool['name'] ."</td>";
			$html .= "<td>". $row['name'] ."</td>";
			$html .= "<td class='center'><input type='checkbox' name='pools[]' value='".utf8entities($pool['pool_id'])."' /></td>";
			$html .= "<td style='background-color:#".$colors[$i % $max]."'>". $pool['name'] .
				"<input type='hidden' name='colors[]' value='".$colors[$i % $max]."'>" .
				"<input type='hidden' name='ids[]' value='". utf8entities($pool['pool_id']) ."'>" .
				"</td>";
			$html .= "<td style='background-color:#".$colors[$i % $max]."'>". $order[$i % $max] ."</td>";
			$html .= "</tr>\n";
			++$i;
		}
	}
	$html .= "</table>\n";
	$html .= "<label>" . _("Offset") . "<input name='offset' type='number' value='$offset' /></label><br>\n";
	$html .= "<label>" . _("Distance") . "<input name='distance' type='number' value='$distance' /></label><br>\n";
	$html .= "<p><input class='button' type='submit' name='change' value='"._("Update")."'/></p>";
	$html .= "<div>";
	$html .= "<input type='hidden' name='season' value='$seasonId' />\n";
	$html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
