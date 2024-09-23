<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';
include_once 'lib/spirit.functions.php';

$title = _("Statistics") . " ";
$viewUrl = "?view=seriesstatus";
$sort = "ranking";
$html = "";

$stdElo = defined('STANDINGS_ELO');
$stdAcc = defined('STANDINGS_ACC');

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

if (!$seriesinfo['valid'] && !hasEditSeriesRight($seriesId)) {
  showPage($title, "<p>" . utf8entities(_("Insufficient rights.")) . "</p>\n");
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

$columns = array();

function add_column(&$columns, $abbr, $name, $mnem = null, $prec = null, $acc = null, $classes = 'center') {
  if (intval($prec) !== $prec && $prec !== null)
    die("invalid prec $abbr $prec");
  $columns[$abbr] = array('name' => $name, 'mnem' => $mnem == null ? $name : $mnem, 'classes' => $classes,
    'prec' => $prec, 'acc' => $acc);
}

$allteams = array();
$teams = SeriesTeams($seriesId);
$spiritAvg = SeriesSpiritBoard($seriesId);

$games = series_games($seriesId);
$powerRanking = PowerRanking($teams, $games);
$winPowerRanking = PowerRanking($teams, $games, 1000);

$rankedteams = SeriesRanking($seriesId);

$elos = [];
$glickos = [];
if ($stdElo) {
  $elos = Elos($teams, $games);
  $glickos = Glickos($teams, $games);
}

add_column($columns, 'fname', _("Team"), null, null, null, 'left');
add_column($columns, 'seed', _("Initial Seeding"), pgettext("team_table", "Sd"));

if ($stdElo || $stdAcc)
  add_column($columns, 'seed_acc', _("Seeding Accuracy"), pgettext("team_table", "Sd*"), 2, 'avg');

  add_column($columns, 'ranking', _("Final Ranking"), pgettext("team_table", "Rnk"));

if ($stdElo || $stdAcc)
  add_column($columns, 'ranking_acc', _("Ranking Accuracy"), pgettext("team_table", "Rnk*"), 2, 'avg');

add_column($columns, 'games', _("Games"), pgettext("team_table", "G"), 0, 'avg');
add_column($columns, 'wins', _("Wins"), pgettext("team_table", "W"), 0, 'avg');
add_column($columns, 'losses', _("Losses"), pgettext("team_table", "L"), 0, 'avg');
add_column($columns, 'for', _("Goals for"), pgettext("team_table", "Pts"), 0, 'avg');
add_column($columns, 'against', _("Goals against"), pgettext("team_table", "oPts"), 0, 'avg');
add_column($columns, 'diff', _("Goals diff"), pgettext("team_table", "diff"), 0, 'avg');
add_column($columns, 'winavg', pgettext("team_table", "Win-%"));
add_column($columns, 'pwr', _("Power Ranking"), pgettext("team_table", "PwrR"), 2, 'avg');

if ($stdElo || $stdAcc)
  add_column($columns, 'pwr_acc', _("Power ranking accuracy"), pgettext("team_table", "PwR*"), 2, 'avg');

if ($stdElo)
  add_column($columns, 'wpwr', _("Win/Loss Power Ranking"), pgettext("team_table", "wPwR"), 2, 'avg');
if ($stdElo && $stdAcc)
  add_column($columns, 'wpwr_acc', _("Win/Loss PwR accuracy"), pgettext("team_table", "wPwR*"), 2, 'avg');

if ($stdElo) {
  $active_ratings = ['ELOp1', 'ELOpX', 'ELOpp1', 'ELOppX', 'ELOX', 'ELOdX'];
  foreach ($elos as $name => $ratings) {
    if (in_array($name, $active_ratings)) {
      // FIXME will not be extracted by gettext
      add_column($columns, $name, pgettext("team_table", $name), null, 0, 'avg');
      add_column($columns, $name . "_acc", pgettext("team_table", $name . " acc"), null, 2, 'avg');
    }
  }

  add_column($columns, 'glicko2', _("Glicko2"), null, 0, 'avg');
  add_column($columns, 'gRD', pgettext("team_table", "RD"), null, 2, 'avg');
  add_column($columns, 'glicko2_acc', pgettext("team table", "Glicko2 acc"), null, 2, 'avg');
  // add_column($columns, 'gS', _("sig"), 2, 'avg');
}
if (GetSeriesSpiritMode($seriesId) > 0 || hasEditSeriesRight($seriesId)) {
  add_column($columns, 'spirit', _("Spirit Points"), pgettext("team_table", "Spir"), 2);
}

function predictionAccuracies($rating, $teams, $games, $debug = false) {
  return ScorePredictionAccuracy($rating, $teams, $games, $debug);
}

$accuracies = [];
$accuracies['pwr'] = predictionAccuracies($powerRanking, $teams, $games);
$accuracies['wpwr'] = predictionAccuracies($winPowerRanking, $teams, $games);
if ($stdElo) {
  foreach ($elos as $name => $r) {
    $accuracies[$name] = predictionAccuracies($r, $teams, $games);
  }
  $glickoRanking = [];
  foreach ($teams as $team) {
    $teamId = $team['team_id'];
    $glickoRanking[$teamId] = $glickos[$teamId]['rating'];
  }
  $accuracies['glicko2'] = predictionAccuracies($glickoRanking, $teams, $games);
}

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
  $teamstats['wpwr'] = $winPowerRanking[$teamId];
  $teamstats['pwr_acc'] = $accuracies['pwr'][$teamId];
  $teamstats['wpwr_acc'] = $accuracies['wpwr'][$teamId];

  foreach ($elos as $name => $r) {
    $teamstats[$name] = intval($r[$teamId]);
    $teamstats[$name . "_acc"] = $accuracies[$name][$teamId]; // predictionAccuracies($r, $teamId, $games);
  }

  if ($stdElo) {
    $teamstats['glicko2'] = $glickos[$teamId]['rating'];
    $teamstats['glicko2_acc'] = $accuracies['glicko2'][$teamId];
    $teamstats['gRD'] = $glickos[$teamId]['rd'];
    $teamstats['gS'] = $glickos[$teamId]['sigma'];
  }

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

$html .= "<table class='infotable' border='1' style='width:100%'>\n";
$html .= "<tr>";

if ($sort == "ranking") {
  mergesort($allteams, uo_create_key_comparator($sort, true, true));
} else if ($sort == "name" || $sort == "pool" || $sort == "against" || $sort == "seed") {
  mergesort($allteams, uo_create_key_comparator($sort, true, false));
} else {
  mergesort($allteams, uo_create_key_comparator($sort, false, false));
}

$c = 0;
foreach ($columns as $key => $col) {
  if (++$c == 1)
    $sty = " style='text-align: left; min-width:10rem;'";
  else
    $sty = " style='min-width:1rem'";
  if ($sort == $key) {
    $html .= "<th$sty>" . utf8entities($col['mnem']) . "</th>";
  } else {
    $html .= "<th$sty><a class='thlink' href='" . $viewUrl . "&amp;Sort=$key'>" . $col['mnem'] . "</a></th>";
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

$legend = "";
foreach ($columns as $key => $col) {
  if ($col['name'] != $col['mnem']) {
    if (!empty($legend))
      $legend .= "<br />";
    $legend .= "<em>" . $col['mnem'] . ":</em> " . $col['name'];
  }
}
if (!empty($legend))
  $html .= "<p>$legend</p>\n";

$html .= "<p><a href='?view=poolstatus&amp;series=$seriesId'>" . _("Show all pools") . "</a></p>\n";

$scores = SeriesScoreBoard($seriesId, "total", 10);

if (mysqli_num_rows($scores) > 0) {

  $html .= "<h2>" . _("Scoreboard leaders") . "</h2>\n";
  $html .= "<table class='infotable' cellspacing='0' border='0' width='100%'>\n";
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
    $html .= "<table class='infotable' cellspacing='0' border='0' width='100%'>\n";
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

if (GetSeriesSpiritMode($seriesId) > 0 && count($spiritAvg) > 0) { // TODO total
  $categories = SpiritCategories(GetSeriesSpiritMode($seriesId));
  $html .= "<h2>" . _("Spirit points average per category") . "</h2>\n";

  $mnem = [];
  foreach ($categories as &$cat) {
    addMnemonic($cat, $cat['text'], $mnem, 2);
  }
  unset($cat);

  $html .= "<table class='infotable' cellspacing='0' border='0' >\n";
  $html .= "<tr><th style='width:150px'>" . _("Team") . "</th>";
  $html .= "<th>" . _("Games") . "</th>";
  foreach ($categories as $cat) {
    if ($cat['index'] > 0 && $cat['type'] == 1)
      $html .= "<th class='center'>" . _($cat['mnem']) . "</th>";
  }
  $html .= "<th class='center'>" . pgettext("spirit_table", "Tot.") . "</th>";
  $html .= "</tr>\n";

  $aggr = ['total' => ['sum' => 0, 'n' => 0]];
  foreach ($spiritAvg as $teamAvg) {
    $html .= "<td><a href='?view=teamcard&amp;team={$teamAvg['team_id']}&series=$seriesId'>" .
      utf8entities($teamAvg['teamname']) . "</a></td>";
    $html .= "<td>" . $teamAvg['games'] . "</td>";
    foreach ($categories as $cat) {
      if ($cat['index'] > 0 && $cat['type'] == 1) {
        $val = $teamAvg[$cat['category_id']];
        if ($cat['factor'] != 0)
          $html .= "<td class='center'><b>" . numf($val, 2) . "</b></td>";
        else
          $html .= "<td class='center'>" . numf($val, 2) . "</td>";
        if (!isset($aggr[$cat['index']])) {
          $aggr[$cat['index']] = ['sum' => 0, 'n' => 0];
        }
        $aggr[$cat['index']]['sum'] += $val;
        ++$aggr[$cat['index']]['n'];
      }
    }
    $html .= "<td class='center'><b>" . numf($teamAvg['total'], 2) . "</b></td>";
    $html .= "</tr>\n";
    $aggr['total']['sum'] += $teamAvg['total'];
    ++$aggr['total']['n'];
  }
  $html .= "<tr><td>". _ ("Average") . "</td><td></td>";
  foreach ($categories as $cat) {
    if ($cat['index'] > 0 && $cat['type'] == 1) {
      if (isset($aggr[$cat['index']])) {
      $val = numf($aggr[$cat['index']]['sum'] / max(1, $aggr[$cat['index']]['n']), 2);
      $html .= "<td>$val</td>";
    } else {
      $html .= "<td></td>";
    }
    }
  }
  $val = numf($aggr['total']['sum'] / max(1, $aggr['total']['n']), 2);
  $html .= "<td>$val</td>";
  $html .= "</tr>\n";
  $html .= "</table>";

  $html .= "<p>";
  foreach ($categories as $cat) {
    if ($cat['type'] == 1) {
      $html .= "<em>" . $cat['mnem'] . ":</em> " . U_($cat['text']) . "<br />\n";
    }
  }
  $html .= "</p>\n";
}

$games = TimetableGames($seriesId, 'series', 'all', 'series');

if ($stdElo || $stdAcc) {
  $html .= "<h2>" . utf8entities(_("Game result accuracies according to PwR")) . "</h2>";
  $html .= "<table class='infotable wide'>\n";
  while ($game = mysqli_fetch_assoc($games)) {
    if (empty($game['hometeam']) || empty($game['hometeam']) || !$game['hasstarted']) {
      $extra = '-';
    } else {
      $diff = $powerRanking[$game['hometeam']] - $powerRanking[$game['visitorteam']];
      $delta = numf(($game['homescore'] - $game['visitorscore']) - $diff, 1);
      $extra = ($delta > 0 ? "+" : "") . $delta;
    }
    $html .= GameRow($game, true, true, false, false, true, false, false, false, false, $extra);
  }
  $html .= "</table>";
}

SetCurrentSeries($seriesId);
showPage($title, $html);

?>
