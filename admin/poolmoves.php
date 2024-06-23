<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

if (isset($_SERVER['HTTP_REFERER']))
  $backurl = utf8entities($_SERVER['HTTP_REFERER']);
else 
  $backurl = '';
$seriesId = 0;
if(!empty($_GET["pool"]))
$poolId = intval($_GET["pool"]);

if(!empty($_GET["series"]))
$seriesId = intval($_GET["series"]);

if(!empty($_GET["season"]))
$season = $_GET["season"];

$pools = SeriesPools($seriesId);

$title = _("Continuing pool");

function js_string_encode($userinput) {
  return str_replace(["'", '"'], ["\\&#39;", '\\&quot;'], ($userinput));
}

//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "slider", "colorpicker", "datasource", "autocomplete"));
?>

<script type="text/javascript">
//<![CDATA[

const ROUNDROBIN = <?php echo PoolTypes('roundrobin'); ?>;
const PLAYOFF = <?php echo PoolTypes('playoff'); ?>;
const SWISSDRAW = <?php echo PoolTypes('swissdraw'); ?>;
const CROSSMATCH = <?php echo PoolTypes('crossmatch'); ?>;

const WINNER = "<?php echo js_string_encode(_("{pool} winner {gameno}")); ?>";
const LOSER = "<?php echo js_string_encode(_("{pool} loser {gameno}")); ?>";

var poolMap = new Map();

{
<?php
foreach ($pools as $pool) {
  echo "  poolMap.set('" . $pool['pool_id'] . "', " . $pool['type'] . ");\n";
}
?>
}
function setId(id1, id2) {
  var input = document.getElementById("hiddenDeleteId");
  input.value = id1 + ":" + id2;
}

function setId2(ids) {
  var input = document.getElementById("hiddenDeleteId");
  input.value = ids;
}

function teamName(poolselector, positionInput) {
  var frompool = poolselector[poolselector.selectedIndex].value;
  if (positionInput.value == '') return null;
  if (poolMap.get(frompool) == ROUNDROBIN || poolMap.get(frompool) == SWISSDRAW)
    return poolselector[poolselector.selectedIndex].innerHTML + " " + positionInput.value;
  else if (poolMap.get(frompool) == PLAYOFF || poolMap.get(frompool) == CROSSMATCH) {
    let pool = poolselector[poolselector.selectedIndex].innerHTML;
    let gameno = Math.floor((parseInt(positionInput.value) + 1) / 2);
    if (isNaN(gameno)) return null;
    let winner = (positionInput.value % 2) == 1;
    let phrase = winner ? WINNER : LOSER;
    return phrase.replace(/\{pool\}/, pool).replace(/\{gameno\}/, gameno);
  }
  return null;
}

function checkMove(frompool, infield, outfield, pteamname) {
  var frompool = document.getElementById(frompool);
  var input = document.getElementById(infield);
  var output = outfield != null ? document.getElementById(outfield) : null;
  var pteamname = document.getElementById(pteamname);

  if (input.value.length>0) {
    if (output!=null)
      output.disabled = false;
    pteamname.disabled = false;
  }else {
    if (output!=null)
      output.disabled = true;
    pteamname.disabled = true;
  }
  pteamname.value = teamName(frompool, input);
}

//]]>
</script>

<?php
pageTopHeadClose($title);
leftMenu();
contentStart();
$poolinfo = PoolInfo($poolId);
$typeRR = $poolinfo['type'] === PoolTypes('roundrobin');
$typePlayoff = $poolinfo['type'] === PoolTypes('playoff');
$typeSwiss = $poolinfo['type'] === PoolTypes('swissdraw');
$typeCross = $poolinfo['type'] === PoolTypes('crossmatch');
//$typePartial = $poolinfo['type'] === PoolTypes('partial');
$typePlacement = $poolinfo['type'] === PoolTypes('placement');

$err="";
//process itself on submit
if(!empty($_POST['add']))
{
  $backurl = utf8entities($_POST['backurl']);

  //series pool
  if($typeRR || $typeSwiss){
    $total_teams = 10;

    for($i=0;$i<$total_teams;$i++){
      if(isset($_POST["frompool$i"]) && isset($_POST["movefrom$i"]) && isset($_POST["moveto$i"])){
        $frompool = intval($_POST["frompool$i"]);
        $movefrom = intval($_POST["movefrom$i"]);
        $moveto = intval($_POST["moveto$i"]);
        if(!empty($_POST["pteamname$i"])){
          $pteamname = $_POST["pteamname$i"];
        }else{
          $err .= "<p class='warning'>"._("No scheduling name given").".</p>\n";
        }
        if(PoolMoveExist($frompool,$movefrom)){
          $err .= "<p class='warning'>"._("Transfer already exists").".</p>\n";
        }

        if(empty($err)){
          PoolAddMove($frompool,$poolId,$movefrom,$moveto,$pteamname);
        }
      }
    }
  }else{
    //playoff pool
    $total_teams = 8;

    for($i=0;$i<$total_teams;$i++){

      if(isset($_POST["frompool$i"]) && !empty($_POST["movefrom$i"])){
        $frompool = intval($_POST["frompool$i"]);
        $movefrom = intval($_POST["movefrom$i"]);
        $moves = PoolMovingsToPool($poolId);
        $moveto = count($moves)+1;

        if(!empty($_POST["pteamname$i"])){
          $pteamname = $_POST["pteamname$i"];
          //$pteamname .= " ($moveto)";
        }else{
          $err .= "<p class='warning'>"._("No scheduling name given").".</p>\n";
        }
        if(PoolMoveExist($frompool,$movefrom)){
          $err .= "<p class='warning'>"._("Transfer already exists").".</p>\n";
        }

        if(empty($err)){
          PoolAddMove($frompool,$poolId,$movefrom,$moveto,$pteamname);
        }
      }
    }
  }
}else if(!empty($_POST['remove_x'])){
  $backurl = utf8entities($_POST['backurl']);

  if ($typeRR || $typeSwiss) {
    $move = preg_split('/:/', $_POST['hiddenDeleteId']);
    if(PoolIsMoved($move[0],$move[1])){
      $err .= "<p class='warning'>"._("Team has already moved.")."</p>\n";
    }else{
      PoolDeleteMove($move[0],$move[1]);
    }
  } else {
    $moves = preg_split('/:/', $_POST['hiddenDeleteId']);

    foreach($moves as $m){
      $move = preg_split('/,/', $m);
      if(PoolIsMoved($move[0],$move[1])){
        $err .= "<p class='warning'>"._("Team has already moved.")."</p>\n";
      }else{
        PoolDeleteMove($move[0],$move[1]);
      }
    }
  }
}
echo "<form method='post' action='?view=admin/poolmoves&amp;series=$seriesId&amp;pool=$poolId&amp;season=$season'>";

echo $err;

echo "<h1>".utf8entities(U_(PoolSeriesName($poolId)).", ". U_(PoolName($poolId)))."</h1>\n";


$poolinfo = PoolInfo($poolId);

//round robin or swissdrawn pool
if($typeRR || $typeSwiss || $typePlacement){

  $moves = PoolMovingsToPool($poolId);
  if (!empty($moves)) {
    echo "<table border='0' width='500'><tr>
		<th>" . _("From pool") . "</th>
		<th>" . _("From position") . "</th>
		<th>" . _("To pool") . "</th>
		<th>" . _("To position") . "</th>
		<th>" . _("Move games") . "</th>
		<th>" . _("Name in Schedule") . "</th>
		<th>" . _("Delete") . "</th></tr>";

    foreach ($moves as $row) {
      echo "<tr>";
      echo "<td>" . utf8entities($row['name']) . "</td>";
      echo "<td class='center'>" . intval($row['fromplacing']) . "</td>";
      echo "<td>" . utf8entities(PoolName($poolId)) . "</td>";
      echo "<td class='center'>" . intval($row['torank']) . "</td>";
      if (intval($poolinfo['mvgames']) == 0)
        echo "<td>" . _("all") . "</td>";
      else if (intval($poolinfo['mvgames']) == 1)
        echo "<td>" . _("nothing") . "</td>";
      else if (intval($poolinfo['mvgames']) == 2)
        echo "<td>" . _("mutual") . "</td>";
      echo "<td>" . utf8entities(U_($row['sname'])) . "</td>";
      echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" .
        _("X") . "' onclick=\"setId(" . $row['frompool'] . "," . $row['fromplacing'] . ");\"/></td>";
      echo "</tr>\n";
    }
    echo "</table>";
    echo "<hr/>\n";
  }
  
  echo "<h2>"._("Make transfer rule").":</h2>\n";

  echo "<table>";
  echo "<tr>
		<th>"._("From pool")."</th>
		<th>"._("From position")."</th>
		<th>"._("To position")."</th>	
		<th>"._("Name in Schedule")."</th>
		</tr>";

  $total_teams = 10;

  for($i=0;$i<$total_teams;$i++){
    echo "<tr>\n";
    echo "<td><select class='dropdown' id='frompool$i' name='frompool$i' onchange=\"checkMove('frompool$i','movefrom$i','moveto$i','pteamname$i');\">";
    foreach($pools as $pool){
      if ($pool['pool_id'] != $poolId) {
        $selected = '';
        if ($typeSwiss && $pool['type'] == PoolTypes('swissdraw') && $pool['pool_id'] == $poolId - 1) {
          $selected = " selected='selected' ";
        }
        echo "<option class='dropdown'$selected value='" . utf8entities($pool['pool_id']) . "'>" . utf8entities(
          U_($pool['name'])) . "</option>";
      }
    }
    echo "</select></td>\n";
    echo "<td><input class='input' id='movefrom$i' name='movefrom$i' maxlength='3' size='3' value='' oninput=\"checkMove('frompool$i','movefrom$i','moveto$i','pteamname$i');\"/></td>\n";
    echo "<td><input class='input' id='moveto$i' name='moveto$i' disabled='disabled' maxlength='3' size='3' value='".($i+1)."'/></td>\n";
    //echo "<td><input class='input' id='pteamname$i' name='pteamname$i' size='50' maxlength='100' value=''/>\n";
    echo "<td>".TranslatedField2("pteamname$i","");

    echo TranslationScript("pteamname$i");
    echo "</td>";
    echo "</tr>\n";

  }
  echo "</table>";

  //playoff or crossmatch pool
}else if ($typePlayoff || $typeCross){

  $moves = PoolMovingsToPool($poolId);

  echo "<table border='0' width='600'><tr>
		<th>"._("From pool")."</th>
		<th>"._("From pos.")."</th>
		<th class='right'>"._("Name in Schedule")."</th>
		<th style='width:50px'></th>
		<th>"._("Name in Schedule")."</th>
		<th>"._("From pos.")."</th>
		<th>"._("From pool")."</th>
		<th>"._("Delete")."</th>
		</tr>";

  mergesort($moves, uo_create_key_comparator('torank'));
  // create_function('$a,$b','return $a[\'torank\']==$b[\'torank\']?0:($a[\'torank\']<$b[\'torank\']?-1:1);'));
  for($i=0;$i<count($moves);$i++){
    $move = $moves[$i];
    echo "<tr>";
    //echo "<td class='right'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$move['frompool'].",".$move['fromplacing'].");\"/></td>";
    $deleteids = $move['frompool'].",".$move['fromplacing'];
    echo "<td >".utf8entities($move['name'])."</td>";
    echo "<td class='center'>".intval($move['fromplacing'])."</td>";
    echo "<td class='right'><i>".utf8entities(U_($move['sname']))."</i></td>";
    echo "<td class='center'><b>"._("vs.")."</b></td>";
    $i++;

    if($i<count($moves)){
      $move = $moves[$i];
      $deleteids .= ":".$move['frompool'].",".$move['fromplacing'];
      echo "<td><i>".utf8entities(U_($move['sname']))."</i></td>";
      echo "<td class='center'>".intval($move['fromplacing'])."</td>";
      echo "<td>".utf8entities($move['name'])."</td>";
    }
    echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId2('".$deleteids."');\"/></td>";
    echo "</tr>";

  }

  echo "</table>";

  echo "<hr/>\n";
  echo "<h2>"._("Make transfer rule").":</h2>\n";

  echo "<table border='0' cellpadding='3' width='250'><tr>
		<th>"._("From pool")."</th>
		<th>"._("From position")."</th>
		<th>"._("Name in Schedule")."</th>
		</tr>";

  $total_teams = 8;
  for ($i = 0; $i < $total_teams; $i++) {
    echo "<tr><td colspan='3'><b>" . _("Pair") . " " . ($i / 2 + 1) . "</b></td></tr>\n";

    for ($j = $i; $j < $i + 2; $j++) {
      echo "<tr>\n";
      echo "<td><select class='dropdown' id='frompool$j' name='frompool$j' onchange=\"checkMove('frompool$j','movefrom$j', null, 'pteamname$j');\">";
      foreach ($pools as $pool) {
        if ($pool['pool_id'] != $poolId) {
          echo "<option class='dropdown' value='" . utf8entities($pool['pool_id']) . "'>" .
            utf8entities(U_($pool['name'])) . "</option>";
        }
      }
      echo "</select></td>\n";
      echo "<td><input class='input' id='movefrom$j' name='movefrom$j' maxlength='3' size='3' value='' oninput=\"checkMove('frompool$j','movefrom$j', null, 'pteamname$j');\"/></td>\n";
      echo "<td>" . TranslatedField2("pteamname$j", "", '20em');
      echo TranslationScript("pteamname$j");
      echo "</td>";
      echo "</tr>\n";
    }

    $i++;
  }
  echo "</table>";
} else {
  echo "<p>" . _("Unknown pool type!") . "</p>\n";
  $unknownType = true;
}

if (empty($unknownType))
  echo "<p><input class='button' name='add' type='submit' value='"._("Add")."'/>";
if (!empty($backurl)) {
  echo "<input type='hidden' name='backurl' value='$backurl'/>";
  echo "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/></p>";
}
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";
contentEnd();
pageEnd();
?>
