<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/search.functions.php';

$title = _("Event data import");

$seasonId = "";

// check access rights before user can upload data into server
if (!empty($_GET['season'])) {
  $seasonId = $_GET["season"];
  ensureSeasonAdmin($seasonId, $title);
} else {
  ensureSuperAdmin($title);
}

$html = JavaScriptWarning();

$scripts = "";

// $html .= "<textarea cols='70' rows='10' style='width:100%'>_POST:" . print_r($_POST, true) . "</textarea>\n";

$seasonPar = "";
if (empty($seasonId)) {
  $html .= "<h2>" . utf8entities($title) . "</h2>";
} else {
  $html .= "<h2>" . utf8entities($title . " (" . SeasonName($seasonId)) . ")</h2>";
  $seasonPar = "&amp;season=" . utf8entities($seasonId);
}

if (empty($seasonId)) {
  $return_url = "?view=admin/seasons";
} else {
  $return_url = "?view=admin/seasonadmin$seasonPar";
}

/**
 * @param array $post
 * @param array $import_data
 * @return array $replacers 
 *   ['season_id'] => new season ID, <br>
 *   ['season_name'] => new season name, <br>
 *   ['location'][$resId] => new reservation ID, <br>
 *   ['reservationgroup'][$resId] => new reservation group, <br>
 *   ['date'][$resId] => new date of reservation, <br>
 *   ['series_name'][$serId] => new series name, <br>
 *   ['team_name'][$teamId] => new team name <br>
 */

function get_replacers($post, &$import_data = null) {
  $replacers = array();
  if (!empty($import_data)) {
    $import_data['season_id'] = $replacers['season_id'] = $post['new_season_id'];
    $import_data['season_name'] = $replacers['season_name'] = $post['new_season_name'];
  }
  $help_replacers = array();
  if (!empty($post['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      if (!empty($import_data)) {
        $sres = $import_data['reservations'][$resId];
      }
      // debug_to_apache($resId . " sres: " . print_r($sres, true) . "import_data: " . print_r($import_data, true));

      $origin = null;
      if (isset($post["copyofrlocation$resId"])) // is this a copy?
        $origin = $post["copyofrlocation$resId"];
      if (!empty($origin) && isset($post["copyloc$origin"])) { // should we copy
        $id = $post["copyofrlocation$resId"];
        $loc = $replacers['location'][$id];
      } else {
        $loc = $post['rlocations'][$i];
      }
      $replacers['location'][$resId] = $loc;

      if (!empty($sres) && isset($replacers['location'][$resId]))
        $sres['location'] = $replacers['location'][$resId];

      $origin = null;
      if (isset($post["copyofresgroup$resId"])) // is this a copy?
        $origin = $post["copyofresgroup$resId"];
      if (!empty($origin) && isset($post["copyrgroup$origin"])) { // should we copy
        $replacers['reservationgroup'][$resId] = $replacers['reservationgroup'][$post["copyofresgroup$resId"]];
      } else
        $replacers['reservationgroup'][$resId] = $post["resgroup$resId"];

      if (!empty($sres) && isset($replacers['reservationgroup'][$resId]))
        $sres['reservationgroup'] = $replacers['reservationgroup'][$resId];

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

      if ($date0 !== $date1) {
        $replacers['date'][$resId] = strtotime($date1) - strtotime($date0);

        if (!empty($sres))
          $sres['starttime'] = EpocToMysql(strtotime($sres['starttime']) + $replacers['date'][$resId]);
      }
      // debug_to_apache([$resId, "sres" =>  $sres, "rep" => $replacers, "help" => $help_replacers]);
      if (!empty($sres))
        $import_data['reservations'][$resId] = $sres;
      // debug_to_apache(["import_data" => $import_data]);
    }
  }
  foreach ($post['rename_series'] as $i => $serId) {
    $replacers['series_name'][$serId] = $post['seriesnames'][$i];
    if (!empty($import_data)) {
      $import_data['series'][$serId]['name'] = $replacers['series_name'][$serId];
    }
  }
  foreach ($post['teams'] as $i => $teamId) {
    $replacers['team_name'][$teamId] = $post['teamnames'][$i];
    if (!empty($import_data)) {
      foreach ($import_data['series'] as $serId => $ser) {
        if (isset($ser['teams'][$teamId])) {
          // debug_to_apache("teamId: $teamId, serId: $serId, old: {$import_data['series'][$serId]['teams'][$teamId]['name']}, new: {$replacers['team_name'][$teamId]}");
          $import_data['series'][$serId]['teams'][$teamId]['name'] = $replacers['team_name'][$teamId];
          break;
        }
      }
    }
  }
  return $replacers;
}

function check_replacers($post, $replacers) {
  $message = "";
  if (!empty($post['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      $origin = null;
      if (isset($post["copyofrlocation$resId"])) // is this a copy?
        $origin = $post["copyofrlocation$resId"];
      if (!empty($origin) && isset($post["copyloc$origin"])) { // should we copy
        $id = $post["copyofrlocation$resId"];
        $loc = $replacers['location'][$id];
        $locName = $post["rlocation{$id}Name"];
      } else {
        $loc = $post['rlocations'][$i];
        $locName = $post["rlocation{$resId}Name"];
      }

      $locHere = LocationInfo($loc)['name'] ?? null;
      if (empty($locName) || $locName != $locHere) {
        $message .= utf8entities(
          sprintf(
            _(
              "Reservation %s, Location %s: Your name '%s' does not match our name '%s'. Add a location or confirm existing location."
            ),
            $resId,
            $loc,
            $locName,
            $locHere
          )
        ) . "<br />\n";
      }
    }
  }
  if (!empty($message)) {
    throw new Exception($message);
  }
  return $replacers;
}

function copy_box($reference, $id, $label, $checked, $listener = '') {
  $cid = $id . $reference;
  $checked = $checked ? "checked='true'" : '';
  return "<label for='$cid'>" .
    "<input class='input' type='checkbox' $checked name='{$cid}' id='$cid' onchange='$listener'/>" .
    "$label</label>";
}

function link_script($all_listeners) {
  $script = "<script type=\"text/javascript\">
//<![CDATA[
  window.onload = function() {\n";
  $map = "  var dependent = new Map();\n";
  foreach ($all_listeners as $type => $listeners) {
    $map .= "  dependent.set('{$type}',new Map());\n";
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
          if (empty($rkeys['getChkBox'])) {
            $script .= "    var masterChecked = false;\n";
          } else {
            $script .= "    var masterChecked = document.getElementById (\"" . ($rkeys['getChkBox']) . "\").checked;\n";
          }
          $script .= "    document.getElementById (\"" . ($rkeys['getDisplay'])($id) . "\").disabled = masterChecked;\n";
        }
        $dependent .= ']';
        $map .= "  dependent.get('{$type}').set($origin, $dependent);\n";
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

function source_selection($new = true) {
  global $seasonPar;
  if ($new) {
    $html = "<p>" . utf8entities(_("You can import from a file or from another division.")) . "</p>\n";
  } else {
    $html = "<p>" . utf8entities(_("Would you like to start over?")) . "</p>\n";
  }

  $html .= "<a href='?view=admin/eventdataexport$seasonPar'>" . utf8entities(_("Export to a file")) . "</a><br />\n";
  $html .= "<a href='?view=admin/eventdataimport$seasonPar&amp;source=xml'>" . utf8entities(_("Import from a file")) .
    "</a><br />\n";
  $html .= "<a href='?view=admin/eventdataimport$seasonPar&amp;source=database'>" .
    utf8entities(_("Import from another division")) . "</a>";
  return $html;
}

$filename = "" . UPLOAD_DIR . "tmp/restorefile.xml";
include_once 'lib/yui.functions.php';
$html .= yuiLoad(array("utilities", "datasource", "autocomplete", "calendar"));

ini_set("post_max_size", "30M");
ini_set("upload_max_filesize", "30M");
ini_set("memory_limit", -1);

$step = $_POST['step'] ?? "";

// $html .= "<form method='post' action='?view=admin/eventdataimport'><input type='hidden' name='a&amp;b' value='c &d'/><input type='submit' /></form>\n";

if (empty($_GET['source']) || isset($_POST['cancel'])) {
  $html .= source_selection();
} else {
  $sourcePar = "&amp;source=" . $_GET['source'];
  $html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport$seasonPar$sourcePar' onkeydown='return event.key != \"Enter\";'>\n";

  if (empty($step) || (!empty($_POST['load_series']) && empty($_POST['series']))) {
    if ($_GET['source'] == 'xml') {
      $html .= getHiddenInput('select_xml', 'step');
      $html .= "<h3>" . utf8entities(_("Select XML file to import")) . ": </h3>\n";

      $html .= "<p><input class='input' type='file' size='80' name='restorefile'/>";
      $html .= getHiddenInput(30000000, 'MAX_FILE_SIZE') . "</p>\n";

      $button_name = 'load';
      $button_label = utf8entities(_("Check file..."));

      $html .= "<p><input class='button' type='submit' name='$button_name' value='$button_label'/>";
      $html .= "<input class='button' type='button' name='return'  value='" . utf8entities(_("Return")) .
        "' onclick=\"window.location.href='$return_url'\"/></p>";
    } else {
      if (!empty($_POST['load_series']) && empty($_POST['series'])) {
        $html .= "<p class='alert'>" . utf8entities(_("No series selected!")) . "</p>\n";
      }

      $html .= "<h3>" . utf8entities(_("Select division from another tournament")) . "</h3>\n";
      $target = "view=admin/eventdataimport$seasonPar$sourcePar";
      if (!empty($seasonId))
        $target .= "&amp;season=$seasonId";
      $html .= SearchSeries($target, ['step' => 'select_division'], array('load_series' => _("Check divison(s)...")));
    }
  }

  if ($step == 'select_xml') {
    if (move_uploaded_file($_FILES['restorefile']['tmp_name'], $filename)) {
      set_time_limit(300);
      $eventdatahandler = new EventDataXMLHandler();

      $import_data = $eventdatahandler->XMLStructure($filename); // $eventdatahandler->XMLGetSeason($filename);
      if (empty($import_data['error'])) {
        $step = 'load_data';
        $source = 'xml';
        $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
      } else {
        $html .= "<p class='alert'>" . $import_data['error'] . "</p>\n";
      }
    } else {
      $html .= "<p>" .
        sprintf(
          _("Invalid file: %s (error code %s). Make sure that the directory is not write-protected."),
          $filename,
          $_FILES['restorefile']['error']
        ) . "</p>\n";
    }
    if (empty($source)) {
      $html .= "<hr />" . source_selection();
    }
  }

  if ($step == 'select_division' && !empty($_POST['series'])) {
    $html .= getHiddenInput($_POST['series'], 'import_series');
    $html .= getHiddenInput('select_template', 'step');
    $html .= "<p>" . utf8entities(_("Selected divisions:")) . "</p>\n";
    $html .= "<ul>";
    foreach ($_POST['series'] as $serieId) {
      $html .= "<li>" . utf8entities(SeriesName($serieId)) . "</li>\n";
    }
    $html .= "</ul>";
    $html .= "<label for='template'>" . utf8entities(_("Import as template, without results")) .
      "<input class='input' checked type='checkbox' id='template' name='template' /></label>\n";
    $html .= "<p><input class='button' type='submit' name='import' value='" . utf8entities(_("Choose mode...")) . "'/>";
  }
  $template = $_POST['template'] ?? '';

  if (isset($_POST['import_data'])) {
    $import_data = json_decode($_POST['import_data'], true);
  }

  if ($step == 'select_template') {
    $eventdatahandler = new EventDataXMLHandler();

    $importedSeason = null;
    $imported = [];
    foreach ($_POST['import_series'] as $ser) {
      $serInfo = SeriesInfo($ser);
      if ($importedSeason != null && $serInfo['season'] != $importedSeason) {
        $html .= "<p class='alert'>" .
          utf8entities(sprintf(_("Warning: Divisions of multiple seasons found, ignoring '%s'."), $serInfo['name'])) .
          "</p>\n";
      } else {
        $importedSeason = $serInfo['season'];
        $imported[] = $ser;
        $html .= getHiddenInput($ser, 'selected_series[]');
      }
    }
    try {
      $data = $eventdatahandler->EventToXML($importedSeason, $imported, $template == 'on');
      $html .= getHiddenInput($template, 'template');

      $import_data = $eventdatahandler->XMLStructure(null, $data);
      if (empty($import_data['error'])) {
        $step = 'load_data';
        $source = 'database';
        $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
      } else {
        $html .= "<p>" . $import_data['error'] . "</p>\n";
      }
    } catch (Exception $e) {
      $html .= "<p id='statusMessage' class='warning' >" . utf8entities($e->getMessage()) . "</p>\n";
      $html .= "<script>setTimeout(\"document.getElementById('statusMessage').style.display='none';\",2000);</script>";
    }
    if (empty($source)) {
      $html .= "<hr />" . source_selection();
    }
  }

  if ($step == 'import') {
    if (isset($_POST['goback'])) {
      $step = 'load_data';
    } else {
      try {

        $modified_import_data = $import_data;
        $replacers = get_replacers($_POST, $modified_import_data);

        check_replacers($_POST, $replacers);

        /*debug_to_apache(
          [
            "do_event_data_import",
            $_POST['import_mode'],
            $_POST['mock'],
            $replacers,
            $_POST['source'],
            $filename,
            $seasonId,
            $_POST['new_season_name'],
            $import_data,
            $_POST['selected_series'],
            $template == 'on',
            $_POST,
          ]
        );*/
        [$import_form, $error_message, $wasImported] = do_event_data_import(
          $_POST['import_mode'],
          !empty($_POST['mock']),
          $replacers,
          $_POST['source'],
          $filename,
          $seasonId,
          $_POST['new_season_name'],
          $import_data,
          $_POST['selected_series'],
          $template == 'on',
          $_POST
        );

        /*debug_to_apache(
          [
            $error_message,
            $wasImported
          ]
        );*/

        $html .= $import_form;

        $step = $wasImported ? $step : 'load_data';
      } catch (Exception $e) {
        $error_message = "<p class='warning'>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) .
          "<br />" . $e->getMessage() . "</p>\n";

        $step = 'load_data';
      }
    }
  }

  $firstRun = false;
  if (!isset($modified_import_data)) {
    if (isset($_POST['modified_import_data'])) {
      $modified_import_data = json_decode($_POST['modified_import_data'], true);
    } else {
      $modified_import_data = $import_data;
      $firstRun = true;
    }
  }

  // debug_to_apache(['import_data' => $import_data, 'modified_import_data' => $modified_import_data]);

  if (!empty($import_data))
    $html .= getHiddenInput(json_encode($import_data), 'import_data');

  if (!empty($modified_import_data))
    $html .= getHiddenInput(json_encode($modified_import_data), 'modified_import_data');

  if ($step == 'load_data') {

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
      uasort(
        $reservations,
        function ($a, $b) {
          $cmp1 = $a['starttime'] <=> $b['starttime'];
          if ($cmp1 != 0)
            return $cmp1;
          return $a['fieldname'] <=> $b['fieldname'];
        }
      );
      return $reservations;
    }

    if (!empty($error_message)) {
      $html .= $error_message;
    }

    $html .= "<p><a href='?view=admin/locations' target='_blank'>" . _("Add a location") . "</a></p>\n";

    $source = $_GET['source'];

    $html .= getHiddenInput('import', 'step');
    $html .= getHiddenInput($source, 'source');
    if (!empty($seasonId)) {
      $html .= "<fieldset>";
      $html .= "<p><input type='radio' checked='checked' id='insert_mode' name='import_mode' value='insert_mode' />";
      $html .= "<label for='insert_mode'>" .
        _(
          "This operation inserts one or more new divisions into the event. It will only add, not alter any data or change user rights."
        ) .
        "</label></p>\n";
      $html .= "<p><input type='radio' id='replace_mode' name='import_mode' value='replace_mode' />";
      $html .= "<label for='replace_mode'>" .
        _(
          "This operation updates and adds event data in the database. It will not delete any data or change user rights."
        ) .
        "</label></p>\n";
      $html .= "</fieldset>";
    } else {
      $html .= "<fieldset>";
      $html .= "<p><input type='radio' checked='checked' id='new_mode' name='import_mode' value='new_mode' />";
      $html .= "<label for='new_mode'>" .
        _(
          "This operation creates a new season and inserts one or more new divisions. It will only not change user rights."
        ) .
        "</label></p>\n";
      $html .= "</fieldset>";
    }
    $html .= "<br /><table class='formtable'><tr><td colspan='4' class='infocell'>" . _(
      "Confirm or replace event data:"
    ) . "</td></tr>\n";
    if (!empty($seasonId)) {
      $html .= "<td class='infocell'>" . _("Event ID") .
        "</td><td><input type='hidden' name='new_season_id' value='$seasonId'/>$seasonId</td>\n";
      $html .= "<td class='infocell'>" . _("Event Name") . "</td><td><input type='hidden' name='new_season_name' value='" .
        utf8entities(SeasonName($seasonId)) . "'/>" . utf8entities(SeasonName($seasonId)) . "</td></tr>\n";
    } else {
      $html .= "<td>" . _("Event ID") .
        "</td><td><input class='input' size='30' maxlength='30' name='new_season_id' value='" .
        utf8entities($import_data['season_id']) . "'/></td>\n";
      $html .= "<td>" . _("Event Name") .
        "</td><td><input class='input' size='30' maxlength='50' name='new_season_name' value='" .
        utf8entities($import_data['season_name']) . "'/></td></tr>\n";
    }
    $html .= "</table><br />\n";
    $html .= "<table class='formtable'>\n";

    if (!empty($import_data['reservations'])) {
      $html .= "<tr><td colspan='4' class='infocell'>" . _("Change reservations?") . "</td></tr>\n";
      $locations = array();
      $dates = array();
      $dateIds = array();
      $resgroups = array();
      $listeners = array('location' => array());
      foreach (locationsSorted($import_data['reservations']) as $loc => $locres) {
        foreach (reservationsSorted($locres['reservations']) as $rkey => $rval) {
          $id = "rlocation" . utf8entities($rkey);
          $locId0 = $rval['location'];
          $rval2 = $modified_import_data['reservations'][$rkey];
          $locId2 = $rval2['location'];
          $value = utf8entities($locId0);
          $label = "<input type='hidden' id='reservation" . utf8entities($rkey) . "' name='reservations[]' value='" .
            utf8entities($rkey) . "' />" . sprintf(_("Location %s map to"), $value);
          if (isset($locations[$locId0])) {
            $origin = $listeners['location'][$locId0]['origin'];
            $postText = "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
              sprintf(_("copy of location %s"), $value);
            $listeners['location'][$locId0]['copys'][] = $rkey;
          } else {
            $label2 = sprintf(_("Change all locations %s to this value"), $value);
            $postText = copy_box($rkey, "copyloc", $label2, $firstRun, "toggleDependent(\"location\", this, $rkey)");
            $locations[$locId0] = $id;
            $listeners['location'][$locId0] = array(
              'origin' => $rkey,
              'copys' => array(),
              'getDisplay' => function ($id) {
                return "rlocation" . utf8entities($id) . "Name";
              },
              'getChkBox' => "copyloc" . utf8entities($rkey),
            );
          }

          $html .= "<tr><th>" . sprintf(_("Reservation %d"), $rkey) . "</th><th>" . _("replacement value") .
            "</th><th></th></tr>\n";
          $html .= "<tr><td><label for='{$id}Name'>$label</label></td><td>";
          // FIXME location id refers to old location
          $lname = LocationInfo($locId2)['name'] ?? '';
          $html .= LocationInput2($id, 'rlocations', $lname, $locId2);
          $html .= "</td><td>" . $postText . "</td></tr>\n";

          $scripts .= LocationScript($id);

          $id = "resgroup" . utf8entities($rkey);
          $value = $rval2['reservationgroup'];

          $html .= "<tr><td><label for='$id'>" . _("Reservation Group") . "</label>:</td><td>" .
            "<input type='text' class='input' name='$id' id='$id' value='$value'/></td><td>";

          if (isset($resgroups[$value])) {
            $origin = $listeners['reservationgroup'][$value]['origin'];
            $html .= "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
              sprintf(_("copy of reservation group %s"), $value) . "</td></tr>\n";
            $listeners['reservationgroup'][$value]['copys'][] = $rkey;
          } else {
            $label = sprintf(_("Change all reservation groups %s to this value"), $value);
            $html .= copy_box($rkey, "copyrgroup", $label, $firstRun, "toggleDependent(\"reservationgroup\", this, $rkey)") .
              "</td></tr>\n";
            $resgroups[$value] = $id;
            $listeners['reservationgroup'][$value] = array(
              'origin' => $rkey,
              'copys' => array(),
              'getDisplay' => function ($id) {
                return "resgroup" . utf8entities($id);
              },
              'getChkBox' => "copyrgroup" . utf8entities($rkey),
            );
          }

          $id = "olddates" . utf8entities($rkey);
          $olddate = ShortDate($rval['starttime']);
          $olddateVal = utf8entities($olddate);
          $newdate = ShortDate($rval2['starttime']);
          $newdateVal = utf8entities($newdate);
          $nid = "newdates" . utf8entities($rkey);
          $html .= "<tr><td><label for='$nid'>" . _("Date") . " (" . _("dd.mm.yyyy") . ")</label>:</td><td>" .
            "<input type='hidden' name='olddates[]' id='$id' value='$olddateVal' />" . getCalendarInput($nid, $newdate) .
            "</td><td>";
          $dateIds[] = $nid;

          if (isset($dates[$olddate])) {
            $origin = $listeners['date'][$olddate]['origin'];
            $html .= "<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
              sprintf(_("copy of date %s"), $olddate) . "</td></tr>\n";
            $listeners['date'][$olddate]['copys'][] = $rkey;
          } else {
            $label = sprintf(_("Change all dates %s to this value"), $olddate);
            $html .= copy_box($rkey, "copydate", $label, $firstRun, "toggleDependent(\"date\", this, $rkey)") . "</td></tr>\n";
            $dates[$olddate] = $id;
            $listeners['date'][$olddate] = array(
              'origin' => $rkey,
              'copys' => array(),
              'getDisplay' => function ($id) {
                return "newdates" . utf8entities($id);
              },
              'getChkBox' => "copydate" . utf8entities($rkey),
            );
          }
        }
      }
      $scripts .= getCalendarScript($dateIds);

      $scripts .= link_script($listeners);
    }

    $html .= "</table><br />\n";
    $html .= "<table class='formtable'>\n";

    if (!isset($modified_import_data)) {
      $modified_import_data = $import_data;
    }

    $html .= "<tr><td colspan='4' class='infocell'>" . _("Change divisions or teams?") . "</td></tr>\n";
    foreach ($modified_import_data['series'] as $skey => $sval) {
      $id = "seriesnames" . utf8entities($skey);
      $html .= "<tr><td  class='infocell'><label for='$id'>" . _("Division Name") . "</label></td><td>" .
        "<input type='hidden' id='import_series" . utf8entities($skey) . "' name='rename_series[]' value='" .
        utf8entities($skey) . "' />" . "<input class='input' maxlength='50' id='$id' name='seriesnames[]' value='" .
        utf8entities($sval['name']) . "'/></td></tr>\n";
      foreach ($sval['teams'] as $tkey => $tval) {
        $tid = "teamnames" . utf8entities($tkey);
        $html .= "<tr><td>" . "<input type='hidden' id='teams" . utf8entities($tkey) . "' name='teams[]' value='" .
          utf8entities($tkey) . "' /><label for='$tid'>" . _("Team Name") .
          "</label></td><td colspan='2'><input class='input' maxlength='50' id='$tid' name='teamnames[]' value='" .
          utf8entities($tval['name']) . "'/></td></tr>\n";
      }
    }
    $html .= "</table>\n";

    $html .= "<input class='button' type='submit' name='mock' value='" . utf8entities(_("Mock (test only)")) . "'/>";
    $html .= " <input class='button' type='submit' name='import' value='" . utf8entities(_("Import")) . "'/>";
    $html .= " <input class='button' type='submit' name='cancel' value='" . utf8entities(_("Cancel")) . "'/></p>";
  }

  $html .= "</form>";
  $html .= "<hr />" . source_selection(false);
}

$html .= $scripts;

showPage($title, $html);
