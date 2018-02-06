<?php
include_once 'lib/season.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';

$backurl = utf8entities(empty($_SERVER['HTTP_REFERER'])?"":$_SERVER['HTTP_REFERER']);
$gameId = $_GET["game"];
$info = GameResult($gameId);

if(!empty($_GET["season"]))
	$season = $_GET["season"];

$title = _("Game") . " $gameId";
$html = "";

//game parameters
$gp = array(
	"hometeam"=>"",
	"visitorteam"=>"",
	"scheduling_name_home"=>"",
	"scheduling_name_visitor"=>"",
	"reservation"=>"",
	"time"=>"",
	"pool"=>$info['pool'],
	"valid"=>1,
	"respteam"=>0,
	"name"=>""
	);
	
//process itself on submit
if(!empty($_POST['save']))
	{
	$backurl = $_POST['backurl'];
	$ok = true;
	if (empty($_POST['pseudo'])) {
		$gp['hometeam'] = $_POST['home'];
		$gp['visitorteam'] = $_POST['away'];
	} else {
		$gp['scheduling_name_home'] = $_POST['home'];
		$gp['scheduling_name_visitor'] = $_POST['away'];
	}
	$gp['reservation'] = $_POST['place'];
	
	$res = ReservationInfo($gp['reservation']);
	if(!empty($_POST['time'])){
		$gp['time'] = ToInternalTimeFormat((ShortDate($res['starttime']) . " " .$_POST['time']));
	}else{
// Chris: I don't see why we want to do that		
//		$gp['time'] = ToInternalTimeFormat($res['starttime']);
	}
	
	
	$gp['pool'] = $_POST['pool'];
	
	if(!empty($_POST['valid']))
		$gp['valid'] = 1;
	else
		$gp['valid'] = 0;
	
	if(!empty($_POST['respteam']))
		$gp['respteam'] = $_POST['respteam'];
	
	if(!empty($_POST['name']))
		$gp['name'] = $_POST['name'];

	
	SetGame($gameId, $gp);
	
	$userid = $_POST['userid'];
    if(empty($userid)){
      $userid = UserIdForMail($_POST['email']);
    }
    if(IsRegistered($userid)){
      AddSeasonUserRole($userid, 'gameadmin:'.$gameId,$season);
    }
    if (!empty($backurl)) {
      session_write_close();
        header("location:$backurl");
      }
	}

function TeamSelectionList($name, $selected, $schedule_selected, $poolId) {
  $html = "";
  $html .= "<select class='dropdown' name='$name'>";
  $html .= "<option class='dropdown' value='0'></option>";
  
  $pseudoteams = false;
  $teams = PoolTeams($poolId);
  if (count($teams) == 0) {
    $teams = PoolSchedulingTeams($poolId);
    $pseudoteams = true;
  }
  
  $teamlist = "";
  foreach ($teams as $row) {
    if ($pseudoteams) {
      if ($row['scheduling_id'] == $schedule_selected) {
        $teamlist .= "<option class='dropdown' selected='selected' value='" . utf8entities($row['scheduling_id']) . "'>" .
           utf8entities($row['name']) . "</option>\n";
      } else {
        $teamlist .= "<option class='dropdown' value='" . utf8entities($row['scheduling_id']) . "'>" .
           utf8entities($row['name']) . "</option>\n";
      }
    } else {
      if ($row['team_id'] == $selected) {
        $teamlist .= "<option class='dropdown' selected='selected' value='" . utf8entities($row['team_id']) . "'>" .
           utf8entities($row['name']) . "</option>\n";
      } else {
        $teamlist .= "<option class='dropdown' value='" . utf8entities($row['team_id']) . "'>" .
           utf8entities($row['name']) . "</option>\n";
      }
    }
  }
  $html .= $teamlist;
  $html .= "</select>";
  
  if ($pseudoteams) {
    $html .= "<div><input type='hidden' name='pseudo' value='1' /></div>";
  }
  return $html;
}
	
//common page
addHeaderScript('script/disable_enter.js.inc');

include_once 'lib/yui.functions.php';
addHeaderText(yuiLoad(array("utilities","calendar", "datasource", "autocomplete")));

$headerText = <<<EOT
<link rel="stylesheet" type="text/css" href="script/yui/calendar/calendar.css" />

<script type="text/javascript">
<!--

YAHOO.namespace("calendar");

YAHOO.calendar.init = function() {

	YAHOO.calendar.cal1 = new YAHOO.widget.Calendar("cal1","calContainer1");
	YAHOO.calendar.cal1.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal1.render();

	function handleCal1Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer1"); 
		
		if(containerDiv.style.display == "none"){
			updateCal("date",YAHOO.calendar.cal1);
			YAHOO.calendar.cal1.show();
		}else{
			YAHOO.calendar.cal1.hide();
		}
	}
	
	// Listener to show the Calendar when the button is clicked
	YAHOO.util.Event.addListener("showcal1", "click", handleCal1Button);
	YAHOO.calendar.cal1.hide();
	
	function handleSelect1(type,args,obj) {
			var dates = args[0]; 
			var date = dates[0];
			var year = date[0], month = date[1], day = date[2];
			
			var txtDate1 = document.getElementById("date");
			txtDate1.value = day + "." + month + "." + year;
		}

	function updateCal(input,obj) {
            var txtDate1 = document.getElementById(input);
            if (txtDate1.value != "") {
				var date = txtDate1.value.split(".");
				obj.select(date[1] + "/" + date[0] + "/" + date[2]);
				obj.cfg.setProperty("pagedate", date[1] + "/" + date[2]);
				obj.render();
            }
        }
	YAHOO.calendar.cal1.selectEvent.subscribe(handleSelect1, YAHOO.calendar.cal1, true);
}
YAHOO.util.Event.onDOMReady(YAHOO.calendar.init);
//-->
</script>
EOT;

addHeaderText($headerText);

$html .= "<h2>"._("Edit game")."</h2>\n";	
$html .= "<form method='post' action='?view=admin/editgame&amp;season=$season&amp;game=$gameId'>";
$info = GameResult($gameId);
$pool_info = PoolInfo($info['pool']);
$seriesId = $pool_info['series'];
$poolId=$info['pool'];

if(GameHasStarted($info))
	{
	$html .= "<p>"._("Game played").". "._("Final score").": ".$info['homescore'] ." - ". $info['visitorscore']."</p>";
	}
$html .= "<ul>";
$html .= "<li><a href='?view=user/addresult&amp;game=".$gameId."'>"._("Change game result")."</a></li>";
$html .= "<li><a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Change game roster")."</a></li>";
$html .= "<li><a href='?view=user/addscoresheet&amp;game=".$gameId."'>"._("Change game score sheet")." </a></li>";
if(ShowDefenseStats())
{
$html .= "<li><a href='?view=user/adddefensesheet&amp;game=".$gameId."'>"._("Change game defense sheet")." </a></li>";
}

$html .= "<li><a href='?view=user/pdfscoresheet&amp;game=".$gameId."'>"._("Print score sheet")." </a></li>";
$html .= "<li><a href='?view=user/addmedialink&amp;game=$gameId'>"._("Add media")."</a></li>";
$html .= "</ul>\n";

$html .= "<table class='formtable'>";


$html .= "<tr><td class='infocell'>"._("Home team").":</td><td>";
$html .= TeamSelectionList('home', $info['hometeam'], $info['scheduling_name_home'], $poolId);
$html .= "</td></tr>\n";

$html .= "<tr><td class='infocell'>"._("Guest team").":</td><td>";
$html .= TeamSelectionList('away', $info['visitorteam'], $info['scheduling_name_visitor'], $poolId);
$html .= "</td></tr>\n";


$html .= "<tr><td class='infocell'>"._("Location").":</td><td><select class='dropdown' name='place'>\n";


$html .= "<option class='dropdown' value='0'></option>";

// places
$places = SeasonReservations($season);

foreach($places as $row){
	if($row['id'] == $info['reservation']){
		$html .= "<option class='dropdown' selected='selected' value='".utf8entities($row['id'])."'>";
		$html .= utf8entities($row['reservationgroup']) ." ". utf8entities($row['name']) .", "._("Field")." ".utf8entities($row['fieldname'])." (".JustDate($row['starttime']) .")";
		$html .= "</option>";
	}else{
		$html .= "<option class='dropdown' value='".utf8entities($row['id'])."'>";
		$html .= utf8entities($row['reservationgroup']) ." ". utf8entities($row['name']) .", "._("Field")." ".utf8entities($row['fieldname'])." (".JustDate($row['starttime']) .")";
		$html .= "</option>";
	}
}
$html .= "</select></td></tr>\n";	

$html .= "<tr><td class='infocell'>"._("Starting time")." (hh:mm):</td>
<td><input class='input' id='time' name='time' value='".DefHourFormat($info['time'])."'/></td></tr>\n";


$html .= "<tr><td class='infocell'>"._("Division").":</td><td><select class='dropdown' name='pool'>\n";
$html .= "<option class='dropdown' value='0'></option>";

$pools = SeasonPools($season);
foreach($pools as $row){
	if($row['pool_id'] == $info['pool'])
		$html .= "<option class='dropdown' selected='selected' value='".utf8entities($row['pool_id'])."'>". utf8entities(U_($row['seriesname'])).", ". utf8entities(U_($row['poolname'])) ."</option>";
	else
		$html .= "<option class='dropdown' value='".utf8entities($row['pool_id'])."'>". utf8entities(U_($row['seriesname'])).", ". utf8entities(U_($row['poolname'])) ."</option>";
}
$html .= "</select></td></tr>\n";	

$html .= "<tr><td class='infocell'>"._("Responsible team").":</td><td>";
$html .= TeamSelectionList('respteam', $info['respteam'],$info['respteam'], $poolId);
$html .= "</td></tr>";
$html .= "<tr><td class='infocell' style='vertical-align:text-top;'>"._("Responsible person").":</td><td>";
$users = GameAdmins($gameId);
foreach($users as $user){
  $html .= utf8entities($user['name'])."<br/>";
}
$html .= _("User Id")." <input class='input' size='20' name='userid'/> "._("or")." ";
$html .= _("E-Mail")." <input class='input' size='20' name='email'/>\n";
$html .= "</td></tr>";

$html .= "<tr><td class='infocell'>"._("Name").":</td>";
$html .= "<td>".TranslatedField("name", $info['gamename']);
$html .= TranslationScript("name");
$html .= "</td></tr>\n";

if(intval($info['valid']))
	{
	$html .= "<tr><td class='infocell'>"._("Valid").":</td>
		<td><input class='input' type='checkbox' id='valid' name='valid' checked='checked' value='".utf8entities($info['valid'])."'/></td></tr>";
	}
else
	{
	$html .= "<tr><td class='infocell'>"._("Valid").":</td>
		<td><input class='input' type='checkbox' id='valid' name='valid' value='".utf8entities($info['valid'])."'/></td></tr>";
	
	}
		
$html .= "</table>";

$html .= "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
$html .= "<p><input class='button' name='save' type='submit' value='"._("Save")."'/>";
if (!empty($backurl)) {
  $html .= "<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/>";
}
$html .= "</p></form>\n";

showPage($title, $html);
?>
