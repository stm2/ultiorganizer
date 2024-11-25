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

function get_replacers($post, &$seasonInfo = null) {
  $replacers = array();
  if (!empty($seasonInfo)) {
    $seasonInfo['season_id'] = $replacers['season_id'] = $post['new_season_id'];
    $seasonInfo['season_name'] = $replacers['season_name'] = $post['new_season_name'];
  }
  $help_replacers = array();
  if (!empty($_POST['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      if (!empty($seasonInfo)) {
        $sres = $seasonInfo['reservations'][$resId];
      }

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
      if (!empty($sres))
        $seasonInfo['reservations'][$resId] = $sres;
    }
  }
  foreach ($post['rename_series'] as $i => $serId) {
    $replacers['series_name'][$serId] = $post['seriesnames'][$i];
    if (!empty($seasonInfo)) {
      $seasonInfo['series'][$serId]['name'] = $replacers['series_name'][$serId];
    }
  }
  foreach ($post['teams'] as $i => $teamId) {
    $replacers['team_name'][$teamId] = $post['teamnames'][$i];
    if (!empty($seasonInfo)) {
      foreach ($seasonInfo['series'] as $serId => $ser) {
        if (isset($ser['teams'][$teamId])) {
          $seasonInfo['series'][$serId]['teams'][$teamId]['name'] = $replacers['team_name'][$teamId];
          break;
        }
      }
    }
  }
  return $replacers;
}

function check_replacers($post, $replacers) {
  $message = "";
  if (!empty($_POST['reservations'])) {
    foreach ($post['reservations'] as $i => $resId) {
      $origin = null;
      if (isset($post["copyofrlocation$resId"])) // is this a copy?
        $origin = $post["copyofrlocation$resId"];
      if (!empty($origin) && isset($post["copyloc$origin"])) { // should we copy
        $id = $post["copyofrlocation$resId"];
        $loc = $replacers['location'][$id];
        $locName = $post["rlocation${id}Name"];
      } else {
        $loc = $post['rlocations'][$i];
        $locName = $post["rlocation${resId}Name"];
      }

      $locHere = LocationInfo($loc)['name'] ?? null;
      if (empty($locName) || $locName != $locHere) {
        $message .= utf8entities(
          sprintf(
            _(
              "Reservation %s, Location %s: Your name '%s' does not match our name '%s'. Add a location or confirm existing location."),
            $resId, $loc, $locName, $locHere)) . "<br />\n";
      }
    }
  }
  if (!empty($message)) {
    throw new Exception($message);
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

      $seasonInfo = $eventdatahandler->XMLStructure($filename); // $eventdatahandler->XMLGetSeason($filename);
      if (empty($seasonInfo['error'])) {
        $step = 'load_data';
        $source = 'xml';
        $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
      } else {
        $html .= "<p class='alert'>" . $seasonInfo['error'] . "</p>\n";
      }
    } else {
      $html .= "<p>" .
        sprintf(_("Invalid file: %s (error code %s). Make sure that the directory is not write-protected."), $filename,
          $_FILES['restorefile']['error']) . "</p>\n";
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
      $template = $_POST['template'] ?? '';
      $data = $eventdatahandler->EventToXML($importedSeason, $imported, $template == 'on');
      $html .= getHiddenInput($template, 'template');

      $seasonInfo = $eventdatahandler->XMLStructure(null, $data);
      if (empty($seasonInfo['error'])) {
        $step = 'load_data';
        $source = 'database';
        $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
      } else {
        $html .= "<p>" . $seasonInfo['error'] . "</p>\n";
      }
    } catch (Exception $e) {
      $html .= "<p id='statusMessage' class='warning' >" . utf8entities($e->getMessage()) . "</p>\n";
      $html .= "<script>setTimeout(\"document.getElementById('statusMessage').style.display='none';\",2000);</script>";
    }
    if (empty($source)) {
      $html .= "<hr />" . source_selection();
    }
  }

  if ($step == 'rename') {
    if (isset($_POST['goback'])) {
      $step = 'load_data';
    } else {
      switch ($_POST['rename_mode']) {
      case 'new_mode':
        if (isSuperAdmin()) {
          $mode = 'new';
        } else {
          die("Insufficient rights");
        }
        break;
      case 'replace_mode':
        $mode = 'replace';
        break;
      case 'insert_mode':
        $mode = 'insert';
        break;
      }
      if ($mode === 'new' || $mode === 'replace' || $mode == 'insert') {
        $html .= getHiddenInput($_POST['selected_series'][0], 'selected_series[]');
        set_time_limit(300);
        $eventdatahandler = new EventDataXMLHandler();
        $mock = !empty($_POST['mock']);
        try {
          $replacers = get_replacers($_POST);
          check_replacers($_POST, $replacers);
          if ($_POST['source'] == 'xml') {
            $eventdatahandler->XMLToEvent($filename, $seasonId, $mode, $replacers, $mock);

            // unlink($filename);
          } else if ($_POST['source'] == 'database') {
            $template = $_POST['template'] ?? '';
            $importedSeason = SeriesInfo($_POST['selected_series']['0'])['season'];
            $data = $eventdatahandler->EventToXML($importedSeason, $_POST["selected_series"], $template == 'on');

            $eventdatahandler->XMLToEvent(null, $seasonId, $mode, $replacers, $mock, $data);
          }

          $html .= "<ul>";
          foreach ($replacers['series_name'] as $newSeriesName) {
            $html .= "<li>" . utf8entities($newSeriesName) . "</li>\n";
          }
          $html .= "</ul>";
          if (empty($eventdatahandler->error)) {
            $html .= "<p>" . sprintf(_("Successfully imported into %s."), $_POST['new_season_name']) . "</p>\n";
          } else {
            $html .= "<p>" . sprintf(_("Error while importing into %s:"), $_POST['new_season_name']) . "<br />" .
              $eventdatahandler->error . "</p>\n";
          }
        } catch (Exception $e) {
          $error_message = "<p class='warning'>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) .
            "<br />" . $e->getMessage() . "</p>\n";
        }

        if ($mock) {
          $html .= "<p>" .
            _("Your data was not actually imported. The debug output is shown below. Do you want to import now?") .
            "</p>";

          $seasonInfo2 = $seasonInfo = json_decode($_POST['reservationsInput'], true);
          $replacers = get_replacers($_POST, $seasonInfo2);

          $html .= "<p>" . _("Debug output:");
          $html .= "<textarea cols='70' rows='10' style='width:100%'>_POST:" . print_r($_POST, true) . "\n\n" .
            $eventdatahandler->debug . "</textarea></p>\n";
        }

        $step = 'load_data';
      }
    }
  }

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
      uasort($reservations,
        function ($a, $b) {
          $cmp1 = $a['starttime'] <=> $b['starttime'];
          if ($cmp1 != 0)
            return $cmp1;
          return $a['fieldname'] <=> $b['fieldname'];
        });
      return $reservations;
    }

    if (!empty($error_message)) {
      $html .= $error_message;
    }

    $html .= "<p><a href='?view=admin/locations' target='_blank'>" . _("Edit locations") . "</a></p>\n";

    $source = $_GET['source'];

    $html .= getHiddenInput(json_encode($seasonInfo), 'reservationsInput');
    $html .= getHiddenInput('rename', 'step');
    $html .= getHiddenInput($source, 'source');
    if (!empty($seasonId)) {
      $html .= "<fieldset>";
      $html .= "<p><input type='radio' checked='checked' id='insert_mode' name='rename_mode' value='insert_mode' />";
      $html .= "<label for='insert_mode'>" .
        _(
          "This operation inserts one or more new divisions into the event. It will only add, not alter any data or change user rights.") .
        "</label></p>\n";
      $html .= "<p><input type='radio' id='replace_mode' name='rename_mode' value='replace_mode' />";
      $html .= "<label for='replace_mode'>" .
        _(
          "This operation updates and adds event data in the database. It will not delete any data or change user rights.") .
        "</label></p>\n";
      $html .= "</fieldset>";
    } else {
      $html .= "<fieldset>";
      $html .= "<p><input type='radio' checked='checked' id='new_mode' name='rename_mode' value='new_mode' />";
      $html .= "<label for='new_mode'>" .
        _(
          "This operation creates a new season and inserts one or more new divisions. It will only not change user rights.") .
        "</label></p>\n";
      $html .= "</fieldset>";
    }
    $html .= "<br /><table class='formtable'><tr><td colspan='4' class='infocell'>" . _(
      "Confirm or replace event data:") . "</td></tr>\n";
    if (!empty($seasonId)) {
      $html .= "<td class='infocell'>" . _("Event ID") .
        "</td><td><input type='hidden' name='new_season_id' value='$seasonId'/>$seasonId</td>\n";
      $html .= "<td class='infocell'>" . _("Event Name") . "</td><td><input type='hidden' name='new_season_name' value='" .
        utf8entities(SeasonName($seasonId)) . "'/>" . utf8entities(SeasonName($seasonId)) . "</td></tr>\n";
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
          if (!empty($seasonInfo2)) {
            $rval2 = $seasonInfo2['reservations'][$rkey];
            $locId2 = $rval2['location'];
          } else {
            $locId2 = $rval['location'];
          }
          $value = utf8entities($locId2);
          $label = "<input type='hidden' id='reservation" . utf8entities($rkey) . "' name='reservations[]' value='" .
            utf8entities($rkey) . "' />" . sprintf(_("Location %s map to"), $value);
          if (isset($locations[$locId2])) {
            $origin = $listeners['location'][$locId2]['origin'];
            $postText = "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
              sprintf(_("copy of location %s"), $value);
            $listeners['location'][$locId2]['copys'][] = $rkey;
          } else {
            $label2 = sprintf(_("Change all locations %s to this value"), $value);
            $postText = copy_box($rkey, "copyloc", $label2, "toggleDependent(\"location\", this, $rkey)");
            $locations[$locId2] = $id;
            $listeners['location'][$locId2] = array('origin' => $rkey, 'copys' => array(),
              'getDisplay' => function ($id) {
                return "rlocation" . utf8entities($id) . "Name";
              });
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
          if (!empty($seasonInfo2)) {
            $value = $rval2['reservationgroup'];
          } else {
            $value = $rval['reservationgroup'];
          }
          
          $html .= "<tr><td><label for='$id'>" . _("Reservation Group") . "</label>:</td><td>" .
            "<input type='text' class='input' name='$id' id='$id' value='$value'/></td><td>";

          if (isset($resgroups[$value])) {
            $origin = $listeners['reservationgroup'][$value]['origin'];
            $html .= "&nbsp;<input type='hidden' name='copyof$id' id='copyof$id' value='$origin' />" .
              sprintf(_("copy of reservation group %s"), $value) . "</td></tr>\n";
            $listeners['reservationgroup'][$value]['copys'][] = $rkey;
          } else {
            $label = sprintf(_("Change all reservation groups %s to this value"), $value);
            $html .= copy_box($rkey, "copyrgroup", $label, "toggleDependent(\"reservationgroup\", this, $rkey)") .
              "</td></tr>\n";
            $resgroups[$value] = $id;
            $listeners['reservationgroup'][$value] = array('origin' => $rkey, 'copys' => array(),
              'getDisplay' => function ($id) {
                return "resgroup" . utf8entities($id);
              });
          }

          $id = "olddates" . utf8entities($rkey);
          if (!empty($seasonInfo2)) {
            $shortdate = ShortDate($rval2['starttime']);
          } else {
            $shortdate = ShortDate($rval['starttime']);
          }
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
?>
