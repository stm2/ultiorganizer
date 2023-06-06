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

$title = utf8entities(SeasonName($seasonId)) . ": " . _("Teams");
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
  debug_to_apache(print_r($_POST, true));
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

    debug_to_apache("add " . print_r($tp, true));
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

function teamTable($series_id, $teams, $club, $country, $edit) {
  global $focusId;
  $html = "<table class='admintable'>\n";

  $html .= "<tr><th class='center' title='" . _("Seed") . "'>#</th>";
  $html .= "<th>" . _("Name") . "</th>";
  $html .= "<th>" . _("Abbrev") . "</th>";

  if ($club) {
    $html .= "<th>" . _("Club") . "</th>";
  }
  if ($country) {
    $html .= "<th>" . _("Country") . "</th>";
  }
  if ($edit) {
    $html .= "<th>" . _("Contact person") . "</th>";
    $html .= "<th>" . _("Roster") . "</th>";
    $html .= "<th></th>";
  }
  $html .= "</tr>\n";

  $total = 0;

  foreach ($teams as $team) {
    $team_id = $team['team_id'] ?? "[]";
    $total++;

    $html .= "<tr class='admintablerow'>";
    $fid = $focusId == null ? " id='focus0'" : "";
    $html .= "<td><input class='input' size='2' maxlength='4'$fid name='seed$team_id' value='" .
      utf8entities($team['rank']) . "'/></td>";
    $html .= "<td><input class='input' size='20' maxlength='50' name='name$team_id' value='" .
      utf8entities($team['name']) . "'/></td>";
    $html .= "<td><input class='input' size='4' maxlength='15' name='abbrev$team_id' value='" .
      utf8entities($team['abbreviation']) . "'/></td>";

    if ($club) {
      $html .= "<td><input class='input' size='20' maxlength='50' name='club$team_id' value='" .
        utf8entities($team['clubname']) . "'/></td>";
    }

    if ($country) {
      $width = "";
      if ($club) {
        $width = "80px";
      }
      $html .= "<td>" . CountryDropListWithValues("country$team_id", "country$team_id", $team['country'], $width) .
        "</td>";
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

      $html .= "&nbsp;<a href='?view=admin/addteamadmins&amp;series=$series_id'>" . _("...") . "</a>";
      $html .= "</td>";

      $html .= "<td class='center'><a href='?view=user/teamplayers&amp;team=" . $team['team_id'] . "'>" . _("Roster") .
        "</a></td>";

      $html .= "<td>";
      $html .= "<a href='?view=admin/addseasonteams&amp;team=$team_id'><img class='deletebutton' src='images/settings.png' alt='D' title='" .
        _("edit details") . "'/></a>";
      if (CanDeleteTeam($team['team_id'])) {
        $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" .
          _("X") . "' onclick=\"setId(" . $team['team_id'] . ");\"/>";
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
      _("Add") . "'/></td>";
    $html .= "</tr>\n";
  }

  $html .= "</table>\n";
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
  $html .= "<input id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/> ";
  $html .= "<input id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/>";
  $html .= "</p>";

  $series_info = SeriesInfo($series_id);

  $seaLink = urlencode($seasonId);

  $html .= "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=1'>" .
    _("Import teams from other division") . "</a><br />\n";
  $html .= "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=2'>" .
    _("Import teams from DFV") . "</a><br />\n";
} else if ($importstage == 1) {
  $html .= "<p><input type='hidden' id='importstage' name='importstage' value='11'/></p>";

  $seasons = SeasonsArray();
  if (count($seasons)) {
    $series_info = SeriesInfo($series_id);
    $html .= "<p>" . _("Add teams from:") . " ";
    $html .= "<select class='dropdown' name='copyteams'>\n";
    foreach ($seasons as $season) {
      $divisions = SeasonSeries($season['season_id']);
      foreach ($divisions as $division) {
        if ($division['type'] != $series_info['type']) {
          continue;
        }

        $html .= "<option class='dropdown' value='" . utf8entities($division['series_id']) . "'>" .
          utf8entities($season['name'] . " " . $division['name']) . "</option>";
      }
    }
    $html .= "</select><br />\n";
    $html .= "<input id='copy' class='button' name='copy' type='submit' value='" . _("Import...") . "'/>";
    $html .= "<input id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/>";
    $html .= "</p>\n";
  }
} else if ($importstage == 2) {
  $html .= "<p><input type='hidden' id='importstage' name='importstage' value='12'/></p>";

  $data = DFVTournaments(($_GET['refresh'] ?? 0) == 1);
  $tournaments = $data['tournaments'];
  $seaLink = urlencode($seasonId);

  $html .= "<p>" .
    sprintf(_("Data retrieved from DFV (%s) at %s"), utf8entities($data['source']),
      utf8entities(EpocToMysql($data['retrieved']))) . " " .
    "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series=${series_id}&amp;importstage=$importstage&amp;refresh=1'>" .
    _("Refresh") . "</a></p>\n";

  if (count($tournaments)) {
    $html .= "<p>" . _("Add teams from:") . " ";
    $html .= "<select class='dropdown' name='importteams'>\n";
    $options = [];
    foreach ($tournaments as $tournament) {
      foreach ($tournament['divisions'] as $div) {
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

    foreach (array_reverse($options) as $id => $div) {
      $val = utf8entities(json_encode($div['val']));
      $html .= "<option class='dropdown' value='$val'>" . utf8entities($div['name']) . "</option>";
    }

    $html .= "</select><br />\n";

    $html .= "<input id='import' class='button' name='import' type='submit' value='" . _("Import...") . "'/>";
    $html .= "<input id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/>";
    $html .= "</p>\n";
  }
} else if ($importstage == 11 || $importstage == 12) {
  if ($importstage == 11) {
    $importSeries = SeriesInfo($_POST['copyteams']);
    $html .= "<p>" . sprintf(_("Teams from %s"), $importSeries['name']);
    $teams = SeriesTeams($_POST['copyteams']);
    foreach ($teams as &$team) {
      $team['country'] = TeamFullInfo($team['team_id'])['country'];
    }
    unset($team);
  } else {
    $teams = json_decode($_POST['importteams'], true);
    foreach ($teams as &$team) {
      $team['name'] = $team['teamName'];
      $team['clubname'] = $team['teamLocation'];
      $team['abbreviation'] = null;
      $team['country'] = null;
    }
    unset($team);
  }
  $html .= "<p><input type='hidden' id='importstage' name='importstage' value='21'/></p>";
  $html .= "<table class='admintable'>\n";

  $html .= "<tr><th class='center' title='" . _("Seed") . "'>#</th>";
  $html .= "<th>" . _("Name") . "</th>";
  $html .= "<th>" . _("Abbrev") . "</th>";

  if ($club) {
    $html .= "<th>" . _("Club") . "</th>";
  }

  if ($country) {
    $html .= "<th>" . _("Country") . "</th>";
  }
  $html .= "</tr>\n";

  $seed = 0;
  foreach ($teams as $team) {
    ++$seed;
    $html .= "<tr><td><input name='iseed[]' class='input' size='2' maxlength='4' value='$seed'/></td>" .
      "<td><input name='iname[]' value='" . utf8entities($team['name']) . "'/></td>" .
      "<td><input class='input' size='4' maxlength='15' name='iabbrev[]' value='" . utf8entities($team['abbreviation']) .
      "' /></td>";
    if ($club) {
      $html .= "<td><input name='iclub[]' value='" . utf8entities($team['clubname']) . "'/></td>";
    }

    if ($country) {
      $width = "";
      if ($club) {
        $width = "80px";
      }
      $html .= "<td>" . CountryDropListWithValues("country$seed", "country[]", $team['country'], $width) . "</td>";
    }

    $html .= "</tr>\n";
  }
  $html .= "</table>";

  $html .= "<fieldset>";
  $html .= "<p><input type='radio' checked='checked' id='add_mode' name='import_mode' value='add_mode' />";
  $html .= "<label for='insert_mode'>" . _("Add new teams") . "</label></p>\n";
  $html .= "<p><input type='radio' id='rename_mode' name='import_mode' value='rename_mode' />";
  $teams = SeriesTeams($series_id, true);
  $html .= "<label for='rename_mode'>" . sprintf(_("Rename %s teams with lowest seed"), count($teams)) . "</label></p>\n";
  $html .= "</fieldset>";

  $html .= "<p>";
  $html .= "<input id='import' class='button' name='import' type='submit' value='" . _("Import...") . "'/>";
  $html .= "<input id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/>";
  $html .= "</p>";
} else if ($importstage == 21) {
  $renamemode = ($_POST['import_mode'] == 'rename_mode');

  $new_teams = [];
  foreach ($_POST['iseed'] as $i => $seed) {
    $new_teams[] = ['rank' => $seed, 'name' => $_POST['iname'][$i], 'abbreviation' => $_POST['iabbrev'][$i],
      'clubname' => $_POST['iclub'][$i] ?? null, 'country' => $_POST['icountry'] ?? null];
  }
  mergesort($new_teams,
    function ($a, $b) {
      return (empty($a['rank']) && !empty($b['rank'])) || $a['rank'] > $b['rank'];
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

  $html .= "<p>" . _("Teams have not been saved!") . "</p>";

  $html .= "<p>";
  if ($renamemode) {
    $html .= "<input id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/>";
  } else {
    $html .= "<input id='add' class='button' name='add_multi' type='submit' value='" . _("Add") . "'/>";
  }
  $html .= "<input id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/>";
  $html .= "</p>";
}

$html .= "<hr/>\n";
$html .= "<p>";
$html .= "<a href='?view=admin/addteamadmins&amp;series=$series_id''>" . _("Add Team Admins") . "</a> | ";
$html .= "<a href='?view=user/pdfscoresheet&amp;series=$series_id'>" . _("Print team rosters") . "</a></p>";

// stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";

$html .= "</form>\n";

if (!empty($focusId))
  setFocus($focusId);

showPage($title, $html);
?>