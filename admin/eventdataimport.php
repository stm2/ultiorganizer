<?php

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';
include_once 'lib/location.functions.php';

$title = _("Event data import");

$seasonId = "";

$imported = false;

//check access rights before user can upload data into server
if (!empty($_GET['season'])){
  $seasonId = $_GET["season"];
  if (!isSeasonAdmin($seasonId)) { die('Insufficient rights to import data'); }
} else {
  if (!isSuperAdmin()) { die('Insufficient rights to import data');}
}

$html = "";
$scripts = "";

if (empty($seasonId)) {
  $html = "<h2>" . $title . "</h2>";
} else {
  $html = "<h2>" . $title ." (". SeasonName($seasonId) . ")</h2>";
}

$mode = 'select';

if (empty($seasonId)) {
  $button_name = 'new';
  $button_label = _("Import");
  $return_url = "?view=admin/seasons";
} else {
  $button_name = 'replace';
  $button_label = _("Update");
  $return_url = "?view=admin/seasonadmin&amp;season=".$seasonId;
}

function get_replacers($post) {
  $replacers = array();
  $replacers['season_id'] = $post['new_season_id'];
  $replacers['season_name'] = $post['new_season_name'];
  foreach ($post['reservations'] as $i => $resId) {
    $replacers['location'][$resId] = $post['rlocations'][$i];
    $replacers['reservationgroup'][$resId] = $post['resgroups'][$i];
    $date0 = $post['olddates'][$i];
    $date1 = $post['newdates'][$i];
    if ($date0 !== $date1)
      $replacers['date'][$resId] = strtotime($date1) - strtotime($date0);
  }
  foreach ($post['series'] as $i => $serId) {
    $replacers['series_name'][$serId] = $post['seriesnames'][$i];
  }
  foreach ($post['teams'] as $i => $teamId) {
    $replacers['team_name'][$teamId] = $post['teamnames'][$i];
  }
  return $replacers;  
}

$filename = "".UPLOAD_DIR."tmp/restorefile.xml";

if (isset($_POST['load']) && isSuperAdmin()) {
  if(move_uploaded_file($_FILES['restorefile']['tmp_name'], $filename)) {
    set_time_limit(300);
    $eventdatahandler = new EventDataXMLHandler();
    
    $seasonInfo = $eventdatahandler->XMLStructure($filename); // $eventdatahandler->XMLGetSeason($filename);
    if (empty($seasonInfo['error'])) {
      $mode = 'rename';
      $return_url = '?view=admin/eventdataimport&amp;season=' . $seasonId;
    } else {
      $html .= "<p>" . $seasonInfo['error'] . "</p>\n";
    }
  } else {
    $html .= "<p>" . sprintf(_("Invalid file: %s (error code %s). Make sure that the directory is not write-protected"), $filename, $_FILES['restorefile']['error']) . "</p>\n";
  }
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
  set_time_limit(300);
  $eventdatahandler = new EventDataXMLHandler();
  
  $eventdatahandler->XMLToEvent($filename, $seasonId, $mode, get_replacers($_POST), !empty($_POST['mock']));
  
  if (empty($eventdatahandler->error))
    $html .= "<p>" . sprintf(_("Successfully imported %s."), $_POST['new_season_name']) . "</p>\n";
  else
    $html .= "<p>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) . "<br />" . $eventdatahandler->error .
      "</p>\n";
    
  if (!empty($_POST['mock']))
    $html .= "<textarea cols='70' rows='10' style='width:100%'>" . $eventdatahandler->debug ."</textarea>\n";

  // unlink($filename);
  $imported = true;
  $mode = 'select';
}

pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
$html .= yuiLoad(array("utilities", "datasource", "autocomplete", "calendar"));

pageTopHeadClose($title);
leftMenu();
contentStart();


//common page
ini_set("post_max_size", "30M");
ini_set("upload_max_filesize", "30M");
ini_set("memory_limit", -1 );

$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport&amp;season=".$seasonId."'>\n";
if($imported) {
  unset($_POST['restore']);
  unset($_POST['replace']);
}

if ($mode == 'rename') {
  if (!empty($seasonId)) {
    $html .= "<fieldset>";
    $html .= "<p><input type='radio' checked='checked' id='insert_mode' name='rename_mode' value='insert_mode' />";
    $html .= "<label for='insert_mode'>"._("This operation inserts one or more new divisions into the database with the content of the file. It will only add, not alter any data or change user rights.")."</label></p>\n";
    $html .= "<p><input type='radio' id='replace_mode' name='rename_mode' value='replace_mode' />";
    $html .= "<label for='replace_mode'>"._("This operation updates and adds event data in the database with the content of the file. It will not delete any data or change user rights.")."</label></p>\n";
    $html .= "</fieldset>";
  }
  $html .= "<table class='formtable'><tr><td colspan='4' class='infocell'>" . _("Confirm or replace event data:") . "</td></tr>\n";
  if (!empty($seasonId)) {
    $html .= "<td class='infocell'>" . _("Event ID") . "</td><td><input type='hidden' name='new_season_id' value='$seasonId'/>$seasonId</td>\n";
    $html .= "<td class='infocell'>" . _("Event Name") . "</td><td><input type='hidden' name='new_season_name' value='" . utf8entities($seasonInfo['season_name']) . "'/>" . utf8entities($seasonInfo['season_name']) . "</td></tr>\n";
  } else {
    $html .= "<td>" . _("Event ID") . "</td><td><input class='input' size='30' maxlength='30' name='new_season_id' value='" . utf8entities($seasonInfo['season_id']) . "'/></td>\n";
    $html .= "<td>" . _("Event Name") . "</td><td><input class='input' size='30' maxlength='50' name='new_season_name' value='" . utf8entities($seasonInfo['season_name']) . "'/></td></tr>\n";
  }
  
  
  $html .= "<tr><td colspan='4' class='infocell'>" . _("Change reservations?") . "</td></tr>\n";
  foreach ($seasonInfo['reservations'] as $rkey => $rval) {
    $id = "rlocation" . utf8entities($rkey);
    $value = utf8entities($rval['location']);
    $html .= LocationInput($id, 'rlocations', LocationInfo($rval['location'])['name'], 
      "<input type='hidden' id='reservation" . utf8entities($rkey) . "' name='reservations[]' value='" . utf8entities($rkey) . "' />" 
      . sprintf(_("Reservation %d, Location %s map to"), $rkey, $value), $rval['location']);
    $html .= "<tr><td></td><td>&nbsp;</td></tr>\n";
    $scripts .= LocationScript($id);
    
    $html .= "<tr><td>"._("Reservation Group").":</td><td>"
      . "<input type='text' class='input' size='20' name='resgroups[]' id='resgroup" . utf8entities($rkey) . "' value='" . $rval['reservationgroup'] ."'/>&nbsp;</td></tr>\n";
      
      
    $olddate = utf8entities(ShortDate($rval['starttime']));
    $html .= "<tr><td>"._("Date")." ("._("dd.mm.yyyy")."):</td><td>"
      . "<input type='hidden' name='olddates[]' id='olddates" . utf8entities($rkey) . "' value='$olddate' />"
      . "<input type='text' class='input' size='20' name='newdates[]' id='newdates" . utf8entities($rkey) . "' value='$olddate'/>&nbsp;</td></tr>\n";
  }
  
  $disabled = "";
  $html .= "<tr><td colspan='4' class='infocell'>" . _("Change divisions or teams?") . "</td></tr>\n";
  foreach ($seasonInfo['series'] as $skey => $sval) {
    $html .= "<tr><td  class='infocell'>" ._("Division Name"). "</td><td>"
      . "<input type='hidden' id='series" . utf8entities($skey) . "' name='series[]' value='" .utf8entities($skey). "' />"
      . "<input class='input' $disabled size='30' maxlength='50' id='seriesnames" . utf8entities($skey) . "' name='seriesnames[]' value='". utf8entities($sval['name']) ."'/></td></tr>\n";
    foreach ($sval['teams'] as $tkey => $tval) {
      $html .= "<tr><td>"
        . "<input type='hidden' id='teams" . utf8entities($tkey) . "' name='teams[]' value='" .utf8entities($tkey). "' />"
        ._("Team Name"). "</td><td><input class='input' $disabled size='30' maxlength='50' id='teamnames" . utf8entities($tkey) . "' name='teamnames[]' value='". utf8entities($tval['name']) ."'/></td></tr>\n";
    }
  }
  $html .= "<tr><td>&nbsp;</td><td></td></tr><tr><td class='infocell'>" . _("Mock (test only)") .
    "</td><td><input class='input' type='checkbox' name='mock' /></td><tr>\n";
  $html .= "</table>";
}

if ($mode == 'select') {
  $html .= "<br /><p><span class='profileheader'>" . _("Select file to import") . ": </span></p>\n";

  $html .= "<p><input class='input' type='file' size='80' name='restorefile'/>";
  $html .= "<input type='hidden' name='MAX_FILE_SIZE' value='30000000'/></p>";
  
  $button_name = 'load';
  $button_label = _("Check file ...");
}
  
$html .= "<p><input class='button' type='submit' name='$button_name' value='$button_label'/>";
$html .= "<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='$return_url'\"/></p>";

$html .= "</form>";

$html .= $scripts;

// showPage($title, $html);
echo $html;
contentEnd();
pageEnd();


?>