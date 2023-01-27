<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/common.functions.php';
require_once ("lib/HSVClass.php");

$LAYOUT_ID = SCHEDULE;
$title = _("Team selection");
define("TEAM_HEIGHT", 15);

$seriesId = 0;
if (isset($_GET['series'])) {
  $seriesId = intval($_GET['series']);
}

$pools = SeriesPools($seriesId, false, true, false);
$not_started = array();
foreach ($pools as $pool) {
  if (!IsPoolStarted($pool['pool_id'])) {
    $not_started[] = $pool['pool_id'];
  }
}
$pools = $not_started;
$teamsNotInPool = SeriesTeamsWithoutPool($seriesId);

// common page
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "dragdrop"));
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

echo JavaScriptWarning();

if (count($pools)) {

  echo "<table class='contenttable'><tr><td>";

  // teams without pool
  echo "<h3>" . _("Without pool") . "</h3>\n";
  echo "<div class='workarea' >\n";
  echo "<ul id='unpooled' class='draglist' style='height:400px'>\n";
  foreach ($teamsNotInPool as $team) {
    if (hasEditTeamsRight($seriesId)) {
      teamEntry(TEAM_HEIGHT, $team['team_id'], $team['name'], $team['rank']);
    }
  }
  echo "</ul>\n";
  echo "</div>\n";
  echo "</td>\n";

  // pools with teams
  echo "<td style='vertical-align:top'>\n";
  echo "<table><tr>\n";
  $areacount = 0;
  $total_pools = count($pools);
  $total_teams = count(SeriesTeams($seriesId));

  foreach ($pools as $poolId) {
    if ($areacount++ % 2 == 0 && $areacount > 1)
      echo "</tr><tr>\n";

    echo "<td>\n";
    $poolinfo = PoolInfo($poolId);
    echo "<table style='width:100%'><tr><td><h3 style='float:left;'>" . $poolinfo['name'] . "</h3></td>";
    echo "<td style='width:1ex'><a href='javascript:clearPool($poolId);'>x</a></td></tr></table>\n";

    echo "<div class='workarea' >\n";
    echo "<ol id='pool" . $poolId . "' class='draglist' style='min-height:" .
      (($total_teams / $total_pools + 1) * TEAM_HEIGHT) . "px'>\n";
    $poolteams = PoolTeams($poolId, 'seed');
    foreach ($poolteams as $team) {
      teamEntry(TEAM_HEIGHT, $team['team_id'], $team['name'], $team['seed']);
    }
    echo "</ol>\n";
    echo "</div>\n";
    echo "</td>\n";
  }

  echo "</tr></table>\n";
  echo "</td></tr>\n";
  echo "<tr><td colspan=4>\n";

  function modeSelect($group, $id, $description, $cells, $checked = false) {
    $checked = $checked ? " checked='checked'" : "";
    echo "      <div class='tabcell'><input type='radio'$checked id='$group$id' name='$group' value='$group$id' /></div>\n";
    echo "     <div class='tabcell'><label for='$group$id'>$description</label>&nbsp;</div><div class='tabcell'><div class='tabular'>";
    foreach ($cells as $row) {
      echo "<div class='tabrow'>";
      foreach ($row as $number) {
        echo "<div class='tabcell' style='padding:1px;'>$number</div>";
      }
      echo "</div>";
    }
    echo "</div></div>\n";
  }

  // save button
  echo "<div class='tabular radioselect' id='user_actions'>\n";
  echo "<div class='tabrow'>";
  echo "  <div class ='tabcell' style='float:left; width:100%; padding:20px 0px 5px 0px'>";
  echo "    <input type='button' style='width:100%;' id='assignButton' value='" . _("Assign to pools") . "'/>";
  echo "  </div>\n";
  if (count($pools) > 1) {
    echo "  <div class='tabcell'><fieldset>\n";
    echo "    <div class='tabular'><div class='tabrow'>\n";
    modeSelect('mode_', 'vertical', _("Vertical"), array(array(1, 3), array(2, 4)));
    modeSelect('mode_', 'horizontal', _("Horizontal"), array(array(1, 2), array(3, 4)));
    modeSelect('mode_', 'snaked', _("Back and forth"), array(array(1, 2), array(4, 3)), true);
    echo "    </div></div></fieldset>";
    echo "  </div>";
  }
  echo "</div>\n";
  echo "<div class='tabrow'><div class='tabcell' style='float:left;width:100%;padding:20px 0px 5px 0px'>\n";
  echo "  <input type='button' style='width:100%;' id='saveButton' value='" . _("Save") . "'/></div>\n";
  echo "  <div class='tabcell center'><div id='responseStatus'></div></div>";
  echo "</div></div>\n";
  echo "<div class='tabrow'><div class='tabcell' style='float:left;width:100%;padding:10px 0px 5px 0px'>\n";
  $seasonId = SeriesSeasonId($seriesId);
  echo "  <a href='?view=admin/seasonpools&amp;season=$seasonId&amp;series=$seriesId'>" . _("Return") . "</a></div>\n";
  echo "</div></div>\n";

  echo "</td></tr></table>\n";

  ?>
<script type="text/javascript">
//<![CDATA[

var Dom = YAHOO.util.Dom;

function hide(id) {
  var elem = Dom.get(id);
  var unpooled = Dom.get("unpooled");
  if (Dom.getAncestorByTagName(elem, "ul") == unpooled)
    unpooled.removeChild(elem);
  else 
    Dom.get("unpooled").appendChild(elem);
}

function clearPool(id) {
  var elem = Dom.get("pool" + id);
  var list = elem.getElementsByTagName(elem, "li");
  var unpooled=Dom.get("unpooled");
  while (elem.firstElementChild != null) {
    unpooled.appendChild(elem.firstElementChild);
  }
}

function assignTeams() {
  var responseDiv = Dom.get("responseStatus");
  Dom.setAttribute(responseDiv, "class", "inprogress");

  var unpooled=Dom.get("unpooled");
  var pools = [
<?php
  $expr = "";
  foreach ($pools as $poolId) {
    if (!empty($expr))
      $expr .= ",";
    $expr .= "    \"pool" . $poolId . "\"\n";
  }
  echo $expr;
  ?>
  ];
  var html = "";
  var items = unpooled.getElementsByTagName("li");
  var numTeams = items.length;
  var mode = 2;
  if (document.getElementById('mode_horizontal') != null) {
    if (document.getElementById('mode_horizontal').checked)
      mode = 1;
    if (document.getElementById('mode_vertical').checked)
      mode = 0;
  }
  var poolSize = Math.floor(numTeams / pools.length);
  var bigger = numTeams - poolSize * pools.length 
  for (var current=0, direction=1, moved=0; items.length > 0; ++moved, current+=direction) {
    Dom.get(pools[current]).appendChild(unpooled.firstElementChild);
    if (mode == 2) {
      if (current >= pools.length - 1 && direction > 0) {
        current += direction;
        direction = -1;
      } else if (current <= 0 && direction < 0) {
        current += direction;
        direction = 1;
      }
    } else if (mode == 1) {
      if (current >= pools.length - 1) {
        current = -direction;
      }
    } else if (mode == 0) {
      if (bigger > current) {
        if ((moved + 1) % (poolSize + 1) !=0) {
          current -= direction;
        }
      } else {
        if ((moved + 1 - bigger) % poolSize !=0) {
          current -= direction;
        }
      }
    }
  }
  Dom.setAttribute(responseDiv, "class", "responseSuccess");
  responseDiv.innerHTML = "<?php echo _("Pools have not been saved!") ?>";
}

document.getElementById("assignButton").onclick = this.assignTeams;


(function() {

var Event = YAHOO.util.Event;
var DDM = YAHOO.util.DragDropMgr;
var pauseIndex = 1;
var minHeight = <?php echo TEAM_HEIGHT; ?>;


YAHOO.example.ScheduleApp = {
  init: function() {
  new YAHOO.util.DDTarget("unpooled");
<?php
  foreach ($pools as $poolId) {
    echo "  new YAHOO.util.DDTarget(\"pool" . $poolId . "\");\n";
    $poolteams = PoolTeams($poolId);
    foreach ($poolteams as $team) {
      if (hasEditTeamsRight($seriesId)) {
        echo "  new YAHOO.example.DDList(\"team" . $team['team_id'] . "\");\n";
      }
    }
  }

  foreach ($teamsNotInPool as $team) {
    if (hasEditTeamsRight($seriesId)) {
      echo "  new YAHOO.example.DDList(\"team" . $team['team_id'] . "\");\n";
    }
  }

  ?>
  Event.on("saveButton", "click", this.requestString);
},
    
    requestString: function() {
        var parseList = function(ul, id) {
            var items = ul.getElementsByTagName("li");
            var out = id;
            if(items.length){
            out += "/";
            }
			var offset = 0;
            for (i=0;i<items.length;i=i+1) {
				var nextId = items[i].id.substring(4);
          
				if (!isNaN(nextId)) {
                	out += nextId;
				}
				if((i+1)<items.length){
				   out += "/";
				}
                
            }
            return out;
        };
<?php
  echo "	var unpooled=Dom.get(\"unpooled\");\n";
  foreach ($pools as $poolId) {
    echo "	var pool" . $poolId . "=Dom.get(\"pool" . $poolId . "\");\n";
  }
  echo "	var request = parseList(unpooled, \"0\") + \"\\n\"";
  foreach ($pools as $poolId) {
    echo " + \"|\" + parseList(pool" . $poolId . ", \"" . $poolId . "\")";
  }
  echo ";\n";
  ?>
	var responseDiv = Dom.get("responseStatus");
	Dom.setAttribute(responseDiv, "class", "inprogress");
	responseDiv.innerHTML = '&nbsp;';
    
	var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=admin/saveteampools', callback, request);         
    },

};

var callback = {
	success: function(o) {
		var responseDiv = Dom.get("responseStatus");
          Dom.setAttribute(responseDiv, "class", "responseSuccess");
		responseDiv.innerHTML = o.responseText;
	},

	failure: function(o) {
		var responseDiv = Dom.get("responseStatus");
          Dom.setStyle(responseDiv, "class", "responseFailure");
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

            DDM.refreshCache();
        }
    }
});

Event.onDOMReady(YAHOO.example.ScheduleApp.init, YAHOO.example.ScheduleApp, true);

})();

//]]>
</script>


<?php
} else {
  echo "no pools";
}
contentEnd();
pageEnd();

function teamEntry($height, $teamId, $name, $seed, $editable = true) {
  // $textColor = textColor($color);
  echo "<li class='list1' style='height:" . $height . "px' id='team" . $teamId . "'>" . $name . " (" . $seed . ")";
  if ($editable) {
    echo "<span style='align:right;float:right'><a href='javascript:hide(\"team" . $teamId . "\");'>x</a></span>";
  }
  echo "</li>\n";
}
?>