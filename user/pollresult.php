<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

function compareTeams($t1, $t2) {
  global $columns;
  global $ranker;
  $r1 = getScore($columns, $ranker, $t1['pt_id']);
  $r2 = getScore($columns, $ranker, $t2['pt_id']);
  return $r2 - $r1 > 0 ? 1 : ($r2 - $r1 < 0?-1:0);
}

function getScore($columns, $ranker, $teamId) {
  if (isset($columns[$ranker][3][$teamId])) {
    $s = $columns[$ranker][3][$teamId];
    if (gettype($s) == 'double')
      return sprintf("%.4f", $s);
    else
      return $s;
  } else
    return "-";
}

if (empty($_GET['season']) || empty($_GET['poll'])) {
  die(_("Season and poll mandatory"));
}
$season = $_GET['season'];
$pollId = $_GET['poll'];

$poll = PollInfo($pollId);
$series = SeriesInfo($poll['series_id']);

$title = _("Results") . ": " . utf8entities($series['name']);
$html = "";

if (!HasResults($pollId)) {
  $html .= "<h2>$title</h2>";
  $html .= "<p>" . _("No results yet") . "</p>";
} else {

  $ranker = 2;
  if (!empty($_POST['change'])) {
    $ranker = (int) $_POST["rankby"];
  }

  $teams = PollTeams($pollId);

  $sum = PollSumRanking($pollId);
  $prefs0 = PollFirstPreferenceRanking($pollId);
  $prefs1 = PollAllPreferences($pollId, 1);
  $prefs2 = PollAllPreferences($pollId, 2);
  $prefs3 = PollAllPreferences($pollId, 3);
  $approval = PollApproveRanking($pollId);
  $copeland = PollCopelandRanking($pollId);
  $borda = PollBordaRanking($pollId);
  $geo = PollGeometricRanking($pollId);
  $harmo = PollHarmonicRanking($pollId);
  $plu = PollPluralityRanking($pollId);

  $html .= "<h2>$title</h2>\n";
  if (!empty($poll['description'])) {
    $html .= "<div id='series_description'><p>" . $poll['description'] . "</p></div>";
  }

  $columns = array(array(_("Rank"), _("Ranked by %s"), '', null), array(_("Team"), "", '', null),
    array(_("Sum"), _("Sum of all scores for this teams"), '', $sum),
    array(_("P"), _("Number of voters with this team as first preference"), 'score', $prefs0),
    array(_("P1"), _("Number of voters with this team as first preference"), 'score', $prefs1),
    array(_("P2"), _("Number of voters with this team as 1st or 2nd preference"), 'score', $prefs2),
    array(_("P3"), _("Number of voters with this team as 1st to 3rd preference"), 'score', $prefs3),
    array(_("App"), _("Number of voters with a positive score for this team"), 'score', $approval),
    array(_("Cope"), _("Round robin tournament where each result is majority voting (Copeland score)"), 'score',
      $copeland),
    array(_("Borda"), _("Round robin tournament score where each vote is a game (Borda score)"), 'score', $borda),
    array(_("Geometric"), _("As Borda but with geometric point distribution (1, 1/2, 1/3, 1/4 ...)"), 'score', $geo),
    array(_("Harmonic"), _("As Borda but with harmonic point distribution (1, 1/2, 1/4, 1/8 ...)"), 'score', $harmo),
    array(_("Plurality"), _("Plurality voting (first choice)"), 'score', $plu));

  $html .= "<table class='ranking'><tr><td class='ranking_column'><div class='worklist'><table class='ranking'><tbody id='ranking'>";

  $html .= "<tr>";
  $c = 0;
  foreach ($columns as $col) {
    $class = empty($col[2]) ? '' : " class ='" . $col[2] . "'";
    $html .= "<th$class>" . $col[0] . "</th>";
    if ($c++ === $ranker)
      $rankname = $col[0];
  }
  $html .= "</tr>";

  $maxl = 20;
  $rank = 0;

  mergesort($teams, 'compareTeams');

  foreach ($teams as $team) {
    $teamId = $team['pt_id'];
    $rank++;
    $html .= "<tr class='rank_item' id='rank_item$teamId'>";
    $html .= "<td>$rank. </td>";
    $html .= "<td class='details'><span class='rank_item_name'>" . utf8entities($team['name']) . "</span>";
    $html .= " <span class='rank_item_mentor'>" . utf8entities($team['mentor']) . "</span><br />";

    $html .= "<span class='rank_item_description'>";
    if (strlen($team['description']) > $maxl)
      $html .= utf8entities(substr($team['description'], 0, $maxl)) . "...</span>";
    else
      $html .= utf8entities($team['description']) . "</span>";
    $html .= " <a href='?view=user/addpollteam&pt_id=$teamId' rel='noopener' target='_blank'>" . _("Details") . "</a>";

    $html .= " </td>\n";
    for ($i = 2; $i < count($columns); ++$i) {
      $html .= "<td class='score'>" . getScore($columns, $i, $teamId) . "</td>";
    }
    $html .= " </tr>\n";
  }

  $html .= "</tbody></table>";

  $html .= "<form method='post' action='?view=user/pollresult&season=$season&poll=$pollId'>";
  $html .= "<p><select class='dropdown' name='rankby'>\n";
  for ($i = 2; $i < count($columns); ++$i) {
    $selected = $ranker == $i ? "selected='selected'" : "";
    $html .= "<option class='dropdown' $selected value='$i'>" . utf8entities($columns[$i][0]) . "</option>\n";
  }
  $html .= "</select>\n";
  $html .= "<input class='button' name='change' type='submit' value='" . _("Rank by") . "'/></p>";
  $html .= "</form>";

  $c = 0;
  foreach ($columns as $col) {
    $name = $col[0];
    $description = $col[1];
    if (++$c == 1) {
      $description = sprintf($description, $rankname);
    }
    if (!empty($description))
      $html .= "<p>$name: $description</p>";
  }

  $html .= "</div></td></tr></table>\n";
}

showPage($title, $html);
?>
