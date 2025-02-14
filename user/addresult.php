<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/standings.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once 'lib/twitter.functions.php';
}
$html = "";
$html2 = "";
$gameId = intval($_GET["game"]);
$gameinfo = GameInfo($gameId);
$seasoninfo = SeasonInfo($gameinfo['season']);

$LAYOUT_ID = ADDRESULT;
$title = _("Result");

//process itself if save button was pressed
if(!empty($_POST['save'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	LogGameUpdate($gameId,"result: $home - $away", "addresult");
	$ok=GameSetResult($gameId, $home, $away);
	if($ok)	{
		$html2 .= "<p>"._("Final result saved: $home - $away").". ";
		ResolvePoolStandings(GamePool($gameId));
		PoolResolvePlayed(GamePool($gameId));
		if(IsTwitterEnabled()){
			TweetGameResult($gameId);
		}
	    $html2 .=  _("Winner is"). " <span style='font-weight:bold'>";
        if($home>$away){
          $html2 .= utf8entities($gameinfo['hometeamname']);
        }else{
          $html2 .= utf8entities($gameinfo['visitorteamname']);
        }
        $html2 .= "</p>";
	}
	$gameinfo = GameInfo($gameId);
}elseif(isset($_POST['update'])) {
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok=GameUpdateResult($gameId, $home, $away);
	$html2 .= "<p>"._("Game ongoing. Current score: $home - $away").".</p>";
	$gameinfo = GameInfo($gameId);
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
//content
$menutabs[_("Result")]= "?view=user/addresult&game=$gameId";
$menutabs[_("Players")]= "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Score sheet")]= "?view=user/addscoresheet&game=$gameId";
if($seasoninfo['spiritpoints'] && isSeasonAdmin($seasoninfo['season_id'])){
  $menutabs[_("Spirit points")]= "?view=user/addspirit&game=$gameId";
}
if(ShowDefenseStats())
{
  $menutabs[_("Defense sheet")]= "?view=user/adddefensesheet&game=$gameId";
}


pageMenu($menutabs);

$html .= "<form  method='post' action='?view=user/addresult&amp;game=".$gameId."'>
<table cellpadding='2'>
<tr><td><b>". utf8entities($gameinfo['hometeamname']) ."</b></td><td><b> - </b></td><td><b>". utf8entities($gameinfo['visitorteamname']) ."</b></td></tr>
<tr>
<td><input class='input' name='home' value='". $gameinfo['homescore'] ."' maxlength='2' size='5'/></td>
<td> - </td>
<td><input class='input' name='away' value='". $gameinfo['visitorscore'] ."' maxlength='2' size='5'/></td></tr>
</table>";

if($gameinfo['homevalid']==2) {
	$poolInfo=PoolInfo($gameinfo['pool']);
	$html .= "<p>"."The home team is the BYE team. You should use the suggested result: ".$poolInfo['forfeitagainst']." - ".$poolInfo['forfeitscore']."</p>";	
} elseif($gameinfo['visitorvalid']==2){
	$poolInfo=PoolInfo($gameinfo['pool']);
	$html .= "<p>"."The visitor team is the BYE team. You should use the suggested result: ".$poolInfo['forfeitscore']." - ".$poolInfo['forfeitagainst']."</p>";	
}

$html .= "<p>"._("If game ongoing, update as current result: ")."    
	<input class='button' type='submit' name='update' value='"._("update")."'/></p>";

$html .= $html2;

$html .= "<p>    
		<input class='button' type='submit' name='save' value='"._("Save as final result")."'/>
	</p></form>";


echo $html;

//common end
contentEnd();
pageEnd();
?>
