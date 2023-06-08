<?php
include_once 'lib/pool.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/reservation.functions.php';


if(!empty($_GET["season"]))
	$season = $_GET["season"];

if(!empty($_GET["series"])){
	$seriesId = $_GET["series"];
	if(empty($season)){
		$season = SeriesSeasonId($seriesId);
	}
}

$seriesinfo = SeriesInfo($seriesId);
$rounds = 1;
$nomutual = 0;
$matches = 1;
$crosses = 1;
$homeresp = isset($_POST["homeresp"]);

$title =U_($seriesinfo['name']).": "._("Games");
$html = "";

if(!empty($_POST['generate'])){
	if(!empty($_POST['rounds'])){
		$rounds = $_POST['rounds'];
	}
	if(!empty($_POST['matches'])){
		$matches = $_POST['matches'];
	}
	if(!empty($_POST['crosses'])){
		$crosses = $_POST['crosses'];
	}
	$nomutual = isset($_POST["nomutual"]);
	
	$pools = SeriesPools($seriesId);
	
	foreach($pools as $pool){
		$info = PoolInfo($pool['pool_id']);
		if($info['type']==1){
			// round-robin
			if($info['mvgames']==2){
				GenerateGames($pool['pool_id'],$rounds,true,$nomutual,$homeresp);
			}else{
				GenerateGames($pool['pool_id'],$rounds,true, false, $homeresp);
			}
		}elseif($info['type']==2){
		  // play-off
			GenerateGames($pool['pool_id'],$matches,true);
			//generate pools needed to solve standings
			$generatedpools = GeneratePlayoffPools($pool['pool_id'], true);
		
			//generate games into generated pools
			foreach($generatedpools as $gpool){
				GenerateGames($gpool['pool_id'],$matches,true);
			}
		}elseif($info['type'] == 4) {
		  // crossmatch
		  $generatedgames=GenerateGames($pool['pool_id'], $rounds, true, $nomutual, $homeresp);
		}
	}
	session_write_close();
	header("location:?view=admin/seasonpools&season=$season");
}

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu();
contentStart();

$html .= "<form method='post' action='?view=admin/seriesgames&amp;season=$season&amp;series=$seriesId'>";

$html .= "<h2>" . _("Creation of games") . "</h2>\n";
$html .= "<h3>" . _("Round Robin pools") . "</h3>\n";
$html .= "<p>" . _("Game rounds") . ": <input class='input' size='2' name='rounds' value='$rounds'/></p>\n";
$html .= "<p><input class='input' type='checkbox' name='nomutual'";
if ($nomutual) {
  $html .= "checked='checked'";
}
$html .= "/> " . _("Do not generate mutual games for teams moved from same pool, if pool format includes mutual games") .
     ".</p>";

$html .= "<h3>" . _("Play-off pools") . "</h3>\n";
$html .= "<p>" . _("best of") . " <input class='input' size='2' name='matches' value='$matches'/></p>\n";

$html .= "<h3>" . _("Crossover pools") . "</h3>\n";
$html .= "<p>" . _("best of") . " <input class='input' size='2' name='crosses' value='$crosses'/></p>\n";

$html .= "<p>" . _("Home team has rights to edit game score sheet") .
     ":<input class='input' type='checkbox' name='homeresp'";
if (isRespTeamHomeTeam()) {
  $html .= "checked='checked'";
}
$html .= "/></p>";

$html .= "<p><input type='submit' name='generate' value='" . _("Generate all games") . "'/></p>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>