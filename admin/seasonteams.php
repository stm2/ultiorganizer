<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

include_once 'lib/dfv.functions.php';

$seasonId = $_GET["season"];
$single = 0;
$series_id = -1;
CurrentSeries($seasonId, $series_id, $single, _("Teams"));

$title = SeasonName($seasonId) . ": " . _("Teams");
$html = "";

ensureEditSeriesRight($series_id);

// team parameters
$tp = array("team_id" => "", "name" => "", "club" => "", "country" => "", "abbreviation" => "", "series" => $series_id,
  "pool" => "", "rank" => "", "valid" => "1", "bye" => "");

$seasonInfo = SeasonInfo($seasonId);

// remove
if (!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  if (CanDeleteTeam($id)) {
    DeleteTeam($id);
  }
} else 

// add
if (!empty($_POST['add'])) {
  $tp['name'] = $_POST['name0'] ?? "no name";
  $tp['club'] = $_POST['club0'] ?? "";
  $tp['rank'] = $_POST["seed0"] ?? "0";

  if (!empty($tp['club'])) {
    $clubId = ClubId($tp['club']);
    if ($clubId == -1) {
      $clubId = AddClub($series_id, $tp['club']);
    }
    $tp['club'] = $clubId;
  }
  $tp['country'] = !empty($_POST['country0']) ? $_POST['country0'] : "";
  $tp['abbreviation'] = !empty($_POST['abbrev0']) ? $_POST['abbrev0'] : "";
  AddTeam($tp);
} else 

// import
if (!empty($_POST['add_multi'])) {
  foreach ($_POST['seed'] as $i => $seed) {
    $tp['rank'] = $_POST['seed'][$i] ?? "0";
    $tp['name'] = $_POST['name'][$i] ?? "no name";
    $tp['abbreviation'] = $_POST['abbrev'][$i] ?? "";
    $tp['club'] = $_POST['club'][$i] ?? "";
    if (!empty($tp['club'])) {
      $clubId = ClubId($tp['club']);
      if ($clubId == -1) {
        $clubId = AddClub($series_id, $tp['club']);
      }
      $tp['club'] = $clubId;
    }
    $tp['country'] = $_POST['country'][$i] ?? "";

    AddTeam($tp);
  }
} else 

// set
if (!empty($_POST['save'])) {
  $teams = SeriesTeams($series_id, true);
  foreach ($teams as $team) {
    $team_id = $team['team_id'];
    $tp['team_id'] = $team_id;
    $tp['name'] = !empty($_POST["name$team_id"]) ? $_POST["name$team_id"] : "no name";
    $tp['club'] = !empty($_POST["club$team_id"]) ? $_POST["club$team_id"] : "";
    $tp['rank'] = !empty($_POST["seed$team_id"]) ? $_POST["seed$team_id"] : "0";
    if (!empty($tp['club'])) {
      $clubId = ClubId($tp['club']);
      if ($clubId == -1) {
        $clubId = AddClub($series_id, $tp['club']);
      }
      $tp['club'] = $clubId;
    }
    $tp['country'] = !empty($_POST["country$team_id"]) ? $_POST["country$team_id"] : "";
    $tp['abbreviation'] = !empty($_POST["abbrev$team_id"]) ? $_POST["abbrev$team_id"] : "";
    SetTeam($tp);
  }
}

$focusId = null;

$get_link = function ($seasonId, $seriesId, $single = 0, $htmlEntities = false) {
  $single = $single == 0 ? "" : "&single=1";
  $seaLink = urlencode($seasonId);
  $link = "?view=admin/seasonteams&season=$seaLink&series={$seriesId}$single";
  return $htmlEntities ? utf8entities($link) : $link;
};

$url_here = $get_link($seasonId, $series_id, $single, true);

$html .= SeriesPageMenu($seasonId, $series_id, $single, $get_link, "?view=admin/seasonseries&season=$seasonId");

$html .= "<form method='post' action='$url_here'>";

$importstage = $_GET['importstage'] ?? $_POST['importstage'] ?? 0;

function teamTable($series_id, $teams, $club, $country, $edit, $short = false) {
  global $focusId;
  $html = "<table class='admintable'>\n";

  $html .= "<tr><th class='center' title='" . utf8entities(_("Seed")) . "'>#</th>";
  $html .= "<th>" . utf8entities(_("Name")) . "</th>";
  $html .= "<th>" . utf8entities(_("Abbrev")) . "</th>";

  if ($club) {
    $html .= "<th>" . utf8entities(_("Club")) . "</th>";
  }
  if ($country) {
    $html .= "<th>" . utf8entities(_("Country")) . "</th>";
  }
  if ($edit) {
    $html .= "<th>" . utf8entities(_("Contact person")) . "</th>";
    $html .= "<th>" . utf8entities(_("Roster")) . "</th>";
    $html .= "<th></th>";
  }
  $html .= "</tr>\n";

  $total = 0;

  function get_field($size, $maxlength, $name, $value, $short) {
    if ($short) {
      return "<td><input type='hidden' name='$name' value='" . utf8entities($value) . "'/>" . utf8entities($value) .
        "</td>\n";
    } else {
      return "<td><input class='input' size='$size' maxlength='$maxlength' name='$name' value='" . utf8entities($value) .
        "'/></td>\n";
    }
  }

  foreach ($teams as $team) {
    if ($short || !isset($team['team_id']))
      $team_id = "[]";
    else
      $team_id = $team['team_id'];
    $total++;

    $html .= "<tr class='admintablerow'>";
    if ($focusId == null) {
      $fid = " id='focus0'";
      $focusId = 'focus0';
    } else {
      $fid = '';
    }
    $html .= "<td><input class='input' size='2' maxlength='4'$fid name='seed$team_id' value='" . $team['rank'] .
      "'/></td>";

    $html .= get_field(20, 50, "name$team_id", $team['name'], $short);
    $html .= get_field(4, 15, "abbrev$team_id", $team['abbreviation'], $short);

    if ($club) {
      $html .= get_field(20, 50, "club$team_id", $team['clubname'], $short);
    }

    if ($country) {
      if ($short) {
        $html .= "<td>" . CountryName($team['country']) . "</td>";
      } else {
        $width = "";
        if ($club) {
          $width = "80px";
        }
        $html .= "<td>" . CountryDropListWithValues("country$team_id", "country$team_id", $team['country'], $width) .
          "</td>";
      }
    }
    if ($edit) {
      $html .= "<td>";

      $admins = getTeamAdmins($team['team_id']);

      for ($i = 0; $i < count($admins); $i++) {
        $user = $admins[$i];
        $html .= "<a href='?view=user/userinfo&amp;user=" . $user['userid'] . "'>" . utf8entities($user['name']) . "</a>";
        if ($i + 1 < count($admins))
          $html .= "<br/>";
      }

      $html .= "&nbsp;<a href='?view=admin/addteamadmins&amp;series=$series_id'>" . utf8entities(_("...")) . "</a>";
      $html .= "</td>";

      $html .= "<td class='center'><a href='?view=user/teamplayers&amp;team=" . $team['team_id'] . "'>" .
        utf8entities(_("Roster")) . "</a></td>";

      $html .= "<td>";
      $html .= "<a href='?view=admin/addseasonteams&amp;team=$team_id'><img class='deletebutton' src='images/settings.png' alt='D' title='" .
        utf8entities(_("edit details")) . "'/></a>";
      if (CanDeleteTeam($team['team_id'])) {
        $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" .
          utf8entities(_("X")) . "' onclick=\"setId(" . $team['team_id'] . ");\"/>";
      }
      $html .= "</td>";
    }
    $html .= "</tr>\n";
  }

  $total++;

  if ($edit) {
    $focusId = 'name0';
    $html .= "<tr>";
    $html .= "<td style='padding-top:15px'><input class='input' size='2' maxlength='4' name='seed0' value='$total'/></td>";
    $html .= "<td style='padding-top:15px'><input class='input' size='20' maxlength='50' name='name0' id='name0' value=''/></td>";
    $html .= "<td style='padding-top:15px'><input class='input' size='4' maxlength='15' name='abbrev0' value=''/></td>";
    if ($club) {
      $html .= "<td style='padding-top:15px'><input class='input' size='20' maxlength='50' name='club0' value=''/></td>";
    }
    if ($country) {
      $html .= "<td style='padding-top:15px'>" . CountryDropListWithValues("country0", "country0", "", $width) . "</td>";
    }

    $html .= "<td style='padding-top:15px'><input style='margin-left:15px' id='add' class='button' name='add' type='submit' value='" .
      utf8entities(_("Add")) . "'/></td>";
    $html .= "</tr>\n";
  }

  $html .= "</table>\n";
  return $html;
}

function shortTeamTable($series_id, $teams, $club, $country) {
  return teamTable($series_id, $teams, $club, $country, false, true);
}

function showSeasonSelection($seasonId, $series_id, $importstage) {
  $seasons = SeasonsArray();
  $series_info = SeriesInfo($series_id);
  $type = $series_info['type'];
  $matching = !isset($_POST['matching_type_shown']) || isset($_POST['matching_type']) ? 'checked' : '';

  $html = "";
  $copyteams = (int) ($_POST['copyteams'] ?? 0);

  foreach ($seasons as $season) {
    $divisions = SeasonSeries($season['season_id']);
    foreach ($divisions as $division) {
      if (!empty($matching) && $division['type'] != $type) {
        continue;
      }

      if ($copyteams == $division['series_id'])
        $selected = " selected='1'";
      else
        $selected = '';
      $html .= "<option class='dropdown'$selected value='" . $division['series_id'] . "'>" .
        utf8entities($season['name'] . " " . $division['name']) . "</option>";
    }
  }
  if (empty($html)) {
    return "<p>" . utf8entities(sprintf(_("No matching divisions ('%s')"), U_($type))) . "</p>\n";
  }

  $html = "<p><label>" . utf8entities(_("Add teams from:")) . "</label> <select class='dropdown' name='copyteams'>\n" .
    $html;
  $html .= "</select></p>\n";

  $html .= "<p><input type='hidden' id='matching_type_shown' name='matching_type_shown' value='1'/></p>";
  $html .= "<p><input type='checkbox' $matching id='matching_type' name='matching_type' />";
  $html .= "<label for='matching_type'>" .
    utf8entities(sprintf(_("List only divisions with matching type ('%s')"), U_($type))) . "</label></p>\n";

  $html .= "<p><input id='refresh' class='button' name='refresh' type='submit' value='" . utf8entities(_("Load...")) .
    "'/>";
  $html .= "<input id='cancel_selection' class='button' name='cancel' type='submit' value='" . utf8entities(_("Cancel")) .
    "'/>";
  $html .= "</p>\n";

  return $html;
}

function showDFVSelection($seasonId, $series_id, $importstage) {
  $data = DFVTournaments(($_GET['refresh'] ?? 0) == 1);
  $tournaments = $data['tournaments'];

  $seaLink = urlencode($seasonId);
  $html = "";

  if ($tournaments == null) {
    $html .= "<p>" . utf8entities(_("Error: could not read DFV data!")) . "</p>";
    $tournaments = [];
  }

  $html .= "<p>" .
    utf8entities(
      sprintf(_("Data retrieved from DFV (%s) at %s"), utf8entities($data['source']),
        utf8entities(EpocToMysql($data['retrieved'])))) . " " .
    "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=$importstage&amp;refresh=1'>" .
    utf8entities(_("Refresh")) . "</a></p>\n";

  if (count($tournaments)) {
    $html .= "<p>" . utf8entities(_("Add teams from:")) . " ";
    $html .= "<select class='dropdown' name='importteams'>\n";
    $options = [];
    mergesort($tournaments,
      uo_create_multi_key_comparator([['year', true, true], ['surface', true, true], ['id', true, true]]));

    foreach ($tournaments as $tournament) {
      $divs = $tournament['divisions'];
      mergesort($divs,
        uo_create_multi_key_comparator(
          [['divisionAge', false, true], ['divisionType', true, true], ['divisionIdentifier', false, true]]));
      foreach ($divs as $div) {
        $name = $tournament['name'];

        if (!empty($tournament['year']))
          $name .= " - " . $tournament['year'];
        if (!empty($tournament['surface']))
          $name .= " (" . $tournament['surface'] . ")";
        if (!empty($div['divisionIdentifier']))
          $name .= " - " . $div['divisionIdentifier'];
        $name .= " (" . $div['divisionType'] . ", " . $div['divisionAge'] . ")";

        $val = $div['teams'];
        $options[$div['id']] = ['name' => $name, 'val' => $val];
      }
    }

    foreach (array_reverse($options) as $div) {
      $val = utf8entities(json_encode($div['val']));
      $html .= "<option class='dropdown' value='$val'>" . utf8entities($div['name']) . "</option>";
    }

    $html .= "</select><br />\n";

    $html .= "<input id='refresh' class='button' name='refresh' type='submit' value='" . utf8entities(_("Load...")) .
      "'/>";
    $html .= "<input id='cancel_load' class='button' name='cancel' type='submit' value='" . utf8entities(_("Cancel")) .
      "'/>";
    $html .= "</p>\n";
  } else {
    $html .= "<p>";
    $html .= "<input id='cancel_load' class='button' name='cancel' type='submit' value='" . utf8entities(_("Cancel")) .
      "'/></p>";
  }
  return $html;
}

$club = !intval($seasonInfo['isnationalteams']);
$country = intval($seasonInfo['isinternational']);

if ($importstage == 0 || isset($_POST['cancel'])) {
  $teams = SeriesTeams($series_id, true);
  foreach ($teams as &$team) {
    $team['country'] = TeamFullInfo($team['team_id'])['country'];
  }
  unset($team);

  $html .= teamTable($series_id, $teams, $club, $country, true);

  $html .= "<p>";
  $html .= "<input id='save' class='button' name='save' type='submit' value='" . utf8entities(_("Save")) . "'/> ";
  $html .= "<input id='cancel_save' class='button' name='cancel' type='submit' value='" . utf8entities(_("Cancel")) .
    "'/>";
  $html .= "</p>";

  $seaLink = urlencode($seasonId);

  $html .= "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=1'>" .
    utf8entities(_("Import teams from other division")) . "</a><br />\n";
  $html .= "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=2'>" .
    utf8entities(_("Import teams from DFV")) . "</a><br />\n";
} else if (!isset($_POST['import']) && ($importstage == 1 || $importstage == 2 || isset($_POST['refresh']))) {
  $html .= "<p><input type='hidden' id='importstage' name='importstage' value='$importstage'/></p>";

  if ($importstage == 1) {
    $html .= showSeasonSelection($seasonId, $series_id, $importstage);
  }
  if ($importstage == 2) {
    $html .= showDFVSelection($seasonId, $series_id, $importstage);
  }

  if (isset($_POST['refresh'])) {
    if ($importstage == 1) {
      $importSeries = SeriesInfo($_POST['copyteams']);
      $html .= "<p>" . utf8entities(sprintf(_("Teams from %s"), $importSeries['name']));
      $teams = SeriesTeams($_POST['copyteams']);
      foreach ($teams as $i => &$team) {
        if ($team['rank'] === null)
          $team['rank'] = $i + 1;
        $team['country'] = TeamFullInfo($team['team_id'])['country'];
      }
      unset($team);
    } else {
      $teams = json_decode($_POST['importteams'], true);
      foreach ($teams as $i => &$team) {
        $team['rank'] = $i + 1;
        $team['name'] = $team['teamName'];
        $team['clubname'] = $team['teamLocation'];
        $team['abbreviation'] = null;
        $team['country'] = null;
      }
      unset($team);
    }

    $html .= shortTeamTable($series_id, $teams, $club, $country);

    $html .= "<fieldset>";
    $html .= "<p><input type='radio' checked='checked' id='add_mode' name='import_mode' value='add_mode' />";
    $html .= "<label for='add_mode'>" . utf8entities(_("Add new teams")) . "</label></p>\n";
    $html .= "<p><input type='radio' id='rename_mode' name='import_mode' value='rename_mode' />";
    $teams = SeriesTeams($series_id, true);
    $html .= "<label for='rename_mode'>" . utf8entities(sprintf(_("Rename %s teams with lowest seed #"), count($teams))) .
      "</label></p>\n";
    $html .= "</fieldset>";

    $html .= "<p>" . utf8entities(_("Leave seed # blank for teams you do not want to import.")) . "</p>";

    $html .= "<p>";
    $html .= "<input id='import' class='button' name='import' type='submit' value='" . utf8entities(_("Import...")) .
      "'/>";
    $html .= "<input id='cancel_import' class='button' name='cancel' type='submit' value='" . utf8entities(_("Cancel")) .
      "'/>";
    $html .= "</p>";
  }
} else if (isset($_POST['import'])) {
  $renamemode = ($_POST['import_mode'] == 'rename_mode');

  $new_teams = [];
  foreach ($_POST['seed'] as $i => $seed) {
    if ($seed !== "")
      $new_teams[] = ['rank' => $seed, 'name' => $_POST['name'][$i], 'abbreviation' => $_POST['abbrev'][$i],
        'clubname' => $_POST['club'][$i] ?? null, 'country' => $_POST['country'] ?? null];
  }
  mergesort($new_teams, function ($a, $b) {
    return (int) $a['rank'] > (int) $b['rank'];
  });

  if ($renamemode) {
    $teams = SeriesTeams($series_id, true);

    foreach ($teams as $i => &$team) {
      if (isset($new_teams[$i]) && !empty($new_teams[$i]['rank'])) {
        $team['rank'] = $new_teams[$i]['rank'];
        $team['name'] = $new_teams[$i]['name'];
        $team['abbreviation'] = $new_teams[$i]['abbreviation'];
        if ($club) {
          $team['clubname'] = $new_teams[$i]['clubname'];
        }
        if ($country) {
          $team['country'] = $new_teams[$i]['country'];
        }
      }
    }
    unset($team);
  } else {
    $teams = [];
    foreach ($new_teams as $newteam) {
      if (!empty($newteam['rank']))
        $teams[] = $newteam;
    }
  }

  $html .= teamTable($series_id, $teams, $club, $country, false);

  $html .= "<p>" . utf8entities(_("Teams have not been saved!")) . " ";
  if ($renamemode) {
    $html .= utf8entities(_("Replace teams?"));
  } else {
    $html .= utf8entities(_("Add teams?"));
  }
  $html .= "</p>";

  $html .= "<p>";
  if ($renamemode) {
    $html .= "<input id='save' class='button' name='save' type='submit' value='" . utf8entities(_("Save")) . "'/>";
  } else {
    $html .= "<input id='add' class='button' name='add_multi' type='submit' value='" . utf8entities(_("Add")) . "'/>";
  }
  $html .= "<input id='cancel_addsave' class='button' name='cancel' type='submit' value='" . utf8entities(_("Cancel")) .
    "'/>";
  $html .= "</p>";
}

$html .= "<hr/>\n";
$html .= "<p>";
$html .= "<a href='?view=admin/addteamadmins&amp;series=$series_id'>" . utf8entities(_("Add Team Admins")) . "</a> | ";
$html .= "<a href='?view=user/pdfscoresheet&amp;series=$series_id'>" . utf8entities(_("Print team rosters")) . "</a></p>";

// stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";

$html .= "</form>\n";

if (!empty($focusId))
  setFocus($focusId);

showPage($title, $html);
?>