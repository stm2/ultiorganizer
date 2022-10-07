<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['season'])) {
  die(_("Season mandatory"));
}
$seasonId = $_GET['season'];
$seasonInfo = SeasonInfo($seasonId);
$title = sprintf(_("Polls for %s"), utf8entities($seasonInfo['name']));

$html = "";

$serieses = SeasonSeries($seasonId);

// view=user/polls&season=$seasonId&poll=$pollId&deleteoption=".
if (isset($_GET['deleteoption']) && hasEditSeriesRight($_GET['series'])) {
  $deleted = DeletePollOption($_GET['deleteoption']);
  $html .= "<p>" . _("Poll option has been deleted.") . "</p>";
}



if (!count($serieses)) {
  $html .= "<p>" . _("No divisions.") . "</p>\n";
} else {

  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];
    $html .= "<h2>" . utf8entities(U_($series['name'])) . "</h2>\n";
    $poll = SeriesPoll($seriesId);
    if (empty($poll) || !IsVisible($poll['poll_id'])) {
      $html .= "<p>" . _("No poll") . "</p>";
    } else {
      $pollId = $poll['poll_id'];
      $html .= "<div class='poll_description'><p>" . $poll['description'] . "</p></div>";

      $html .= "<table class='infotable poll_options' style='width:100%' cellpadding='2'><tr><th>" . _("Option") .
        "</th>";
      $html .= "<th>" . _("Mentor") . "</th><th>" . _("Description") . "</th><th>&nbsp;</th><th>&nbsp;</th></tr>\n";
      $options = PollOptions($pollId);
      $maxl = 50;

      foreach ($options as $option) {
        $html .= "<td>" . utf8entities($option['name']) . "</td>";
        $html .= "<td>" . utf8entities($option['mentor']) . "</td>";
        $html .= "<td>";

        if (strlen($option['description']) > $maxl)
          $html .= utf8entities(substr($option['description'], 0, $maxl)) . "...</td>";
        else
          $html .= utf8entities($option['description']) . "</td>";

        $html .= "<td><a href='?view=user/addpolloption&series=$seriesId&poll=$pollId&option_id=" . $option['option_id'] .
          "'>" . _("Details") . "</a>";
        if (hasEditSeriesRight($seriesId)) {
          $html .= "<td><a href='?view=user/polls&season=$seasonId&poll=$pollId&series=$seriesId&deleteoption=" . $option['option_id'] .
            "'" . "onclick='return confirm(\"" . sprintf(_("Do you want to delete %s?"), $option['name']) . "\");'" . // "onclick='return deletePollOption($pollId, " . $option['option_id'] . ");' .
            "/><img src='images/remove.png' class='delete_icon' alt='X' /></a> </td>";
        }

        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
      if ($pollId > 0) {
        if (CanSuggest(null, null, $pollId) || hasEditSeriesRight($seriesId)) {
          $html .= "<p><a href=?view=user/addpolloption&series=$seriesId&poll=$pollId>" . _("Suggest option") .
            "</a></p>\n";
        }
        if (CanVote(null, null, $pollId) || hasEditSeriesRight($seriesId)) {
          $html .= "<p><a href=?view=user/votepoll&series=$seriesId&poll=$pollId>" . _("Vote") . "</a></p>\n";
        }
        if (HasResults($pollId) || hasEditSeriesRight($seriesId)) {
          $html .= "<p><a href='?view=user/pollresult&series=$seriesId&poll=$pollId'>" . _("View results") . "</a></p>";
        }
      }
    }
  }
}

showPage($title, $html);
?>