<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$html = "";

$seriesId=0;
$season=0;

if(!empty($_GET["series"]))
	$seriesId = intval($_GET["series"]);

if(!empty($_GET["season"]))
	$season = $_GET["season"];

$title = _("Edit");
//series parameters
$sp = array(
	"series_id"=>"",
	"name"=>"",
	"type"=>"",
	"ordering"=>"A",
	"season"=>"",
	"valid"=>"1");


//process itself on submit
if(!empty($_POST['add']))
	{
	if(!empty($_POST['name']))
		{
		$sp['name'] = $_POST['name'];
		$sp['type'] = $_POST['type'];
		$sp['ordering'] = $_POST['ordering'];
		$sp['season'] = $season;
		if(!empty($_POST['valid']))
			$sp['valid']=1;
		else
			$sp['valid']=0;
		
		$seriesId = AddSeries($sp);
		session_write_close();
		header("location:?view=admin/seasonseries&Season=$season");
		}
	else
		{
		$html .= "<p class='warning'>"._("Division name is mandatory!")."</p>";
		}
	}
else if(!empty($_POST['save']))
	{
	if(!empty($_POST['name']))
		{
		$sp['series_id'] = $seriesId;
		$sp['name'] = $_POST['name'];
		$sp['type'] = $_POST['type'];
		$sp['ordering'] = $_POST['ordering'];
		$sp['season'] = $season;
		if(!empty($_POST['valid']))
			$sp['valid']=1;
		else
			$sp['valid']=0;
		
		SetSeries($sp);
		session_write_close();
		header("location:?view=admin/seasonseries&Season=$season");
		}
	else
		{
		$html .= "<p class='warning'>"._("Division name is mandatory!")."</p>";
		}
	}
	
//common page
addHeaderScript('script/disable_enter.js.inc');

include_once $include_prefix . 'lib/yui.functions.php';

$html .= yuiLoad(array("utilities", "datasource", "autocomplete"));

//retrieve values if series id known
if($seriesId)
	{
	$info = SeriesInfo($seriesId);
	$sp['series_id']=$info['series_id'];
	$sp['name']=$info['name'];
	$sp['type']=$info['type'];
	$sp['ordering']=$info['ordering'];
	$sp['season']=$info['season'];
	$sp['valid']=$info['valid'];
	}
	
//if seriesid is empty, then add new serie	
if($seriesId)
	$html .= "<h2>"._("Edit division").":</h2>\n";	
else
	$html .= "<h2>"._("Add division")."</h2>\n";	
	
	$html .= "<form method='post' action='?view=admin/addseasonseries&amp;series=$seriesId&amp;season=$season'>";
	$html .= "<table class='formtable'>
			<tr>
			<td class='infocell'>"._("Name").":</td>
			<td>".TranslatedField2("name", $sp['name'])."
			</td>
			</tr>\n";
	$html .= "<tr><td class='infocell'>"._("Order")." (A,B,C,D..):</td>
			<td><input class='input' size='30' id='ordering' name='ordering' value='".utf8entities($sp['ordering'])."'/></td></tr>";

	$html .= "<tr><td class='infocell'>"._("Type").": </td><td><select class='dropdown' name='type'>\n";
	
	$types = SeriesTypes();

	
	foreach($types as $type){
		if($sp['type']==$type)
			$html .= "<option class='dropdown' selected='selected' value='$type'>".U_($type)."</option>\n";
		else
			$html .= "<option class='dropdown' value='$type'>".U_($type)."</option>\n";
	}
	
	$html .= "</select></td></tr>\n";
	
	$html .= "<tr><td class='infocell'>"._("Valid").":</td>";
	if(intval($sp['valid']))
		$html .= "<td><input class='input' type='checkbox' id='valid' name='valid' checked='checked'/></td></tr>";
	else
		$html .= "<td><input class='input' type='checkbox' id='valid' name='valid'/></td></tr>";
		
	$html .= "</table><p>\n";
if($seriesId)
	$html .= "<input class='button' name='save' type='submit' value='"._("Save")."'/>";
else
	$html .= "<input class='button' name='add' type='submit' value='"._("Add")."'/>";
	
	$html .= "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/seasonseries&amp;season=$season'\"/>
		  </p></form>";

$html .= TranslationScript("name");

showPage($title, $html);
?>
