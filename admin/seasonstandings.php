<?php
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$season = $_GET["season"];
$single = 0;
$series_id = -1;
CurrentSeries($season, $series_id, $single, _("Pool Rankings"));

$title = utf8entities(SeasonName($season)) . ": " . _("Pool Rankings");
$html = "";

ensureEditSeriesRight($series_id);

$_SESSION['hide_played_pools'] = !empty($_SESSION['hide_played_pools']) ? $_SESSION['hide_played_pools'] : 0;

if(!empty($_GET["v"])) {
  $visibility = $_GET["v"];
  
  if ($visibility == "pool") {
    $_SESSION['hide_played_pools'] = $_SESSION['hide_played_pools'] ? 0 : 1;
  }
}

//process itself on submit
if(!empty($_POST['remove_x'])){
  $pool = $_POST['PoolId'];
  $team = $_POST['TeamDeleteId'];
  if(CanDeleteTeamFromPool($pool, $team)){
    PoolDeleteTeam($pool, $team);
    $move = PoolGetMoveByTeam($pool, $team);
    if (count($move)) {
      PoolUndoMove($move[0]['frompool'], $move[0]['fromplacing'], $pool);
    }
  }
}

if(!empty($_POST['recalculate'])){
  ResolvePoolStandings($_POST['PoolId']);
}

if (!empty($_POST['editType'])) {
  $editPool = $_POST['PoolId'];
  editPoolStandings($_POST['editType'], $editPool, $_POST['startId'], $_POST['editStart'], $_POST['editEnd'], $_POST['seedId'], $_POST['seed'], $_POST['rankId'], $_POST['rank']);
}

if (!empty($_POST['undoFromPlacing'])) {
  $place = -1;
  if ($_POST['undoFromPlacing'] == "from") {
    $moves = PoolMovingsFromPool($_POST['PoolId']);
  } else if ($_POST['undoFromPlacing'] == "to") {
    $moves = PoolMovingsToPool($_POST['PoolId']);
  } else {
    $place = $_POST['undoFromPlacing'];
  }
  
  if ($place == -1) {
    foreach ($moves as $row) {
      if ($row['ismoved']) {
        $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
        if (CanDeleteTeamFromPool($row['topool'], $team['team_id'])) {
          PoolUndoMove($row['frompool'], $row['fromplacing'], $row['topool']);
        }
      }
    }
  } else {
	PoolUndoMove($_POST['PoolId'], $_POST['undoFromPlacing'],$_POST['undoToPool']);
  }
}

if (!empty($_POST['confirmMoves'])) {
  $swapped = PoolConfirmMoves($_POST['PoolId']);
  if (!empty($swapped))
    $html .= "<p>" . $swapped . "</p>";
}

if (!empty($_POST['setVisible'])) {
  SetPoolVisibility($_POST['PoolId'], true);
} else if (!empty($_POST['setInvisible'])) {
  SetPoolVisibility($_POST['PoolId'], false);
} 

$get_link = function ($season, $seriesId, $single = 0, $htmlEntities = false) {
  $single = $single == 0 ? "" : "&single=1";
  $link = "?view=admin/seasonstandings&season=$season&series={$seriesId}$single";
  return $htmlEntities ? utf8entities($link) : $link;
};

$url_here_raw = $get_link($season, $series_id, $single, false);
$url_here = $get_link($season, $series_id, $single, true);

$html .= SeriesPageMenu($season, $series_id, $single, $get_link, "?view=admin/seasonseries&season=$season");

// $html .= "<form method='post' action='${url_here}'>";

// $series = SeasonSeries($season);

// $menutabs = array();
// foreach($series as $row){
//   if (!isset($menutabs[U_($row['name'])]))
//     $menutabs[U_($row['name'])]=array();
//   $menutabs[U_($row['name'])][]="?view=admin/seasonstandings&season=".$season."&series=".$row['series_id'];
// }
// $menutabs[_("...")]="?view=admin/seasonseries&season=".$season;
// $html .= pageMenu($menutabs,"?view=admin/seasonstandings&season=".$season."&series=".$series_id, false);

$html .= "<p>";
if($_SESSION['hide_played_pools']){
  $html .= "<a href='${url_here}&amp;v=pool'>"._("Show played pools")."</a> ";
}else{
  $html .= "<a href='${url_here}&amp;v=pool'>"._("Hide played pools")."</a> ";
}
$html .= "</p>";

$html .= "<form method='post' id='theForm' action='${url_here}'>";
$pools = SeriesPools($series_id);
if(!count($pools)){
  $html .= "<p>"._("Add pools first")."</p>\n";
}

$html .= "<h2><a name='Tasks' id='Tasks'>" . _("Tasks") . "</a></h2>";

$firstTask = false;
$missingresults = "";
foreach ($pools as $spool) {
  $poolId = $spool['pool_id'];
  $poolinfo = PoolInfo($poolId);
  if (!$poolinfo['played'] && PoolCountGames($poolId) > 0) {
    if ($missingresults)
      $missingresults .= ", ";
    else
      $missingresults .= " ";
    $missingresults .= poolLink($poolId, $spool['name']);
  }
  
  if (PoolIsMoveFromPoolsPlayed($poolId) && !PoolIsAllMoved($poolId)) {
    if (!$firstTask) {
      $html .= "<ul id='tasklist'>";
      $firstTask = true;
    }
    
    $deplist = "";
    $dependees = PoolDependsOn($poolId);
    foreach ($dependees as $dep) {
      if ($deplist) {
        $deplist .= ", ";
      } else {
        $deplist = " ";
      }
      $deplist .=  poolLink($dep['frompool'], $dep['name']);
    }
    
    $confirmtext = poolLink($poolId, sprintf(_("Confirm moves to pool %s."), PoolName($poolId)));
    
    $html .= "<li>" . sprintf(_("Check rankings of %s. Then: %s"), $deplist, $confirmtext) . "</li>\n"; 
  }
}
if ($firstTask) {
  $html .= "</ul>\n";
} else if ($missingresults) {
  $html .= "<p>" . _("Games results missing for") . $missingresults . "</p>";  
} else {
  $html .= "<p>" . _("Division completed.") ."</p>\n";
}

$teamNum = 0;
$poolNum = 0;
foreach ($pools as $spool) {
  $poolId = $spool['pool_id'];
  $start = $teamNum;
  $poolinfo = PoolInfo($poolId);
  
  if ($_SESSION['hide_played_pools'] && $poolinfo['played']) {
    continue;
  }
  
  $standings = PoolTeams($poolId, "rank");
  
  if ($poolNum>0)
    $html .= "<div class='right pagemenu_container'><a href='#Tasks'>" . _("Go to top") . "</a></div>\n";
  $html .= "<h2><a name='P" . $poolId . "' id='P" . $poolId . "'>" . utf8entities(U_($poolinfo['name'])) . "</a>
    <a href='?view=admin/addseasonpools&amp;pool=$poolId'><img class='button' src='images/settings.png' alt='E' title='"._("edit pool")."'/></a></h2>";
  
  $style = "class='admintable'";
  
  if ($poolinfo['played'] || $poolinfo['type'] == 100) {
    $style = "class='admintable tablelowlight'";
  }
  
  if ($poolinfo['type'] == 3) { // Swissdraw
    $getHeading = 'swissHeading';
    $getRow = 'swissRow';
    $columns = 10;
  } else {
    // regular pool or playoff
    $getHeading = 'regularHeading';
    $getRow = 'regularRow';
    $columns = 10;
  }
  $html .= "<table $style id='poolstanding_" . $poolId . "'>\n";
  $html .= $getHeading($poolId, $poolinfo, count($standings) > 0);
  
  if (count($standings)) {
    $seeds = array();
    foreach ($standings as $row) {
      if (isset($seeds[$row['rank']]))
        ++$seeds[$row['rank']];
      else 
        $seeds[$row['rank']] = 1;
    }
    foreach ($standings as $row) {
      $html .= $getRow($poolId, $poolinfo, $row, $teamNum, $seeds[$row['rank']] > 1);
      $teamNum++;
    }
    $html .= "<tr><th></th><th>";
    if (count(PoolTeams($poolId))) {
      $html .= "<input class='button' type='submit' name='recalculate' value='" . _("Recalc.") . "' onclick='setPoolId(" . $poolId . ");'/>";
    }
    $html .= "</th>";
    for ($i = 0; $i < $columns - 2; ++$i) {
      $html .= "<th></th>";
    }
    $html .= "</tr>\n";
  } else {
    $html .= "<tr>";
    for ($i=0; $i< $columns-1; ++$i)
      $html .= "<td class='center'>-</td>";
    $html .= "<td></td></tr>\n";
  }
  $html .= "</table>\n";
  $html .= "<input type='hidden' id='startId" . $poolNum . "' name='startId[]' value='" . ($poolId) . "'/>\n";
  $html .= "<input type='hidden' id='editStart" . $poolId . "' name='editStart[]' value='" . ($start) . "'/>\n";
  $html .= "<input type='hidden' id='editEnd" . $poolId . "' name='editEnd[]' value='" . ($teamNum - 1) . "'/>\n";

  if ($poolinfo['played'] || $poolinfo['type'] == 100) {
    $html .= "<p>" . _("Pool is completed:");
  } else {
    $html .= "<p>" . _("Pool games not completed:");
  }
  $html .=" <a href='?view=admin/seasongames&amp;season=".$season."&amp;series=".$series_id."&amp;single=$single&amp;pool=". $poolId . "'>". _("Games") ."</a></p>\n";

  $fromMoves = PoolMovingsFromPool($poolId);
  $toMoves = PoolMovingsToPool($poolId);
  
  $html .= "<div style='display: table; width: 100%;'><div style='display: table-cell; width:50%; vertical-align:top;'>\n";
  
  if (count($toMoves)) {
    $html .= moveTable($toMoves, "to", $poolId, $poolinfo, $season, $series_id, $single);
  }
  
  $html .= "</div><div style='display: table-cell; width:50%; vertical-align:top;'>\n";
  
  if (count($fromMoves)) {
    $html .= moveTable($fromMoves, "from", $poolId, $poolinfo, $season, $series_id, $single);
  }
  
  $html .= "</div></div>\n";
 
  ++$poolNum;
}

$html .= "<p>";
$html .= "<input type='hidden' id='PoolId' name='PoolId'/>\n";
$html .= "<input type='hidden' id='TeamDeleteId' name='TeamDeleteId'/>\n";
$html .= "<input type='hidden' id='editType' name='editType'/>\n";

$html .= "<input type='hidden' id='undoFromPlacing' name='undoFromPlacing'/>\n";
$html .= "<input type='hidden' id='undoToPool' name='undoToPool'/>\n";

$html .= "</p>";
$html .= "</form>\n";

addHeaderCallback(
  function () use ($url_here_raw) {
    $editstring = _("Edit");
    echo <<<EOG
    
<script type="text/javascript">
<!--
function setAnchor(pool) {
	var form = document.getElementById("theForm");
	form.action = "${url_here_raw}#P"+pool;
}

function setDeleteId(pool, team){
  var input = document.getElementById("PoolId");
  input.value = pool;
  var input = document.getElementById("TeamDeleteId");
  input.value = team;
  setAnchor(pool);
}

function setPoolId(pool) {
  document.getElementById("PoolId").value = pool;
  setAnchor(pool);
}

function edit(src, prefix, pool){
  var edit = src.value == "$editstring";
  var table = document.getElementById("poolstanding_"+pool);
  var displays = table.getElementsByClassName(prefix+"_display");
  var edits = table.getElementsByClassName(prefix+"_edit");
  for (i=0;i<displays.length;i=i+1) {
    YAHOO.util.Dom.setStyle(displays[i], "display", edit?"none":"inline");
    YAHOO.util.Dom.setStyle(edits[i], "display", edit?"inline":"none");
  }
  setAnchor(pool);
}

function setEditId(prefix, pool){
  var input = document.getElementById("PoolId");
  input.value = pool;
  var input = document.getElementById("editType");
  input.value = prefix;
  setAnchor(pool);
}

function setUndoMove(frompool, fromplacing, topool) {
  document.getElementById("PoolId").value = frompool;
  document.getElementById("undoFromPlacing").value = fromplacing;
  document.getElementById("undoToPool").value = topool;
  setAnchor(topool);
}

function setUndoPool(pool, from) {
  document.getElementById("PoolId").value = pool;
  document.getElementById("undoFromPlacing").value = from;
  setAnchor(pool);
}

function setConfirm(pool) {
  document.getElementById("PoolId").value = pool;
  setAnchor(pool);
}

function setCVisible(pool) {
  document.getElementById("PoolId").value = pool;
  setAnchor(pool);
}

//-->
</script>

EOG;
  });

showPage($title, $html);

function poolLink($id, $name) {
  return "<a href='#P". intval($id) . "'>". utf8entities($name) ."</a>";
}

function swissHeading($poolId, $poolinfo, $editbuttons) {
  $html = "";
  $html .= "<tr><th>" . _("Seed") . "&nbsp;" . ($editbuttons?editButton("seed", $poolId):"") . "</th>
            <th>" . _("Pos.") . "&nbsp;" . ($editbuttons?editButton("rank", $poolId):"") . "</th>
            <th>" . _("Team") . "</th>";
  $html .= "<th class='center'>" . _("Games") . "</th>";
  $html .= "<th class='center'>" . _("VP") . "</th>";
  $html .= "<th class='center'>" . _("Opp. VP") . "</th>";
  $html .= "<th class='center'>" . _("Margin") . "</th>";
  $html .= "<th class='center'>" . _("Goals") . "</th>";
  $html .= "<th class='center'>" . _("PwrR") . "</th>";
  $html .= "<th></th></tr>";
  return $html;
}

$lastPoolId = null;
$pwrCache = null;

function swissRow($poolId, $poolinfo, $row, $teamNum, $warn=false) {
  global $lastPoolId;
  global $pwrCache;
  $html = "";
  $vp = TeamVictoryPointsByPool($poolId, $row['team_id']);
  if ($pwrCache == null || $lastPoolId == null || $lastPoolId !== $poolId) {
    $pwrCache = PoolPowerRanking($poolId);
    $lastPoolId = $poolId;
  }
  
  if ($warn)
    $html .= "<tr class='attention'>";
  else
    $html .= "<tr>";
  $html .= "<td>" . editField("seed", $teamNum, $row['team_id'], intval($row['rank'])) . "</td>";
  $html .= "<td>" . editField("rank", $teamNum, $row['team_id'], intval($row['activerank'])) . "</td>";
  $html .= "<td>" . utf8entities($row['name']) . "</td>";
  
  $html .= "<td class='center'>" . intval($vp['games']) . "</td>";
  $html .= "<td class='center'>" . intval($vp['victorypoints']) . " / " . intval($vp['games']) . "</td>";
  $html .= "<td class='center'>" . intval($vp['oppvp']) . " / " . intval($vp['oppgames'])  . "</td>";
  $html .= "<td class='center'>" . intval($vp['margin']) . " / " . intval($vp['games'])  . "</td>";
  $html .= "<td class='center'>" . intval($vp['score']) . " / " . intval($vp['games'])  . "</td>";
  $html .= "<td class='center'>" . number_format($pwrCache[$row['team_id']], 2)  . "</td>";
  if (CanDeleteTeamFromPool($poolId, $row['team_id'])) {
    $html .= "<td class='center' style='width:20px;'>
              <input class='deletebutton' type='image' src='images/remove.png' alt='X' title='"._("delete team from pool") ."' name='remove' 
               value='" . _("X") . "' onclick=\"setDeleteId(" . $poolId . "," . $row['team_id'] . ");\"/></td>";
  } else {
    $html .= "<td></td>";
  }
  $html .= "</tr>\n";
  return $html;
}

function regularHeading($poolId, $poolinfo, $editbuttons) {
  $html = "";
  $html .= "<tr><th>"._("Seed")."&nbsp;". ($editbuttons?editButton("seed", $poolId):"") ."</th>
            <th>"._("Pos.")."&nbsp;". ($editbuttons?editButton("rank", $poolId):"") ."</th>
            <th>"._("Team")."</th>";
  $html .= "<th class='center'>" . _("Games") . "</th>";
  $html .= "<th class='center'>" . _("Wins") . "</th>";
  if ($poolinfo['drawsallowed'])
    $html .= "<th class='center'>" . _("Draws") . "</th>";
  $html .= "<th class='center'>" . _("Losses") . "</th>";
  $html .= "<th class='center'>" . _("Goals for") . "</th>";
  $html .= "<th class='center'>" . _("against") . "</th>";
  $html .= "<th class='center'>" . _("diff.") . "</th>";
  $html .= "<th></th></tr>";
  return $html;
}

function regularRow($poolId, $poolinfo, $row, $teamNum, $warn=false) {
  $html = "";
  $stats = TeamStatsByPool($poolId, $row['team_id']);
  $points = TeamPointsByPool($poolId, $row['team_id']);
  
  if ($warn)
    $html .= "<tr class='attention'>";
  else
    $html .= "<tr>";
  $html .= "<td>" . editField("seed", $teamNum, $row['team_id'], intval($row['rank'])) . "</td>";
  $html .= "<td>" . editField("rank", $teamNum, $row['team_id'], intval($row['activerank'])) . "</td>";
  $html .= "<td>" . utf8entities($row['name']) . "</td>";
  $html .= "<td class='center'>" . intval($stats['games']) . "</td>";
  $html .= "<td class='center'>" . intval($stats['wins']) . "</td>";
  if ($poolinfo['drawsallowed']) {
    $html .= "<td class='center'>" . intval($stats['draws']) . "</td>";
  }
  $html .= "<td class='center'>" . intval($stats['losses']) . "</td>";
  $html .= "<td class='center'>" . intval($points['scores']) . "</td>";
  $html .= "<td class='center'>" . intval($points['against']) . "</td>";
  $html .= "<td class='center'>" . ((intval($points['scores']) - intval($points['against']))) . "</td>";
  if (CanDeleteTeamFromPool($poolId, $row['team_id'])) {
    $html .= "<td class='center' style='width:20px;'>
              <input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' title='"._("delete team from pool") ."'
               value='"._("X")."' onclick=\"setDeleteId(".$poolId .",". $row['team_id'].");\"/></td>";
  } else {
    $html .= "<td></td>";
  }
  $html .= "</tr>\n";
  return $html;
}

function moveTable($moves, $type, $poolId, $poolinfo, $seasonId, $seriesId, $single) {
  $html = "<table class='admintable' style='width:100%; margin-left:0pt'>";
  if ($type == "to") {
    $html .= "<tr><th colspan='5'>" . _("Moves to") ." ". $poolinfo['name'] . "</th></tr>\n";
    $html .= "<tr><th>" . _("From pool") . "</th><th>". _("Pos") ."</th><th>" . _("To") . "</th>";
  } else {
    $html .= "<tr><th colspan='5'>" . _("Moves from") ." ". $poolinfo['name'] . "</th></tr>\n";
    $html .= "<tr><th>" . _("From") . "</th><th>" . _("To pool") . "</th><th>". _("Pos") ."</th>";
  }
  $html .= "<th>"._("Team")."</th><th></th></tr>\n";

  $undo = false;
  $allMoved = true;
  foreach ($moves as $row) {
    $topoolinfo = PoolInfo($row['topool']);
    if ($row['ismoved']) {
      $html .= "<tr class='highlight'>";
    } else {
      $html .= "<tr>";
    }
    if ($type == "to") {
      $html .= "<td>" . poolLink($row['frompool'], PoolName($row['frompool'])) . "</a></td>";
      $html .= "<td>" . utf8entities($row['fromplacing']) . "</td>";
      $html .= "<td>" . $row['torank'] . "</td>";
    } else {
      $html .= "<td>" . utf8entities($row['fromplacing']) . "</td>";
      $html .= "<td>" . poolLink($row['topool'], PoolName($row['topool'])) . "</td>";
      $html .= "<td>" . $row['torank'] . "</td>";
    }
    $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing'], $topoolinfo['type']!=2);  // do not count the BYE team if we are moving to a playoff pool
    if (empty($team))
      $html .= "<td>???</td>";
      else
      $html .= "<td>" . $team['name'] . "</td>";

    if ($row['ismoved']) {
      $undo = true;
      $html .= undoButton($row['frompool'], $row['fromplacing'], $row['topool']);
    } else {
      $allMoved = false;
      $html .= "<td></td>";
    }
    $html .= "</tr>\n";
  }
  
  $html .= "<tr><th colspan='4'>";
  if ($type == "to") {
    if (PoolIsMoveFromPoolsPlayed($poolId)) {
      if (!PoolIsAllMoved($poolId)) {
        $html .= "<input class='button' type='submit' name='confirmMoves' value='" . _("Confirm moves") .
             "' onclick='setConfirm(" . $poolId . ")'/>&nbsp;";
      } else {
        if ($poolinfo['visible'])
          $html .= "<input class='button' type='submit' name='setInvisible' value='" . _("Hide pool") .
               "' title='". utf8entities(_("Don't show pool in public menus")) . "' onclick='setCVisible(" . $poolId . ")'/>&nbsp;";
        else
          $html .= "<input class='button' type='submit' name='setVisible' value='" . _("Show pool") .
          "' title='". utf8entities(_("Show pool in public menus")) . "' onclick='setCVisible(" . $poolId . ")'/>&nbsp;";
      }
    }
    $html .= "<a href='?view=admin/serieteams&amp;season=$seasonId&amp;series=". $seriesId ."&amp;single=$single&amp;pool=". $poolId ."'>". _("Manage moves") ."</a>";
  }
  $html .= "</th>";
  if ($undo)
    $html .= undoPoolButton($poolId, $type == "from");
  else
    $html .= "<th></th>";
  $html .= "</tr></table>\n";
  return $html;
}

function editButton($prefix, $id) {
  $title = ($prefix == "seed")?_("change initial pool ranking"):_("change final pool ranking"); 
  return "<input class='button " . $prefix . "_display' type='image' src='images/settings.png' alt='D' name='" . $prefix .
       "Display' title='".$title."' value='" . _("Edit") . "' onclick='edit(this,\"" . $prefix . "\", " . $id .
       "); return false;'/>
          <input class='button " .
       $prefix . "_edit' style='display:none' type='image' src='images/save.gif' name='" . $prefix . "Save' title='"._("save ranking")."' value='" .
       _("Save") . "' onclick='setEditId(\"" . $prefix . "\", " . $id . ");'/>";
}

function editField($prefix, $teamNum, $id, $value) {
  return "<input type='hidden' id='".$prefix."Id" . $teamNum . "' name='".$prefix."Id[]' value='$id'/>
          <div class='".$prefix."_display'>".$value."</div>
          <div class='".$prefix."_edit' style='display:none'>
            <input class='input' size='3' maxlength='4' id='".$prefix.$teamNum."' name='".$prefix."[]' value='".$value."' /></div>";
}

function editPoolStandings($type, $pool, $startIds, $editStarts, $editEnds, $seedIds, $seeds, $rankIds, $ranks) {
  foreach ($startIds as $key => $value) {
    if ($value == $pool) {
      $start = $editStarts[$key];
      $end = $editEnds[$key];
      break;
    }
  }
  
  if ($type == "seed") {
    foreach ($seedIds as $key => $value) {
      if (intval($key) >= $start && intval($key) <= $end)
        SetTeamPoolRank($value, $pool, $seeds[$key]);
    }
  } else if ($type == "rank") {
    foreach ($rankIds as $key => $value) {
      if (intval($key) >= $start && intval($key) <= $end){
        SetTeamRank($value, $pool, $ranks[$key]);
      }
    }
  }
}

function undoButton($frompool, $fromplacing, $topool) {
  return "<td class='right'><input class='button' type='submit' name='moveUndo' value='" . _("Undo") .
         "' onclick='setUndoMove(".$frompool.", ".$fromplacing.", ".$topool. ")' /></td>";
}

function undoPoolButton($pool, $from){
  return "<th class='right'><input class='button' type='submit' name='poolUndo' value='" . _("Undo all") . "' onclick='setUndoPool(" . $pool . ", ".($from?"\"from\"":"\"to\"").");'/></th>";
}
?>