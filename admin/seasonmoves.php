<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/yui.functions.php';

$seriesId = 0;
$season = 0;
$title = _("Moved teams");
$html = "";


$seriesId = $_GET["series"] ?? 0;
ensureEditSeriesRight($seriesId, $title);
$season = SeriesSeasonId($seriesId);

$order = $_GET["order"] ?? "to";

$preText = yuiLoad(array("utilities"));

$preText .= <<<EOT
<script type="text/javascript">
function setId3(id1,id2,id3){
     setId(id1 + ":" + id2 + ":" + id3);
}
	
function setPool(poolid,from){
	var input = document.getElementById("hiddenPoolId");
	input.value = poolid + ":" + from;
}
	
function ChgName(index) {
	YAHOO.util.Dom.get('schedulingnameEdited' + index).value = 'yes';
	YAHOO.util.Dom.get("save").disabled = false;
}

function ChgValue(index) {
	YAHOO.util.Dom.get('moveEdited' + index).value = 'yes';
	YAHOO.util.Dom.get("save").disabled = false;
}
</script>

EOT;

addHeaderText($preText);

// process itself on submit
if (!empty($_POST['remove_x'])) {
  $move = preg_split('/:/', $_POST['hiddenDeleteId']);

  PoolDeleteMove($move[0], $move[1]);
} elseif (!empty($_POST['undo'])) {
  $move = preg_split('/:/', $_POST['hiddenDeleteId']);

  PoolUndoMove($move[0], $move[1], $move[2]);
} elseif (!empty($_POST['removeAll_x'])) {
  $params = preg_split('/:/', $_POST['hiddenPoolId']);

  if ($order == "from") { // FIXME or parms[1]?
    $moves = PoolMovingsFromPool($params[0]);
  } else {
    $moves = PoolMovingsToPool($params[0]);
  }

  foreach ($moves as $row) {
    if (!$row['ismoved']) {
      PoolDeleteMove($row['frompool'], $row['fromplacing']);
    }
  }
} elseif (!empty($_POST['undoPool'])) {
  $params = preg_split('/:/', $_POST['hiddenPoolId']);

  if ($order == "from") { // FIXME or parms[1]?
    $moves = PoolMovingsFromPool($params[0]);
  } else {
    $moves = PoolMovingsToPool($params[0]);
  }

  foreach ($moves as $row) {
    if ($row['ismoved']) {
      $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
      if (CanDeleteTeamFromPool($row['topool'], $team['team_id'])) {
        PoolUndoMove($row['frompool'], $row['fromplacing'], $row['topool']);
      }
    }
  }
} elseif (!empty($_POST['save'])) {
  for ($i = 0; $i < count($_POST['schedulingnameEdited']); $i++) {
    if ($_POST['schedulingnameEdited'][$i] == "yes") {
      $id = $_POST['schedulingnameId'][$i];
      PoolSetSchedulingName($id, $_POST["sn$i"], $season);
    }
  }
  for ($i = 0; $i < count($_POST['moveEdited']); $i++) {
    if ($_POST['moveEdited'][$i] == "yes") {
      $id = $_POST['moveId'][$i];
      // PoolSetSchedulingName($id, $_POST["sn$i"], $season);
      $move = preg_split('/:/', $id);
      $frompool = $move[0];
      $fromplacing = $move[1];
      $newfp = $_POST["fromplacing$i"];
      $newtp = $_POST["torank$i"];
      PoolSetMove($frompool, $fromplacing, $newfp, $newtp);
    }
  }
}

if ($order == "to") {
  $html .= "[" . _("Move to") . "]";
  $html .= "&nbsp;&nbsp;";
  $html .= "[<a href='?view=admin/seasonmoves&amp;season=$season&amp;series=$seriesId&amp;order=from'>" . _("Move from") .
    "</a>]";
} else {
  $html .= "[<a href='?view=admin/seasonmoves&amp;season=$season&amp;series=$seriesId&amp;order=to'>" . _("Move to") .
    "</a>]";
  $html .= "&nbsp;&nbsp;";
  $html .= "[" . _("Move from") . "]";
}

$html .= "<form method='post' id='moves' action='?view=admin/seasonmoves&amp;season=$season&amp;series=$seriesId&amp;order=$order'>";

$serieslist = array();
// all series from season
if (!$seriesId) {
  $series = SeasonSeries($season);
  foreach ($series as $row) {
    $serieslist[] = $row;
  }
} else {
  $serieslist[] = array("series_id" => $seriesId, "name" => SeriesName($seriesId));
}

foreach ($serieslist as $series) {
  $hasTransfers = false;
  $pools = SeriesPools($series['series_id']);

  if (count($pools)) {
    $html .= "<h2>" . utf8entities(U_($series['name'])) . "</h2>\n";
    $i = 0;
    foreach ($pools as $pool) {
      if ($order == "from") {
        $moves = PoolMovingsFromPool($pool['pool_id']);
      } else {
        $moves = PoolMovingsToPool($pool['pool_id']);
      }

      if (count($moves)) {
        $hasTransfers = true;
        $html .= "<table class='admintable'><tr>
					<th style='width:25%'>" . _("From pool") . "</th>
					<th style='width:4%'>" . _("From position") . "</th>
					<th style='width:25%'>" . _("To pool") . "</th>
					<th style='width:4%'>" . _("To position") . "</th>
					<th style='width:18%'>" . _("Scheduling name") . "</th>
					<th style='width:10%'>" . _("Move games") .
          "</th>
					<th style='width:14%'><input class='button' type='submit' name='undoPool' value='" . _("Undo") .
          "' onclick=\"setPool(" . $pool['pool_id'] . "," . ($order == "from" ? "true" : "false") .
          ");\"/>
						<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='removeAll' value='" . _("X") .
          "' onclick=\"setPool(" . $pool['pool_id'] . "," . ($order == "from" ? "true" : "false") . ");\"/></th></tr>";
      }

      foreach ($moves as $row) {
        $poolinfo = PoolInfo($row['topool']);
        if ($row['ismoved']) {
          $html .= "<tr class='highlight'>";
        } else {
          $html .= "<tr>";
        }
        $html .= "<td>" . utf8entities(PoolName($row['frompool'])) . "</td>";
        $html .= "<td class='center'>";
        $html .= "<input type='hidden' id='moveEdited" . $i . "' name='moveEdited[]' value='no'/>\n";
        $html .= "<input type='hidden' name='moveId[]' value='" . utf8entities($row['frompool']) . ":" .
          $row['fromplacing'] . "'/>\n";
        $html .= "<input type='text' size='3' maxlength='3' name='fromplacing$i' value='" .
          utf8entities($row['fromplacing']) . "' onkeypress='ChgValue(" . $i . ")'/></td>";
        $html .= "<td>" . utf8entities(PoolName($row['topool'])) . "</td>";
        $html .= "<td class='center'><input type='text' size='3' maxlength='3' name='torank$i' value='" .
          utf8entities($row['torank']) . "' onkeypress='ChgValue(" . $i . ")'/></td>";
        $html .= "<td class='left'>";
        $html .= "<input type='hidden' id='schedulingnameEdited" . $i . "' name='schedulingnameEdited[]' value='no'/>\n";
        $html .= "<input type='hidden' name='schedulingnameId[]' value='" . utf8entities($row['scheduling_id']) . "'/>\n";
        $html .= "<input type='text' size='22' maxlength='50' value='" . utf8entities($row['sname']) .
          "' name='sn$i' onkeypress='ChgName(" . $i . ")'/>";
        $html .= "</td>";
        if (intval($poolinfo['mvgames']) == 0)
          $html .= "<td>" . _("all") . "</td>";
        else if (intval($poolinfo['mvgames']) == 1)
          $html .= "<td>" . _("nothing") . "</td>";
        else if (intval($poolinfo['mvgames']) == 2)
          $html .= "<td>" . _("mutual") . "</td>";
        if ($row['ismoved']) {
          $team = PoolTeamFromStandings($row['frompool'], $row['fromplacing']);
          if (!empty($team) && CanDeleteTeamFromPool($row['topool'], $team['team_id'])) {
            $html .= "<td class='right'><input class='button' type='submit' name='undo' value='" . _("Undo") .
              "' onclick=\"setId3(" . $row['frompool'] . "," . $row['fromplacing'] . "," . $row['topool'] . ");\"/></td>";
          } else {
            $html .= "<td class='right'></td>";
          }
        } else {
          $html .= "<td class='right'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='X' onclick=\"setId3(" .
            $row['frompool'] . "," . $row['fromplacing'] . ");\"/></td>";
        }
        $html .= "</tr>\n";
        $i++;
      }
      if (count($moves)) {
        $html .= "</table>\n";
      }
    }
  }
  if (!$hasTransfers) {
    $html .= "<p>" . _("No transfers.") . "</p>";
  }
}
// stores id to delete
$html .= "<br /><p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/><input type='hidden' id='hiddenPoolId' name='hiddenPoolId'/>";
$html .= "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/>";
$html .= "<input class='button' type='button' name='back'  value='" . _("Return") .
  "' onclick=\"window.location.href='?view=admin/seasonpools&amp;season=$season'\"/></p>";
$html .= "</form>\n";

showPage($title, $html);
?>
