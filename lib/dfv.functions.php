<?php
include_once $include_prefix . 'lib/debug.functions.php';

class DFVParsed {

  var $refs = [];

  var $raw;

  var $error = 0;

  const FILE_NOT_FOUND = 1;

  const INVALID_DATA = 2;

  function __construct($path) {
    // FIXME How to supress E_WARNING when url does not work?
    $data = file_get_contents($path);
    if ($data === false) {
      $this->error = self::FILE_NOT_FOUND;
    } else {
      $this->raw = json_decode($data);
      if ($this->raw == null) {
        $this->error = self::INVALID_DATA;
      }
    }
  }

  function search_ref($base, $parent, $search_key, $id) {
    if (is_array($base)) {
      foreach ($base as $new_base) {
        $found = null;
        if (is_array($new_base) || is_object($new_base)) {
          $found = $this->search_ref($new_base, null, $search_key, $id);
        }
        if ($found != null)
          return $found;
      }
    } else {
      foreach ($base as $key => $value) {
        if ($key == "@id") {
          if (!isset($this->refs[$parent][$value])) {
            $this->refs[$parent][$value] = $base;
          }
          if ($search_key == $parent && $id == $value) {
            return $base;
          }
        } else {
          if (is_array($value)) {
            $found = $this->search_ref($value, $key, $search_key, $id);
            if ($found != null)
              return $found;
          } else if (is_object($value)) {
            $found = $this->search_ref($value, $key, $search_key, $id);
            if ($found != null)
              return $found;
          }
        }
      }
    }
    return null;
  }

  function find_ref($key, $id) {
    $refs = $this->refs;

    return $refs[$key][$id] ?? $this->search_ref($this->raw, null, $key, $id);
  }

  function access($base, $path) {
    if ($this->error > 0)
      throw new Exception("No valid data found. Error " . $this->error);

    if ($base == null)
      $base = $this->raw;

    foreach ($path as $step) {
      if (is_int($step)) {
        if (!isset($base[$step])) {
          throw new Exception("not found");
        }
        $base = $base[$step];
      } else {
        $element = $base->$step ?? null;
        if (is_array($element)) {
          if (isset($element->{'@ref'})) {
            throw new Exception("not implemented");
          } else {
            $base = $element;
          }
        }
        if (isset($element->{'@ref'})) {
          $base = $this->find_ref($step, $element->{'@ref'});
        } else {
          $base = $element;
        }
      }
    }
    return $base;
  }
}

$dfv_refresh_interval = 86400; // 24h

/**
 *
 * Gets tournament data from dfv-turniere.de API or from local cache if available. If refresh == true, the cache is deleted.
 *
 * @param boolean $refresh
 *          force refresh
 *          
 * @return array[] Tournament data:
 *         <code>
 *         [
 *         'source' : string (api url),
 *         'retrieved' : int (UNIX timestamp of latest call,
 *         'tournaments' :
 *         [ 'id' : int,
 *         'name' : string,
 *         'surface' : string,
 *         'year' : int,
 *         'divisions' : [
 *         'id' : int,
 *         'divisionIdentifier' : string,
 *         'divisionType' : string,
 *         'divisionAge' : string,
 *         'teams' : [
 *         'id' : int,
 *         'name' : string,
 *         'teamName' : string,
 *         'teamId' : int,
 *         'teamClub' : string,
 *         'teamLocation' : string
 *         ]
 *         ]
 *         ]
 *         ]
 *         </code>
 */
function DFVTournaments($refresh = true) {
  global $dfv_refresh_interval;
  $filename = UPLOAD_DIR . "dfv/dfv_tournaments.json";
  recur_mkdirs(UPLOAD_DIR . "dfv", 0775);
  $data = [];

  if (!$refresh && file_exists($filename)) {
    $data = file_get_contents($filename);
    if ($data === false) {
      debug_to_apache("could not read file $filename");
    } else {
      $data = json_decode($data, true);
      if (
        $data === null || !is_array($data) || !isset($data['retrieved']) || !isset($data['tournaments']) ||
        !is_array($data['tournaments'])
      ) {
        debug_to_apache("invalid content in $filename");
      }
    }
  }

  if (empty($data) ||  time() - $data['retrieved'] >= $dfv_refresh_interval) {
    $refresh = true;
  }

  if ($refresh) {
    $retrieved = time();
    ini_set("post_max_size", "30M");
    ini_set("upload_max_filesize", "30M");
    ini_set("memory_limit", -1);

    $source = 'https://www.dfv-turniere.de/api/tournaments';
    // $source = 'tournaments.test.json';
    $parsed = new DFVParsed($source);
    if ($parsed->error > 0) {
      $data = ["source" => $source, "retrieved" => $retrieved, "tournaments" => null, "error" => $parsed->error];
    } else {
      foreach ($parsed->access(null, []) as $tournament) {
        $new_tournament = [
          'id' => $parsed->access($tournament, ['id']),
          'name' => $parsed->access($tournament, ['name']),
          'year' => $parsed->access($tournament, ['season', 'year']),
          'surface' => $parsed->access($tournament, ['season', 'surface'])
        ];

        $events = [];
        foreach ($parsed->access($tournament, ['events']) as $event) {
          $new_event = [
            'id' => $parsed->access($event, ['id']),
            'name' => $parsed->access($event, ['name']),
            'city' => $parsed->access($event, ['locations', 0, 'city']),
            'startDate' => $parsed->access($event, ['startDate']),
            'endDate' => $parsed->access($event, ['endDate'])
          ];
          foreach ($parsed->access($event, ['divisionConfirmations']) as $divconf) {
            $div = $parsed->access($divconf, ['divisionRegistration', 'id']);
            if (!isset($events[$div])) {
              $events[$div] = [];
            }
            $events[$div][] = $new_event;
          }
        }

        foreach ($parsed->access($tournament, ['divisionRegistrations']) as $divreg) {
          $divId = $parsed->access($divreg, ['id']);
          $new_div = [
            'id' => $divId,
            'divisionType' => $parsed->access($divreg, ['divisionType']),
            'divisionAge' => $parsed->access($divreg, ['divisionAge']),
            'divisionIdentifier' => $parsed->access($divreg, ['divisionIdentifier'])
          ];
          $new_div['teams'] = [];
          foreach ($parsed->access($divreg, ['registeredTeams']) as $team) {
            $new_team = [
              'id' => $parsed->access($team, ['id']),
              'name' => $parsed->access($team, ['roster', 'team', 'name']),
              'teamName' => $parsed->access($team, ['teamName']),
              'teamId' => $parsed->access($team, ['roster', 'team', 'id']),
              'teamClub' => $parsed->access($team, ['roster', 'team', 'club', 'name']),
              'teamLocation' => $parsed->access($team, ['roster', 'team', 'location', 'city']),
              'status' => $parsed->access($team, ['status'])
            ];
            $new_div['teams'][] = $new_team;
          }
          $myEvents = $events[$divId] ?? [];

          mergesort($myEvents, function ($a, $b) {
            return strtotime($a['startDate']) - strtotime($b['startDate']);
          });
          $new_div['events'] = $myEvents;

          $new_tournament['divisions'][] = $new_div;
        }
        // debug_to_apache([$new_tournament, $events]);

        $new_tournaments[] = $new_tournament;
      }
      $data = ["source" => $source, "retrieved" => $retrieved, "tournaments" => $new_tournaments];
    }
    if (!file_put_contents($filename, json_encode($data), LOCK_EX))
      $data['error'] = _('Warning: Could not cache DFV data locally!');
  }
  return $data;
}

function division_to_dfv($ulti_division) {
  // return array("open", "women", "mixed", "master open", "master women", "master mixed", "grand master", "U19 open",
  // "U19 women", "U19 mixed", "U23 open", "U23 women", "U23 mixed", "junior open", "junior women");
  $translation = ['open' => 'OPEN', 'women' => 'WOMEN', 'mixed' => 'MIXED'];
  return $translation[$ulti_division] ?? '';
}

function teamTable($series_id, $teams, $club, $country, $edit, $short = false, &$teamListItems) {
  global $focusId;
  $html = "<table class='admintable'><tbody id='addedteams' class='dd_target'>\n";

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
  $delete = utf8entities(_("Delete"));

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
    $displayName = $team['name'] != $team['teamName'] ?
      utf8entities($team['name'] . " (" . $team['teamName'] . ")") : utf8entities($team['name']);
    $abbrev = utf8entities($team['abbreviation']);
    // $club = intval($team['club']);
    $club = utf8entities($team['clubname']);
    // $club = utf8entities(ClubName($team['clubname']));
    if ($country)
      $countryId = intval($team['country']);

    $teamListItems[] = [
      'id' => "team_item$teamId",
      'hId' => "draggable$teamId",
      'parent' => 'addedteams',
      'item_class' => 'team_item'
    ];

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
    $html .= "<span class='teamname'>" . $displayName . "</span>";
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

  //   $html .= "<div style='border: thin black solid; min-width: 40em; min-height: 10rem;'>";
  $html = "<h3>" . _("Teams to add") . "</h3>\n";
  $html .= "<div class='team_list' >";
  $html .= "<table class='team_list'><tbody id='addedteams' class='dd_target'  style='min-height:10rem'>\n";
  $html .= $added;
  $html .= "<tr><td class='team_item placeholder'>&nbsp;</td></tr>\n";
  $html .= "</tbody></table></div>\n";

  $html .= "<h3>" . _("Teams to ignore") . "</h3>\n";
  $html .= "<div class='team_list'>";
  $html .= "<table class='team_list'><tbody id='notaddedteams' class='dd_target' >\n";
  $html .= "<tr><td class='team_item placeholder'>&nbsp;</td></tr>\n";
  $html .= $notAdded;
  $html .= "</tbody></table></div>\n";
  return $html;
}

function showSeasonSelection($seasonId, $series_id) {
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
    $divs = $tournament['divisions'] ?? [];
    foreach ($divs as $div) {
      foreach ($division_attributes as $att) {
        $choices[$att][$div[$att]] = 1;
      }
    }
  }
  return $choices;
}

function showDFVSelection($seasonId, $series_id, $url_here) {
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
        sprintf(
          _("Data retrieved from DFV (%s) at %s"),
          utf8entities($data['source']),
          utf8entities(EpocToMysql($data['retrieved']))
        )
      );
  }
  $html .= " <a href='$url_here'>" .
    utf8entities(_("Refresh")) . "</a></p>\n";

  if (!empty($data['error']))
    $html .= "<p>" . $data['error'] . "</p>\n";

  if (count($tournaments)) {
    $html .= "<table>";
    $choices = fill_choices($tournaments, ['year', 'surface'], ['divisionType', 'divisionAge']);

    $year_filter = add_filter($html, 'year_filter', _('Year'), $choices['year'], date('Y', time()));
    $surface_filter = add_filter($html, 'surface_filter', _('Surface'), $choices['surface']);

    $division_filter = add_filter(
      $html,
      'division_filter',
      _('Division'),
      $choices['divisionType'],
      $series_id > 0 ? division_to_dfv(SeriesInfo($series_id)['type']) : ''
    );

    $age_filter = add_filter($html, 'age_filter', _('Age'), $choices['divisionAge']);

    $html .= "<tr><td colspan='2'><input id='filter_button' class='button' name='filter_division' type='submit' value='" .
      utf8entities(_("Show matching divisions...")) . "'/></td></tr><tr><td>&nbsp;</td></tr>\n";

    $html .= "<tr><td>" . utf8entities(_("Add teams from:")) . "</td><td>";

    $html .= "<select class='dropdown' name='importdivision'>\n";
    $options = [];
    mergesort(
      $tournaments,
      uo_create_multi_key_comparator([['year', true, true], ['surface', true, true], ['id', true, true]])
    );

    foreach ($tournaments as $tournament) {
      $divs = $tournament['divisions'] ?? [];
      mergesort(
        $divs,
        uo_create_multi_key_comparator(
          [['divisionAge', false, true], ['divisionType', true, true], ['divisionIdentifier', false, true]]
        )
      );
      foreach ($divs as $div) {
        if (matching_division(
          $tournament,
          $div,
          ['year' => $year_filter, 'surface' => $surface_filter],
          ['divisionType' => $division_filter, 'divisionAge' => $age_filter]
        )) {
          $name = $tournament['name'];

          if (!empty($tournament['year']))
            $name .= " - " . $tournament['year'];
          if (!empty($tournament['surface']))
            $name .= " (" . $tournament['surface'] . ")";
          if (!empty($div['divisionIdentifier']))
            $name .= " - " . $div['divisionIdentifier'];
          $name .= " (" . $div['divisionType'] . ", " . $div['divisionAge'] . ")";

          $options[$div['id']] = ['id' => $div['id'], 'name' => $name, 'division' => $div['divisionIdentifier'] ?? $div['id'] ?? "", 'teams' => $div['teams'], 'events' => $div['events']];
        }
      }
    }

    foreach (array_reverse($options) as $div) {
      $val = utf8entities(json_encode($div));

      if (isset($_POST['importdivision'])) {
        $selectedId = json_decode($_POST['importdivision'], true)['id'];
      } else {
        $selectedId = 0;
      }

      if ($selectedId == $div['id'])
        $selected = " selected='1'";
      else
        $selected = "";
      $html .= "<option class='dropdown'$selected value='$val'>" . utf8entities($div['name']) . "</option>";
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
