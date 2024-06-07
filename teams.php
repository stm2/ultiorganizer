<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/statistical.functions.php';

$title = _("Teams");
$html = "";

$list = iget("list");
$season = iget("season");

if (empty($season)) {
  $season = CurrentSeason();
}

if (empty($list)) {
  $list = "allteams";
}

$seasonInfo = SeasonInfo($season);
$series = SeasonSeries($season, true);

$menutabs[_("By division")] = "?view=teams&season=$season&list=allteams";
$menutabs[_("By pool")] = "?view=teams&season=$season&list=bypool";
$menutabs[_("By seeding")] = "?view=teams&season=$season&list=byseeding";
$menutabs[_("By result")] = "?view=teams&season=$season&list=bystandings";
$html .= pageMenu($menutabs, "", false);

$cols = 2;
if (!intval($seasonInfo['isnationalteams'])) {
  $cols++;
}
if (intval($seasonInfo['isinternational'])) {
  $cols++;
}
if ($list == "byseeding") {
  $cols++;
}
$isstatdata = IsStatsDataAvailable();

$html .= "<h1>" . _("Teams") . "</h1>";

$html .= CommentHTML(1, $season);

if ($list == "allteams" || $list == "byseeding") {

  foreach ($series as $row) {

    $html .= "<table border='0' cellspacing='0' cellpadding='2' width='100%'>\n";
    $html .= "<tr>";
    $html .= "<th colspan='$cols'>";
    $html .= utf8entities(U_($row['name'])) . "</th>\n";
    $html .= "</tr>\n";
    if ($list == "byseeding") {
      $teams = SeriesTeams($row['series_id'], true);
    } else {
      $teams = SeriesTeams($row['series_id']);
    }
    $i = 0;
    foreach ($teams as $team) {
      $i++;
      $html .= "<tr>";
      if ($list == "byseeding") {
        if (!empty($team['rank'])) {
          $html .= "<td style='width:2px'>" . $team['rank'] . ".</td>";
        } else {
          $html .= "<td style='width:2px'>-</td>";
        }
      }
      if (intval($seasonInfo['isnationalteams'])) {
        $html .= "<td style='width:200px'><a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" .
          utf8entities(U_($team['name'])) . "</a></td>";
      } else {
        $html .= "<td style='width:150px'><a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" .
          utf8entities($team['name']) . "</a></td>";
        $html .= "<td style='width:150px'><a href='?view=clubcard&amp;club=" . $team['club'] . "'>" .
          utf8entities($team['clubname']) . "</a></td>";
      }
      if (intval($seasonInfo['isinternational'])) {
        $html .= "<td style='width:150px'>";

        if (!empty($team['flagfile'])) {
          $html .= "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/>&nbsp;";
        }
        if (!empty($team['countryname'])) {
          $html .= "<a href='?view=countrycard&amp;country=" . $team['country'] . "'>" .
            utf8entities(_($team['countryname'])) . "</a>";
        }
        $html .= "</td>";
      }

      $html .= "<td class='right' style='white-space: nowrap;width:15%'>\n";
      if ($isstatdata) {
        $html .= "<a href='?view=playerlist&amp;team=" . $team['team_id'] . "'>" . _("Roster") . "</a>";
        $html .= "&nbsp;&nbsp;";
      }
      $html .= "<a href='?view=scorestatus&amp;team=" . $team['team_id'] . "'>" . _("Scoreboard") . "</a>";

      $html .= "&nbsp;&nbsp;";
      $html .= "<a href='?view=games&amp;team=" . $team['team_id'] . "&amp;singleview=1'>" . _("Games") . "</a>";
      $html .= "</td>";
      $html .= "</tr>\n";
    }
    $html .= "</table>\n";
  }
} elseif ($list == "bypool") {

  foreach ($series as $row) {
    $html .= "<h2>" . utf8entities(U_($row['name'])) . "</h2>\n";

    $pools = SeriesPools($row['series_id'], true);
    if (!count($pools)) {
      $html .= "<p>" . _("Pools not yet created") . "</p>";
      continue;
    }
    foreach ($pools as $pool) {
      $html .= "<table border='0' cellspacing='0' cellpadding='2' width='100%'>\n";
      $html .= "<tr>";
      $html .= "<th colspan='" . ($cols - 1) . "'>" .
        utf8entities(U_(PoolSeriesName($pool['pool_id'])) . ", " . U_($pool['name'])) . "</th><th class='right'>" .
        _("Scoreboard") . "</th>\n";
      $html .= "</tr>\n";
      if ($pool['type'] == 2) {
        // find out sub pools
        $pools = array();
        $pools[] = $pool['pool_id'];
        $followers = PoolFollowersArray($pool['pool_id']);
        $pools = array_merge($pools, $followers);
        $playoffpools = implode(",", $pools);
      }
      $teams = PoolTeams($pool['pool_id']);

      foreach ($teams as $team) {
        $html .= "<tr>";
        if (intval($seasonInfo['isnationalteams'])) {
          $html .= "<td style='width:150px'><a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" .
            utf8entities(U_($team['name'])) . "</a></td>";
        } else {
          $html .= "<td style='width:150px'><a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" .
            utf8entities($team['name']) . "</a></td>";
          $html .= "<td style='width:150px'><a href='?view=clubcard&amp;club=" . $team['club'] . "'>" .
            utf8entities($team['clubname']) . "</a></td>";
        }
        if (intval($seasonInfo['isinternational'])) {
          $html .= "<td style='width:150px'>";
          if (!empty($team['flagfile'])) {
            $html .= "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/>&nbsp;";
          }
          if (!empty($team['countryname'])) {
            $html .= "<a href='?view=countrycard&amp;country=" . $team['country'] . "'>" .
              utf8entities(_($team['countryname'])) . "</a>";
          }
          $html .= "</td>";
        }

        $html .= "<td class='right' style='white-space: nowrap;width:15%'>\n";
        $html .= "<a href='?view=games&amp;team=" . $team['team_id'] . "&amp;singleview=1'>" . _("Games") . "</a>";
        $html .= "&nbsp;&nbsp;";

        if ($pool['type'] == 2) {
          $html .= "<a href='?view=scorestatus&amp;team=" . $team['team_id'] . "&amp;pools=" . $playoffpools . "'>" .
            _("Pool") . "</a>";
        } else {
          $html .= "<a href='?view=scorestatus&amp;team=" . $team['team_id'] . "&amp;pool=" . $pool['pool_id'] . "'>" .
            _("Pool") . "</a>";
        }
        $html .= "&nbsp;&nbsp;";

        $html .= "<a href='?view=scorestatus&amp;team=" . $team['team_id'] . "'>" . _("Division") . "</a></td>";
        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
    }
  }
} elseif ($list == "bystandings") {
  $htmlseries = array();
  $maxplacements = 0;
  
  function getName($teamInfo) {
    return !empty($teamInfo['abbreviation']) ? $teamInfo['abbreviation'] : $teamInfo['name'];
  }
  

  $series = SeasonSeries($seasonInfo['season_id'], true);
  foreach ($series as $ser) {
    $htmlteams = array();
    $teams = SeriesRanking($ser['series_id']);
    foreach ($teams as $team) {
      if (empty($team))
        $htmlteams[] = "<div class='bys_td'>?</div>";
      else if ($team['valid'] != 2) {
        if ($team) {
          if ($team['placement'] <= 3)
            $htmltmp = "<div class='bys_td rankingtop'>";
          else
            $htmltmp = "<div class='bys_td ranking'>";

          $htmltmp .= ordinal($team['placement']) . " ";
          if (intval($seasonInfo['isinternational'])) {
            $htmltmp .= "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
          }
          $htmltmp .= "<a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" . utf8entities(getName($team)) .
            "</a>";
          $htmltmp .= "</div>";
          $htmlteams[] = $htmltmp;
        }
      }
    }
    $htmlseries[] = $htmlteams;
  }

  function get_ordering($series, $index) {
    $o = $series[$index]['ordering'];
    if (empty($o)) {
      return "";
    }
    return $o[0];
  }

  $batches = array();
  $prev_ordering = "";

  if (count($series) <= 4) {
    $batches[] = 0;
  } else {
    for ($series_index = 0; $series_index < count($series); $series_index++) {
      if (get_ordering($series, $series_index) != $prev_ordering) {
        $batches[] = $series_index;
        $prev_ordering = get_ordering($series, $series_index);
      }
    }
  }
  $batches[] = count($series);

  for ($c = 0; $c < count($batches) - 1; $c++) {
    $html .= "<div class='bystandings'>\n";
    
    $maxplacements = 3;
    for ($series_index = $batches[$c]; $series_index < $batches[$c + 1]; $series_index++) {
      $maxplacements = max(count(SeriesTeams($ser['series_id'])), $maxplacements);
    }
    $maxplacements = 3;

    // $html .= "<div class='bys_row ranking'>";

    for ($series_index = $batches[$c]; $series_index < $batches[$c + 1]; $series_index++) {
      $ser = $series[$series_index];
      $html .= "<div class='bys_col'>";
      $html .= "<div class='th bys_th'><a href='?view=seriesstatus&amp;series=" . $ser['series_id'] . "'>" .
        utf8entities(U_($ser['name'])) . "</a></div>\n";
      for ($pos = 0; $pos < max(count(SeriesTeams($ser['series_id'])), $maxplacements); $pos++) {
        if (!empty($htmlseries[$series_index][$pos])) {
          $html .= $htmlseries[$series_index][$pos];
        } else {
          $html .= "<div class='bys_td'>&nbsp;</div>";
        }
      }
      $html .= "</div>\n";
    }
    // $html .= "</div><br />\n";
    $html .= "</div>\n";
  }
}

showPage($title, $html);

?>
