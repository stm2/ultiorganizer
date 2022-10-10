<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

function compareOptions($t1, $t2) {
  global $columns;
  global $ranker;
  $r1 = getScore($columns, $ranker, $t1['option_id']);
  $r2 = getScore($columns, $ranker, $t2['option_id']);
  if (!is_numeric($r1))
    $r1 = 0;
  if (!is_numeric($r2))
    $r2 = 0;
  return $r2 - $r1 > 0 ? 1 : ($r2 - $r1 < 0 ? -1 : 0);
}

function getScore($columns, $ranker, $optionId) {
  if (isset($columns[$ranker][3][$optionId])) {
    $s = $columns[$ranker][3][$optionId];
    if (gettype($s) == 'double')
      return sprintf("%.4g", $s);
    else
      return $s;
  } else
    return "-";
}

if (empty($_GET['series']) || empty($_GET['poll'])) {
  die(_("Series and poll mandatory"));
}
$seriesId = $_GET['series'];
$pollId = $_GET['poll'];

$poll = PollInfo($pollId);
if ($seriesId != $poll['series_id'])
  die("Invalid poll and series");
$series = SeriesInfo($poll['series_id']);

$title = sprintf(_("Poll Results for %s"), $series['name']);
$html = "";

$hasResults = HasResults($pollId);

if (!($hasResults && IsVisible($pollId)) && !hasEditSeriesRight($seriesId)) {
  $html .= "<h2>$title</h2>";
  $html .= "<p>" . _("No results yet") . "</p>";
} else {

  $options = PollOptions($pollId);

  $sum = PollSumRanking($pollId);
  $avg = PollRangeRanking($pollId);
  $prefs0 = PollFirstPreferenceRanking($pollId);
  // $prefs1 = PollAllPreferences($pollId, 1);
  // $prefs2 = PollAllPreferences($pollId, 2);
  // $prefs3 = PollAllPreferences($pollId, 3);
  $approval = PollApproveRanking($pollId);
  $copeland = PollCopelandRanking($pollId);
  $borda = PollBordaRanking($pollId);
  $borda0 = PollBordaRanking($pollId, true);
  $geo = PollGeometricRanking($pollId);
  $harmo = PollHarmonicRanking($pollId);
  // $plu = PollPluralityRanking($pollId);
  $num = PollVotesRanking($pollId);

  $norm = pollArithmeticRanking($pollId, true);
  $avg2 = pollArithmeticRanking($pollId, true, true, true);

  if (!$hasResults) {
    $html .= "<p>" . _("Results not published yet.") . "</p>";
  }
  $html .= "<h2>$title</h2>\n";
  if (!empty($poll['description'])) {
    $html .= "<div id='poll_description'><p>" . $poll['description'] . "</p></div>";
  }

  $html .= "<p>" . sprintf(_("%d votes cast."), PollVoters($pollId)) . "</p>";

  $columns = array(/**/
    array(_("R"), _("Ranked by %s"), '', null), array(_("Option"), "", '', null),
    array(_("#"), _("Number of votes (0 or not) for this option"), 'score', $num),
    array(_("App"), _("Number of voters with a positive score for this option (approval voting)"), 'score', $approval),
    array(_("P"), _("Number of voters with this option as first preference (plurality voting)"), 'score', $prefs0),
    array(_("Sum"), _("Sum of all scores for this option (score voting)"), 'score', $sum),
    array(_("Norm"), _("Normalized sum (scores between 0 and 1)"), 'score', $norm),
    array(_("Range"), _("Average of all scores (not counting 0s) for this option (range voting)"), 'score', $avg),
    array(_("RangeN"), _("Normalized average"), 'score', $avg2),
    array(_("Cope"), _("Round robin tournament where each result is majority voting (Copeland score)"), 'score',
      $copeland),
    array(_("Borda"), _("Round robin tournament score where each voter represents a full round (Borda score)"), 'score',
      $borda), array(_("Borda0"), _("As Borda, but allowing abstention"), 'score', $borda0),
    array(_("Geometric"), _("As Borda but with geometric point distribution (1, 1/2, 1/3, 1/4 ...)"), 'score', $geo),
    array(_("Harmonic"), _("As Borda but with harmonic point distribution (1, 1/2, 1/4, 1/8 ...)"), 'score', $harmo) //
  /*
   * array(_("Plurality"), _("Plurality voting (first choice)"), 'score', $plu),
   * array(_("P1"), _("Number of voters with this option as first preference"), 'score', $prefs1),
   * array(_("P2"), _("Number of voters with this option as 1st or 2nd preference"), 'score', $prefs2),
   * array(_("P3"), _("Number of voters with this option as 1st to 3rd preference"), 'score', $prefs3)
   */
  );

  $ranker = 9;
  if (!empty($_POST['change'])) {
    $ranker = (int) $_POST["rankby"];
  }

  $html .= "<table class='infotable ranking'><tbody id='ranking'>";

  $html .= "<tr>";
  $c = 0;
  foreach ($columns as $col) {
    if ($c++ === $ranker) {
      $rankname = $col[0];
      $class = $col[2] . " sorter";
    } else {
      $class = $col[2];
    }
    $class = empty($class) ? '' : " class ='" . $class . "'";
    $html .= "<th$class>" . $col[0] . "</th>";
  }
  $html .= "</tr>";

  $maxl = 20;
  $rank = 0;

  mergesort($options, 'compareOptions');

  $optionMap = array();
  foreach ($options as $option) {
    $optionId = $option['option_id'];
    $optionMap[$optionId] = $option;
    $rank++;
    $html .= "<tr class='rank_item' id='rank_item$optionId'>";
    $html .= "<td>$rank. </td>";
    $html .= "<td class='details'><span class='rank_item_name'>" . utf8entities($option['name']) . "</span>";
    $html .= " <span class='rank_item_mentor'>" . utf8entities($option['mentor']) . "</span><br />";

    if (!empty($option['description'])) {
      $html .= "<span class='rank_item_description'>";
      if (strlen($option['description']) > $maxl)
        $html .= utf8entities(substr($option['description'], 0, $maxl)) . "...</span>";
      else
        $html .= utf8entities($option['description']) . "</span>";
    }
    $html .= " <a href='?view=user/addpolloption&series=$seriesId&poll=$pollId&option_id=$optionId&edit=0' rel='noopener' target='_blank'>" .
      _("Details") . "</a>";

    $html .= " </td>\n";
    for ($i = 2; $i < count($columns); ++$i) {
      if ($i === $ranker) {
        $class = 'score sorter';
      } else {
        $class = 'score';
      }
      $html .= "<td class='$class'>" . getScore($columns, $i, $optionId) . "</td>";
    }
    $html .= " </tr>\n";
  }

  $html .= "</tbody></table>";

  $html .= "<form method='post' action='?view=user/pollresult&series=$seriesId&poll=$pollId'>";
  if (hasEditSeriesRight($seriesId)) {
    $html .= "<p><label for=show_votes>" . _("Show votes") . "</label>";
    $html .= "<input class='input' type='checkbox' id='show_votes' name='show_votes' ";
    if (isset($_POST['show_votes'])) {
      $html .= "checked='checked'";
    }
    $html .= "/></p>\n";
  }
  $html .= "<p><label>" . _("Select metric") . ": <select class='dropdown' name='rankby'>\n";
  for ($i = 2; $i < count($columns); ++$i) {
    $selected = $ranker == $i ? "selected='selected'" : "";
    $html .= "<option class='dropdown' $selected value='$i'>" . utf8entities($columns[$i][0]) . "</option>\n";
  }
  $html .= "</select></label>\n";

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

  if (hasEditSeriesRight($seriesId)) {
    if (isset($_POST['show_votes'])) {
      $html .= " <br />";

      $html .= "<h3>" . _("Votes") . "</h3>\n";
      $html .= "<table class='infotable poll_votes'>";
      $html .= "<tr><th>" . _("Name") . "</th><th>" . _("Option") . "</th><th>" . _("Score") . "</th></tr>\n";

      $votes = PollVotes($pollId, array('voter', 'score'));
      foreach ($votes as $vote) {
        $html .= "<tr><td>" . $vote['name'] . "</td><td>" . $optionMap[$vote['option_id']]['name'] . "</td><td>" .
          $vote['score'] . "</td></tr>\n";
      }
      $html .= "</table>\n";
    }
  }
}

showPage($title, $html);
?>
