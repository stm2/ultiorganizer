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

// $html .= JavaScriptWarning();

ensureEditSeriesRight($series_id);

// team parameters
$tp = array(
  "team_id" => "",
  "name" => "",
  "club" => "",
  "country" => "",
  "abbreviation" => "",
  "series" => $series_id,
  "pool" => "",
  "rank" => "",
  "valid" => "1",
  "bye" => ""
);

$seasonInfo = SeasonInfo($seasonId);

$teamListItems = [];

$focusId = null;

$get_link = function ($seasonId, $seriesId, $add = null, $single = 0, $htmlEntities = false) {
  $single = $single == 0 ? "" : "&single=1";
  $seaLink = urlencode($seasonId);
  $link = "?view=admin/seasonteams&season=$seaLink&series={$seriesId}$single";
  if (!empty($add))
    foreach ($add as $key => $value) {
      $link .= "&$key=$value";
    }
  return $htmlEntities ? utf8entities($link) : $link;
};

$importstage = $_GET['importstage'] ?? $_POST['importstage'] ?? 0;

$club = !intval($seasonInfo['isnationalteams']);
$country = intval($seasonInfo['isinternational']);

$url_here = $get_link($seasonId, $series_id, ['importstage' => $importstage, 'refresh' => 0], $single, true);

$html .= SeriesPageMenu($seasonId, $series_id, $single, $get_link, "?view=admin/seasonseries&season=$seasonId");

$html .= "<form method='post' action='$url_here'>";


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
      // FIXME dead code?
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
            if ($seed > 0) {
              $clubId = 0;
              if ($club && !empty($_POST['clubname'])) {
                $clubId = ClubId($_POST['clubname'][$i]);
                if ($clubId == -1) {
                  $clubId = AddClub($series_id, $_POST['club'][$i]);
                }
              }

              $new_teams[] = [
                'rank' => $seed,
                'name' => $_POST['name'][$i],
                'abbreviation' => $_POST['abbrev'][$i],
                'clubname' => $_POST['club'][$i] ?? null,
                'club' => $clubId,
                'country' => $_POST['country'][$i] ?? null
              ];
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
        } else if ($importstage == 0 || isset($_POST['action_cancel'])) {
          $showTeams = true;
        } else if (!isset($_POST['import_add']) && !isset($_POST['import_replace']) && ($importstage == 1 || $importstage == 2 || isset($_POST['refresh']))) {
          $showTeams = false;
          $html .= "<p><input type='hidden' id='importstage' name='importstage' value='$importstage'/></p>";

          if ($importstage == 1) {
            $html .= showSeasonSelection($seasonId, $series_id, $importstage);
          }
          if ($importstage == 2) {
            $url_here = $get_link($seasonId, $series_id, ['importstage' => $importstage], $single, true);
            $html .= showDFVSelection($seasonId, $series_id, $importstage, $url_here);
          }

          if (isset($_POST['action_load_teams'])) {
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
              if (!empty($_POST['importdivision'])) {
                $teams = json_decode($_POST['importdivision'], true)['teams'];
                $valid = 0;
                foreach ($teams as $i => &$team) {
                  $team['rank'] = (($team['status'] ?? '') == 'CONFIRMED') ? ++$valid : '';
                  $team['name'] = $team['name'];
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
              $html .= "<input class='button' name='import_add' type='submit' value='" . utf8entities(_("Add")) .
                "'/> " . _("Add new teams") . "<br />";
              $num = min(count($teams), count(SeriesTeams($series_id)));
              if ($num) {
                $html .= "<input class='button' name='import_replace' type='submit' value='" .
                  utf8entities(_("Replace")) . "'/> " . utf8entities(sprintf(_("Rename %s teams with lowest seed #"), $num)) .
                  "<br />";
              }
              $html .= "<input id='action_cancel' class='button' name='action_cancel' type='submit' value='" .
                utf8entities(_("Cancel")) . "'/>";
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
  $html .= "<input id='cancel_save' class='button' name='action_cancel' type='submit' value='" . utf8entities(_("Cancel")) .
    "'/>";
  $html .= "</p>";

  $seaLink = urlencode($seasonId);

  $html .= "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series={$series_id}&amp;importstage=1'>" .
    utf8entities(_("Import teams from other division")) . "</a><br />\n";
  $html .= "<a href='?view=admin/seasonteams&amp;season=$seaLink&amp;series={$series_id}&amp;importstage=2'>" .
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

$html .= getDragDropApp(
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

showPage($title, $html);
