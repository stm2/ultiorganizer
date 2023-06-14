<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/search.functions.php';

$title = _("Event data import");

$seasonId = "";

$imported = false;

// check access rights before user can upload data into server
if (!empty($_GET['season'])) {
  $seasonId = $_GET["season"];
  ensureSeasonAdmin($seasonId, $title);
} else {
  ensureSuperAdmin($title);
}

$html = JavaScriptWarning();

$scripts = "";

if (empty($seasonId)) {
  $html .= "<h2>" . utf8entities($title) . "</h2>";
} else {
  $html .= "<h2>" . utf8entities($title . " (" . SeasonName($seasonId)) . ")</h2>";
}

$mode = 'select';

if (empty($seasonId)) {
  $button_name = 'new';
  $button_label = _("Import");
  $return_url = "?view=admin/seasons";
} else {
  $button_name = 'replace';
  $button_label = _("Update");
  $return_url = "?view=admin/seasonadmin&amp;season=" . $seasonId;
}

function get_replacers($post) {
  $replacers = array();
  $replacers['season_id'] = $post['new_season_id'];
  $replacers['season_name'] = $post['new_season_name'];
  $help_replacers = array();
  if (!empty($_POST['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      $origin = null;
      if (isset($post["copyofrlocation$resId"])) // is this a copy?
        $origin = $post["copyofrlocation$resId"];
      if (!empty($origin) && isset($post["copyloc$origin"])) { // should we copy
        $replacers['location'][$resId] = $replacers['location'][$post["copyofrlocation$resId"]];
      } else {
        $replacers['location'][$resId] = $post['rlocations'][$i];
      }

      $origin = null;
      if (isset($post["copyofresgroup$resId"])) // is this a copy?
        $origin = $post["copyofresgroup$resId"];
      if (!empty($origin) && isset($post["copyrgroup$origin"])) { // should we copy
        $replacers['reservationgroup'][$resId] = $replacers['reservationgroup'][$post["copyofresgroup$resId"]];
      } else
        $replacers['reservationgroup'][$resId] = $post["resgroup$resId"];

      $date0 = $post['olddates'][$i];

      $origin = null;
      if (isset($post["copyofolddates$resId"])) // is this a copy?
        $origin = $post["copyofolddates$resId"];
      if (!empty($origin) && isset($post["copydate$origin"])) { // should we copy
        $help_replacers['newdate'][$resId] = $help_replacers['newdate'][$post["copyofolddates$resId"]];
      } else
        $help_replacers['newdate'][$resId] = $post["newdates$resId"];
      $date1 = $help_replacers['newdate'][$resId];
      // $date1 = $post['newdates'][$i];

      if ($date0 !== $date1)
        $replacers['date'][$resId] = strtotime($date1) - strtotime($date0);
    }
  }
  foreach ($post['series'] as $i => $serId) {
    $replacers['series_name'][$serId] = $post['seriesnames'][$i];
  }
  foreach ($post['teams'] as $i => $teamId) {
    $replacers['team_name'][$teamId] = $post['teamnames'][$i];
  }
  return $replacers;
}

function copy_box($reference, $id, $label, $listener = '') {
  $cid = $id . $reference;
  return "<label for='$cid'>" .
    "<input class='input' type='checkbox' checked='true' name='{$cid}' id='$cid' onchange='$listener'/>" .
    "$label</label>";
}

function link_script($all_listeners) {
  $script = "<script type=\"text/javascript\">
//<![CDATA[
  window.onload = function() {\n";
  $map = "  var dependent = new Map();\n";
  foreach ($all_listeners as $type => $listeners) {
    $map .= "  dependent.set('${type}',new Map());\n";
    foreach ($listeners as $location => $rkeys) {
      if (!empty($rkeys['copys'])) {
        $origin = $rkeys['origin'];
        $dependent = '';
        foreach ($rkeys['copys'] as $id) {
          if (empty($dependent))
            $dependent .= '[';
          else
            $dependent .= ',';
          $dependent .= '"' . ($rkeys['getDisplay'])($id) . '"';
          $script .= "    document.getElementById (\"" . ($rkeys['getDisplay'])($id) . "\").disabled = true;\n";
        }
        $dependent .= ']';
        $map .= "  dependent.get('${type}').set($origin, $dependent);\n";
        $script .= "    document.getElementById (\"" . ($rkeys['getDisplay'])($origin) .
          "\").addEventListener('change',
        (event) => {
          updateDependent(\"$type\", $origin, event.target.value);
        });\n";
      }
    }
  }
  $script .= "  };\n
$map
  var toggleDependent = function(type, checkbox, rkey) {
    var active = checkbox.checked;
    if (dependent.get(type) != null && dependent.get(type).get(rkey) != null)
      dependent.get(type).get(rkey).forEach((dep) => document.getElementById(dep).disabled = active);
  };
  var updateDependent = function(type, origin, value) {
    dependent.get(type).get(origin).forEach((dep) => { 
      var elem = document.getElementById(dep); 
      if (elem.disabled) elem.value = value; 
    });
  };
//]]>
</script>\n";
  return $script;
}

$filename = "" . UPLOAD_DIR . "tmp/restorefile.xml";

if (isset($_POST['load']) && isSuperAdmin()) {
  if (move_uploaded_file($_FILES['restorefile']['tmp_name'], $filename)) {
    set_time_limit(300);
    $eventdatahandler = new EventDataXMLHandler();

    $seasonInfo = $eventdatahandler->XMLStructure($filename); // $eventdatahandler->XMLGetSeason($filename);
    if (empty($seasonInfo['error'])) {
      $mode = 'rename';
      $source = 'xml';
      $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
    } else {
      $html .= "<p>" . $seasonInfo['error'] . "</p>\n";
    }
  } else {
    $html .= "<p>" .
      sprintf(_("Invalid file: %s (error code %s). Make sure that the directory is not write-protected."), $filename,
        $_FILES['restorefile']['error']) . "</p>\n";
  }
} elseif (isset($_POST['import'])) {
  $html .= "Import " . print_r($_POST['series'], true);
  $mode = 'rename';
  $source = 'series';
  $seasonInfo = ['season_id' => 1, 'season_name' => 'mock series', 'reservations' => [],
    'series' => [3 => ['name' => 'mock series', 'teams' => [6 => ['name' => 'mock team 1']]]]];
} elseif (isset($_POST['new']) && isSuperAdmin()) {
  $mode = 'new';
} elseif (isset($_POST['replace'])) {
  if ($_POST['rename_mode'] === 'replace_mode') {
    $mode = 'replace';
  } elseif ($_POST['rename_mode'] === 'insert_mode') {
    $mode = 'insert';
  }
}
if ($mode === 'new' || $mode === 'replace' || $mode == 'insert') {
  if ($_POST['source'] == 'xml') {
    set_time_limit(300);
    $eventdatahandler = new EventDataXMLHandler();

    try {
      $eventdatahandler->XMLToEvent($filename, $seasonId, $mode, get_replacers($_POST), !empty($_POST['mock']));

      if (empty($eventdatahandler->error))
        $html .= "<p>" . sprintf(_("Successfully imported %s."), $_POST['new_season_name']) . "</p>\n";
      else
        $html .= "<p>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) . "<br />" .
          $eventdatahandler->error . "</p>\n";
    } catch (Exception $e) {
      $html .= "<p>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) . "<br />" . $e->getMessage() .
        "</p>\n";
    }

    if (!empty($_POST['mock']))
      $html .= "<textarea cols='70' rows='10' style='width:100%'>_POST:" . print_r($_POST, true) . "\n\n" .
        $eventdatahandler->debug . "</textarea>\n";

    // unlink($filename);
    $imported = true;
  } else if ($_POST['source'] == 'series') {
    $html .= "<textarea cols='70' rows='10' style='width:100%'>_POST:" . print_r($_POST, true) . "\n\n" . "</textarea>\n";
  }

  $mode = 'select';
}

pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
$html .= yuiLoad(array("utilities", "datasource", "autocomplete", "calendar"));

pageTopHeadClose($title);
leftMenu();
contentStart();

// common page
ini_set("post_max_size", "30M");
ini_set("upload_max_filesize", "30M");
ini_set("memory_limit", -1);

$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport&amp;season=" . $seasonId .
  "'>\n";
if ($imported) {
  unset($_POST['restore']);
  unset($_POST['replace']);
}

if ($mode == 'rename') {

  function locationsSorted(&$reservations) {
    $locationsSorted = [];
    foreach ($reservations as $rkey => $rval) {
      if (isset($locationsSorted[$rval['location']])) {
        $location = &$locationsSorted[$rval['location']];
      } else {
        $location = ['date' => $rval['starttime'], 'reservations' => []];
        $locationsSorted[$rval['location']] = &$location;
      }
      if ($location['date'] > $rval['starttime'])
        $location['date'] = $rval['starttime'];
      $location['reservations'][$rkey] = $rval;
      unset($location);
    }
    uasort($locationsSorted, function ($a, $b) {
      return $a['date'] <=> $b['date'];
    });
    return $locationsSorted;
  }

  function reservationsSorted(&$reservations) {
    uasort($reservations,
      function ($a, $b) {
        $cmp1 = $a['starttime'] <=> $b['starttime'];
        if ($cmp1 != 0)
          return $cmp1;
        return $a['fieldname'] <=> $b['fieldname'];
      });
    return $reservations;
  }

  if (!empty($seasonId)) {
    $html .= "<input type='hidden' name='source' value='$source'/>";
    $html .= "<fieldset>";
    $html .= "<p><input type='radio' checked='checked' id='insert_mode' name='rename_mode' value='insert_mode' />";
    $html .= "<label for='insert_mode'>" .
      _(
        "This operation inserts one or more new divisions into the database with the content of the file. It will only add, not alter any data or change user rights.") .
      "</label></p>\n";
    $html .= "<p><input type='radio' id='replace_mode' name='rename_mode' value='replace_mode' />";
    $html .= "<label for='replace_mode'>" .
      _(
        "This operation updates and adds event data in the database with the content of the file. It will not delete any data or change user rights.") .
      "</label></p>\n";
    $html .= "</fieldset>";
  }
  $html .= "<br /><table class='formtable'><tr><td colspan='4' class='infocell'>" . _("Confirm or replace event data:") .
    "</td></tr>\n";
  if (!empty($seasonId)) {
    $html .= "<td class='infocell'>" . _("Event ID") .
      "</td><td><input type='hidden' name='new_season_id' value='$seasonId'/>$seasonId</td>\n";
    $html .= "<td class='infocell'>" . _("Event Name") . "</td><td><input type='hidden' name='new_season_name' value='" .
      utf8entities($seasonInfo['season_name']) . "'/>" . utf8entities($seasonInfo['season_name']) . "</td></tr>\n";
  } else {
    $html .= "<td>" . _("Event ID") .
      "</td><td><input class='input' size='30' maxlength='30' name='new_season_id' value='" .
      utf8entities($seasonInfo['season_id']) . "'/></td>\n";
    $html .= "<td>" . _("Event Name") .
      "</td><td><input class='input' size='30' maxlength='50' name='new_season_name' value='" .
      utf8entities($seasonInfo['season_name']) . "'/></td></tr>\n";
  }
  $html .= "</table><br />\n";
  $html .= "<table class='formtable'>\n";

  if (!empty($seasonInfo['reservations'])) {
    $html .= "<tr><td colspan='4' class='infocell'>" . _("Change reservations?") . "</td></tr>\n";
    $locations = array();
    $dates = array();
    $dateIds = array();
    $resgroups = array();
    $listeners = array('location' => array());
    foreach (locationsSorted($seasonInfo['reservations']) as $loc => $locres) {
      foreach (reservationsSorted($locres['reservations']) as $rkey => $rval) {
        $id = "rlocation" . utf8entities($rkey);
        $value = utf8entities($rval['location']);
        $label = "<input type='hidden' id='reservation" . utf8entities($rkey) . "' name='reservations[]' value='" .
          utf8entities($rkey) . "' />" . sprintf(_("Location %s map to"), $value);
        if (isset($locations[$rval['location']])) {
          $origin = $listeners['location'][$rval['location']]['origin'];
          $postText = "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
            sprintf(_("copy of location %s"), $value);
          $listeners['location'][$rval['location']]['copys'][] = $rkey;
        } else {
          $label2 = sprintf(_("Change all locations %s to this value"), $value);
          $postText = copy_box($rkey, "copyloc", $label2, "toggleDependent(\"location\", this, $rkey)");
          $locations[$rval['location']] = $id;
          $listeners['location'][$rval['location']] = array('origin' => $rkey, 'copys' => array(),
            'getDisplay' => function ($id) {
              return "rlocation" . utf8entities($id) . "Name";
            });
        }

        $html .= "<tr><th>" . sprintf(_("Reservation %d"), $rkey) . "</th><th>" . _("replacement value") .
          "</th><th></th></tr>\n";
        $html .= "<tr><td><label for='{$id}Name'>$label</label></td><td>";
        $html .= LocationInput2($id, 'rlocations', LocationInfo($rval['location'])['name'], $rval['location']);
        $html .= "</td><td>" . $postText . "</td></tr>\n";

        $scripts .= LocationScript($id);

        $id = "resgroup" . utf8entities($rkey);
        $value = utf8entities($rval['reservationgroup']);
        $html .= "<tr><td><label for='$id'>" . _("Reservation Group") . "</label>:</td><td>" .
          "<input type='text' class='input' name='$id' id='$id' value='$value'/></td><td>";

        if (isset($resgroups[$rval['reservationgroup']])) {
          $origin = $listeners['reservationgroup'][$rval['reservationgroup']]['origin'];
          $html .= "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
            sprintf(_("copy of reservation group %s"), $value) . "</td></tr>\n";
          $listeners['reservationgroup'][$rval['reservationgroup']]['copys'][] = $rkey;
        } else {
          $label = sprintf(_("Change all reservation groups %s to this value"), $value);
          $html .= copy_box($rkey, "copyrgroup", $label, "toggleDependent(\"reservationgroup\", this, $rkey)") .
            "</td></tr>\n";
          $resgroups[$rval['reservationgroup']] = $id;
          $listeners['reservationgroup'][$rval['reservationgroup']] = array('origin' => $rkey, 'copys' => array(),
            'getDisplay' => function ($id) {
              return "resgroup" . utf8entities($id);
            });
        }

        $id = "olddates" . utf8entities($rkey);
        $shortdate = ShortDate($rval['starttime']);
        $olddate = utf8entities($shortdate);
        $nid = "newdates" . utf8entities($rkey);
        $html .= "<tr><td><label for='$nid'>" . _("Date") . " (" . _("dd.mm.yyyy") . ")</label>:</td><td>" .
          "<input type='hidden' name='olddates[]' id='$id' value='$olddate' />" . getCalendarInput($nid, $shortdate) .
          "</td><td>";
        $dateIds[] = $nid;

        if (isset($dates[$olddate])) {
          $origin = $listeners['date'][$shortdate]['origin'];
          $html .= "<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
            sprintf(_("copy of date %s"), $olddate) . "</td></tr>\n";
          $listeners['date'][$shortdate]['copys'][] = $rkey;
        } else {
          $label = sprintf(_("Change all dates %s to this value"), $olddate);
          $html .= copy_box($rkey, "copydate", $label, "toggleDependent(\"date\", this, $rkey)") . "</td></tr>\n";
          $dates[$olddate] = $id;
          $listeners['date'][$shortdate] = array('origin' => $rkey, 'copys' => array(),
            'getDisplay' => function ($id) {
              return "newdates" . utf8entities($id);
            });
        }
      }
    }
    $scripts .= getCalendarScript($dateIds);

    $scripts .= link_script($listeners);
  }

  $html .= "</table><br />\n";
  $html .= "<table class='formtable'>\n";

  $html .= "<tr><td colspan='4' class='infocell'>" . _("Change divisions or teams?") . "</td></tr>\n";
  foreach ($seasonInfo['series'] as $skey => $sval) {
    $id = "seriesnames" . utf8entities($skey);
    $html .= "<tr><td  class='infocell'><label for='$id'>" . _("Division Name") . "</label></td><td>" .
      "<input type='hidden' id='series" . utf8entities($skey) . "' name='series[]' value='" . utf8entities($skey) .
      "' />" . "<input class='input' maxlength='50' id='$id' name='seriesnames[]' value='" . utf8entities($sval['name']) .
      "'/></td></tr>\n";
    foreach ($sval['teams'] as $tkey => $tval) {
      $tid = "teamnames" . utf8entities($tkey);
      $html .= "<tr><td>" . "<input type='hidden' id='teams" . utf8entities($tkey) . "' name='teams[]' value='" .
        utf8entities($tkey) . "' /><label for='$tid'>" . _("Team Name") .
        "</label></td><td colspan='2'><input class='input' maxlength='50' id='$tid' name='teamnames[]' value='" .
        utf8entities($tval['name']) . "'/></td></tr>\n";
    }
  }
  $html .= "<tr><td>&nbsp;</td><td></td></tr>\n<tr><td class='infocell'><label for='mock'>" . _("Mock (test only)") .
    "</label></td><td><input class='input' type='checkbox' name='mock' id='mock'/></td></tr>\n";
  $html .= "</table>\n";
}

if ($mode == 'select') {
  $html .= "<h3>" . _("Select XML file to import") . ": </h3>\n";

  $html .= "<p><input class='input' type='file' size='80' name='restorefile'/>";
  $html .= "<input type='hidden' name='MAX_FILE_SIZE' value='30000000'/></p>";

  $button_name = 'load';
  $button_label = _("Check file ...");
}

$html .= "<p><input class='button' type='submit' name='$button_name' value='$button_label'/>";
$html .= "<input class='button' type='button' name='return'  value='" . _("Return") .
  "' onclick=\"window.location.href='$return_url'\"/></p>";

if ($mode == 'select') {
  $html .= "<h3>" . _("Select division from other tournament") . "</h3>\n";
  $target = "view=admin/eventdataimport";
  if (!empty($seasonId))
    $target .= "&amp;season=$seasonId";
  $html .= SearchSeries($target, [], array('import' => _("Import")));
}

$html .= "</form>";

$html .= $scripts;

echo $html;
contentEnd();
pageEnd();

?>
