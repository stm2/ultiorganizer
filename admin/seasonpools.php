<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$season = $_GET["season"];
$single = 0;
$series_id = -1;
CurrentSeries($season, $series_id, $single, _("Pools"));

$title = utf8entities(SeasonName($season)) . ": " . _("Pools");
$html = "";

ensureEditSeriesRight($series_id);

//pool parameters
$pp = array(
	"name"=>"no name",
	"ordering"=>"A",
	"visible"=>"1",
	"continuingpool"=>"0",
	"placementpool"=>"0",
	"type"=>"1");

//remove
if(!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  if(CanDeletePool($id)){
    DeletePool($id);
  }
}

//clone
if(!empty($_POST['clone_x'])) {
  $id = $_POST['hiddenDeleteId'];
  $poolinfo = PoolInfo($id);
  PoolFromAnotherPool($series_id, $poolinfo['name'], $poolinfo['ordering'], $id);
}

//add
if (!empty($_POST['add'])) {
  $pp['name'] = !empty($_POST['name0']) ? $_POST['name0'] : "no name";
  $pp['type'] = intval($_POST["type0"]);
  $pp['ordering'] = !empty($_POST['ordering0']) ? $_POST['ordering0'] : "A";
  $pp['visible'] = isset($_POST["visible0"]) ? 1 : 0;
  $pp['continuingpool'] = isset($_POST["continuation0"]) ? 1 : 0;
  $pp['placementpool'] = isset($_POST["placement0"]) ? 1 : 0;

  if ($pp['type'] == 100) {
    $pp['visible'] = 0;
    $pp['continuingpool'] = 1;
    $pp['placementpool'] = 1;
  }
  
  $poolId = PoolFromPoolTemplate($series_id, $pp['name'], $pp['ordering'], $_POST['new_pool_template']);
  SetPoolDetails($poolId, $pp);
}

//save
if (!empty($_POST['save'])) {
  $pools = SeriesPools($series_id);
  foreach ($pools as $pool) {
    $pool_id = $pool['pool_id'];
    $pp['name'] = !empty($_POST["name$pool_id"]) ? $_POST["name$pool_id"] : "no name";
    $pp['type'] = intval($_POST["type$pool_id"]);
    $pp['ordering'] = !empty($_POST["ordering$pool_id"]) ? $_POST["ordering$pool_id"] : "A";
    $pp['visible'] = isset($_POST["visible$pool_id"]) ? 1 : 0;
    $pp['continuingpool'] = isset($_POST["continuation$pool_id"]) ? 1 : 0;
    $pp['placementpool'] = isset($_POST["placement$pool_id"]) ? 1 : 0;
    
    if (isset($_POST["root$pool_id"])) {
      $root = $_POST["root$pool_id"];
      $pp['continuingpool'] = 1;
      $pp['visible'] = 0;
      $pp['placementpool'] = isset($_POST["placement$root"]) ? 1 : 0;
    } else if ($pp['type'] == 100) {
      $pp['visible'] = 0;
      $pp['continuingpool'] = 1;
      $pp['placementpool'] = 1;
    }
    
    SetPoolDetails($pool_id, $pp);
  }
}

$get_link = function ($season, $seriesId, $single = 0, $htmlEntities = false) {
  $single = $single == 0 ? "" : "&single=1";
  $seaLink = urlencode($season);
  $link = "?view=admin/seasonpools&season=$seaLink&series={$seriesId}$single";
  return $htmlEntities ? utf8entities($link) : $link;
};

$url_here = $get_link($season, $series_id, $single, true);

$html .= SeriesPageMenu($season, $series_id, $single, $get_link, "?view=admin/seasonseries&season=" . urlencode($season));

$seriesinfo = SeriesInfo($series_id);

$html .= "<form method='post' action='$url_here'>";

$types = PoolTypes();

$html .= "<table class='admintable'>\n";
$html .= "<tr><th>"._("Name")."</th>
			<th>"._("Order")."</th>
			<th class='center' title='"._("Visible")."'>"._("V")."</th>
			<th class='center' title='"._("Continuing")."'>"._("C")."</th>
			<th title='"._("Placement")."'>"._("Placement")."</th>
			<th>"._("Type")."</th>
			<th>"._("Operations")."</th>
			<th></th>
			</tr>\n";
$last_ordering = 0;
$is_continuation = "";
$is_placement = "";
$is_visible = "";
$is_played = "";

$pools = SeriesPools($series_id);

foreach($pools as $pool){
  $info = PoolInfo($pool['pool_id']);
  $id = $pool['pool_id'];
  $placements = "";
  $allmoved = true;
  $moves = 1;
  $started = IsPoolStarted($pool['pool_id']);
  $teams = count(PoolTeams($pool['pool_id']));
   
  if(intval($info['continuingpool'])){
    $allmoved = PoolIsAllMoved($pool['pool_id']);
    $moves = count(PoolMovingsToPool($pool['pool_id']));
  }
  if(intval($info['placementpool']) && !intval($info['follower'])){
    $ppools = SeriesPlacementPoolIds($series_id);
    $placementfrom = 1;
    $placementto = 0;
    foreach ($ppools as $ppool){
      $teams = PoolTeams($ppool['pool_id']);
      if(count($teams)==0){
        $teams = PoolSchedulingTeams($ppool['pool_id']);
      }
      if($pool['pool_id']==$ppool['pool_id']){
         
        for($i=1;$i<=count($teams);$i++){
          $moved = PoolMoveExist($ppool['pool_id'], $i);
          if(!$moved){
            $placementto++;
          }
        }
        break;
      }
      for($i=1;$i<=count($teams);$i++){
        $moved = PoolMoveExist($ppool['pool_id'], $i);
        if(!$moved){
          $placementfrom++;
          $placementto++;
        }
      }
    }
    if($placementfrom <= $placementto){
      $placements .= " [$placementfrom.-$placementto.]";
    }else{
      $placements .= " [$placementfrom...]";
    }
  }
   
  $html .= "<tr class='admintablerow'>";
  $html .= "<td><input class='input' size='20' maxlength='50' name='name$id' value='".utf8entities($info['name'])."'/></td>";
  $html .= "<td><input class='input' size='3' maxlength='20' name='ordering$id' value='".utf8entities($info['ordering'])."'/></td>";
   
  $is_continuation = intval($info['continuingpool'])?"checked='checked'":"";
  $is_placement = intval($info['placementpool'])?"checked='checked'":"";
  $is_visible = intval($info['visible'])?"checked='checked'":"";
  $is_played = intval($info['played'])?"checked='checked'":"";
  
  $hidden = "";
  if($info['type'] == 2){
    $rootid = PoolPlayoffRoot($id);
    if($rootid!=$id){
      $info['continuingpool'] = 1;
      // $root_info = PoolInfo($rootid);
      $is_visible .= " disabled='disabled'";
      $is_continuation .= " disabled='disabled'";
      $is_placement .= " disabled='disabled'";
      $hidden = "<input type='hidden' id='root$id' name='root$id' value='$rootid' />";
    }
  } else if($info['type'] == 100){
    $is_visible .= " disabled='disabled'";
    $is_continuation .= " disabled='disabled' checked='checked'";
    $is_placement .= " disabled='disabled' checked='checked'";
  }
  
  $html .= "<td class='center'>$hidden<input class='input' type='checkbox' name='visible$id' id='visible$id' $is_visible/></td>";
  $html .= "<td class='center'><input class='input' type='checkbox' name='continuation$id' id='continuation$id' $is_continuation/></td>";
  $html .= "<td><input class='input' type='checkbox' name='placement$id' id='placement$id' $is_placement/> <span style='vertical-align:top;font-size:80%'>$placements</span></td>";
   
  $html .= "<td><select class='dropdown' name='type$id' id='type$id' onchange='updateBoxes($id);'>\n";

  $hasGames = !CanGenerateGames($info['pool_id']);
    
  foreach($types as $type => $typeId) {
    $selected = ($typeId == $info['type'])?" selected='selected'":"";
    $name = PoolTypeName($typeId);
    if ($name === null) continue;
    $name = utf8entities($name);
    if ($hasGames && $typeId == 100) $selected .= " disabled";
    
    $html .= "<option class='dropdown'${selected} value='$typeId'>$name</option>";
  }

  $html .=  "</select></td>";

  //$html .= "<td style='background-color:#".$info['color'].";background-color:".RGBtoRGBa($info['color'],0.3).";color:#".textColor($info['color']).";'>"._("Team")."</td>";
  //$html .= "<td><a href='?view=admin/addseasonpools&amp;pool=$id'>"._("Edit")."</a></td>";
   
  $html .= "<td>";

  if(!intval($info['continuingpool']) && !$started){
    if($teams){
      $html .= "<a href='?view=admin/select_teams&amp;series=".$series_id."'>"._("Select teams")."</a> | ";
    }else{
      $html .= "<b><a href='?view=admin/select_teams&amp;series=".$series_id."'>"._("Select teams")."</a></b> | ";
    }
  }elseif($allmoved && $moves>0 || $started){
    $html .= "<a href='?view=admin/serieteams&amp;season=$season&amp;series=".$series_id."&amp;pool=".$info['pool_id']."'>"._("Teams")."</a> | ";
  }else{
    if($moves){
      if(PoolIsMoveFromPoolsPlayed($info['pool_id'])){
        $html .= "<b><a href='?view=admin/serieteams&amp;season=$season&amp;series=".$series_id."&amp;pool=".$info['pool_id']."'>"._("Move teams")."</a></b> | ";
      }else{
        $html .= "<a href='?view=admin/serieteams&amp;season=$season&amp;series=".$series_id."&amp;pool=".$info['pool_id']."'>"._("Move teams")."</a> | ";
      }
    }else{
      $html .= "<b><a href='?view=admin/poolmoves&amp;pool=".$info['pool_id']."'>"._("Manage moves")."</a></b> | ";
    }
  }

  $hasGames = $info['type'] == 1 || $info['type'] == 2 || $info['type'] == 3 || $info['type'] == 4;
  if ($hasGames) {
  //playoff pool
  if($info['type']==2){
    if (CanGenerateGames($info['pool_id'])) {
      $html .= "<b><a href='?view=admin/poolgames&amp;season=".$info['season']."&amp;series=".$info['series']."&amp;pool=".$info['pool_id']."'>"._("Play-off games")."</a></b>";
    }else{
      $html .= "<a href='?view=admin/poolgames&amp;season=".$info['season']."&amp;series=".$info['series']."&amp;pool=".$info['pool_id']."'>"._("Play-off games")."</a>";
    }
  }else{
    if (CanGenerateGames($info['pool_id'])) {
      $html .= "<b><a href='?view=admin/poolgames&amp;season=$season&amp;pool=".$info['pool_id']."'>"._("Game management")."</a></b>";
    }else{
      $html .= "<a href='?view=admin/poolgames&amp;season=$season&amp;pool=".$info['pool_id']."'>"._("Game management")."</a>";
    }
  }
  } else {
    $html .= _("no games");
  }
  $html .= "</td>";
  $html .= "<td>";
  $html .= "<a href='?view=admin/addseasonpools&amp;pool=$id'><img class='deletebutton' src='images/settings.png' alt='D' title='"._("edit details")."'/></a>";
  
  $html .= "<input class='deletebutton' type='image' src='images/clone.png' alt='C' name='clone' title='"._("clone")."' value='"._("C")."' onclick=\"setId(".$info['pool_id'].");\"/>";
  if (CanDeletePool($info['pool_id'])) {
    $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' title='"._("remove")."' name='remove' value='"._("X")."' onclick=\"setId(".$info['pool_id'].");\"/>";
  }
  $html .= "</td>";
   
  $html .= "</tr>\n";
  $last_ordering = $info['ordering'];
}

if(!$last_ordering){
  $last_ordering='A';
}else{
  $last_ordering++;
}

$html .=  "<tr>";
$html .=  "<td style='padding-top:15px'><input class='input' size='20' maxlength='50' id='name0' name='name0'/></td>";
$html .= "<td style='padding-top:15px'><input class='input' size='3' maxlength='20' name='ordering0' value='$last_ordering'/></td>";
$html .= "<td class='center' style='padding-top:15px'><input class='input' type='checkbox' name='visible0' id='visible0'/></td>";
$html .= "<td class='center' style='padding-top:15px'><input class='input' type='checkbox' name='continuation0' id='continuation0'/></td>";
$html .= "<td class='center' style='padding-top:15px'><input class='input' type='checkbox' name='placement0' id='placement0'/></td>";
$html .= "<td style='padding-top:15px'><select class='dropdown' name='type0' id='type0' onchange='updateBoxes(0);'>\n";

foreach($types as $type => $typeId) {
  $name = PoolTypeName($typeId);
  if ($name === null) continue;
  $name = utf8entities($name);
  $html .= "<option class='dropdown' value='$typeId'>$name</option>";
}

$html .=  "</select></td>";

$html .= "<td colspan='2' style='padding-top:15px'><select class='dropdown' name='new_pool_template'>\n";
$templates = PoolTemplates();
foreach($templates as $template) {
  if ($template['template_id'] == $seriesinfo['pool_template'])
    $html .= "<option class='dropdown' selected='selected' value='".utf8entities($template['template_id'])."'>" . utf8entities(U_($template['name'])) . "</option>";
  else
    $html .= "<option class='dropdown' value='".utf8entities($template['template_id'])."'>" . utf8entities(U_($template['name'])) . "</option>";
}
$html .=  "</select>";

$html .=  " <input  style='margin-left:15px' id='add' class='button' name='add' type='submit' value='"._("Add")."'/>";
$html .=  "</td></tr>\n";
$html .= "</table>\n";
$html .=  "<p>";
$html .=  "<input id='save' class='button' name='save' type='submit' value='"._("Save")."'/> ";
$html .=  "<input id='cancel' class='button' name='cancel' type='submit' value='"._("Cancel")."'/>";
$html .=  "</p>";
$html .= "<hr/>";
$html .= "<p>";
$html .= "<a href='?view=admin/seasonmoves&amp;series=".$series_id."'>"._("Show all moves")."</a> ";
$html .= "| <a href='?view=admin/seriesgames&amp;series=".$series_id."'>"._("Generate all games")."</a></p>";


//stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>\n";

$html .= "<script type=\"text/javascript\">
  function enable(id, value) {
    if (value)
      document.getElementById(id).removeAttribute(\"disabled\");
    else
      document.getElementById(id).setAttribute(\"disabled\", \"true\");
  }
  function updateBoxes(id) {
    var typeSelect = document.getElementById(\"type\" + id);
    var value = typeSelect.value;
    if (value == 100) {
      enable(\"visible\" + id, false);
      enable(\"continuation\" + id, false);
      enable(\"placement\" + id, false);
    } else {
      enable(\"visible\" + id, true);
      enable(\"continuation\" + id, true);
      enable(\"placement\" + id, true);
    }
  }
</script>
";


//common page
setFocus('name0');
showPage($title, $html);
?>
