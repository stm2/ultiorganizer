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
    $current = $base;

    foreach ($path as $step) {
      if (is_int($step)) {
        if (!isset($current[$step])) {
          throw new Exception(sprintf("%s not found in %s / %s", $step, json_encode($base), json_encode($path)));
        }
        $current = $current[$step];
      } else {
        $element = $current->$step ?? null;
        if (is_array($element)) {
          if (isset($element->{'@ref'})) {
            throw new Exception("not implemented");
          } else {
            $current = $element;
          }
        }
        if (isset($element->{'@ref'})) {
          $current = $this->find_ref($step, $element->{'@ref'});
        } else {
          $current = $element;
        }
      }
    }
    return $current;
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
 * <code>
 * [
 *   'source' : string (api url),
 *   'retrieved' : int (UNIX timestamp of latest call),
 *   'tournaments' :
 *   [ 
 *     'id' : int,
 *     'name' : string,
 *     'surface' : string,
 *     'year' : int,
 *     'divisions' : [
 *       'id' : int,
 *       'divisionIdentifier' : string,
 *       'divisionType' : string,
 *       'divisionAge' : string,
 *       'teams' : [
 *         'id' : int,
 *         'name' : string,
 *         'name_addition' : string,
 *         'team_name' : string,
 *         'team_id' : int,
 *         'team_club' : string,
 *         'team_location' : string,
 *         'status' : string,
 *       ],
 *       'events': [
 *         'id' : string,
 *         'name' : string,
 *         'city' : string,
 *         'startDate': string (mysql date),
 *         'endDate': string,
 *         'admins' : array or null (emails),
 *       ]
 *     ]
 *   ]
 * ]
 * </code>
 */
function DFVTournaments($refresh, $privileged) {
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
    $debug = 0;
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
          try {
            $city = $parsed->access($event, ['locations', 0, 'city']);
          } catch (Exception $e) {
            $city = '-';
          }
          $dfvadmins = $parsed->access($event, ['admins']);
          $admins = [];

          if (!empty($dfvadmins))
            foreach ($dfvadmins as $adm) {
              $admins[] = $parsed->access($adm, ['email']);
            }

          $new_event = [
            'id' => $parsed->access($event, ['id']),
            'tournament_id' => $new_tournament['id'],
            'name' => $parsed->access($event, ['name']),
            'city' => $city,
            'startDate' => $parsed->access($event, ['startDate']),
            'endDate' => $parsed->access($event, ['endDate']),
            'admins' => !empty($admins) ? $admins : null,
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
            'tournament_id' => $new_tournament['id'],
            'divisionType' => $parsed->access($divreg, ['divisionType']),
            'divisionAge' => $parsed->access($divreg, ['divisionAge']),
            'divisionIdentifier' => $parsed->access($divreg, ['divisionIdentifier'])
          ];
          $new_div['teams'] = [];
          foreach ($parsed->access($divreg, ['registeredTeams']) as $team) {
            $new_team = [
              'id' => $parsed->access($team, ['id']),
              'division_id' => $new_div['id'],
              'tournament_id' => $new_tournament['id'],
              'name' => $parsed->access($team, ['roster', 'team', 'name']),
              'name_addition' => $parsed->access($team, ['roster', 'nameAddition']), // TODO deprecated?
              'team_name' => $parsed->access($team, ['teamName']),
              'team_id' => $parsed->access($team, ['roster', 'team', 'id']),
              'team_club' => $parsed->access($team, ['roster', 'team', 'club', 'name']),
              'team_location' => $parsed->access($team, ['roster', 'team', 'location', 'city']),
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

        $new_tournaments[] = $new_tournament;
      }
      $data = ["source" => $source, "retrieved" => $retrieved, "tournaments" => $new_tournaments];
    }
    if (!file_put_contents($filename, json_encode($data), LOCK_EX))
      $data['error'] = _('Warning: Could not cache DFV data locally!');
  }

  if (!$privileged)
    cleanData($data);

  return $data;
}

function cleanData($data) {
  foreach ($data['tournaments'] as &$t) {
    if (isset($t['divisions']))
      foreach ($t['divisions'] as &$div) {
        if (isset($t['events']))
          foreach ($div['events'] as &$e) {
            $e['admins'] = null;
          }
      }
  }
  return $data;
}

function division_to_dfv($ulti_division) {
  // return array("open", "women", "mixed", "master open", "master women", "master mixed", "grand master", "U19 open",
  // "U19 women", "U19 mixed", "U23 open", "U23 women", "U23 mixed", "junior open", "junior women");
  $translation = ['open' => 'OPEN', 'women' => 'WOMEN', 'mixed' => 'MIXED'];
  return $translation[$ulti_division] ?? '';
}

function displaySeasonSelection($seasonId, $series_id) {
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

  $html .= "<p><input id='action_load_teams' class='button' name='action_load_teams' type='submit' value='" . utf8entities(_("Load...")) .
    "'/>";
  $html .= "<input id='cancel_selection' class='button' name='action_cancel' type='submit' value='" . utf8entities(_("Cancel")) .
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

function getDFVDivision($tournament_id, $id) {
  $data = DFVTournaments(($_GET['action_refresh'] ?? 0) == 1, true);
  $tournaments = $data['tournaments'];
  foreach ($tournaments as $tournament) {
    if ($tournament['id'] == $tournament_id) {
      $divs = $tournament['divisions'] ?? [];
      foreach ($divs as $div) {
        if ($div['id'] == $id) return $div;
      }
    }
  }
  return null;
}

function displayDFVSelection($seasonId, $series_id, $url_here) {
  $data = DFVTournaments(($_GET['action_refresh'] ?? 0) == 1, false);

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
  $html .= " <a href='$url_here&action_refresh=1'>" .
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
    $showEmpty = !empty($_POST['show_empty']);
    $checked =  $showEmpty ?  "checked='checked'" : '';
    $html .= "<tr><td><label for='show_empty'>" . _("Show empty divisions") . "</label><td><input type='checkbox'$checked id='show_empty' name='show_empty' /></td></tr>\n";


    $html .= "<tr><td colspan='2'>" . utf8entities(_("Optionally select search parameters above, then")) . "</td></tr>\n";
    $html .= "<tr><td colspan='2'><input id='filter_button' class='button' name='action_filter_division' type='submit' value='" .
      utf8entities(_("Update list of divisions...")) . "'/></td></tr><tr><td>&nbsp;</td></tr>\n";

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
        if (
          matching_division(
            $tournament,
            $div,
            ['year' => $year_filter, 'surface' => $surface_filter],
            ['divisionType' => $division_filter, 'divisionAge' => $age_filter]
          ) && ($showEmpty || !empty($div['teams']))
        ) {
          $name = $tournament['name'];

          if (!empty($tournament['year']))
            $name .= " - " . $tournament['year'];
          if (!empty($tournament['surface']))
            $name .= " (" . $tournament['surface'] . ")";
          if (!empty($div['divisionIdentifier']))
            $name .= " - " . $div['divisionIdentifier'];
          $name .= " (" . $div['divisionType'] . ", " . $div['divisionAge'] . ")";

          $options[$div['id']] = [
            'id' => $div['id'],
            'tournament_id' => $div['tournament_id'],
            'name' => $name,
            'division' => $div['divisionIdentifier'] ?? $div['id'] ?? "",
            'tournament' => $tournament['name'],
            'year' => $tournament['year'],
            'surface' => $tournament['surface'],
            'divisionType' => $div['divisionType'],
            'divisionAge' => $div['divisionAge'],

            'teams' => $div['teams'],
            'events' => $div['events']
          ];
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

    $html .= "<p><input id='action_load_teams' class='button' name='action_load_teams' type='submit' value='" . utf8entities(_("Show teams...")) .
      "'/>";
    $html .= "<input id='action_cancel' class='button' name='action_cancel' type='submit' value='" . utf8entities(_("Cancel")) .
      "'/>";
    $html .= "</p>\n";
  } else {
    $html .= "<p>";
    $html .= "<input id='action_cancel' class='button' name='action_cancel' type='submit' value='" . utf8entities(_("Cancel")) .
      "'/></p>";
  }
  return $html;
}
