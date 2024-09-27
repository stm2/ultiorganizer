<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['season'])) {
  if (empty($_GET['series'])) {
    die(_("Season or division mandatory"));
  }
  $seriesId = $_GET['series'];
  $name = SeriesName($seriesId);
  if (empty($name))
    die(_("Unknown division"));

  $title = sprintf(_("Polls for %s"), $name);
  $serieses = array(array('series_id' => $seriesId, 'name' => $name));
} else {
  $seasonId = $_GET['season'];
  $seasonInfo = SeasonInfo($seasonId);
  $title = sprintf(_("Polls for %s"), $seasonInfo['name']);
  $serieses = SeasonSeries($seasonId);
}
$html = "";

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
    $polls = SeriesPolls($seriesId);
    $pollNum = 0;
    foreach ($polls as $poll) {
      ++$pollNum;
      $pollId = $poll['poll_id'];
      if (!IsVisible($pollId) && !hasEditSeriesRight($seriesId))
        continue;
      if (empty($poll['name'])) {
        $html .= "<h3>" . sprintf(_("Poll %d"), $pollNum) . "</h3>";
      } else {
        $html .= "<h3>" . utf8entities($poll['name']) . "</h3>";
      }

      if (!empty($poll['description']))
        $html .= "<div class='poll_description'><p>" . $poll['description'] . "</p></div>";

      $options = PollOptions($pollId);
      if (count($options) == 0) {
        $html .= "<p>" . _("No options have been defined, yet.") . "</p>";
      } else {
        $html .= "<table class='infotable poll_options' style='width:100%' cellpadding='2'><tr><th>" . _("Option") .
          "</th>";
        $html .= "<th>" . _("Mentor") . "</th><th>" . _("Description") . "</th><th>" . _("Op") . "</th></tr>\n";
        $maxl = 50;

        foreach ($options as $option) {
          $html .= "<td>" . utf8entities($option['name']) . "</td>";
          $html .= "<td>" . utf8entities($option['mentor']) . "</td>";
          $html .= "<td>";

          if (strlen($option['description']) > $maxl)
            $html .= utf8entities(mb_strcut($option['description'], 0, $maxl)) . "...</td>";
          else
            $html .= utf8entities($option['description']) . "</td>";

          $html .= "<td><a href='?view=user/addpolloption&amp;series=$seriesId&amp;poll=$pollId&amp;option_id=" .
            $option['option_id'] . "'><img class='deletebutton' src='images/settings.png' alt='E' title='" .
            _("Details") . "'/></a>";
          if (hasEditSeriesRight($seriesId)) {
            $html .= "&nbsp;<a href='?view=user/polls&amp;poll=$pollId&amp;series=$seriesId&amp;deleteoption=" . $option['option_id'] .
              "' onclick='return confirm(\"" . utf8entities(sprintf(_("Do you want to delete %s?"), $option['name'])) .
              "\");'" . // "onclick='return deletePollOption($pollId, " . $option['option_id'] . ");' .
              "/><img src='images/remove.png' class='deletebutton' alt='X' /></a>";
          }

          $html .= " </td></tr>\n";
        }
        $html .= "</table>\n";
      }
      if ($pollId > 0) {
        $html .= "<br />";
        $open = false;
        if (CanSuggest(null, null, $pollId) || hasEditSeriesRight($seriesId)) {
          $open = true;
          $heading = _("Suggest option");
          $html .= "<p><a href='?view=user/addpolloption&amp;series=$seriesId&amp;poll=$pollId'>" .
            "<img src='images/options.png' alt='$heading'/>&ensp;$heading</a>\n";
        }
        if (count($options) > 0) {
          if (CanVote(null, null, $pollId) || hasEditSeriesRight($seriesId)) {
            if (!$open)
              $html .= "<p>";
            $open = true;
            $heading = _("Vote");
            $html .= "&emsp;<a href='?view=user/votepoll&amp;series=$seriesId&amp;poll=$pollId'>" .
              "<img src='images/vote.png' alt='$heading'/>&ensp;$heading</a>\n";
          }
          if (HasResults($pollId) || hasEditSeriesRight($seriesId)) {
            if (!$open)
              $html .= "<p>";
            $open = true;
            $heading = _("View Results");
            $html .= "&emsp;<a href='?view=user/pollresult&amp;series=$seriesId&amp;poll=$pollId'>" .
              "<img src='images/result.png' alt='$heading'/>&ensp;$heading</a>";
          }
        }
        if ($open)
          $html .= "</p>\n";
      }
      $html .= "<br />";
    }
  }
}

showPage($title, $html);
?>