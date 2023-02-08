<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/common.functions.php';
$backurl = utf8entities(isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:"");

$seriesId = 0;
if(!empty($_GET["pool"]))
  $poolId = intval($_GET["pool"]);

if(!empty($_GET["series"]))
  $seriesId = intval($_GET["series"]);

if(!empty($_GET["season"]))
  $season = $_GET["season"];

$title = _("Teams");
$reroute = "";

//process itself on submit
if(!empty($_POST['save'])) {
  $backurl = utf8entities($_POST['backurl']);
  $teams = PoolTeams($poolId);

  //Remove un-checked teams
  foreach($teams as $team){
    $found=false;
    if(!empty($_POST["selcheck"])) {
      foreach($_POST["selcheck"] as $selId) {
        if($team['team_id']==$selId) {
          $found=true;
          break;
        }
      }
    }
    if(!$found) {
      PoolSetTeam($poolId, $team['team_id'],0,0);
    }
  }

  if(!empty($_POST["selcheck"])) {
    foreach($_POST["selcheck"] as $selId) {
      $found=false;
      $rank = 0;
      if(!empty($_POST["rank$selId"]))
      	$rank = $_POST["rank$selId"];
      $teams = PoolTeams($poolId);
      foreach($teams as $team){
        if($team['team_id']==$selId) {
          $found=true;
          break;
        }
      }
      if($found){
        PoolSetTeam($poolId, $team['team_id'],$rank,$poolId);
      }else{
        PoolSetTeam(0, $selId,$rank,$poolId);
      }
    }
  }
  ResolvePoolStandings($poolId);
} else if(!empty($_POST['move'])) {

  function applyMoves($poolId, $moves) {
    usort($moves, uo_create_key_comparator('fromplacing'));
//    create_function('$a,$b','return $a[\'fromplacing\']==$b[\'fromplacing\']?0:($a[\'fromplacing\']<$b[\'fromplacing\']?-1:1);'));
      
    $series = PoolSeries($poolId);
    if (hasEditSeriesRight($series)) {
      for ($i = 0; $i < count($moves); $i++) {
        $query = sprintf("
			UPDATE uo_moveteams SET torank=%s,scheduling_id=%s
			WHERE fromplacing=%s AND frompool=%s", mysql_adapt_real_escape_string($moves[$i]['torank']),
          mysql_adapt_real_escape_string($moves[$i]['scheduling_id']),
          mysql_adapt_real_escape_string($moves[$i]['fromplacing']),
          mysql_adapt_real_escape_string($moves[$i]['frompool']));

        $result = mysql_adapt_query($query);
        if (!$result)
          die($query . 'Invalid query: ' . mysql_adapt_error());
      }
    }
  }
  
  $newmoves = array();
  if (isset($_POST['movefrompool'])) {
    for ($i = 0; $i < count($_POST['movefrompool']); ++$i) {
      $newmoves[] = array('frompool' => $_POST['movefrompool'][$i], 'fromplacing' => $_POST['movefromplacing'][$i],
        'torank' => $_POST['movetorank'][$i], 'scheduling_id' => $_POST['movescheduling_id'][$i]);
    }
  }
  applyMoves($poolId, $newmoves);
  
  $swapped = PoolConfirmMoves($poolId, isset($_POST['visible']) && $_POST['visible'] == "on");
  $backurl = $_POST['backurl'];
  session_write_close();
  header("location:$backurl");
}else if(!empty($_POST['ties'])){
  //	$backurl = $_POST['backurl'];
  AutoResolveTiesInSourcePools($poolId);
  //	header("location:$backurl");
}

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setId(id) {
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
  }

//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu();
contentStart();

echo "<form method='post' action='?view=admin/serieteams&amp;series=$seriesId&amp;pool=$poolId&amp;season=$season'>";

echo "<h1>".utf8entities(PoolName($poolId))."</h1>\n";


$poolinfo = PoolInfo($poolId);
$continuation = intval($poolinfo['continuingpool']);
$moves = null;


function getInput($name, $value) {
  $value=utf8entities($value);
  return "<input type='hidden' name='$name' value='$value'/>";
  
}

// for Swiss-draw: come up with moves such that no team plays
// a team that they have played previously
// this can only be done if all ties from the previous pool have been resolved
if ($poolinfo['type']==3){
  $moves = array();
  $SwissOK=CheckSwissdrawMoves($poolId, $moves);
  //returned -1 if ties were detected
  //-2 if not all activeranks were found
  // 1+duplicates if a correct Swissdraw move has been found
  foreach ($moves as $move) {
    echo getInput('movefrompool[]', $move['frompool']);
    echo getInput('movefromplacing[]', $move['fromplacing']);
    echo getInput('movetorank[]', $move['torank']);
    echo getInput('movescheduling_id[]', $move['scheduling_id']);
  }
}else{
  $SwissOK=0;
}
if ($poolinfo['type']==2){
  $PlayoffOK=CheckPlayoffMoves($poolId);
  // returns -1 if the number of teams in the pool is odd, i.e. one team will have a BYE,
  // and at least one team already had a BYE previously

  // returns 0 if everything is OK
}

$moved = PoolIsAllMoved($poolId);
$numMoves = count(PoolMovingsToPool($poolId));

$pstart = 0;
if ($continuation && $SwissOK==-1) {
  echo "<p>" .
    _(
      "Ties detected in previous pool. Swissdraw moves only make sense if there are no ties in the previous pools. Do you want to automatically resolve these ties?") .
    "</p>";
  echo "<p><input class='button' name='ties' type='submit' value='"._("Resolve Ties")."'/>\n";
  $pstart = 1;
}elseif($continuation && $SwissOK==-2) {
  echo "<p>" . _("Swissdraw moves cannot be determined, because the previous pool has not been played yet.") . "</p>\n";
}elseif(!$continuation || ($moved && $numMoves>0)) {
  echo "<h2>"._("Select teams").":</h2>\n";
  echo "<table class='admintable'>\n";

  $allteams = PoolTeams($poolId,"seed");
  $serieteams = SeriesTeamsWithoutPool($seriesId);

  if(count($allteams)>0 || count($serieteams)>0) {
    echo "<tr><th>"._("Plays")."</th><th>"._("Seed")."</th>
			<th>"._("Name")."</th>	<th>"._("Club")."</th></tr>\n";
  }else{
    echo "<tr><td>"._("No teams without a pool")."</td></tr>\n";
  }

  foreach($allteams as $team){
    echo "<tr>";
    echo "<td style='text-align: center;'>
		<input onchange=\"toggleField(this,'rank".$team['team_id']."');\"  type='checkbox' name='selcheck[]' checked='checked' value='".utf8entities($team['team_id'])."'/></td>";
    echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input'
			name='rank".$team['team_id']."' id='rank".$team['team_id']."' style='width: 20px' maxlength='3' size='2' value='".utf8entities($team['rank'])."'/></td>";
    echo "<td>".utf8entities($team['name'])."</td>";
    echo "<td>".utf8entities($team['clubname'])."</td>";
    echo "</tr>\n";
  }
  if(count($allteams)){
    echo "<tr><td colspan='5' class='menuseparator'></td></tr>\n";
  }

  foreach($serieteams as $team){
    echo "<tr>";
    echo "<td style='text-align: center;'>
		<input onchange=\"toggleField(this,'rank".$team['team_id']."');\"  type='checkbox' name='selcheck[]' value='".utf8entities($team['team_id'])."'/></td>";
    echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input'
			name='rank".$team['team_id']."' id='rank".$team['team_id']."' style='width: 20px' maxlength='3' size='2' value='".utf8entities($team['rank'])."'/></td>";
    echo "<td>".utf8entities($team['name'])."</td>";
    echo "<td>".utf8entities($team['clubname'])."</td>";
    echo "</tr>\n";
  }
  if(count($serieteams)){
    echo "<tr><td colspan='5' class='menuseparator'></td></tr>\n";
  }

  echo "</table>";
  echo "<p><input class='button' name='save' type='submit' value='"._("Save")."'/>";
  $pstart = 1;
}else{
  $playoffpool = false;
  if ($SwissOK == -3)
    echo "<p>" . _("Odd number of teams detected. You can fix this in 'Game management'.") . "</p>\n";
  
  if ($SwissOK > 1) {
    echo "<p><strong>" .
      sprintf(_("Did not find arrangement without duplicates. Arrangement with %d duplicate(s) found."), $SwissOK - 1) .
      "</strong></p>";
    
    $mvgames = intval($poolinfo['mvgames']);
    $games2 = PoolGetGamesToMove($poolId, $mvgames);
    
    $team1 = null;
    $team2 = null;
    foreach ($moves as $row) {
      $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing'], $poolinfo['type'] != 2); // do not count the BYE team if we are moving to a playoff pool
      if ($team1 == null) {
        $team1 = $team['team_id'];
      } else {
        $team2 = $team['team_id'];
        foreach ($games2 as $game2) {
          $game2 = GameInfo($game2);
          if (
            ($team1 == $game2['hometeam'] && $team2 == $game2['visitorteam']) ||
            ($team1 == $game2['visitorteam'] && $team2 == $game2['hometeam'])) {
              echo "<p><strong>" . $game2['hometeamname'] . " - " . $game2['visitorteamname'] . "</strong></p>";
          }
        }
        $team1 = null;
      }
    }
    echo "<br />"; 
  }
  
  echo "<table class='admintable'><tr>
		<th>"._("From pool")."</th>
		<th>"._("From pos.")."</th>
		<th>"._("Team")."</th>
		<th>"._("To pos.")."</th>
		<th>"._("To pool")."</th>
		<th>"._("Name in Schedule")."</th></tr>";

  if ($moves == null)
    $moves = PoolMovingsToPool($poolId);
  $BYEs=false;

  foreach($moves as $row){
    echo "<tr>";
    echo "<td style='white-space: nowrap'>".utf8entities($row['name'])."</td>";
    if(!$playoffpool){
      $frompool = PoolInfo($row['frompool']);
      if($frompool['type']==2){
        $playoffpool=true;
      }
    }
    $team = PoolTeamFromStandings($row['frompool'],$row['fromplacing'],$poolinfo['type']!=2);  // do not count the BYE team if we are moving to a playoff pool
//    if ($team['name']=="") {
//   		die('yay! '.$team['team_id']);
//    }
    echo "<td class='center'>".intval($row['fromplacing'])."</td>";
    if (empty($team)) {
      echo "<td></td>";
    } else {
    if(TeamPoolCountBYEs($team['team_id'],$row['frompool'])>0){
      echo "<td class='highlight'><b>".utf8entities($team['name'])."</b></td>";
      $BYEs=true;
    }else{
      echo "<td class='highlight'>".utf8entities($team['name'])."</td>";
      $BYEs=false;
    }
    }
    echo "<td class='center'>".intval($row['torank'])."</td>";
    echo "<td style='white-space: nowrap'>".utf8entities(PoolName($poolId))."</td>";
    echo "<td>".utf8entities($row['sname'])."</td>";
    echo "</tr>\n";
  }
  echo "</table>";

  if ($BYEs) {
    echo "<p>teams in <b>bold</b> had a BYE previously</p>";
  }

  if ($poolinfo['type']==2 && $PlayoffOK==-1) {
    echo "<p><b>" . _("Warning:"). "</b> " . _("You are about to move an odd number of teams which might result in one of the teams having another BYE.") . "</p>";
  }
  echo "<p><a href='?view=admin/poolmoves&amp;season=$season&amp;series=".$seriesId."&amp;pool=".$poolId."'>"._("Manage moves")."</a></p>";

  echo "<p>"._("Games to move").":</p>";
  $mvgames = intval($poolinfo['mvgames']);
  $games = PoolGetGamesToMove($poolId, $mvgames);

  if(count($games)) {
    echo "<table class='infotable'>";
    foreach ($games as $id ) {
      echo "<tr>";
      $result = GameResult($id);
      echo "<td>".DefWeekDateFormat($result['time'])."</td>";
      echo "<td>".utf8entities(TeamName($result['hometeam']))."</td>";
      echo "<td> - </td>";
      echo "<td>".utf8entities(TeamName($result['visitorteam']))."</td>";
      echo "<td>". intval($result['homescore']) ."</td><td> - </td><td>". intval($result['visitorscore'])."</td>";
      echo "</tr>\n";
    }
    echo "</table>";
  }else{
    echo "<p><i>"._("No games to move").".</i></p>";
  }

  echo "<p><input class='input' type='checkbox' id='visible' name='visible' checked='checked'/>";
  echo _("Make this pool visible on menu")."</p>";
  
  echo "<p><input class='button' name='move' type='submit' value='"._("Confirm moves")."'/>";
  $reroute = "<p><a href='?view=admin/seasonstandings&season=$season&amp;series=$seriesId#P$poolId'>" . _("... or change ranking of from pool and confirm manually") ."</a></p>\n";
  $pstart = 1;
}
if ($pstart == 0)
  echo "<p>";
echo "<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/></p>";
echo "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
echo $reroute;
echo "</form>\n";

contentEnd();
pageEnd();
?>