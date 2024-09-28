<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$html = "";
$template = 0;
$addmore = false;

$poolId = intval($_GET["pool"] ?? -1);
if ($poolId > 0) {
  $info = PoolInfo($poolId);
  $season = $info['season'];
  $seriesId = $info['series'];
} else {
  $poolId = 0;
  $season = $_GET['season'] ?? null;
  $seriesId = intval($_GET['series'] ?? -1);
  if ($season === null || $seriesId <= 0)
    die("missing parameter");
}

ensureEditSeriesRight($seriesId);

// pool parameters
$pp = array(
  "name" => "",
  "ordering" => "A",
  "visible" => "0",
  "continuingpool" => "0",
  "placementpool" => "0",
  "mvgames" => "0",
  "timeoutlen" => "0",
  "halftime" => "0",
  "winningscore" => "0",
  "timecap" => "0",
  "timeslot" => "0",
  "scorecap" => "0",
  "played" => "0",
  "addscore" => "0",
  "halftimescore" => "0",
  "timeouts" => "0",
  "timeoutsper" => "game",
  "timeoutsovertime" => "0",
  "timeoutstimecap" => "0",
  "betweenpointslen" => "0",
  "series" => $seriesId,
  "type" => "0",
  "playoff_template" => "",
  "color" => "ffffff",
  "forfeitscore" => "0",
  "forfeitagainst" => "0",
  "drawsallowed" => "0"
);

// process itself on submit
if (! empty($_POST['add'])) {
  if (! empty($_POST['name'])) {
    $ordering = 'A';
    if (! empty($_POST['ordering'])) {
      $ordering = $_POST['ordering'];
    }
    $template = $_POST['template'];
    $poolId = PoolFromPoolTemplate($seriesId, $_POST['name'], $ordering, $template);
    $html .= "<p>" . _("Pool added") . ": <a href='?view=admin/addseasonpools&pool=$poolId'>" . utf8entities(U_($_POST['name'])) . "</a></p>";
    $html .= "<hr/>";
    $addmore = true;
  } else {
    $html .= "<p class='warning'>" . _("Pool name is mandatory!") . "</p>";
  }
}

if (! empty($_POST['save'])) {
  $ok = true;
  $pp['name'] = $_POST['name'];
  $pp['series'] = $seriesId;
  $pp['timeoutlen'] = intval($_POST['timeoutlength']);
  $pp['halftime'] = intval($_POST['halftimelength']);
  $pp['winningscore'] = intval($_POST['gameto']);
  $pp['timecap'] = intval($_POST['timecap']);
  $pp['timeslot'] = intval($_POST['timeslot']);
  $pp['scorecap'] = intval($_POST['pointcap']);
  $pp['addscore'] = intval($_POST['extrapoint']);
  $pp['halftimescore'] = intval($_POST['halftimepoint']);
  $pp['timeouts'] = intval($_POST['timeouts']);
  $pp['timeoutsper'] = $_POST['timeoutsfor'];
  $pp['timeoutsovertime'] = intval($_POST['timeoutsOnOvertime']);
  $pp['timeoutstimecap'] = intval($_POST['timeoutsOnOvertime']);
  $pp['betweenpointslen'] = intval($_POST['timebetweenPoints']);
  $pp['type'] = intval($_POST['type']);
  if (empty($_POST['playoff_template'])) {
    $pp['playoff_template'] = NULL;
  } else {
    $pp['playoff_template'] = $_POST['playoff_template'];
  }
  $comment = $_POST['comment'];
  $pp['ordering'] = $_POST['ordering'];
  $pp['mvgames'] = intval($_POST['mvgames']);
  $pp['color'] = $_POST['color'];
  $pp['forfeitscore'] = intval($_POST['forfeitscore']);
  $pp['forfeitagainst'] = intval($_POST['forfeitagainst']);
  
  if (! empty($_POST['visible']) && $_POST['type'] != 100)
    $pp['visible'] = 1;
  else
    $pp['visible'] = 0;
  
  if (! empty($_POST['played']))
    $pp['played'] = 1;
  else
    $pp['played'] = 0;
  
  if (! empty($_POST['continuation']) || $_POST['type'] == 100)
    $pp['continuingpool'] = 1;
  else
    $pp['continuingpool'] = 0;
  
  if (! empty($_POST['placementpool']) || $_POST['type'] == 100)
    $pp['placementpool'] = 1;
  else
    $pp['placementpool'] = 0;
  
  if (! empty($_POST['drawsallowed']))
    $pp['drawsallowed'] = 1;
  else
    $pp['drawsallowed'] = 0;
  
  if ($ok) {
    SetPoolDetails($poolId, $pp, $comment);
    session_write_close();
    header("location:?view=admin/seasonpools&season=$season");
  }
}
if ($poolId) {
  $info = PoolInfo($poolId);
  
  $pp['name'] = $info['name'];
  $pp['timeoutlen'] = $info['timeoutlen'];
  $pp['halftime'] = $info['halftime'];
  $pp['winningscore'] = $info['winningscore'];
  $pp['timecap'] = $info['timecap'];
  $pp['timeslot'] = $info['timeslot'];
  $pp['scorecap'] = $info['scorecap'];
  $pp['addscore'] = $info['addscore'];
  $pp['halftimescore'] = $info['halftimescore'];
  $pp['timeouts'] = $info['timeouts'];
  $pp['timeoutsper'] = $info['timeoutsper'];
  $pp['timeoutsovertime'] = $info['timeoutsovertime'];
  $pp['timeoutstimecap'] = $info['timeoutstimecap'];
  $pp['betweenpointslen'] = $info['betweenpointslen'];
  $pp['continuingpool'] = $info['continuingpool'];
  $pp['placementpool'] = $info['placementpool'];
  $pp['played'] = $info['played'];
  $pp['visible'] = $info['visible'];
  $pp['series'] = $info['series'];
  $pp['type'] = $info['type'];
  $pp['playoff_template'] = $info['playoff_template'];
  $pp['ordering'] = $info['ordering'];
  $pp['mvgames'] = $info['mvgames'];
  $pp['color'] = $info['color'];
  $pp['forfeitagainst'] = $info['forfeitagainst'];
  $pp['forfeitscore'] = $info['forfeitscore'];
  $pp['drawsallowed'] = $info['drawsallowed'];
}
$title = _("Edit");

// common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array(
  "utilities",
  "slider",
  "colorpicker",
  "datasource",
  "autocomplete"
));

?>
<script type="text/javascript">

(function() {
    var Event = YAHOO.util.Event, picker;

    Event.onDOMReady(function() {
            picker = new YAHOO.widget.ColorPicker("colorcontainer", {
                    showhsvcontrols: false,
                    showhexcontrols: true,
                    showhexsummary: false,
                    showrgbcontrols: false,
                    showwebsafe: false,
          images: {
            PICKER_THUMB: "script/yui/colorpicker/assets/picker_thumb.png",
            HUE_THUMB: "script/yui/colorpicker/assets/hue_thumb.png"
            }
                });
            picker.setValue([<?php
            echo hexdec(substr($pp['color'], 0, 2)) . ", ";
            echo hexdec(substr($pp['color'], 2, 2)) . ", ";
            echo hexdec(substr($pp['color'], 4, 2));
            ?>], true);
      var onRgbChange = function(o) {
        var val = picker.get("hex");
        YAHOO.util.Dom.get('color').value = val;
        var btn = YAHOO.util.Dom.get('showcolor');
        YAHOO.util.Dom.setStyle(btn, "background-color", "#" + val);
      }

      //subscribe to the rgbChange event;
      picker.on("rgbChange", onRgbChange);

      var handleColorButton = function() {
        var containerDiv = YAHOO.util.Dom.get("colorcontainer");
        if(containerDiv.style.display == "none"){
          YAHOO.util.Dom.setStyle(containerDiv, "display", "block");
        } else {
          YAHOO.util.Dom.setStyle(containerDiv, "display", "none");
        }
      }
        YAHOO.util.Event.addListener("showcolor", "click", handleColorButton);

        });
})();

</script>

<?php
pageTopHeadClose($title);
leftMenu();
contentStart();

echo $html;

// if poolId is empty, then add new pool
if ((! $poolId || $addmore) && !empty($season) && !empty($seriesId)) {
  echo "<h2>" . _("Add pool") . "</h2>\n";
  echo "<form method='post' action='?view=admin/addseasonpools&amp;season=$season&amp;series=$seriesId'>";
  echo "<table class='formtable'>
      <tr>
      <td class='infocell'>" . _("Name") . ":</td>
      <td>" . TranslatedField2("name", $pp['name']) . "</td>
      </tr>\n";
  echo "<tr>
      <td class='infocell'>" . _("Order") . " (A,B,C,D ...):</td>
      <td><input class='input' id='ordering' name='ordering' value='" . utf8entities($pp['ordering']) . "'/></td>
    </tr>\n";
  echo "<tr>
      <td class='infocell'>" . _("Template") . ":</td>
      <td><select class='dropdown' name='template'>";
  
  $templates = PoolTemplates();
  
  foreach ($templates as $row) {
    if ($template == $row['template_id']) {
      echo "<option class='dropdown' selected='selected' value='" . utf8entities($row['template_id']) . "'>" . utf8entities($row['name']) . "</option>";
    } else {
      echo "<option class='dropdown' value='" . utf8entities($row['template_id']) . "'>" . utf8entities($row['name']) . "</option>";
    }
  }
  echo "</select></td>
    </tr>";
  
  echo "</table>
      <p><input class='button' name='add' type='submit' value='" . _("Add") . "'/>
      <input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonpools&amp;season=$season'\"/></p>
      </form>";
} else if ($poolId && !$addmore) {
  echo "<h2>" . _("Edit pool") . ":</h2>\n";
  echo "<form method='post' action='?view=admin/addseasonpools&amp;pool=$poolId&amp;season=$season'>";
  
  echo "<table class='formtable'>
    <tr>
      <td class='infocell'>" . _("Name") . ":</td>
      <td>" . TranslatedField("name", $pp['name']) . "</td>
    </tr>\n";
  
  $seriesname = SeriesName($pp['series']);
  echo "<tr><td class='infocell'>" . _("Division") . ":</td>
      <td><input class='input' id='series' name='series' disabled='disabled' value='" . utf8entities($seriesname) . "'/></td>
      <td></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("Order") . " (A,B,C,D ...):</td>
    <td><input class='input' id='ordering' name='ordering' value='" . utf8entities($pp['ordering']) . "'/></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("Type") . ":</td>\n<td>";
  
  echo "<select class='dropdown' name='type' id='type' onchange='updateBoxes();'>";
  
  $hasGames = !CanGenerateGames($poolId);
  
  foreach (PoolTypes() as $type => $typeId) {
    $selected = ($typeId == $info['type'])?" selected='selected'":"";
    $name = PoolTypeName($typeId);
    if ($name === null) continue;
    $name = utf8entities($name);
    if ($hasGames && $typeId == 100) $selected .= " disabled='disabled'";
    
    echo "<option class='dropdown'${selected} value='$typeId'>$name</option>";
  }
    
  echo "</select></td></tr>\n";
    
  echo "<tr><td class='infocell'>" . _("Special playoff template") . ":</td>";
  
  echo "<td><input class='input' type='text' id='playoff_template' name='playoff_template' list='template_choices' value='" . utf8entities($pp['playoff_template']) . "' />\n";
  echo "<datalist id='template_choices'>\n";
  foreach (PlayoffTemplates() as $key => $template) {
    $selected = $template === $pp['playoff_template'] ? "selected='selected'" : "";
    echo "<option $selected value='" . utf8entities($template) . "' />\n";
  }
  echo "</datalist></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("Move games") . ":</td><td>";
  
  echo "<select class='dropdown' name='mvgames'>";
  if ($pp['mvgames'] == "0")
    echo "<option class='dropdown' selected='selected' value='0'>" . _("All") . "</option>";
  else
    echo "<option class='dropdown' value='0'>" . _("All") . "</option>";
  
  if ($pp['mvgames'] == "1")
    echo "<option class='dropdown' selected='selected' value='1'>" . _("Nothing") . "</option>";
  else
    echo "<option class='dropdown' value='1'>" . _("Nothing") . "</option>";
  
  if ($pp['mvgames'] == "2")
    echo "<option class='dropdown' selected='selected' value='2'>" . _("Mutual") . "</option>";
  else
    echo "<option class='dropdown' value='2'>" . _("Mutual") . "</option>";
  
  echo "</select></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("Visible") . ":</td>";
  
  $frompool = PoolGetMoveFrom($info['pool_id'], 1);
  $followingPool = false;
  if (!empty($frompool)) {
    $frompoolinfo = PoolInfo($frompool['frompool']);
    $followingPool = rtrim($frompoolinfo['ordering'], "0..9") == rtrim($pp['ordering'], "0..9");
  }
  // CS: Sometimes you want to change the visibility setting in Swissdraw
  if ($followingPool || $pp['type'] == 100) { // Playoff or Swissdraw or placement
    echo "<td><input class='input' disabled='disabled' type='checkbox' id='visible' name='visible'/></td>";
  } else {
    if (intval($pp['visible']))
      echo "<td><input class='input' type='checkbox' id='visible' name='visible' checked='checked'/></td>";
    else
      echo "<td><input class='input' type='checkbox' id='visible' name='visible' /></td>";
  }
  echo "<td></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("Played") . ":</td>";
  if (intval($pp['played']))
    echo "<td><input class='input' type='checkbox' id='played' name='played' checked='checked'/></td>";
  else
    echo "<td><input class='input' type='checkbox' id='played' name='played' /></td>";
  echo "<td></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("continuation pool") . ":</td>";
  if ($followingPool || $pp['type'] == 100) { // Playoff or Swissdraw or placement
    echo "<td><input class='input' disabled='disabled' type='checkbox' id='continuation' name='continuation' checked='checked'/></td>";
  } else {
    if (intval($pp['continuingpool']))
      echo "<td><input class='input' type='checkbox' id='continuation' name='continuation' checked='checked'/></td>";
    else
      echo "<td><input class='input' type='checkbox' id='continuation' name='continuation' /></td>";
  }
  echo "<td></td></tr>";
  
  echo "<tr><td class='infocell'>" . _("Placement pool") . ":</td>";
  if ($pp['type'] == 100)
    echo "<td><input class='input' disabled='disabled' type='checkbox' id='placementpool' name='placementpool' checked='checked'/></td>";
  else if (intval($pp['placementpool']))
    echo "<td><input class='input' type='checkbox' id='placementpool' name='placementpool' checked='checked'/></td>";
  else
    echo "<td><input class='input' type='checkbox' id='placementpool' name='placementpool' /></td>";
  echo "<td></td></tr>";
  
  if (intval($pp['continuingpool'])) {
    echo "<tr><td class='infocell'>" . _("Initial moves") . ":</td>
      <td><a href='?view=admin/poolmoves&amp;pool=$poolId'>" . _("select") . "</a></td>
      <td></td></tr>";
  }
  echo "<tr><td class='infocell'>" . _("Color") . ":</td>";
  echo "<td><input class='input' type='hidden' id='color' name='color' value='" . utf8entities($pp['color']) . "'/>\n";
  echo "<button type='button' id='showcolor' class='button' style='background-color:#" . $pp['color'] . "'>" . _("Select") . "</button></td>";
  echo "<td></td></tr>";
  
  $comment = CommentRaw(3, $poolId);
  echo "<tr><td class='infocell'>" . htmlentities(_("Comment (you can use <b>, <em>, and <br /> tags)")) . ":</td>
    <td><textarea class='input' rows='10' cols='70' maxlength='5000' id='comment' name='comment'>" . htmlentities($comment) . "</textarea></td></tr>";
  
  echo "</table>";
  echo "<div class='yui-skin-sam colorcontainer' id='colorcontainer' style='display:none'></div>";
  
  echo "<h2>" . _("Teams") . ":</h2>";
  
  $teams = PoolTeams($poolId);
  if (count($teams)) {
    echo "<table class='infotable'><tr><th>" . _("Name") . "</th><th>" . _("Club") . "</th></tr>\n";
    
    foreach ($teams as $team) {
      echo "<tr>";
      echo "<td>" . utf8entities($team['name']) . "</td>";
      echo "<td>" . utf8entities($team['clubname']) . "</td>";
      echo "</tr>\n";
    }
    echo "</table>";
  } else {
    echo "<p>" . _("No teams") . "</p>";
  }
  // echo "<p><input class='button' name='add' type='button' value='"._("Valitse ...")."' onclick=\"window.location.href='?view=admin/serieteams&amp;Serie=$seriesId&amp;season=$season'\"/></p>";
  
  echo "<h2>" . _("Rules") . " " . _("(from the selected template)") . ":</h2>";
  
  echo "<table class='formtable'>";
  
  echo "<tr><td class='infocell'>" . _("Game points") . ":</td>
      <td><input class='input' id='gameto' name='gameto' value='" . utf8entities($pp['winningscore']) . "'/></td>
      <td></td></tr>

    <tr><td class='infocell'>" . _("Half-time") . ":</td>
      <td><input class='input' id='halftimelength' name='halftimelength' value='" . utf8entities($pp['halftime']) . "'/></td>
      <td>" . _("minutes") . "</td></tr>

    <tr><td class='infocell'>" . _("Half-time at point") . ":</td>
      <td><input class='input' id='halftimepoint' name='halftimepoint' value='" . utf8entities($pp['halftimescore']) . "'/></td>
      <td></td></tr>

    <tr><td class='infocell'>" . _("Time cap") . ":</td>
      <td><input class='input' id='timecap' name='timecap' value='" . utf8entities($pp['timecap']) . "'/></td>
      <td>" . _("minutes") . "</td></tr>

    <tr><td class='infocell'>" . _("Time slot") . ":</td>
      <td><input class='input' id='timeslot' name='timeslot' value='" . utf8entities($pp['timeslot']) . "'/></td>
      <td>" . _("minutes") . "</td></tr>

    <tr><td class='infocell'>" . _("Point cap") . ":</td>
      <td><input class='input' id='pointcap' name='pointcap' value='" . utf8entities($pp['scorecap']) . "'/></td>
      <td>" . _("points") . "</td></tr>

    <tr><td class='infocell'>" . _("Additional points after time cap") . ":</td>
      <td><input class='input' id='extrapoint' name='extrapoint' value='" . utf8entities($pp['addscore']) . "'/></td>
      <td>" . _("points") . "</td></tr>


    <tr><td class='infocell'>" . _("Time between points") . ":</td>
      <td><input class='input' id='timebetweenPoints' name='timebetweenPoints' value='" . utf8entities($pp['betweenpointslen']) . "'/></td>
      <td>" . _("seconds") . "</td></tr>

    <tr><td class='infocell'>" . _("Time-outs") . ":</td>
      <td><input class='input' id='timeouts' name='timeouts' value='" . utf8entities($pp['timeouts']) . "'/></td>
      <td>
      <select class='dropdown' name='timeoutsfor'>";
  if ($pp['timeoutsper'] == "game" || $pp['timeoutsper'] == "")
    echo "<option class='dropdown' selected='selected' value='game'>" . _("per game") . "</option>";
  else
    echo "<option class='dropdown' value='game'>" . _("per game") . "</option>";
  
  if ($pp['timeoutsper'] == "half")
    echo "<option class='dropdown' selected='selected' value='half'>" . _("per half") . "</option>";
  else
    echo "<option class='dropdown' value='half'>" . _("per half") . "</option>";
  
  echo "  </select>
      </td></tr>

    <tr><td class='infocell'>" . _("Time-out duration") . ":</td>
      <td><input class='input' id='timeoutlength' name='timeoutlength' value='" . utf8entities($pp['timeoutlen']) . "'/></td>
      <td>" . _("seconds") . "</td></tr>

    <tr><td class='infocell'>" . _("Time-outs in overtime") . ":</td>
      <td><input class='input' id='timeoutsOnOvertime' name='timeoutsOnOvertime' value='" . utf8entities($pp['timeoutsovertime']) . "'/></td>
      <td>" . _("per team") . "</td></tr>
    ";
  
  echo "
    <tr><td class='infocell'>" . _("Forfeit/BYE against") . ":</td>
      <td><input class='input' id='forfeitagainst' name='forfeitagainst' value='" . utf8entities($pp['forfeitagainst']) . "'/></td>
      <td>" . _("points for the team giving up / BYE") . "</td></tr>

    <tr><td class='infocell'>" . _("Forfeit/BYE score") . ":</td>
      <td><input class='input' id='forfeitscore' name='forfeitscore' value='" . utf8entities($pp['forfeitscore']) . "'/></td>
      <td>" . _("points for their remaining opponent") . "</td></tr>

    ";
  
  echo "<tr><td class='infocell'>" . _("Draws allowed") . ":</td>";
  if (intval($pp['drawsallowed']))
    echo "<td><input class='input' type='checkbox' id='drawsallowed' name='drawsallowed' checked='checked'/></td>";
  else
    echo "<td><input class='input' type='checkbox' id='drawsallowed' name='drawsallowed' /></td>";
  echo "<td></td></tr>";
  
  echo "</table>";
  
  echo "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
  echo "<input class='button' type='button' name='back'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonpools&amp;season=$season'\"/></p>";
  echo "</form>\n";
}
echo TranslationScript("name");
echo "<script type=\"text/javascript\">
  function enable(id, value) {
    if (value)
      document.getElementById(id).removeAttribute(\"disabled\");
    else
      document.getElementById(id).setAttribute(\"disabled\", \"true\");
  }
  function updateBoxes() {
    var typeSelect = document.getElementById(\"type\");
    var value = typeSelect.value;
    if (value == 100) {
      enable(\"visible\", false);
      enable(\"continuation\", false);
      enable(\"placementpool\", false);
    } else {
      enable(\"visible\", true);
      enable(\"continuation\", true);
      enable(\"placementpool\", true);
    }
  }
</script>
";


postContent();
?>
