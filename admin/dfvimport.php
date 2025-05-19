<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/data.functions.php';

include_once 'lib/common.functions.php';
include_once 'lib/dfv.functions.php';

include_once 'lib/yui.functions.php';
addHeaderCallback(function () {
  echo yuiLoad(array("utilities", "calendar", "datasource", "autocomplete"));
});

$seasonId = $_GET["season"];
$templateSeasonId = 'template_' . $seasonId;

$title = SeasonName($seasonId) . ": " . _("Import from DFV");
$html = "";
$scripts = "";
$error_message = "";

function displayAdvancedToggle() {
  $html = "<button id='toggleButton' type='button' onclick='toggleSummary()'>" . utf8entities(_("Show advanced options...")) . "</button>";

  $scripts = "<script>
  function toggleDiv(div) {
    if (div == null) return;
    if (div.style.display == 'none') {
      div.style.display = 'block';
    } else {
      div.style.display = 'none';
    }
  }
  function toggleSummary() {
    var toggleButton = document.getElementById('toggleButton');
    if (toggleButton.innerHTML == '" . utf8entities(_("Show advanced options...")) . "') {
      toggleButton.innerHTML = '" . utf8entities(_("Hide advanced options...")) . "';
    } else {
      toggleButton.innerHTML = '" . utf8entities(_("Show advanced options...")) . "';
    }
    var advancedDiv = document.getElementById('advanced');    
    toggleDiv(advancedDiv);
    var advancedDiv = document.getElementById('simple_list');
    toggleDiv(advancedDiv);
    var advancedDiv = document.getElementById('simple_buttons');
    toggleDiv(advancedDiv);
  }
  </script>";
  return [$html, $scripts];
}

$html .= JavaScriptWarning();

ensureSeasonAdmin($seasonId, $title);

$seasonInfo = SeasonInfo($seasonId);

$teamListItems = [];

$seaLink = urlencode($seasonId);
$url_here = utf8entities("?view=admin/dfvimport&season=$seaLink");

$html .= "<form method='post' action='$url_here'>";

$club = !intval($seasonInfo['isnationalteams']);
$country = intval($seasonInfo['isinternational']);

if (!empty($focusId))
  setFocus($focusId);


$importstage = $_GET['importstage'] ?? $_POST['importstage'] ?? 0;
if (isset($_POST['action_cancel'])) {
  $importstage = 0;
  $_POST['importdivision'] = null;
}
if (isset($_POST['action_refresh'])) {
  $importstage = 1;
}
if (isset($_POST['action_import_add'])) {
  $importstage = 2;
}
if (isset($_POST['action_import_create'])) {
  $importstage = 3;
}
if (isset($_POST['action_import_save'])) {
  $importstage = 4;
}
if (isset($_POST['action_filter_division'])) {
  $importstage = 1;
}
if (isset($_POST['action_load_teams'])) {
  $importstage = 1;
}

$importDivision = json_decode($_POST['importdivision'] ?? "", true);

if ($importstage < 2) {
  $html .= displayDFVSelection($seasonId, null, $url_here);

  if ($importstage >= 1) {
    $teams = $importDivision['teams'];

    $valid = 0;
    foreach ($teams as $i => &$team) {
      $team['rank'] = (($team['status'] ?? '') == 'CONFIRMED') ? ++$valid : '';
      if (empty($team['name'])) $team['name'] = $team['team_name'];
      //$team['clubname'] = $team['team_location'];
      $team['abbreviation'] = null;
      $team['country'] = null;
    }
    unset($team);

    if (!empty($teams)) {
      $html .= displayTeamList($teams, $club, $country, $teamListItems);

      $html .= "<p>";
      $html .= utf8entities(_("Order teams to add in correct seeding order, then")) . " <br />\n";
      $html .= "<input class='button' name='action_import_add' type='submit' value='" . utf8entities(_("Accept seeding...")) . "'/> <br />";
      $html .= "<input id='action_cancel' class='button' name='action_cancel' type='submit' value='" .
        utf8entities(_("Cancel")) . "'/>";
      $html .= "</p>";

      $scripts .= getDragDropApp(
        ["addedteams", "notaddedteams"],
        $teamListItems,
        [
          'endDrag' => "
    function(srcEl, proxy, parent) { updateList(srcEl.parentNode);}",
          'dragOver' => "
    function(srcEl, proxy, goingUp) {
      updateList(srcEl.parentNode);
      var orig = srcEl.getElementsByClassName('team_item_seed')[0];
      var copy = proxy.getElementsByClassName('team_item_seed')[0];
      copy.value = parseInt(orig.value) + (goingUp ? -1 : 1);
    }",
          'appContent' => "
    function updateList(list) {
      var i = 0;
      var add = list.id == \"addedteams\";
      for (child of list.children) {
        var tId = Number(child.id.replace(/[a-z_]*/, ''));
        var seeds = child.getElementsByClassName('team_item_seed');
        if (seeds.length > 0)
          seeds[0].value = add ? ++i : \"\";
      }
    }"
        ]
      );
    } else {
      $html .= "<p>" . _("No teams") . "</p>\n";
    }
  }
} else if ($importstage < 3) {
  // $html .= "<input type='hidden' name='importdivision' value='" . json_encode($importDivision) . "'/>\n";
  $newpost = array_filter($_POST, function ($key) {
    return !startsWith($key, 'action_') && $key !== 'importstage';
  }, ARRAY_FILTER_USE_KEY);
  // unset($newpost['importstage']);
  $html .= getHiddenInput($newpost);
} else {
  $html .= getHiddenInput(json_encode($importDivision), 'importdivision');
}

if ($importstage >= 2) {
  $html .= "<h2>" . utf8entities($importDivision['name']) . "</h2>\n";
}

$teamList = '';
$numTeams = 0;
if (!empty($_POST['seed'])) {
  foreach ($_POST['seed'] as $i => $seed) {
    if (!empty($seed)) {
      ++$numTeams;
      $teamList .= "<li>" . utf8entities($_POST['name'][$i]) . "</li>\n";
    }
  }
}

if ($importstage == 2) {
  // teams selected
  if ($numTeams > 0) {
    $html .= "<h2>" . utf8entities(_("Teams")) . "</h2>\n<ul>$teamList</ul><br />\n";
  }

  $templates = SeasonSeries($templateSeasonId);
  if (empty($templates)) {
    $html .= "<p>" . sprintf(_("No matching templates found for this season (ID %s)."), $templateSeasonId) . "</p>\n";
  } else {
    $matches = [];
    foreach ($templates as $template) {
      if (startsWith($template['name'], $numTeams . "_teams")) {
        $matches[] = $template;
      }
    }

    if (count($matches) > 0) {
      $html .= "<p>" . _("Select a template to use for the new series") . "</p>\n";
      $html .= "<select name='template_series_id'>\n";
      foreach ($matches as $template) {
        $html .= "<option value='" . $template['series_id'] . "'>" . $template['name'] . "</option>";
      }
      $html .= "</select>\n";
      $html .= "<input class='button' name='action_import_create' type='submit' value='" . utf8entities(_("Create...")) . "'/>";
    } else {
      $html .= "<p>" . sprintf(_("No templates with %s teams found in %s"), $numTeams, SeasonName($templateSeasonId)) . "</p>\n";
    }
  }
}

$show_replacement_form = false;
if ($importstage == 4) {
  if (isset($_POST['selected_series']))
    $html .= getHiddenInput($_POST['selected_series'], 'selected_series');

  $wasImported = [];
  try {

    if (isset($_POST['import_data'])) {
      $import_data = json_decode($_POST['import_data'], true);
    }
    $modified_import_data = $import_data;
    $replacers = get_replacers($_POST, $modified_import_data);

    check_replacers($_POST, $replacers);

    $filename = '';
    $source = 'database';
    $template = 'on';
    $imported_series = [key($import_data['series'])];
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
      $imported_series,
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


    $div = getDFVDivision($importDivision['tournament_id'], $importDivision['id']);

    $admins = [];
    foreach ($div['events'] as $event) {
      $dfvadmins = $event['admins'];
      foreach ($dfvadmins as $email) {
        $id = UserIdForMail($email);
        if (empty($id) || $id < 0 || !IsRegistered($id))
          $admins[] = array('type' => 'invalid', 'value' => $email);
        else
          $admins[] = array('type' => 'email', 'value' => $id);
      }
    }

    // $wasImported = json_decode($_POST['wasImported'], true);
    foreach ($wasImported as $importedSeries) {
      $serId = $importedSeries['id'];
      if (
        $mock || ($seasonId == SeriesSeasonId($serId)  // check series belongs to season to avoid privilege escalation
          && hasEditSeriesRight($serId))
      ) {
        if ($mock)
          $html .= "<p>" . utf8entities(sprintf(_("Division admins for %s"), $serId)) . ":<br />\n";
        else
          $html .= "<p>" . utf8entities(sprintf(_("Division admins for %s"), SeriesInfo($serId)['name'])) . ":<br />\n";
        $invalid = 0;
        $valid = 0;
        foreach ($admins as $admin) {
          if ($admin['type'] != 'invalid') {
            if (++$valid == 1)  $html .= "<ul>";
            $userid = $admin['value'];
            $html .= "<li>" . utf8entities(sprintf(_("Found user %s"), $userid)) . "</li>\n";
            if (!$mock)
              AddSeasonUserRole($userid, "seriesadmin:$serId",  $seasonId);
          } else {
            ++$invalid;
          }
        }
        if ($valid > 0) $html .= "</ul>";
        if ($invalid > 0) {
          $html .= "<p>" . utf8entities(sprintf(_("Did not find %s users"), $invalid)) . "</p>\n";
        }
      } else {
        $html .= "<p>" . utf8entities(sprintf(_("Insufficient privileges to add admins for series %s"), SeriesInfo($serId)['name'] ?? "$serId")) . ":</p>\n";
      }
    }
  } catch (Exception $e) {
    $error_message = "<p class='warning'>" . sprintf(_("Error while importing %s:"), $_POST['new_season_name']) .
      "<br />" . $e->getMessage() . "</p>\n";

    $step = 'load_data';
  }

  $show_replacement_form = empty($wasImported) || $mock;

  getHiddenInput(json_encode($wasImported), 'wasImported');
  $firstRun = false;

  $html .= "<hr />\n";
  if (!$show_replacement_form) {
    $html .= "<p>" . utf8entities(_("Would you like to start over?")) . "</p>\n";
    $html .= "<a href=$url_here>" . _("Import from DFV") . "</a>";
  } else {
    [$html2, $scripts2] = displayAdvancedToggle();
    $html .= $html2;
    $scripts .= $scripts2;
  }
}

if ($importstage == 3) {

  $reservations = SeriesReservations($_POST['template_series_id']);

  $resgroup = $importDivision['division'];
  switch ($importDivision['divisionType']) {
    case 'WOMEN':
      $resgroup .= " Frauen";
      break;
    case 'OPEN':
      $resgroup .= " Open";
      break;
    case 'MIXED':
      $resgroup .= " Mixed";
      break;
  }
  switch ($importDivision['surface']) {
    case 'GYM':
      $resgroup .= ", Indoor";
      break;
    case 'TURF':
      // $resgroup .= ", Outdoor";
      break;
    case 'BEACH':
      $resgroup .= ", Beach";
      break;
  }
  switch ($importDivision['divisionAge']) {
    case 'REGULAR':
      break;
    default:
      $resgroup .= ", " . mb_convert_case($importDivision['divisionAge'], MB_CASE_TITLE);
      break;
  }

  // foreach ($reservations as $res) {
  //   $resgroup = $res['reservationgroup'];
  //   break;
  // }

  // $html .= "<div><label>" . _("Reservation Group") . "</label>" .
  //   "<input type='text' class='input' name='resgroup' value='$resgroup'/></div>\n";

  $prevLocation = null;
  $rNum = -1;
  $ranks = [];
  $rsizes = [];
  foreach ($reservations as $res) {
    if (!isset($ranks[$res['location']])) {
      $ranks[$res['location']] = ++$rNum;
      if (!isset($rsizes[$res['location']]))
        $rsizes[$res['location']] = [$res['date'] => 1];
      else if (!isset($rsizes[$res['location']][$res['date']]))
        $rsizes[$res['location']][$res['date']] = 1;
    } else {
      if (!isset($rsizes[$res['location']][$res['date']]))
        $rsizes[$res['location']][$res['date']] = 1;
      else
        ++$rsizes[$res['location']][$res['date']];
    }
  }

  usort($reservations, function ($a, $b) use ($ranks) {
    return [$ranks[$a['location']], $a['starttime'], $a['fieldname']] <=> [$ranks[$b['location']], $b['starttime'], $b['fieldname']];
  });

  // split multi-day events into single day events
  $events = $importDivision['events'];
  $all_events = [];
  foreach ($events as $event) {
    $date = new DateTime($event['startDate']);
    $end = new DateTime($event['endDate']);
    while ($date <= $end) {
      $event['startDate'] = $date->format('Y-m-d');
      $all_events[]  = $event;
      $date->modify('+1 day');
    }
  }


  $ranks = [];
  $rNum = -1;
  usort($events, function ($a, $b) {
    return [$a['startDate'], $a['name']] <=> [$b['startDate'], $b['name']];
  });
  foreach ($events as $ev) {
    if (!isset($ranks[$ev['id']])) {
      $ranks[$ev['id']] = ++$rNum;
    }
  }

  usort($all_events, function ($a, $b) use ($ranks) {
    $d1 = $ranks[$a['id']] <=> $ranks[$b['id']];
    if ($d1 != 0) return $d1;
    $d1 = $a['startDate'] <=> $b['startDate'];
    return $d1;
  });

  // $debug = print_r(['reservations' => $reservations, 'events' => $events, 'all_events' => $all_events], true);

  $resNum = 0;
  $dateIds = array();
  $prevLocation = $prevStart = null;

  $eventdatahandler = new EventDataXMLHandler();

  $data = $eventdatahandler->EventToXML($templateSeasonId, [$_POST['template_series_id']], true);
  $import_data = $eventdatahandler->XMLStructure(null, $data);
  $modified_import_data = $import_data;

  $resList = '';

  foreach ($reservations as $res) {
    $rid = $res['id'];
    $rrid = "reservation" . $res['id'];
    $rlid = "rlocation" . $res['id'];
    $locId2 = $res['location'];

    // $html .= "<input type='hidden' name= 'reservations[]' id='$rid' value='$rid' />";
    if ($res['location'] == $prevLocation && $res['starttime'] == $prevStart) {
      // $html .= "<input type='hidden' name= 'rlocations[]' id='$rlid' value='$locId2' />";
    } else {
      $prevLocation = $res['location'];
      $prevStart = $res['starttime'];
      $event = $all_events[$resNum];

      ++$resNum;
      $place = $res['location_name'];
      $size = $rsizes[$res['location']][$res['date']];
      $resStart = $res['starttime'];

      $newplace = $event['name'] . " - " . $event['city'];
      $newlocation = DBResourceToArray(getLocations('exact_name', $event['city']));
      $newLocationName = $newlocation[0]['name'] ?? $event['city'];
      $newLocationAddress = $newlocation[0]['address'] ?? _("new location");
      $newLocationId = $newlocation[0]['id'] ?? $locId2;
      $shortdate = ShortDate($event['startDate']);
      $olddate = utf8entities($shortdate);
      $oid = "olddates" . $res['id'];
      $nid = "newdates" . $res['id'];

      $lname = $res['location_name'];

      // $html .= "<div style='display: flex; flex-flow: row wrap'><label><p>" .
      //   sprintf("Reservation %s (%s, %s, %s) mapped to %s", $resNum, $place, $size, $resStart, $newplace)
      //   . "</p></label>"; // $newplace, $newstart, $newlocation
      // $html .= LocationInput2($rlid, 'rlocations', $lname, $locId2);
      // // $html .= "<div style='flex: 1 1 0%'><input class='input' name='newlocations[]' value='$newLocationName' /> ($newLocationAddress)</div>";
      // $html .= "<div style='flex: 1 1 0%'><input type='hidden' name='olddates[]' id='$oid' value='$olddate' />" . getCalendarInput($nid, $shortdate) . "</div></div>\n";
      $dateIds[] = $nid;
    }

    $modres = $modified_import_data['reservations'][$rid];


    $modres['location'] = $newLocationId;

    $resDay = (new DateTime($resStart))->format('Y-m-d');
    $firstDay = (new DateTime($event['startDate']))->format('Y-m-d');
    $resDayTime = new DateTime($resDay);
    $firstDayTime = new DateTime($firstDay);

    // FIXME instead isolate the game days of reservations and event, map them and apply res start times
    // Calculate the difference (interval) between $resStart and $firstDay
    $diff = $resDayTime->diff($firstDayTime);
    $diffDays = $diff->days;

    // Add the difference to $startTime
    $newDate = (new DateTime($modres['starttime']));
    $newDate->modify("+$diffDays days");

    $modres0 = $modres;
    $modres['starttime'] = EpocToMysql($newDate->getTimestamp());
    // $debug .= print_r([
    //   'modres' => $modres0,
    //   'start' => $resStart,
    //   'eventDate' => $event['startDate'],
    //   'resDay' => $resDay,
    //   'firstDay' => $firstDay,
    //   'resdayTime' => $resDayTime,
    //   'firstdayTime' => $firstDayTime,
    //   'diff' => $diff,
    //   'starttime' => $modres['starttime'],
    //   'newDate' => $newDate,
    //   'newstart' => $newDate->format('Y-m-d H:i:s'),
    //   'newtime' => $modres['starttime']
    // ], true);
    $modres['reservationgroup'] = $resgroup;

    $modified_import_data['reservations'][$rid] = $modres;

    $resList .= "<li>" . utf8entities(sprintf("%s, %s, field %s", LocationInfo($modres['location'])['name'] ?? '??', $modres['starttime'], $modres['fieldname'])) . "</li>\n";
  }

  $modified_import_data['season_id'] = $seasonId;
  $modified_import_data['season_name'] = SeasonName($seasonId);
  $newSeries = $modified_import_data['series'][$_POST['template_series_id']];
  $newSeries['name'] = $resgroup;

  $imported_teams = [];

  foreach ($_POST['tIds'] as $i => $tId) {
    $team_i = null;
    foreach ($importDivision['teams'] as $team) {
      if ($team['team_id'] == $tId) {
        $team_i = $team;
        break;
      }
    }
    if ($team_i == null) {
      debug_to_apache("error: team not found: $tId");
      $team_i = ['team_id' => $tId, 'team_name' => "Team $tId", 'team_club' => '', 'team_location' => ''];
    }

    $imported_teams[] = $team_i;
  }

  $i = 0;
  foreach ($newSeries['teams'] as $id => $team) {
    $newSeries['teams'][$id]['name'] = $imported_teams[$i]['team_name'];
    $newSeries['teams'][$id]['clubname'] = $imported_teams[$i]['team_club'];
    $newSeries['teams'][$id]['location'] = $imported_teams[$i]['team_location'];
    if (empty($newSeries['teams'][$id]['clubname']))
      $newSeries['teams'][$id]['clubname'] = $newSeries['teams'][$id]['location'];
    // TODO abbrev
    ++$i;
  }

  $modified_import_data['series'][$_POST['template_series_id']] = $newSeries;

  $scripts .= getCalendarScript($dateIds);


  $html .= "<p>" . sprintf(_("Import %s into %s"), $newSeries['name'], $modified_import_data['season_name']) . "</p>\n";
  $html .= "<div id='simple_list' style='display:block;'>";
  $html .= "<p>" . sprintf(_("%s teams"), count($newSeries['teams'])) . "</p>\n";
  $html .= "<ul>$teamList</ul>\n";
  $html .= "<p>" . sprintf(_("%s reservations"), count($modified_import_data['reservations'])) . "</p>\n";
  $html .= "<ul>$resList</ul>\n";
  $html .= "</div>";

  [$html2, $scripts2] = displayAdvancedToggle();
  $html .= $html2;
  $scripts .= $scripts2;

  $show_replacement_form = true;
  $firstRun = true;
  // set insert_mode??
  // set source?
  // set importdata:
  //   - reservations
  // set modified_importdata:
  // - Res group
  // - place/date
  // - series name
  // - team names
}

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

if ($show_replacement_form) {
  [$html2, $scripts2] = get_replacement_form($error_message, $seasonId, $import_data, $modified_import_data, $firstRun);
  $html .= "<div id='advanced' style='display:none;'>$html2</div>\n";
  $scripts .= $scripts2;

  $html .= "<div id='simple_buttons' style='display:block;'><br />";
  $html .= " <input class='button' type='submit' name='action_import' value='" . utf8entities(_("Import")) . "'/>";
  $html .= " <input class='button' type='submit' name='action_cancel' value='" . utf8entities(_("Cancel")) . "'/></p>";
  $html .= "</div>";
}

// FIXME!
if ($importstage > 0 && $importstage < 4) {
  ++$importstage;
}

$html .= "<input type='hidden' name='importstage' value='$importstage'>\n";
$html .= "</form>\n";

// $html .= "<div id='debug' style='display:block; white-space:pre'>";
// $html .= $debug;
// $html .= "</div>";

$html .= $scripts;

showPage($title, $html);
