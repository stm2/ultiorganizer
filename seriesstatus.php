<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$title = _("Statistics") . " ";
$viewUrl = "?view=seriesstatus";
$sort = "ranking";
$html = "";

if (iget("series")) {
  $seriesId = iget('series');
  $seriesinfo = SeriesInfo(iget("series"));
  $viewUrl .= "&amp;series=$seriesId";
  $seasoninfo = SeasonInfo($seriesinfo['season']);
  $title .= U_($seriesinfo['name']);
} else {
  showPage($title, "<p>" . utf8entities(_("missing series parameter")) . "</p>\n");
  exit();
}

if (iget("sort")) {
  $sort = iget("sort");
}

$tabs = array( //
_("Statistics") => MakeUrl($_GET), //
_("Games") => MakeUrl(array('view' => 'games', 'series' => $seriesId)), //
  _("Show all pools") => MakeUrl(array('view' => 'poolstatus', 'series' => $seriesId)));

foreach (SeriesPools($seriesId, true) as $pool) {
  if (!isset($tabs[$pool['name']]))
    $tabs[$pool['name']] = array();
  $tabs[$pool['name']][] = MakeUrl(array('view' => 'poolstatus', 'pool' => $pool['pool_id']));
}

$html .= pageMenu($tabs, MakeUrl($_GET), false);

function numf($num, $acc) {
  if ($num == intval($num))
    return sprintf("%d", $num);
  return sprintf("%.{$acc}f", $num);
}

$columns = array();

function add_column(&$columns, $abbr, $name, $prec = null, $acc = null, $classes = 'center') {
  $columns[$abbr] = array('name' => $name, 'classes' => $classes, 'prec' => $prec, 'acc' => $acc);
}

$allteams = array();
$teams = SeriesTeams($seriesId);
$spiritAvg = SeriesSpiritBoard($seriesId);

$games = series_games($seriesId);
$powerRanking = PowerRanking($teams, $games);

$rankedteams = SeriesRanking($seriesId);

$elos = Elos($teams, $games);
$glickos = Glickos($teams, $games);

add_column($columns, 'fname', _("Team"));
add_column($columns, 'seed', _("Seeding"));
add_column($columns, 'seed_acc', _("Seeding Acc"), 2, 'avg');
add_column($columns, 'ranking', _("Ranking"));
add_column($columns, 'ranking_acc', _("Ranking Acc"), 2, 'avg');
add_column($columns, 'games', _("Games"), 0, 'avg');
add_column($columns, 'wins', _("Wins"), 0, 'avg');
add_column($columns, 'losses', _("Losses"), 0, 'avg');
add_column($columns, 'for', _("Goals for"), 0, 'avg');
add_column($columns, 'against', _("Goals against"), 0, 'avg');
add_column($columns, 'diff', _("Goals diff"), 0, 'avg');
add_column($columns, 'winavg', _("Win-%"));
add_column($columns, 'pwr', _("PwrR"), 2, 'avg');
add_column($columns, 'pwr_acc', _("PwrR") . " " . _("acc"), 2, 'avg');

$active_ratings = ['ELOp1', 'ELOpX', 'ELOpp1', 'ELOppX', 'ELOX', 'ELOdX'];
foreach ($elos as $name => $ratings) {
  if (in_array($name, $active_ratings)) {
    add_column($columns, $name, _($name), 0, 'avg');
    add_column($columns, $name . "_acc", _($name) . " " . _("acc"), 2, 'avg');
  }
}

add_column($columns, 'glicko2', _("Glicko2"), 0, 'avg');
add_column($columns, 'gRD', _("RD"), 2, 'avg');
add_column($columns, 'glicko2_acc', _("Glicko2") . " " . _("acc"), 2, 'avg');
// add_column($columns, 'gS', _("sig"), 2, 'avg');

if ($seasoninfo['spiritmode'] > 0 && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))) {
  add_column($columns, 'spirit', _("Spirit Points"));
}

function predictionAccuracies($rating, $teams, $games, $debug = false) {
  return ScorePredictionAccuracy($rating, $teams, $games, $debug);
}

$accuracies['pwr'] = predictionAccuracies($powerRanking, $teams, $games, true);
foreach ($elos as $name => $r) {
  $accuracies[$name] = predictionAccuracies($r, $teams, $games);
}
$glickoRanking = [];
foreach ($teams as $team) {
  $teamId = $team['team_id'];
  $glickoRanking[$teamId] = $glickos[$teamId]['rating'];
}
$accuracies['glicko2'] = predictionAccuracies($glickoRanking, $teams, $games);
foreach ($teams as $team) {
  $teamId = $team['team_id'];
  $stats = TeamStats($teamId);
  $points = TeamPoints($teamId);

  $teamstats = array();
  $teamstats['name'] = $team['name'];

  $flag = "";
  if (intval($seasoninfo['isinternational'])) {
    $flag = "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
  }
  $teamstats['fname'] = "$flag<a href='?view=teamcard&amp;team=" . $teamId . "'>" . utf8entities(U_($team['name'])) .
    "</a>";

  $teamstats['team_id'] = $teamId;
  $teamstats['seed'] = $team['rank'] . ".";
  $teamstats['flagfile'] = $team['flagfile'];

  $teamstats['wins'] = $stats['wins'];
  $teamstats['games'] = $stats['games'];

  $teamstats['for'] = $points['scores'];
  $teamstats['against'] = $points['against'];

  $teamstats['losses'] = $teamstats['games'] - $teamstats['wins'];
  $teamstats['diff'] = $teamstats['for'] - $teamstats['against'];

  $teamstats['spirit'] = isset($spiritAvg[$teamId]) ? $spiritAvg[$teamId]['total'] : "-";

  $teamstats['winavg'] = numf(SafeDivide(intval($stats['wins']), intval($stats['games'])) * 100, 1) . "%";

  $teamstats['pwr'] = $powerRanking[$teamId];
  $teamstats['pwr_acc'] = $accuracies['pwr'][$teamId];

  foreach ($elos as $name => $r) {
    $teamstats[$name] = intval($r[$teamId]);
    $teamstats[$name . "_acc"] = $accuracies[$name][$teamId]; // predictionAccuracies($r, $teamId, $games);
  }

  $teamstats['glicko2'] = $glickos[$teamId]['rating'];
  $teamstats['glicko2_acc'] = $accuracies['glicko2'][$teamId];
  $teamstats['gRD'] = $glickos[$teamId]['rd'];
  $teamstats['gS'] = $glickos[$teamId]['sigma'];

  $teamstats['ranking'] = 0;
  $allteams[] = $teamstats;
}

$rankedteams = SeriesRanking($seriesId);

foreach ($rankedteams as $rteam) {
  if ($rteam) {
    foreach ($allteams as &$ateam) {
      if ($ateam['team_id'] == $rteam['team_id']) {
        $ateam['ranking'] = $rteam['placement'];
      }
    }
  }
}
unset($ateam);

$seedRanking = [];
$rankRanking = [];
foreach ($allteams as &$ateam) {
  $rank = $ateam['ranking'] ?? null;
  if ($rank === null)
    $rank = "-";
  else
    $rank = intval($rank);
  $ateam['ranking'] = $rank;
  $rankRanking[$ateam['team_id']] = count($allteams) - $rank;
  $seedRanking[$ateam['team_id']] = count($allteams) - $ateam['seed'];
}
unset($ateam);

$accuracies['seed'] = predictionAccuracies($seedRanking, $teams, $games);
$accuracies['ranking'] = predictionAccuracies($rankRanking, $teams, $games);

foreach ($allteams as &$ateam) {
  $teamId = $ateam['team_id'];
  $ateam['seed_acc'] = $accuracies['seed'][$teamId];
  $ateam['ranking_acc'] = $accuracies['ranking'][$teamId];
}
unset($ateam);

$html .= CommentHTML(2, $seriesId);

$html .= "<h2>" . _("Division statistics:") . " " . utf8entities($seriesinfo['name']) . "</h2>";

$html .= "<table border='1' style='width:100%'>\n";
$html .= "<tr>";

if ($sort == "ranking") {
  mergesort($allteams, uo_create_key_comparator($sort, true, true));
} else if ($sort == "name" || $sort == "pool" || $sort == "against" || $sort == "seed") {
  mergesort($allteams, uo_create_key_comparator($sort, true, false));
} else {
  mergesort($allteams, uo_create_key_comparator($sort, false, false));
}

foreach ($columns as $key => $col) {
  if ($sort == $key) {
    $html .= "<th style='width:180px'>" . utf8entities($col['name']) . "</th>";
  } else {
    $html .= "<th style='width:180px'><a class='thlink' href='" . $viewUrl . "&amp;Sort=$key'>" . $col['name'] .
      "</a></th>";
  }
}

$html .= "</tr>\n";

$sums = [];
foreach ($allteams as $stats) {
  $html .= "<tr>";

  foreach ($columns as $key => $col) {
    $classes = $col['classes'];
    if ($sort == $key) {
      $classes .= " highlight";
    }

    if ($col['prec'] !== null) {
      $val = numf($stats[$key], $col['prec']);
    } else {
      $val = $stats[$key];
    }
    $html .= "<td class='$classes'>" . $val . "</td>";
    if ($col['acc'] == 'sum' || $col['acc'] == 'avg') {
      if (!isset($sums[$key]))
        $sums[$key] = 0;
      $sums[$key] += $stats[$key];
    }
  }

  $html .= "</tr>\n";
}

$html .= "<tr>";
foreach ($columns as $key => $col) {
  if ($key == 'fname')
    $html .= "<th>" . _("Accumulated") . "</th>";
  else {
    $cont = "";
    if ($col['acc'] == 'sum') {
      $cont = $sums[$key];
    }
    if ($col['acc'] == 'avg') {
      $cont = $sums[$key] / count($teams);
    }
    if (is_numeric($cont))
      $cont = numf($cont, $col['prec'] + 1);

    $classes = $col['classes'];
    $html .= "<th class='$classes'>" . utf8entities($cont) . "</th>";
  }
}

$html .= "</table>\n";

$html .= "<p><a href='?view=poolstatus&amp;series=$seriesId'>" . _("Show all pools") . "</a></p>\n";

$scores = SeriesScoreBoard($seriesId, "total", 10);

if (mysqli_num_rows($scores) > 0) {

  $html .= "<h2>" . _("Scoreboard leaders") . "</h2>\n";
  $html .= "<table cellspacing='0' border='0' width='100%'>\n";
  $html .= "<tr>
<th style='width:200px'>" . _("Player") . "</th>
<th style='width:200px'>" . _("Team") . "</th>
<th class='center'>" . _("Games") . "</th>
<th class='center'>" . _("Assists") . "</th>
<th class='center'>" . _("Goals") . "</th>
<th class='center'>" . _("Tot.") . "</th></tr>\n";

  while ($row = mysqli_fetch_assoc($scores)) {
    $html .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
    $html .= "<td>" . utf8entities($row['teamname']) . "</td>";
    $html .= "<td class='center'>" . intval($row['games']) . "</td>";
    $html .= "<td class='center'>" . intval($row['fedin']) . "</td>";
    $html .= "<td class='center'>" . intval($row['done']) . "</td>";
    $html .= "<td class='center'>" . intval($row['total']) . "</td></tr>\n";
  }

  $html .= "</table>";
  $html .= "<a href='?view=scorestatus&amp;series=$seriesId'>" . _("Scoreboard") . "</a>";
}

if (ShowDefenseStats()) {
  $defenses = SeriesDefenseBoard($seriesId, "deftotal", 10);
  if (mysqli_num_rows($defenses) > 0) {
    $html .= "<h2>" . _("Defenseboard leaders") . "</h2>\n";
    $html .= "<table cellspacing='0' border='0' width='100%'>\n";
    $html .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") .
      "</th><th class='center'>" . _("Games") . "</th>
	<th class='center'>" . _("Total defenses") . "</th></tr>\n";

    while ($row = mysqli_fetch_assoc($defenses)) {
      $html .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
      $html .= "<td>" . utf8entities($row['teamname']) . "</td>";
      $html .= "<td>" . _("Games") . "</td>";
      $html .= "<td class='center'>" . intval($row['games']) . "</td>";
      $html .= "<td class='center'>" . intval($row['deftotal']) . "</td></tr>\n";
    }

    $html .= "</table>";
    $html .= "<a href='?view=defensestatus&amp;series=$seriesId'>" . _("Defenseboard") . "</a>";
  }
}

if ($seasoninfo['showspiritpoints'] && count($spiritAvg) > 0) { // TODO total
  $categories = SpiritCategories($seasoninfo['spiritmode']);
  $html .= "<h2>" . _("Spirit points average per category") . "</h2>\n";

  $html .= "<table cellspacing='0' border='0' width='100%'>\n";
  $html .= "<tr><th style='width:150px'>" . _("Team") . "</th>";
  $html .= "<th>" . _("Games") . "</th>";
  foreach ($categories as $cat) {
    if ($cat['index'] > 0)
      $html .= "<th class='center'>" . _($cat['index']) . "</th>";
  }
  $html .= "<th class='center'>" . _("Tot.") . "</th>";
  $html .= "</tr>\n";

  foreach ($spiritAvg as $teamAvg) {
    $html .= "<td>" . utf8entities($teamAvg['teamname']) . "</td>";
    $html .= "<td>" . $teamAvg['games'] . "</td>";
    foreach ($categories as $cat) {
      if ($cat['index'] > 0) {
        if ($cat['factor'] != 0)
          $html .= "<td class='center'><b>" . numf($teamAvg[$cat['category_id']], 2) . "</b></td>";
        else
          $html .= "<td class='center'>" . numf($teamAvg[$cat['category_id']], 2) . "</td>";
      }
    }
    $html .= "<td class='center'><b>" . numf($teamAvg['total'], 2) . "</b></td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>";

  $html .= "<ul>";
  foreach ($categories as $cat) {
    if ($cat['index'] > 0)
      $html .= "<li>" . $cat['index'] . " " . $cat['text'] . "</li>";
  }
  $html .= "</ul>\n";
}

SetCurrentSeries($seriesId);
showPage($title, $html);

?>
