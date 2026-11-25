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

$scripts = '';
$error_message = '';

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

$filename = "" . UPLOAD_DIR . "tmp/restorefile.xml";
include_once 'lib/yui.functions.php';
$html .= yuiLoad(array("utilities", "datasource", "autocomplete", "calendar"));

ini_set("post_max_size", "30M");
ini_set("upload_max_filesize", "30M");
ini_set("memory_limit", -1);

/* Steps:
  * '': first select source, then select xml file or division to import
  * load_xml: select XML file to import
  * select_import_mode: select division to import
  * read_import_data: select template to import
  * load_data: load data to import
  * do_import: import data
  */

$step = $_POST['next_step'] ?? "";




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
  $html .= "<hr />\n";
  $html .= "<a href='?view=admin/dfvimport$seasonPar'>" . utf8entities(_("Import from DFV")) . "</a><br />\n";
  $html .= "<a href='?view=plugins/utimport$seasonPar'>" . utf8entities(_("Import from Ultimate Tournament")) . "</a><br />\n";
  return $html;
}

// $html .= "<form method='post' action='?view=admin/eventdataimport'><input type='hidden' name='a&amp;b' value='c &d'/><input type='submit' /></form>\n";

if (empty($_GET['source']) || isset($_POST['action_cancel'])) {
  $html .= source_selection();
} else {
  $sourcePar = "&amp;source=" . $_GET['source'];
  $html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport$seasonPar$sourcePar' onkeydown='return event.key != \"Enter\";'>\n";

  if (empty($step) || (!empty($_POST['load_series']) && empty($_POST['series']))) {
    if ($_GET['source'] == 'xml') {
      $html .= getHiddenInput('load_xml', 'next_step');
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
        $html .= "<p class='warning'>" . utf8entities(_("No series selected!")) . "</p>\n";
      }

      $html .= "<h3>" . utf8entities(_("Select division from another tournament")) . "</h3>\n";
      $target = "view=admin/eventdataimport$seasonPar$sourcePar";
      if (!empty($seasonId))
        $target .= "&amp;season=$seasonId";
      $html .= SearchSeries($target, ['next_step' => 'select_import_mode'], array('load_series' => _("Check divison(s)...")));
    }
  }

  if ($step == 'load_xml') {
    if (move_uploaded_file($_FILES['restorefile']['tmp_name'], $filename)) {
      set_time_limit(300);
      $eventdatahandler = new EventDataXMLHandler();

      $import_data = $eventdatahandler->XMLStructure($filename); // $eventdatahandler->XMLGetSeason($filename);
      if (empty($import_data['error'])) {
        $step = 'load_data';
        $source = 'xml';
        $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
      } else {
        $html .= "<p class='warning'>" . $import_data['error'] . "</p>\n";
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

  if ($step == 'select_import_mode' && !empty($_POST['series'])) {
    $html .= getHiddenInput($_POST['series'], 'import_series');
    $html .= getHiddenInput('read_import_data', 'next_step');
    $html .= "<p>" . utf8entities(_("Selected divisions:")) . "</p>\n";
    $html .= "<ul>";
    foreach ($_POST['series'] as $serieId) {
      $html .= "<li>" . utf8entities(SeriesName($serieId)) . "</li>\n";
    }
    $html .= "</ul>";
    $html .= "<label for='template'>" . utf8entities(_("Import as template, without results")) .
      "<input class='input' checked type='checkbox' id='template' name='template' /></label>\n";
    $html .= "<p><input class='button' type='submit' name='action_import' value='" . utf8entities(_("Choose mode...")) . "'/>";
  }
  $template = $_POST['template'] ?? '';

  if (isset($_POST['import_data'])) {
    $import_data = json_decode($_POST['import_data'], true);
  }

  if ($step == 'read_import_data') {
    $eventdatahandler = new EventDataXMLHandler();

    $importedSeason = null;
    $imported = [];
    foreach ($_POST['import_series'] as $ser) {
      $serInfo = SeriesInfo($ser);
      if ($importedSeason != null && $serInfo['season'] != $importedSeason) {
        $html .= "<p class='warning'>" .
          utf8entities(sprintf(_("Warning: Divisions of multiple seasons found, ignoring '%s'."), $serInfo['name'])) .
          "</p>\n";
      } else {
        $importedSeason = $serInfo['season'];
        $imported[] = $ser;
      }
    }
    $html .= getHiddenInput($imported, 'selected_series');

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

  if ($step == 'do_import') {
    if (isset($_POST['selected_series']))
      $html .= getHiddenInput($_POST['selected_series'], 'selected_series');

    try {

      $modified_import_data = $import_data;
      $replacers = get_replacers($_POST, $modified_import_data);

      check_replacers($_POST, $replacers);

      $source = $_POST['source'];
      $mock = !empty($_POST['mock']);

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
        $mock,
        $replacers,
        $source,
        $filename,
        $seasonId,
        $_POST['new_season_name'],
        $import_data,
        $_POST['selected_series'] ?? [],
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

      $step = !empty($wasImported) && !$mock ? $step : 'load_data';
    } catch (Exception $e) {
      $error_message = "<p class='warning'>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) .
        "<br />" . $e->getMessage() . "</p>\n";

      $step = 'load_data';
    }
  }

  $firstRun = false;
  if (!isset($modified_import_data)) {
    if (isset($_POST['modified_import_data'])) {
      $modified_import_data = json_decode($_POST['modified_import_data'], true);
    } else {
      $modified_import_data = $import_data ?? null;
      $firstRun = true;
    }
  }

  if (!empty($import_data))
    $html .= getHiddenInput(json_encode($import_data), 'import_data');

  if (!empty($modified_import_data))
    $html .= getHiddenInput(json_encode($modified_import_data), 'modified_import_data');

  if ($step == 'load_data') {
    $source = $_GET['source'];
    $html .= getHiddenInput($source, 'source');
    $html .= getHiddenInput('do_import', 'next_step');

    [$html2, $scripts2] = get_replacement_form(
      $error_message,
      $seasonId,
      $import_data,
      $modified_import_data,
      $firstRun
    );
    $html .= $html2;
    $scripts .= $scripts2;
  }


  $html .= "</form>";
  $html .= "<hr />" . source_selection(false);
}

$html .= $scripts;

showPage($title, $html);
