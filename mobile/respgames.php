<?php
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
$html = "";
mobilePageTop(_("Game responsibilities"));

$season = CurrentSeason();
$reservationgroup = "";
$location = "";
$showall = false;
$day="";
$dayPar="";
$locationPar="";
$allPar="";
$massPar="";
$rgPar="";

if(isset($_GET['rg'])){
	$reservationgroup = $_GET['rg'];
	$rgPar = "&amp;rg=".urlencode($reservationgroup);
}

if(isset($_GET['loc'])){
	$location = $_GET['loc'];
	$locationPar = "&amp;loc=".urlencode($location);
}

if(isset($_GET['day'])){
	$day = $_GET['day'];
	$dayPar = "&amp;day=".urlencode($day);
}

if(isset($_GET['all'])){
	$showall = intval($_GET['all']);
	$allPar = "&amp;all=1";
}

if (!empty($_GET["massinput"])) {
  $_SESSION['massinput'] = true;
  $mass = "1";
  $massPar = "&amp;massinput=1";
} else {
  $_SESSION['massinput'] = false;
  $mass = "0";
  $massPar = "&amp;massinput=0";
}

//process itself on submit
$feedback = "";
if (!empty($_POST['save'])) {
	$feedback = GameProcessMassInput($_POST);
}


$respGameArray = GameResponsibilityArray($season);
$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";

if(count($respGameArray) == 0) {
	$html .= headerrow(_("No game responsibilities"));	
} else	{
	$prevdate="";
	$prevrg = "";
	$prevloc = "";
	if ($_SESSION['massinput']) {
		$html .= headerrow("<a class='button' href='?view=mobile/respgames$allPar$rgPar$locationPar$dayPar&amp;massinput=0'>" . _("Just display values") . "</a>");
	}else {
		$html .= headerrow("<a class='button' href='?view=mobile/respgames$allPar$rgPar$locationPar$dayPar&amp;massinput=1'>" . _("Mass input") . "</a>");
	}
	
	foreach ($respGameArray as $tournament => $resArray) {
		foreach($resArray as $resId => $gameArray) {
			foreach ($gameArray as $gameId => $game) {
				if (!is_numeric($gameId)) {
					continue;
				}
				
				if($showall){
					if(!empty($prevdate) && $prevdate != JustDate($game['time'])){
						$html .= "<tr><td colspan='3'>";
						$html .= "<hr/>";
						$html .= "</td></tr>\n";
					}
					$html .= gamerow($gameId, $game, $mass);
					$prevdate = JustDate($game['time']);
					continue;
				}
				
				if($prevrg != $game['reservationgroup']){
					if($reservationgroup == $game['reservationgroup']){
						$html .= headerRow("<b>".utf8entities($game['reservationgroup'])."</b>");
					}else{
						$html .= headerRow("+ <a href='?view=mobile/respgames&amp;rg=".urlencode($game['reservationgroup'])."$massPar'>".utf8entities($game['reservationgroup'])."</a>");
					}
					$prevrg = $game['reservationgroup'];
				}

				if ($reservationgroup == $game['reservationgroup']) {

					$gameloc = $game['location']."#".$game['fieldname'];
					
					if($prevloc != $gameloc){
						if($location == $gameloc && $day==JustDate($game['starttime'])){
							$html .= headerRow("&nbsp;<b>". utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname'])."</b>");
						}else{
							$html .= headerRow("&nbsp;+<a href='?view=mobile/respgames&amp;rg=".urlencode($game['reservationgroup'])
							  ."&amp;loc=".urlencode($gameloc)."&amp;day=".urlencode(JustDate($game['starttime']))."$massPar'>"
							  . utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname'])."</a>");
						}
						$prevloc = $gameloc;
					}
					
					if ($location == $gameloc && $day == JustDate($game['starttime'])) {
						$html .= gamerow($gameId, $game, $mass);
					}
				}

			}
		}
	}
}
if ($_SESSION['massinput']) {
	$html .= headerRow("<input class='button' name='save' type='submit' value='" . _("Save") . "' onclick='confirmLeave(null, false, null);'/>");
}
if ($feedback)
  $html .= headerRow($feedback);

if($showall){
	$html .= headerRow("<a href='?view=mobile/respgames'>"._("Group games")."</a>");
}else{
	$html .= headerRow("<a href='?view=mobile/respgames&amp;all=1'>"._("Show all")."</a>");
}
$html .= "</table>\n";
$html .= "</form>";

echo $html;
		
pageEnd();

function headerRow($content) {
  return "<tr><td colspan='3'><p>$content</p></td></tr>\n";
}

function gamerow($gameId, $game, $mass){
     global $thinsp, $nobrthinsp;
     $gamesep = "${thinsp}-${thinsp}";
	$ret = "<tr><td>&nbsp;&nbsp;";
	$ret .= str_replace(" ", $nobrthinsp, ShortTimeFormat($game['time'])) ."</td><td>";
	if($game['hometeam'] && $game['visitorteam']){
	  $ret .= utf8entities($game['hometeamname']) . $gamesep . utf8entities($game['visitorteamname']) ."</td><td>";

		if ($mass=="1") {
			$ret .= "<input type='hidden' id='scoreId" . $gameId . "' name='scoreId[]' value='$gameId'/>";
			$ret .= "<span style='white-space:nowrap'><input type='text' size='3' maxlength='3' style='width:4ex' value='" . (is_null($game['homescore'])?"":intval($game['homescore'])) . "' id='homescore$gameId' name='homescore[]' oninput='confirmLeave(this, true, null);' />";
			$ret .= "<input type='text' size='3' maxlength='3' style='width:4ex' value='" . (is_null($game['visitorscore'])?"":intval($game['visitorscore'])) . "' id='visitorscore$gameId' name='visitorscore[]' oninput='confirmLeave(this, true, null);' /></span>";
			// $ret .= "<input class='button' name='saveOne' type='submit' value='" . _("Save") . "' onPress='setSaved(".$gameID.")'/></td></tr><tr><td>\n";
		}elseif(GameHasStarted($game)){
			$ret .=  "<a style='white-space: nowrap' href='?view=mobile/gameplay&amp;game=".$gameId."'>".intval($game['homescore']) ." - ". intval($game['visitorscore'])."</a>";
		}else{
			$ret .= intval($game['homescore']) ." - ". intval($game['visitorscore']);
		}
		$ret .= "</td></tr><tr><td colspan='3'>\n";
		$ret .= "&nbsp;&nbsp;";
		$ret .=  "<a style='white-space: nowrap' href='?view=mobile/addresult&amp;game=".$gameId."'>"._("Result")."</a> | ";
		$ret .=  "<a style='white-space: nowrap' href='?view=mobile/addplayerlists&amp;game=".$gameId."&amp;team=".$game['hometeam']."'>"._("Players")."</a> | ";
		$ret .=  "<a style='white-space: nowrap' href='?view=mobile/addscoresheet&amp;game=$gameId'>"._("Scoresheet")."</a>";
		$ret .= "</td></tr>\n";
	}else{
		$ret .= utf8entities($game['phometeamname']) . $gamesep . utf8entities($game['pvisitorteamname']);
		$ret .= "</td></tr>\n";
	}
	return $ret;
}
?>
