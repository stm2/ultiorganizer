<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$title = _("Scheduling");

$reservations = array();

$ddMode = $_POST['ddmode'] ?? 0;
if (isset($_POST['save_schedule'])) {
  foreach ($_POST['games'] as $i => $gameId) {
    $resId = -1;
    if ($_POST['gameres'][$i] > 0) {
      $resId = $_POST['res_code'][$_POST['gameres'][$i] - 1] ?? -2;
    }
    if ($resId > 0) {
      $resInfo = ReservationInfo($resId);
      $location = $resInfo['name'];
      $field = $resInfo['fieldname'];
      $time = $_POST['gamestart'][$i];
      $time = strtotime(ShortEnDate($resInfo['starttime']) . " " . $time);
      ScheduleGame($gameId, $time, $resId);
    } else if ($resId == -1){
      UnScheduleGame($gameId);
    }
  }
}

if (isset($_GET['reservations'])) {
  $reservations = explode(",", $_GET['reservations']);
} else if (isset($_SESSION['userproperties']['userrole'])) {
  $reservations = array_flip($_SESSION['userproperties']['userrole']['resadmin']);
}
$reservationData = ReservationInfoArray($reservations);

$EM_PER_MINUTE = 0.1;
$MAX_COLUMNS = 4;

$maxtimeslot = 30;
$seriesId = 0;
$poolId = 0;
$seasonId = "";

if (!empty($_GET["series"])) {
  $seriesId = intval($_GET["series"]);
}

if (!empty($_GET["pool"])) {
  $poolId = intval($_GET["pool"]);
}

if (!empty($_GET["season"])) {
  $seasonId = $_GET["season"];
}

$backurl = "?view=admin/reservations";
if (!empty($seasonId))
  $backurl .= "&season=$seasonId";
if (!empty($seriesId))
  $backurl .= "&series=$seriesId";
if (!empty($pool))
  $backurl .= "&pool=$poolId";

$seasonfilter = array();
$seriesfilter = array();
$poolfilter = array();

$seasons = Seasons();
while ($season = mysqli_fetch_assoc($seasons)) {
  $seasonfilter[] = array('id' => $season['season_id'], 'name' => U_(SeasonName($season['season_id'])));
}

$series = SeasonSeries($seasonId);
foreach ($series as $ser) {
  $seriesfilter[] = array('id' => $ser['series_id'], 'name' => U_($ser['name']));
}

$pools = SeriesPools($seriesId);
foreach ($pools as $tmppool) {
  $poolfilter[] = array('id' => $tmppool['pool_id'], 'name' => U_($tmppool['name']));
}

$MIN_HEIGHT = 4; // 4em
function getHeightFactor($gameData, $reservationData, &$minDuration, &$maxDuration, &$emPerMinute) {
  global $MIN_HEIGHT;
  $minDuration = PHP_INT_MAX;
  $maxDuration = 0;
  foreach ($gameData as $game) {
    $duration = gameDuration($game);
    if ($duration < $minDuration)
      $minDuration = $duration;
      if ($duration > $maxDuration) $maxDuration = $duration;
  }
  foreach ($reservationData as $dayArray) {
    foreach ($dayArray as $reservationId => $reservationArray) {
      foreach ($reservationArray['games'] as $gameId => $gameInfo) {
        $duration = $gameInfo['timeslot'];
        if ($duration < $minDuration)
          $minDuration = $duration;
          if ($duration > $maxDuration) $maxDuration = $duration;
      }
    }
  }
  if ($minDuration == PHP_INT_MAX)
    $minDuration = 90;
    if ($maxDuration <= 0) $maxDuration = 1;
    if ($minDuration * 5 < $maxDuration)
      $minDuration = $maxDuration / 5;
      
      $emPerMinute = $MIN_HEIGHT / $minDuration;
}

function gameHeight($duration) {
  global  $EM_PER_MINUTE;
  return max(($duration * $EM_PER_MINUTE), (1 * $EM_PER_MINUTE));
}

function pauseHeight($duration) {
  global  $EM_PER_MINUTE;
  return ($duration * $EM_PER_MINUTE);
}

function jsSecure($string) {
  return str_replace(array('"', "\n"), array('\"', ''), $string);
}

function pauseEntry($height, $duration, $gameId, $editable = true) {
  if ($editable)
    $id = "pause$gameId";
  else
    $id = "fixed$gameId";
  $tid = "ptime$gameId";
  $names = "ptimes[]";
  $alarm = $duration < 0 ? " negative" : "";
  $html = "<li class='schedule_item$alarm' id='$id' style='min-height:" . $height . "em'>";
  $html .= "<input type='hidden' id='$tid' name='$names' value='" . $duration . "'/>";
  $html .= sprintf(_("Pause: %s&thinsp;min."), $duration);
  if ($editable) {
    $html .= "<span style='align:right;float:right'><a href='javascript:hide(\"$id\");'>x</a></span></li>\n";
  } else {
    $html .= "<span style='align:right;float:right;'>#</span>";
  }

  return $html;
}

function gameEntry($gameInfo, $height, $duration, $poolname, $editable = true) {
  $color = $gameInfo['color'];
  $textColor = textColor($color);
  $gameId = $gameInfo['game_id'];
  $gamename = utf8entities(GameName($gameInfo, false, true));
  $sTime = empty($gameInfo['time']) ? "00:00" : DefHourFormat($gameInfo['time']);
  $tooltip = utf8entities(GameName($gameInfo, false));
  if ($tooltip == $gamename)
    $tooltip = "";
  else
    $tooltip = " title='" . $tooltip . "'";
  $html = "<li class='schedule_item' style='color:#" . $textColor . ";background-color:#" . $color . ";min-height:" .
    $height . "em' id='game" . $gameId . "'" . $tooltip . ">";
  $html .= "<input type='hidden' id='gtime" . $gameId . "' name='gtimes[]' value='" . $duration . "'/>";
  $html .= "<input type='hidden' class='editmode' name='games[]' value='$gameId' />\n";
  $html .= "<input type='text' class='editmode gamestart' name='gamestart[]' style='display:inline; width:5em' type='text' minlength='5' maxlength='5' value='$sTime'/>\n";
  $resCode = $gameInfo['res_code'] ?? 0;
  $html .= "<span class='editmode'>Res #</span><input type='text' class='editmode gameres' name='gameres[]' style='display:inline; width:5em' type='text' value='$resCode'/>\n";
  $html .= "<span class='ddmode schedule_time'>$sTime</span><span class='ddmode'> - </span>";
  $html .= $poolname;
  if ($editable) {
    $html .= " <span style='align:right;float:right;'><a href='javascript:hide(\"game" . $gameId . "\");'>x</a></span>";
  } else {
    $html .= "<span style='align:right;float:right;'>#</span>";
  }
  $html .= "<br/>\n" . (empty($gamename) ? "" : "<b>$gamename</b> ") . sprintf(_("%d&thinsp;min."), $duration);
  $html .= "</li>\n";
  return $html;
}

function getHName($gameInfo) {
  return empty($gameInfo['hometeamshortname']) ? $gameInfo['hometeamname'] : $gameInfo['hometeamshortname'];
}

function getVName($gameInfo) {
  return empty($gameInfo['visitorteamshortname']) ? $gameInfo['visitorteamname'] : $gameInfo['visitorteamshortname'];
}

function gamePoolName($gameInfo) {
  return U_($gameInfo['seriesname']) . ", " . U_($gameInfo['poolname']);
}

function tableStart($dayArray, $skip, $max) {
  echo "<table class='scheduling'><tr>\n";
  $index = 0;
  $firstStart = PHP_INT_MAX;
  foreach ($dayArray as $reservationArray) {
    if (++$index <= $skip)
      continue;
    if ($index > $skip + $max)
      break;
    $startTime = strtotime($reservationArray['starttime']);
    $firstStart = min($firstStart, $startTime);
    echo "<th class='scheduling'>" . $reservationArray['name'] . " " . _("Field") . " " . $reservationArray['fieldname'] .
      " " . date("H:i", $startTime) . "<span class='editmode'>: " . sprintf("Res # %d", $reservationArray['res_code']) . "</span></th>\n";
  }
  echo "<th>" . JustDate($reservationArray['starttime']) . "</th></tr><tr>\n";
  return $firstStart;
}

function tableEnd($firstStart, $lastEnd) {
  global  $EM_PER_MINUTE;
  echo "<td class='time_column'>";
  if (isset($firstStart)) {
    echo "<ul class='time_list'>\n";
    for ($t = $firstStart + 60 * 60, $lastTick = $firstStart; $t <= $lastEnd; $t += 60 * 60) {
      $height = pauseHeight(($t - $lastTick) / 60);
      if ($height >= 2 || $t >= $lastEnd) {
        echo "<li style='min-height:" . $height . // (max(0, min(100000, ($lastEnd - $t) / 60) * $EM_PER_MINUTE))
        "em'>" . date("H:i", $t - 60 * 60) . "</li>\n";
        $lastTick = $t;
      }
    }
    echo "</ul>";
  }
  echo "</td>\n";
  echo "</tr>\n</table>\n";
}

// common page
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities"));

?>

<script type="text/javascript">
<!--
var with_ctrl_key=false;
var with_shift_key = false;

function KeyDown(event){
  var KeyID = event.keyCode;
  
  switch(KeyID){

  case 16:
    with_shift_key = true;
    break; 

  case 17:
    with_ctrl_key = true;
    break;

  case 37:
    //Arrow Left
    if(with_ctrl_key){
      window.scrollBy(2*-window.innerWidth+50,0);
    }else if(with_shift_key){
      window.scrollBy(-50,0);
    }else{
      window.scrollBy(-window.innerWidth+50,0);
    }

    break;

  case 38:
    //Arrow Up
    if(with_ctrl_key){
    window.scrollBy(0,2*-window.innerHeight+50);
    }else if(with_shift_key){
      window.scrollBy(0,-50);
    }else{
      window.scrollBy(0,-window.innerHeight+50);
    }
    break;

  case 39:
    //Arrow Right
    if(with_ctrl_key){
      window.scrollBy(2*window.innerWidth-50,0);
    }else if(with_shift_key){
      window.scrollBy(50,0);
    }else{
      window.scrollBy(window.innerWidth-50,0);
    }
    break;

  case 40:
    //Arrow Down
    if(with_ctrl_key){
      window.scrollBy(0,2*window.innerHeight-50);
    }else if(with_shift_key){
      window.scrollBy(0,50);
    }else{
      window.scrollBy(0,window.innerHeight-50);
    }
    break;
  }
}

function KeyUp(event){
  var KeyID = event.keyCode;
  
  switch(KeyID){

  case 16:
    with_shift_key = false;
    break; 

  case 17:
    with_ctrl_key = false;
    break;
  }
}
//-->

</script>

<?php

$scrolling = "onkeydown='KeyDown(event);' onkeyup='KeyUp(event);'";
pageTopHeadClose($title, false, $scrolling);
pageMainStart();
contentStartWide();

echo JavaScriptWarning();
echo "<a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a>";

echo "<form action='' method='post'>";
echo "<input type='hidden' id='ddmode' name='ddmode' value='$ddMode' />";
echo "<table class='scheduling'><tr><td class='scheduling_column'>";

// $teams = UnscheduledTeams();
// $unscheduledTeams = array_flip(UnscheduledTeams());
if ($poolId) {
  $gameData = UnscheduledPoolGameInfo($poolId);
} elseif ($seriesId) {
  $gameData = UnscheduledSeriesGameInfo($seriesId);
} elseif (!empty($seasonId)) {
  $gameData = UnscheduledSeasonGameInfo($seasonId);
} else {
  $gameData = array();
}

getHeightFactor($gameData, $reservationData, $MIN_DURATION, $MAX_DURATION, $EM_PER_MINUTE);

echo "<table class='scheduling'><tr><td class='scheduling_column'>\n";
echo "<h3>" . _("Unscheduled") . "</h3>\n";
echo "<p><select class='dropdown' name='eventfilter' onchange='OnEventSelect(this);'>\n";
echo "<option class='dropdown' value=''>" . _("Select event") . "</option>";
foreach ($seasonfilter as $season) {
  if ($seasonId == $season['id']) {
    echo "<option class='dropdown' selected='selected' value='" . utf8entities($season['id']) . "'>" .
      utf8entities($season['name']) . "</option>";
  } else {
    echo "<option class='dropdown' value='" . utf8entities($season['id']) . "'>" . utf8entities($season['name']) .
      "</option>";
  }
}
echo "</select><br/>\n";
$disabled = "";
if (empty($seasonId)) {
  $disabled = "disabled='disabled'";
}
echo "<select class='dropdown' $disabled name='seriesfilter' onchange='OnSeriesSelect(this);'>\n";
echo "<option class='dropdown' value='0'>" . _("All divisions") . "</option>";
foreach ($seriesfilter as $series) {
  if ($seriesId == $series['id']) {
    echo "<option class='dropdown' selected='selected' value='" . utf8entities($series['id']) . "'>" .
      utf8entities($series['name']) . "</option>";
  } else {
    echo "<option class='dropdown' value='" . utf8entities($series['id']) . "'>" . utf8entities($series['name']) .
      "</option>";
  }
}
echo "</select><br/>\n";
$disabled = "";
if (!$seriesId) {
  $disabled = "disabled='disabled'";
}
echo "<select class='dropdown' $disabled name='poolfilter' onchange='OnPoolSelect(this);'>\n";
echo "<option class='dropdown' value='0'>" . _("All pools") . "</option>";
foreach ($poolfilter as $pool) {
  if ($poolId == $pool['id']) {
    echo "<option class='dropdown' selected='selected' value='" . utf8entities($pool['id']) . "'>" .
      utf8entities($pool['name']) . "</option>";
  } else {
    echo "<option class='dropdown' value='" . utf8entities($pool['id']) . "'>" . utf8entities($pool['name']) .
      "</option>";
  }
}
echo "</select></p>\n";

$zeroGames = array();

$jsStartTimes = array();
$jsGameTimes = array();
$jsPauseTimes = array();


echo "<div class='workarea' >\n";
echo "<ul class='draglist' id='unscheduled'  style='min-height:600px'>\n";
foreach ($gameData as $gameId => $gameInfo) {
  if (hasEditGamesRight($gameInfo['series'])) {
    $duration = gameDuration($gameInfo);
    if ($duration <= 0) {
      $zeroGames[] = count($zeroGames) == 0 ? $gameInfo : $gameId;
    }
    $height = gameHeight($duration);
    $poolname = gamePoolName($gameInfo);
    $maxtimeslot = max($maxtimeslot, $duration);
    $jsGameTimes[$gameId] = $duration;
    if ($duration > 0)
      echo gameEntry($gameInfo, $height, $duration, $poolname);
    else
      echo gameEntry($gameInfo, $height, $duration, $poolname, false);
  }
}
if (count($gameData) == 0) {
  echo "<li></li>";
}
echo "</ul>\n";
echo "</div>\n</td>\n";
echo "</tr>\n</table>\n";
echo "<p>&nbsp;</p>";
echo "<input type='button' id='pauseButton' value='" . _("Add pause") . "'/>";
echo "<div style='white-space:nowrap'><input type='text' id='pauseLen' value='$maxtimeslot' size='3'/>&thinsp;" .
  _("minutes") . "</div>\n";
echo "</td><td class='tdcontent' style='vertical-align:top'>\n";
$reservedPauses = array();

$MINTOP = 30;

$reservationCode = 0;
{
  foreach ($reservationData as &$dayArray) {
    foreach ($dayArray as $reservationId => &$reservationArray) {
      $reservationArray['res_code'] = ++$reservationCode;      
    }
    unset ($reservationArray);
  }
  unset ($dayArray);
}

{
  global  $EM_PER_MINUTE;
  foreach ($reservationData as $dayArray) {
    $lastEnd = 0;
    $columnCount = $lastBreak = 0;
    $firstStart = tableStart($dayArray, 0, $MAX_COLUMNS);

    foreach ($dayArray as $reservationId => $reservationArray) {
      echo "<td class='scheduling_column'>\n";
      echo "<input class='res_code' type='hidden' name='res_code[]' value='$reservationId' />\n";
      $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + $MINTOP;
      $lastEnd = max($lastEnd, strtotime($reservationArray['endtime']));
      $startTime = strtotime($reservationArray['starttime']);
      $endTime = strtotime($reservationArray['endtime']);
      $duration = ($endTime - $firstStart) / 60;
      $height = gameHeight($duration);
      
      $jsStartTimes[$reservationId] = $startTime;

      echo "<div class='workarea' >\n";
      echo "<ul id='res" . $reservationId . "' class='draglist' style='min-height:{$height}em'>\n";

      $duration = ($startTime - $firstStart) / 60;
      $height = pauseHeight($duration);
      if ($firstStart < $startTime) {
        $jsPauseTimes[-$reservationId] = $duration;
        echo pauseEntry($height, $duration, -$reservationId, false);
        // $reservedPauses[] = "fixed".$reservationId;
      }

      $nextStart = $startTime;
      foreach ($reservationArray['games'] as $gameId => $gameInfo) {
        $gameInfo['res_code'] = $reservationArray['res_code'];
        $gameStart = strtotime($gameInfo['time']);
        $duration = ($gameStart - $nextStart) / 60;
        $height = pauseHeight($duration);
        if ($nextStart != $gameStart) {
          $jsPauseTimes[$gameId] = $duration;
          echo pauseEntry($height, $duration, $gameId);
          $reservedPauses[] = "pause" . $gameId;
        }
        $duration = gameDuration($gameInfo);
        if ($duration <= 0) {
          $zeroGames[] = count($zeroGames) == 0 ? $gameInfo : $gameId;
        }
        $nextStart = $gameStart + ($duration * 60);
        $height = gameHeight($duration);
        $pooltitle = gamePoolName($gameInfo);
        $jsGameTimes[$gameId] = $duration;
        if ($duration > 0 && hasEditGamesRight($gameInfo['series'])) {
          echo gameEntry($gameInfo, $height, $duration, $pooltitle);
        } else {
          echo gameEntry($gameInfo, $height, $duration, $pooltitle, false);
        }
      }
      echo "</ul>\n";
      echo "</div>\n</td>\n";

      ++$columnCount;
      if (count($dayArray) > $MAX_COLUMNS && ($columnCount - $lastBreak >= $MAX_COLUMNS)) {
        tableEnd($firstStart, $lastEnd);
        unset($firstStart);
        $lastEnd = 0;
        if ($columnCount < count($dayArray))
          $firstStart = tableStart($dayArray, $columnCount, $MAX_COLUMNS);
        $lastBreak = $columnCount;
      }
    }
    if (isset($firstStart)) {
      tableEnd($firstStart, $lastEnd);
    }
    unset($firstStart);
    $lastEnd = 0;
  }
}

echo "<table>";
echo "<tr><td>";
echo "<input class='ddmode' type='button' id='editButton' style='display:none;' value='" . _("Edit games directly") . "' />";
echo "<input class='editmode' type='button' id='ddButton' style='display:none;' value='" . _("Drag and drop mode") . "' />";
echo "</td></tr>";
echo "<tr><td id='user_actions'>";
echo "<input class='ddmode' type='button' id='showButton' value='" . _("Save schedule") . "' />";
echo "<input class='editmode' type='submit' name='save_schedule' id='submitButton' value='" . _("Save schedule") . "' /></td>";
echo "<td class='center'><div id='responseStatus'></div>";
if (!empty($zeroGames)) {
  echo "<p>" .
    sprintf(
      _(
        "Warning: Games with duration 0 found. They can not be scheduled. Edit the game duration or the time slot length of pool %s ..."),
      gamePoolName($zeroGames[0])) . "</p>";
}
echo "</td></tr>";
echo "</table>\n";
echo "</form>";
echo "<p><a href='?view=admin/movingtimes&season=$seasonId&reservations=" . implode(',', $reservations) . "'>" .
  _("Manage transfer times") . "</a><br />";
echo "<a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a></p>";
?>
<script type="text/javascript">
//<![CDATA[

var Dom = YAHOO.util.Dom;
var redirecturl="";
var modified=0;
var startMap = new Map();
var gameMap = new Map();
var pauseMap = new Map();

function updateLists (list) {
<?php
foreach ($jsStartTimes as $reservationId => $time) {
  echo "    startMap.set($reservationId, $time);\n";
}
foreach ($jsGameTimes as $gameId => $duration) {
  echo "    gameMap.set($gameId, $duration);\n";
}
foreach ($jsPauseTimes as $id => $duration) {
  echo "    pauseMap.set($id, $duration);\n";
}
?>
  for (list2 of document.getElementsByClassName("draglist")) {
    updateList(list2);
  }
}

function updateList(list) {
  var id = Number(list.id.replace(/[^0-9]*/, ''));
  var startTime = startMap.get(id);
  if (!startTime) startTime = -60*60;
  for (child of list.children) {
    var duration = -1;
    var gid = Number(child.id.replace(/[a-z]*/, ''));
    if (child.id.startsWith("game")) {
      duration = gameMap.get(gid) * 60;
    } else if (child.id.startsWith("pause")) {
      duration = pauseMap.get(gid) * 60;
    }
      
    var time = child.getElementsByClassName('schedule_time');
    if (time && time.length > 0)
      time[0].innerHTML = shortTime(startTime);
    time = child.getElementsByClassName('gamestart');
    if (time && time.length > 0)
      time[0].value = shortTime(startTime);
    var res = child.getElementsByClassName('gameres');
    if (res && res.length > 0) {
      var i = 0;
      res[0].value = 0;
      for (rescode of document.getElementsByClassName("res_code")) {
        ++i;
        if (rescode.value == id)
            res[0].value = i;
      }
    }
      
    if (startTime > 0)
      startTime += duration;
  }
}

function hide(id) {
  var elem = Dom.get(id);
  var list = Dom.getAncestorByTagName(elem, "ul");
  list.removeChild(elem);
  updateLists(list);
}

function setModified(newValue) {
  modified=newValue;
  if (modified)
    window.onbeforeunload = function() {
      return "";
    }
  else
    window.onbeforeunload = null;
}

function paramExp(param) {
  return new RegExp("([?|&])" + param + "=([^&]*)?(&|$)","i")
}

function getParam(url, param) {
  var found = 0;
  var re = paramExp(param);
  if (url.match(re)){
    found = url.match(re)[2];
  }
  return found;
}

function changeParam(url, param, value) {
  var re = paramExp(param);
  if (url.match(re)){
    url=url.replace(re,'$1' + param + "=" + value + '$3');
  }else{
    url = url + '&' + param + "=" + value;
  }
  return url;
}

function getUrl(url, season, series, pool) {
  if (season!=null) {
    url = changeParam(url, "season", season);
  }
  if (series!=null) {
    url = changeParam(url, "series", series);
  }
  if (pool != null) {
    url = changeParam(url, "pool", pool);
  }
  return url;
}

//ajax based solution would be better, but at now this feels simpler because also dropdown fields need to be updated according user selection.
function OnEventSelect(dropdown)
{
  var myindex  = dropdown.selectedIndex;
  var SelValue = dropdown.options[myindex].value;
  var event = getParam(location.href, "season");

  redirecturl=getUrl(location.href, SelValue, 0, 0);
  if (!redirectWithConfirm()){
    dropdown.value = series;
  }
}
  
function OnSeriesSelect(dropdown)
{
  var myindex  = dropdown.selectedIndex;
  var SelValue = dropdown.options[myindex].value;
  var series = getParam(location.href, "series");

  redirecturl = getUrl(location.href, null, SelValue, 0);
  if (!redirectWithConfirm()){
    dropdown.value = series;
  }
}

function OnPoolSelect(dropdown)
{
  var myindex  = dropdown.selectedIndex;
  var SelValue = dropdown.options[myindex].value;
  var pool = getParam(location.href, "pool");

  redirecturl = getUrl(location.href, null, null, SelValue);
  if (!redirectWithConfirm()){
    dropdown.value = pool;
  }
}

function redirectWithConfirm(){
  if(modified){
    var answer = confirm('<?php echo _("Save changes?");?>');
    if (answer){
      YAHOO.example.ScheduleApp.requestString();
      setModified(false);
    }else{
      answer = confirm('<?php echo _("Do you want to leave anyway? If you select Yes, you will lose all your changes.");?>');
      if (answer) {
        setModified(false);
        location.href=redirecturl;
      } else
        return false;
    }
  }else{
    location.href=redirecturl;
  }
  return true;
}

(function() {

var Event = YAHOO.util.Event;
var DDM = YAHOO.util.DragDropMgr;
var pauseIndex = 1;
var emPerMinute = <?php echo $EM_PER_MINUTE; ?>;


YAHOO.example.ScheduleApp = {
    init: function() {
      this.toggleDDMode(<?php echo $ddMode?>);
<?php
echo "    new YAHOO.util.DDTarget(\"unscheduled\");\n";
foreach ($reservationData as $day => $dayArray) {
  foreach ($dayArray as $reservationId => $reservationArray) {
    echo "    new YAHOO.util.DDTarget(\"res" . $reservationId . "\");\n";
    foreach ($reservationArray['games'] as $gameId => $gameInfo) {
      if (hasEditGamesRight($gameInfo['series'])) {
        echo "    new YAHOO.example.DDList(\"game" . $gameId . "\");\n";
      }
    }
  }
}
foreach ($gameData as $gameId => $gameInfo) {
  if (hasEditGamesRight($gameInfo['series'])) {
    echo "    new YAHOO.example.DDList(\"game" . $gameId . "\");\n";
  }
}
foreach ($reservedPauses as $pauseId) {
  echo "    new YAHOO.example.DDList(\"" . $pauseId . "\");\n";
}

?>
    Event.on("showButton", "click", this.requestString);
    Event.on("pauseButton", "click", this.addPause);
    Event.on("editButton", "click", this.editMode);
    Event.on("ddButton", "click", this.ddMode);
  },
    
  addPause: function() {
    var unscheduled = Dom.get("unscheduled");
    var pauseElement = document.createElement("div");
    var duration = Dom.get("pauseLen").value;
    if (duration > 0) {
      for(++pauseIndex; Dom.get("pause-" + pauseIndex) !=null || Dom.get("res" + pauseIndex) !=null; ++pauseIndex) {}
      var height = Math.max(1,(duration * emPerMinute));
      var html = "<?php echo jsSecure(pauseEntry('%h%', '%d%', '%i%')); ?>";
      html = html.replace(/%h%/g, height);
      html = html.replace(/%d%/g, duration);
      html = html.replace(/%i%/g, -pauseIndex);
      pauseElement.innerHTML = html;
      pauseElement = pauseElement.firstChild;
          
      unscheduled.appendChild(pauseElement);
      new YAHOO.example.DDList("pause-" + pauseIndex);
      pauseMap.set(-pauseIndex, duration);
    }
  },  
    
  requestString: function() {
    var parseList = function(ul, id) {
      var items = ul.getElementsByClassName("schedule_item");
      var out = id;
      var offset = 0;
      for (i=0;i<items.length;i=i+1) {
        var duration =  parseInt(items[i].firstChild.value);
        const nextId = /[0-9]+/.exec(items[i].id)[0];
        const type = /[^0-9-]+/.exec(items[i].id)[0];
        if (type == "game") {
          out += ":" + nextId + "/" + offset;
          offset += duration;
        } else if (type  == "pause") {
          offset += duration;
        }
      }
      return out;
    };
<?php
echo "  var unscheduled=Dom.get(\"unscheduled\");\n";
foreach ($reservationData as $day => $dayArray) {
  foreach ($dayArray as $reservationId => $reservationArray) {
    echo "  var res" . $reservationId . "=Dom.get(\"res" . $reservationId . "\");\n";
  }
}
echo "  var request = parseList(unscheduled, \"0\") + \"\\n\"";
foreach ($reservationData as $day => $dayArray) {
  foreach ($dayArray as $reservationId => $reservationArray) {
    echo " + \"|\" + parseList(res" . $reservationId . ", \"" . $reservationId . "\")";
  }
}
echo ";\n";
?>
    var responseDiv = Dom.get("responseStatus");
    Dom.setStyle(responseDiv,"background-image","url('images/indicator.gif')");
    Dom.setStyle(responseDiv,"background-repeat","no-repeat");
    Dom.setStyle(responseDiv,"background-position", "top right");
    Dom.setStyle(responseDiv,"class", "inprogress");
    responseDiv.innerHTML = '&nbsp;';
    var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=admin/saveschedule', callback, request);         
  },
  editMode: function() {
    YAHOO.example.ScheduleApp.toggleDDMode(-1);
  },
  ddMode: function() {
    YAHOO.example.ScheduleApp.toggleDDMode(1);
  },
  toggleDDMode: function(dd) {
    if (dd >= 0) DDM.unlock(); else DDM.lock();
    for(el of document.getElementsByClassName('ddmode')) {
      el.style.display = dd >= 0 ? 'inline' : 'none';
    }
    for(el of document.getElementsByClassName('editmode')) {
      el.style.display = dd >= 0 ? 'none' : 'inline';
    }
    Dom.get('ddmode').value = dd;
    Dom.get('ddButton').style.display = dd >= 0 ? 'none' : 'inline';
    Dom.get('editButton').style.display = dd >= 0 ? 'inline' : 'none';
  },
};

var callback = {
  success: function(o) {
    var responseDiv = Dom.get("responseStatus");
    Dom.setStyle(responseDiv,"background-image","");
    if (o.responseText.length == 0) {
      responseDiv.innerHTML = "<?php echo "<p>".utf8entities(_("Unknown error."))."</p>"; ?>";
    } else {
      responseDiv.innerHTML = o.responseText;
    }
    var response = Dom.get("responseValue")?parseInt(Dom.get("responseValue").value):null;
    if (response!=null && response>0) {
      YAHOO.util.Dom.removeClass(responseDiv,"attention");
      YAHOO.util.Dom.addClass(responseDiv,"highlight");
      setModified(false);
      if(redirecturl){
        location.href=redirecturl;
      }
    } else {
      YAHOO.util.Dom.removeClass(responseDiv,"highlight");
      YAHOO.util.Dom.addClass(responseDiv,"attention");
    }
  },

  failure: function(o) {
    var responseDiv = Dom.get("responseStatus");
    YAHOO.util.Dom.removeClass(responseDiv,"highlight");
    YAHOO.util.Dom.addClass(responseDiv,"attention");
    Dom.setStyle(responseDiv,"background-image","");
    responseDiv.innerHTML = o.responseText;
  }
}

YAHOO.example.DDList = function(id, sGroup, config) {

  YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup, config);

  this.logger = this.logger || YAHOO;
  var el = this.getDragEl();
  Dom.setStyle(el, "opacity", 0.57); // The proxy is slightly transparent

  this.goingUp = false;
  this.lastY = 0;
};

YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy, {

  startDrag: function(x, y) {
    this.logger.log(this.id + " startDrag");

    // make the proxy look like the source element
    var dragEl = this.getDragEl();
    var clickEl = this.getEl();
    Dom.setStyle(clickEl, "visibility", "hidden");

    dragEl.innerHTML = clickEl.innerHTML;

    Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
    Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
    Dom.setStyle(dragEl, "font-size", Dom.getStyle(clickEl, "font-size"));
    Dom.setStyle(dragEl, "font-family", Dom.getStyle(clickEl, "font-family"));
    Dom.setStyle(dragEl, "border", "2px solid gray");
    Dom.setStyle(dragEl, "text-align", "center");
  },

  endDrag: function(e) {

    var srcEl = this.getEl();
    var proxy = this.getDragEl();
    setModified(true);
    // Show the proxy element and animate it to the src element's location
    Dom.setStyle(proxy, "visibility", "");
    var a = new YAHOO.util.Motion( 
      proxy, { 
        points: { 
          to: Dom.getXY(srcEl)
        }
      }, 
      0.2, 
      YAHOO.util.Easing.easeOut 
    )
    var proxyid = proxy.id;
    var thisid = this.id;

    // Hide the proxy and show the source element when finished with the animation
    a.onComplete.subscribe(function() {
        Dom.setStyle(proxyid, "visibility", "hidden");
        Dom.setStyle(thisid, "visibility", "");
      });
    a.animate();
  },

  onDragDrop: function(e, id) {

    // If there is one drop interaction, the li was dropped either on the list,
    // or it was dropped on the current location of the source element.
    if (DDM.interactionInfo.drop.length === 1) {

      // The position of the cursor at the time of the drop (YAHOO.util.Point)
      var pt = DDM.interactionInfo.point; 

      // The region occupied by the source element at the time of the drop
      var region = DDM.interactionInfo.sourceRegion; 

      // Check to see if we are over the source element's location.  We will
      // append to the bottom of the list once we are sure it was a drop in
      // the negative space (the area of the list without any list items)
      if (!region.intersect(pt)) {
        var destEl = Dom.get(id);
        var destDD = DDM.getDDById(id);
        destEl.appendChild(this.getEl());
        updateLists(destEl);
        destDD.isEmpty = false;
        DDM.refreshCache();
      }

    }
  },

  onDrag: function(e) {

    // Keep track of the direction of the drag for use during onDragOver
    var y = Event.getPageY(e);

    if (y < this.lastY) {
      this.goingUp = true;
    } else if (y > this.lastY) {
      this.goingUp = false;
    }

    this.lastY = y;
  },

  onDragOver: function(e, id) {
  
    var srcEl = this.getEl();
    var destEl = Dom.get(id);

    // We are only concerned with list items, we ignore the dragover
    // notifications for the list.
    if (destEl.nodeName.toLowerCase() == "li") {
      var orig_p = srcEl.parentNode;
      var p = destEl.parentNode;

      if (this.goingUp) {
        p.insertBefore(srcEl, destEl); // insert above
      } else {
        p.insertBefore(srcEl, destEl.nextSibling); // insert below
      }
      updateLists(p);

      DDM.refreshCache();
    }
  }
});

Event.onDOMReady(YAHOO.example.ScheduleApp.init, YAHOO.example.ScheduleApp, true);

})();

//]]>
</script>


<?php
echo "</td></tr>\n</table>\n";
contentEnd();
pageEnd();
?>
