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
      throw Exception("No valid data found. Error " . $this->error);

    if ($base == null)
      $base = $this->raw;

    foreach ($path as $step) {
      if (is_int($step)) {
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
  $filename = UPLOAD_DIR . "dfv/dfv_tournaments.json";
  recur_mkdirs(UPLOAD_DIR . "dfv", 0775);

  if (!$refresh && file_exists($filename)) {
    $data = file_get_contents($filename);
    if ($data === false) {
      debug_to_apache("could not read file $filename");
    } else {
      $data = json_decode($data, true);
      if ($data === null || !is_array($data) || !isset($data['retrieved']) || !isset($data['tournaments']) ||
        !is_array($data['tournaments'])) {
        debug_to_apache("invalid content in $filename");
      } else {
        $refresh = false;
      }
    }
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
        $new_tournament = ['id' => $parsed->access($tournament, ['id']),
          'name' => $parsed->access($tournament, ['name']), 'year' => $parsed->access($tournament, ['season', 'year']),
          'surface' => $parsed->access($tournament, ['season', 'surface'])];

        foreach ($parsed->access($tournament, ['divisionRegistrations']) as $divreg) {
          $new_div = ['id' => $parsed->access($divreg, ['id']),
            'divisionType' => $parsed->access($divreg, ['divisionType']),
            'divisionAge' => $parsed->access($divreg, ['divisionAge']),
            'divisionIdentifier' => $parsed->access($divreg, ['divisionIdentifier'])];
          $new_div['teams'] = [];
          foreach ($parsed->access($divreg, ['registeredTeams']) as $team) {
            $new_team = ['id' => $parsed->access($team, ['id']), 'teamName' => $parsed->access($team, ['teamName']),
              'teamId' => $parsed->access($team, ['roster', 'team', 'id']),
              'teamClub' => $parsed->access($team, ['roster', 'team', 'club', 'name']),
              'teamLocation' => $parsed->access($team, ['roster', 'team', 'location', 'city'])];
            $new_div['teams'][] = $new_team;
          }
          $new_tournament['divisions'][] = $new_div;
        }

        $new_tournaments[] = $new_tournament;
      }
      $data = ["source" => $source, "retrieved" => $retrieved, "tournaments" => $new_tournaments];
    }
    file_put_contents($filename, json_encode($data), LOCK_EX);
  }
  return $data;
}
