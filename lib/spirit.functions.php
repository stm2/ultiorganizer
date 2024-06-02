<?php

function SpiritMode($mode_id) {
  $query = sprintf("SELECT mode, text AS name FROM `uo_spirit_category`
          WHERE `mode` = %d AND `index` = 0", (int) $mode_id);
  return DBQueryToRow($query);
}

function SpiritModes() {
  $query = sprintf("SELECT mode, text AS name FROM `uo_spirit_category`
          WHERE `index` = 0 ORDER BY `group`");
  return DBQueryToArray($query);
}

function SpiritCategories($mode_id) {
  $query = sprintf("SELECT * FROM `uo_spirit_category`
      WHERE `mode`=%d
      ORDER BY `index` ASC", (int) $mode_id);
  $cats = DBQueryToArray($query);
  $categories = array();
  foreach ($cats as $cat) {
    $categories[$cat['category_id']] = $cat;
  }
  return $categories;
}

function SpiritTotal($points, $categories) {
  $allset = true;
  $total = 0;
  foreach ($categories as $cat) {
    if ($cat['type'] == 1)
      if (isset($points[$cat['category_id']])) {
        $total += $points[$cat['category_id']] * $cat['factor'];
      } else {
        $allset = false;
      }
  }
  if ($allset)
    return $total;
  else
    return null;
}

function SpiritTable($gameinfo, $points, $categories, $home, $wide = true) {
  $home = $home ? "home" : "vis";
  $html = "<table class='spirit_table'>\n";
  $colspan = 1;
  // $wide = false;
  if ($wide) {
    $html .= "<tr><th style='min-width:10rem'>" . _("Category") . "</th><th></th></tr>\n";
    $colspan = 2;
  }
  $vmin = 99999;
  $vmax = -99999;
  foreach ($categories as $cat) {
    if ($vmin > $cat['min'])
      $vmin = $cat['min'];
    if ($vmax < $cat['max'])
      $vmax = $cat['max'];
  }

  if ($vmax - $vmin < 12) {
    foreach ($categories as $cat) {
      $html .= "<tr>";
      if ($cat['index'] == 0)
        continue;
      $id = $cat['category_id'];
      if ($cat['type'] == 1) {
        $html .= "<td>" . _($cat['text']);
        $html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/>";
        if ($wide)
          $html .= "</td>";
        else
          $html .= "</td></tr>\n<tr>";

        $html .= "<td><fieldset id='" . $home . "cat" . $id . "_x' data-role='controlgroup' data-type='horizontal' >";
        for ($i = $vmin; $i <= $vmax; ++$i) {
          if ($i < $cat['min']) {
            // $html .= "<td></td>";
          } else {
            $id = $cat['category_id'];
            $checked = (isset($points[$id]) && !is_null($points[$id]) && $points[$id] == $i) ? "checked='checked'" : "";
            $html .= "<label for='" . $home . "cat" . $id . "_" . $i . "'>$i</label>";
            $html .= "<input type='radio' id='" . $home . "cat" . $id . "_" . $i . "' name='" . $home . "cat" . $id .
              "' value='$i' $checked/>";

            // $html .= "<td class='center'>
            // <input type='radio' id='".$home."cat".$id."_".$i."' name='".$home."cat". $id . "' value='$i' $checked/></td>";
          }
        }
        $html .= "</fieldset></td>";
      } else if ($cat['type'] == 2) {
        $html .= "<td colspan='$colspan'><div>";
        $html .= _($cat['text']) . "<input name='layout' type='radio' style='opacity: 0.01;' disabled /><br />";
        $html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/>";
        $val = $points[$id] ?? "";
        $html .= "<textarea  class='input borderbox' rows='6' maxlength='1000' id='{$home}cat{$id}' name='{$home}cat{$id}'>$val</textarea></div></td>\n";
      }
      $html .= "</tr>\n";
    }
  } else {
    $colspan = 2;
    $html .= "<th colspan='2'></th></tr>\n";

    foreach ($categories as $cat) {
      $html .= "<tr>";
      $id = $cat['category_id'];
      if ($cat['type'] == 1) {
        $html .= "<td>" . _($cat['text']);
        $html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/></td>";
        $html .= "<td class='center'>
      <input type='text' size=3 id='" . $home . "cat" . $id . "_0' name='" . $home . "cat$id' value='" . $points[$id] .
          "'/></td>";
      } else if ($cat['type'] == 2) {
        $cols = $vmax - $vmin + 1 + 1;
        $html .= "<td colspan='$cols'><div>";
        $html .= _($cat['text']) . "<input name='layout' type='radio' style='opacity: 0.01;' disabled /><br />";
        $html .= "<input type='hidden' id='" . $home . "valueId$id' name='" . $home . "valueId[]' value='$id'/>";
        $html .= "<textarea  class='input borderbox' rows='6' maxlength='1000' id='{$home}cat{$id}' name='{$home}cat{$id}'>{$points[$id]}</textarea></div></td>\n";
      }
      $html .= "</tr>\n";
    }
  }

  $html .= "<tr>";
  $html .= "<td class='highlight' colspan='$colspan'>" . _("Total points");
  $total = SpiritTotal($points, $categories);
  if (!isset($total))
    $total = ": -";
  else
    $html .= ": $total";
  $html .= "</tr>";

  $html .= "</table>\n";

  return $html;
}

function GameGetSpiritPoints($gameId, $teamId, $received = true) {
  if ($received)
    $teamclause = sprintf(" AND team_id = %d", (int) $teamId);
  else
    $teamclause = sprintf(" AND team_id != %d", (int) $teamId);
  $query = sprintf(
    "SELECT score.category_id, score.value, score.text, cat.type, team_id FROM uo_spirit_score score, uo_spirit_category cat WHERE  score.category_id = cat.category_id AND game_id=%d $teamclause ORDER BY cat.index",
    (int) $gameId);
  $scores = DBQueryToArray($query);
  $points = array();
  foreach ($scores as $score) {
    if ($score['type'] == 1)
      $points[$score['category_id']] = $score['value'];
    else
      $points[$score['category_id']] = $score['text'];
  }
  return $points;
}

function GameSpiritComplete(int $gameId, int $submitterId, int $spiritmode) {
  $query = sprintf(
    "SELECT game_id, team_id, count(*) scores
         FROM uo_spirit_score sc
         JOIN uo_spirit_category cat on (sc.category_id = cat.category_id)
         WHERE sc.game_id = %d AND team_id != %d AND cat.factor > 0 AND cat.mode = %d
         GROUP BY game_id, team_id
         HAVING scores = (SELECT count(*) FROM uo_spirit_category cat2 WHERE cat2.factor > 0 AND cat2.mode = %d)", //
    intval($gameId), intval($submitterId), intval($spiritmode), intval($spiritmode));

  $ret = DBQueryRowCount($query) > 0;

  return $ret;
}

function GetSeriesSpiritMode(int $seriesId) {
  return SeasonInfo(SeriesSeasonId($seriesId))['spiritmode'];
}

function GameSetSpiritPoints($gameId, $teamId, $home, $points, $categories, $checkRights = true) {
  if (!$checkRights || hasEditGameEventsRight($gameId)) {
    Log1("spirit", "update", $gameId, $teamId);
    $query = sprintf("DELETE FROM uo_spirit_score
        WHERE game_id=%d AND team_id=%d", (int) $gameId, (int) $teamId);
    DBQuery($query);

    foreach ($points as $cat => $value) {
      if ($categories[$cat]['type'] == 1) {
        if (!is_null($value)) {
          $query = sprintf(
            "INSERT INTO uo_spirit_score (`game_id`, `team_id`, `category_id`, `value`)
            VALUES (%d, %d, %d, %d)", (int) $gameId, (int) $teamId, (int) $cat, (int) $value);
          DBQuery($query);
        }
      } else if ($categories[$cat]['type'] == 2) {
        $query = sprintf(
          "INSERT INTO uo_spirit_score (`game_id`, `team_id`, `category_id`, `text`)
            VALUES (%d, %d, %d, '%s')", (int) $gameId, (int) $teamId, (int) $cat, mysql_adapt_real_escape_string($value));
        DBQuery($query);
      }
    }
  } else {
    die('Insufficient rights to edit game');
  }
}

/**
 * Get division spirit score per category.
 *
 * @param int $seriesId
 *          uo_series.series_id
 * @return array mysql array of spirit scores per team.
 *
 */
function SeriesSpiritBoard($seriesId) {
  $query = sprintf(
    "SELECT st.team_id, te.name, st.category_id, st.value, pool.series, cat.type
      FROM uo_team AS te
      LEFT JOIN uo_spirit_score AS st ON (te.team_id=st.team_id)
      LEFT JOIN uo_game_pool AS gp ON (st.game_id=gp.game)
      LEFT JOIN uo_pool pool ON(gp.pool=pool.pool_id)
      LEFT JOIN uo_game AS g1 ON (gp.game=g1.game_id)
      LEFT JOIN uo_spirit_category cat ON (st.category_id = cat.category_id)
      WHERE pool.series=%d AND gp.timetable=1 AND g1.isongoing=0 AND g1.hasstarted>0
      ORDER BY st.team_id, st.category_id", $seriesId);
  
  $scores = DBQuery($query);
  $last_team = null;
  $last_category = null;
  $averages = array();
  $total = 0;
  $games = $sum = 0;
  $factor = [];
  while ($row = mysqli_fetch_assoc($scores)) {
    if ($row['type'] == 1) {
      if ($last_team != $row['team_id'] || $last_category != $row['category_id']) {
        if (!is_null($last_category)) {
          if (!isset($factor[$last_category])) {
            $factor[$last_category] = DBQueryToArray(
              sprintf("SELECT * FROM uo_spirit_category WHERE category_id=%d", (int) $last_category))[0]['factor'];
          }
          $teamline[$last_category] = SafeDivide($sum, $games);
          $total += SafeDivide($factor[$last_category] * $sum, $games);
        }
        if ($last_team != $row['team_id']) {
          if (!is_null($last_team)) {
            $teamline['total'] = $total;
            $teamline['games'] = $games;
            $averages[$last_team] = $teamline;
            $total = 0;
          }
          $teamline = array('teamname' => $row['name'], 'team_id' => $row['team_id']);
        }
        $sum = 0;
        $games = 0;
        $last_team = $row['team_id'];
        $last_category = $row['category_id'];
      }
      $sum += $row['value'];
      ++$games;
    }
  }
  if (!is_null($last_team)) {
    $factor[$last_category] = DBQueryToArray(
      sprintf("SELECT * FROM uo_spirit_category WHERE category_id=%d", (int) $last_category))[0]['factor'];
      $teamline[$last_category] = SafeDivide($sum, $games);
      $total += SafeDivide($factor[$last_category] * $sum, $games);
      $teamline['total'] = $total;
      $teamline['games'] = $games;
      $averages[$last_team] = $teamline;
  }
  return $averages;
}


function SpiritSubmitted(int $teamId, int $spiritmode) {
  $query = sprintf(
    "SELECT sub.own, count(*) as num FROM(
       SELECT gg.game_id, team_id, team_id != %d as own, count(*) scores
         FROM uo_game gg
         JOIN uo_spirit_score sc on (gg.game_id = sc.game_id)
         JOIN uo_spirit_category cat on (sc.category_id = cat.category_id)
         WHERE (hometeam = %d OR visitorteam = %d) AND cat.factor > 0 AND cat.mode = %d
         GROUP BY game_id, team_id
         HAVING scores = (SELECT count(*) FROM uo_spirit_category cat2 WHERE cat2.factor > 0 AND cat2.mode = %d)) sub
       GROUP BY sub.own ORDER BY own DESC", //
    intval($teamId), intval($teamId), intval($teamId), intval($spiritmode), intval($spiritmode));
  
  $results = DBQueryToArray($query);
  
  if (empty($results))
    return ['submitted' => 0, 'received' => 0];
    
    return ['submitted' => $results[0]['num'] ?? 0, 'received' => $results[1]['num'] ?? 0];
}