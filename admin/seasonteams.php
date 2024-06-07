<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

include_once 'lib/dfv.functions.php';

include_once 'lib/yui.functions.php';
addHeaderCallback(function () {
  echo yuiLoad(array("utilities"));
});

$seasonId = $_GET["season"];
$single = 0;
$series_id = -1;
CurrentSeries($seasonId, $series_id, $single, _("Teams"));

$title = SeasonName($seasonId) . ": " . _("Teams");
$html = "";

$html .= JavaScriptWarning();

ensureEditSeriesRight($series_id);

// team parameters
$tp = array("team_id" => "", "name" => "", "club" => "", "country" => "", "abbreviation" => "", "series" => $series_id,
  "pool" => "", "rank" => "", "valid" => "1", "bye" => "");

$seasonInfo = SeasonInfo($seasonId);

$teamListItems = [];

function teamTable($series_id, $teams, $club, $country, $edit, $short = false, &$teamListItems) {
  global $focusId;
  $html = "<table class='admintable'><tbody id='addedteams'>\n";

  $html .= "<tr><td></td><th class='center' title='" . utf8entities(_("Seed")) . "'>#</th>";
  $html .= "<th>" . utf8entities(_("Name")) . "</th>";
  $html .= "<th>" . utf8entities(_("Abbrev")) . "</th>";

  if ($club) {
    $html .= "<th>" . utf8entities(_("Club")) . "</th>";
  }
  if ($country) {
    $html .= "<th>" . utf8entities(_("Country")) . "</th>";
  }
  if ($edit) {
    $html .= "<th colspan='4'></th>";
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
  $contact = utf8entities(_("Contact person"));
  $roster = utf8entities(_("Roster"));
  $details = utf8entities(_("edit details"));
  $delete = utf8entities(_("Roster"));

  $tId = 0;
  foreach ($teams as $team) {
    if ($short || !isset($team['team_id'])) {
      $team_id = "[]";
      ++$tId;
    } else {
      $team_id = $team['team_id'];
      $tId = $team_id;
    }
    $total++;
    $rid = "teamdrag$tId";
    $hid = "draghandle$tId";
    $teamListItems[] = ['id' => $rid, 'hId' => $hid, 'parent' => 'addedteams', 'item_class' => 'admintablerow'];

    $html .= "<tr class='admintablerow' id='$rid' ><td class='teamdrag' ><img id='$hid' src='images/draghandle.png' class='draghandle tableicon' /></td>";
    if ($focusId == null) {
      $fid = " id='focus0'";
      $focusId = 'focus0';
    } else {
      $fid = '';
    }
    $html .= "<td><input class='input team_item_seed' size='2' maxlength='4'$fid name='seed$team_id' value='" .
      $team['rank'] . "'/></td>";

    $html .= get_field(20, 50, "name$team_id", $team['name'], $short);
    $html .= get_field(9, 15, "abbrev$team_id", $team['abbreviation'], $short);

    if ($club) {
      $html .= get_field(18, 50, "club$team_id", $team['clubname'], $short);
    }

    $width = "";
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
      if (count($admins) > 0) {
        $is_empty = "not_empty";
      } else {
        $is_empty = "is_empty";
      }

      $html .= "<a href='?view=admin/addteamadmins&amp;series=$series_id'><img class='tableicon $is_empty' src='images/contact.png' alt='C' title='$contact' /></a>";
      $html .= "</td>";

      $html .= "<td class='center'><a href='?view=user/teamplayers&amp;team=" . $team['team_id'] . "'>" .
        "<img class='tableicon' src='images/playerlist.png' alt='R' title='$roster'/>" . "</a></td>";

      $html .= "<td>";
      $html .= "<a href='?view=admin/addseasonteams&amp;team=$team_id'><img class='deletebutton' src='images/settings.png' alt='D' title='$details'/></a></td>";
      if (CanDeleteTeam($team['team_id'])) {
        $html .= "<td><input class='deletebutton tableicon' type='image' src='images/remove.png' alt='X' title='$delete' name='remove' value='" .
          utf8entities(_("X")) . "' onclick=\"setId(" . $team['team_id'] . ");\"/>";
      } else {
        $html .= "<td>";
      }
      $html .= "</td>";
    }
    $html .= "</tr>\n";
  }

  $total++;

  if ($edit) {
    $focusId = 'name0';
    $html .= "<tr><td></td>";
    $html .= "<td style='padding-top:15px'><input class='input' size='2' maxlength='3' name='seed0' value='$total'/></td>";
    $html .= "<td style='padding-top:15px'><input class='input' size='20' maxlength='50' name='name0' id='name0' value=''/></td>";
    $html .= "<td style='padding-top:15px'><input class='input' size='9' maxlength='15' name='abbrev0' value=''/></td>";
    if ($club) {
      $html .= "<td style='padding-top:15px'><input class='input' size='18' maxlength='50' name='club0' value=''/></td>";
    }
    if ($country) {
      $html .= "<td style='padding-top:15px'>" . CountryDropListWithValues("country0", "country0", "", $width) . "</td>";
    }

    $html .= "<td style='padding-top:15px' colspan='4'><input id='add' class='button' name='add' type='submit' value='" .
      utf8entities(_("Add")) . "'/></td>";
    $html .= "</tr>\n";
  }

  $html .= "</tbody></table>\n";
  return $html;
}

function teamList(array $teams, bool $club, bool $country, array &$teamlist) {

  function getItem(array $team, bool $country, array &$teamListItems) {
    $teamId = intval($team['team_id']);
    $seed = intval($team['rank']);
    $name = utf8entities($team['name']);
    $abbrev = utf8entities($team['abbreviation']);
    // $club = intval($team['club']);
    $club = utf8entities($team['clubname']);
    // $club = utf8entities(ClubName($team['clubname']));
    if ($country)
      $countryId = intval($team['country']);

    $teamListItems[] = ['id' => "team_item$teamId", 'hId' => "draggable$teamId", 'parent' => 'addedteams',
      'item_class' => 'team_item'];

    $html = "<tr class='team_item' id='team_item$teamId'><td>";
    $html .= "<input type='hidden' id='tId$teamId' name='tIds[]' value='$teamId'/>";
    $html .= "<input type='hidden' class='editmode' name='name[]' value='$name'/>\n";
    $html .= "<input type='hidden' class='editmode' name='abbrev[]' value='$abbrev'/>\n";
    $html .= "<input type='hidden' class='editmode' name='clubname[]' value='$club'/>\n";
    if ($country)
      $html .= "<input type='hidden' class='editmode' name='country[]' value='$countryId'/>\n";

    $html .= "<input type='number' class='editmode team_item_seed' name='seed[]' min='0' size='3' style='width: 3rem;' id='seed$teamId' style='display:inline' maxlength='5' value='$seed'/>\n";
    $html .= "<div class ='draggable_wrapper' id='draggable$teamId'>";
    $html .= "<img id='draghandle$teamId' src='images/draghandle.png' class='draghandle tableicon' />";
    $html .= "<span class='teamname'>" . $name . "</span>";
    if (!empty($club)) {
      $html .= " - <span class='clubname'>" . $club . "</span>";
    }
    if ($country) {
      $countryName = utf8entities(CountryName($team['country']));
      if ($countryName != -1)
        $html .= " - <span class='countryname'>$countryName</span>";
      else
        $html .= " - <span class='countryname'>???</span>";
    }
    // $html .= "<span class='draghandle' id='draghandle$teamId'>&#8661;</span></div>";
    $html .= "</input>";
    $html .= "</td></tr>\n";
    return $html;
  }

  $t = 0;
  $added = $notAdded = "";
  foreach ($teams as $team) {
    ++$t;
    if (empty($team['team_id']))
      $team['team_id'] = $t;
    if ($team['rank'] > 0)
      $added .= getItem($team, $country, $teamlist);
    else
      $notAdded .= getItem($team, $country, $teamlist);
  }

  $html .= "<div style='border: thin black solid; min-width: 40em; min-height: 10rem;'>";
  $html .= "<table><tbody id='addedteams' class='team_list'  style='min-height:10rem'>\n";
  $html .= "<tr><td>" . _("Teams to add") . "</td></tr>\n";
  $html .= $added;
  $html .= "</tbody></table></div>\n";

  $html .= "<div style='border: thin black solid; min-width: 40em; min-height: 10rem;' id='notaddedteams' >";
  $html .= "<h3>" . _("Ignored teams") . "</h3>\n";
  $html .= "<table style='min-height: 2rem; width:100%'><tbody class='team_list' style='min-height:10rem'>\n";
  $html .= $notAdded;
  $html .= "</tbody></table></div>\n";
  return $html;
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

function matching_division(array $tournament, array $division, array $tournament_filters, array $division_filters): bool {
  foreach ($tournament_filters as $key => $value) {
    if (!empty($value) && $tournament[$key] != $value)
      return false;
  }
  foreach ($division_filters as $key => $value) {
    if (!empty($value) && $division[$key] != $value)
      return false;
  }

  return true;
}

function add_filter(string &$html, string $id, string $name, array $options, string $default = ''): string {
  $html .= "<tr><td><label for='{$id}'>$name</label></td><td><select id='{$id}' name='$id' />";
  $html .= "<option value=''/>\n";
  $selected = $_POST[$id] ?? $default;
  foreach ($options as $opt => $dummy) {
    $select = ($opt == $selected) ? "selected='1' " : "";
    $html .= "<option value='$opt' $select>$opt</opt>\n";
  }
  $html .= "</select></td></tr>\n";
  return $selected;
}

function fill_choices(array $tournaments, $tournament_attributes, $division_attributes): array {
  $choices = [];
  foreach ($tournaments as $tournament) {
    foreach ($tournament_attributes as $att) {
      $choices[$att][$tournament[$att]] = 1;
    }
    $divs = $tournament['divisions'];
    foreach ($divs as $div) {
      foreach ($division_attributes as $att) {
        $choices[$att][$div[$att]] = 1;
      }
    }
  }
  return $choices;
}

function showDFVSelection($seasonId, $series_id, $importstage) {
  $data = DFVTournaments(($_GET['refresh'] ?? 0) == 1);
  $tournaments = $data['tournaments'] ?? null;

  $seaLink = urlencode($seasonId);
  $html = "";

  if ($tournaments == null) {
    $html .= "<p>" . utf8entities(_("Error: could not read DFV data!"));
    $tournaments = [];
  } else {
    $html .= "<p>" .
      utf8entities(
        sprintf(_("Data retrieved from DFV (%s) at %s"), utf8entities($data['source']),
          utf8entities(EpocToMysql($data['retrieved']))));
  }
  $html .= " <a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=$importstage&amp;refresh=1'>" .
    utf8entities(_("Refresh")) . "</a></p>\n";

  if (!empty($data['error']))
    $html .= "<p>" . $data['error'] . "</p>\n";

  if (count($tournaments)) {
    $html .= "<table>";
    $choices = fill_choices($tournaments, ['year', 'surface'], ['divisionType', 'divisionAge']);

    $year_filter = add_filter($html, 'year_filter', _('Year'), $choices['year'], date('Y', time()));
    $surface_filter = add_filter($html, 'surface_filter', _('Surface'), $choices['surface']);
    $division_filter = add_filter($html, 'division_filter', _('Division'), $choices['divisionType'],
      division_to_dfv(SeriesInfo($series_id)['type']));
    $age_filter = add_filter($html, 'age_filter', _('Age'), $choices['divisionAge']);

    $html .= "<tr><td colspan='2'><input id='filter_button' class='button' name='filter_division' type='submit' value='" .
      utf8entities(_("Show matching divisions...")) . "'/></td></tr><tr><td>&nbsp;</td></tr>\n";

    $html .= "<tr><td>" . utf8entities(_("Add teams from:")) . "</td><td>";

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
        if (matching_division($tournament, $div, ['year' => $year_filter, 'surface' => $surface_filter],
          ['divisionType' => $division_filter, 'divisionAge' => $age_filter])) {
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
    }

    foreach (array_reverse($options) as $div) {
      $val = utf8entities(json_encode($div['val']));
      $html .= "<option class='dropdown' value='$val'>" . utf8entities($div['name']) . "</option>";
    }

    $html .= "</select></td></tr></table>\n";

    $html .= "<p><input id='refresh' class='button' name='refresh' type='submit' value='" . utf8entities(_("Load...")) .
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

$club = !intval($seasonInfo['isnationalteams']);
$country = intval($seasonInfo['isinternational']);

$showTeams = true;
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
} else 
// import
if (isset($_POST['import_add']) || isset($_POST['import_replace'])) {
  $renamemode = (isset($_POST['import_replace']));

  $new_teams = [];
  foreach ($_POST['seed'] as $i => $seed) {
    if ($seed !== "") {
      $clubId = 0;
      if ($club && !empty($_POST['clubname'])) {
        $clubId = ClubId($_POST['clubname'][$i]);
        if ($clubId == -1) {
          $clubId = AddClub($series_id, $_POST['club'][$i]);
        }
      }

      $new_teams[] = ['rank' => $seed, 'name' => $_POST['name'][$i], 'abbreviation' => $_POST['abbrev'][$i],
        'clubname' => $_POST['club'][$i] ?? null, 'club' => $clubId, 'country' => $_POST['country'][$i] ?? null];
    }
  }
  mergesort($new_teams, function ($a, $b) {
    return (int) $a['rank'] > (int) $b['rank'];
  });

  if ($renamemode) {
    $teams = SeriesTeams($series_id, true);

    foreach ($teams as $i => &$team) {
      if (isset($new_teams[$i]) && !empty($new_teams[$i]['rank'])) {
        $team['valid'] = 1;
        $team['series'] = $series_id;
        $team['pool'] = "";

        $team['rank'] = $new_teams[$i]['rank'];
        $team['name'] = $new_teams[$i]['name'];
        $team['abbreviation'] = $new_teams[$i]['abbreviation'];
        $team['club'] = $new_teams[$i]['club'];
        $team['clubname'] = $new_teams[$i]['clubname'];
        if ($country) {
          $team['country'] = $new_teams[$i]['country'];
        }
        SetTeam($team);
      }
    }
    unset($team);
  } else {
    $teams = [];
    foreach ($new_teams as $newteam) {
      if (!empty($newteam['rank'])) {
        $newteam['valid'] = 1;
        $newteam['series'] = $series_id;
        $newteam['pool'] = "";
        $teams[] = $newteam;
        AddTeam($newteam);
      }
    }
  }
} else if ($importstage == 0 || isset($_POST['cancel'])) {
  $showTeams = true;
} else if (!isset($_POST['import']) && ($importstage == 1 || $importstage == 2 || isset($_POST['refresh']))) {
  $showTeams = false;
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
      $html .= "<p>" . utf8entities(sprintf(_("Teams from %s"), $importSeries['name'])) . "</p>";
      $teams = SeriesTeams($_POST['copyteams'], true);
      foreach ($teams as $i => &$team) {
        if ($team['rank'] === null)
          $team['rank'] = $i + 1;
        $team['country'] = TeamFullInfo($team['team_id'])['country'];
      }
      unset($team);
    } else {
      if (!empty($_POST['importteams'])) {
        $teams = json_decode($_POST['importteams'], true);
        $valid = 0;
        foreach ($teams as $i => &$team) {
          $team['rank'] = (($team['status'] ?? '') == 'CONFIRMED') ? ++$valid : '';
          $team['name'] = $team['teamName'];
          $team['clubname'] = $team['teamLocation'];
          $team['abbreviation'] = null;
          $team['country'] = null;
        }
        unset($team);
      }
    }

    if (!empty($teams)) {
      $html .= teamList($teams, $club, $country, $teamListItems);

      $html .= "<p>";
      $html .= "<input id='import' class='button' name='import_add' type='submit' value='" . utf8entities(_("Add")) .
        "'/> " . _("Add new teams") . "<br />";
      $num = min(count($teams), count(SeriesTeams($series_id)));
      if ($num) {
        $html .= "<input id='import' class='button' name='import_replace' type='submit' value='" .
          utf8entities(_("Replace")) . "'/> " . utf8entities(sprintf(_("Rename %s teams with lowest seed #"), $num)) .
          "<br />";
      }
      $html .= "<input id='cancel_import' class='button' name='cancel' type='submit' value='" . utf8entities(
        _("Cancel")) . "'/>";
      $html .= "</p>";
    } else {
      $html .= "<p>" . _("No teams") . "</p>\n";
    }
  }
}

if ($showTeams) {
  $teams = SeriesTeams($series_id, true);
  foreach ($teams as &$team) {
    $team['country'] = TeamFullInfo($team['team_id'])['country'];
  }
  unset($team);

  $html .= teamTable($series_id, $teams, $club, $country, true, false, $teamListItems);

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

  $html .= getDragDropApp('TeamApp', ["addedteams", "notaddedteams"], $teamListItems, [ 'endDrag' =>
    "      updateList(srcEl.parentNode);",  'dragOver' =>
  "
      updateList(srcEl.parentNode);
      var orig = srcEl.getElementsByClassName('team_item_seed')[0];
      var copy = proxy.getElementsByClassName('team_item_seed')[0];
      copy.value = parseInt(orig.value) + (goingUp?-1:1);" ],
  "
    function updateList(list) {
      var i = 0;
      var add = list.id == \"addedteams\";
      for (child of list.children) {
        var tId = Number(child.id.replace(/[a-z_]*/, ''));
        var seeds = child.getElementsByClassName('team_item_seed');
        if (seeds.length > 0)
          seeds[0].value = add?++i:\"\";
      }
    }");

function getDragDropApp(string $appName, array $targets, array $listItems, array $callbacks = [], $appContent = null) {
  $html = <<<EOF
  <script type="text/javascript">
  //<![CDATA[

  var Dom = YAHOO.util.Dom;
    (function() {

    var Event = YAHOO.util.Event;
    var DDM = YAHOO.util.DragDropMgr;

    YAHOO.example.$appName = {
      init: function() {

EOF;

  foreach ($targets as $target) {
    $html .= "    new YAHOO.util.DDTarget(\"$target\");\n";
  }

  foreach ($listItems as $item) {
    $html .= "      new YAHOO.example.DDList(\"{$item['id']}\", \"{$item['hId']}\", \"{$item['parent']}\", \"{$item['item_class']}\");\n";
  }

  $html .= <<< EOF
      }
    }

    function onDragDropCallback(list, parent) {
{$callbacks['dragDrop']};

    }

    function onDragOverCallback(srcEl, proxy, goingUp) {
{$callbacks['dragOver']};
    }

    function onEndDragCallback(srcEl, proxy) {
{$callbacks['endDrag']};
    }

$appContent

    YAHOO.example.DDList = function(id, handleId, parentId, itemClass, sGroup, config) {

      YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup, config);

      this.logger = this.logger || YAHOO;
      var el = this.getDragEl();
      Dom.setStyle(el, "opacity", 0.57); // The proxy is slightly transparent
      this.setHandleElId(handleId);
      this.parent = parentId;
      this.goingUp = false;
      this.lastY = 0;
      this.itemClass = itemClass;
      this.parentClass = 'team_list';
    };

    YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy, {
  
      startDrag: function(x, y) {
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
          // onStartDragCallback(clickEl, dragEl, this.parent);
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
          onEndDragCallback(srcEl, proxy, this.parent);
      },
  
      onDragDrop: function(e, id) {
  
          // If there is one drop interaction, the item was dropped either on the list,
          // or it was dropped on the current location of the source element.
          if (DDM.interactionInfo.drop.length === 1) {
  
              // The position of the cursor at the time of the drop (YAHOO.util.Point)
              var pt = DDM.interactionInfo.point; 
  
              // The region occupied by the source element at the time of the drop
              var region = DDM.interactionInfo.sourceRegion; 
  
              // Check to see if we are over the source element's location.  We will
              // append to the bottom of the list once we are sure it was a drop in
              // the negative space (the area of the list without any list items)
              var parent = null
              if (!region.intersect(pt)) {
                  var destEl = Dom.get(id);
                  var destDD = DDM.getDDById(id);
                  parent = destEl.getElementsByClassName(this.parentClass)[0]
                  parent.appendChild(this.getEl());
                  destDD.isEmpty = false;
                  DDM.refreshCache();
              }
              onDragDropCallback(Dom.get(id), parent);
  
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
          if (destEl.className == this.itemClass) {
              var orig_p = srcEl.parentNode;
              var p = destEl.parentNode;
  
              if (this.goingUp) {
                  p.insertBefore(srcEl, destEl); // insert above
              } else {
                  p.insertBefore(srcEl, destEl.nextSibling); // insert below
              }
              onDragOverCallback(destEl, this.getDragEl(), this.goingUp);

              DDM.refreshCache();
          }
      }
    });

    Event.onDOMReady(YAHOO.example.TeamApp.init, YAHOO.example.ScheduleApp, true);
    
  })();

  //]]>
  </script>

EOF;
  return $html;
}

showPage($title, $html);
?>
