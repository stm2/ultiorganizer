<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['season'])) {
  die(_("Season mandatory"));
}
$season = $_GET['season'];
$seasonInfo = SeasonInfo($season);
$title = _("Team polls") . ": " . utf8entities($seasonInfo['name']);

$html = "";

$serieses = SeasonSeries($season);

if (!count($serieses)) {
  $html .= "<p>" . _("No divisions.") . "</p>\n";
} else {

  foreach ($serieses as $series) {
    $html .= "<h2>" . utf8entities(U_($series['name'])) . "</h2>\n";
    $poll = SeriesPoll($series['series_id']);
    if (empty($poll)) {
      $html .= "<p>" . _("No poll") . "</p>";
    } else {
      $pollId = $poll['poll_id'];
      $html .= "<div id='series_description'><p>" . $poll['description'] . "</p></div>";

      $html .= "<table class='yui-skin-sam' style='width:100%' cellpadding='2'><tr><th>" . _("Team") . "</th>";
      $html .= "<th>" . _("Mentor") . "</th><th>" . _("Description") . "</th><th>" . _("State") .
        "</th><th>&nbsp;</th><th>&nbsp;</th></tr>\n";
      $teams = PollTeams($pollId);
      $maxl = 20;

      foreach ($teams as $team) {
        $html .= "<td>" . utf8entities($team['name']) . "</td>";
        $html .= "<td>" . utf8entities($team['mentor']) . "</td>";
        $html .= "<td>";

        if (strlen($team['description']) > $maxl)
          $html .= utf8entities(substr($team['description'], 0, $maxl)) . "...</td>";
        else
          $html .= utf8entities($team['description']) . "</td>";

        $html .= "<td>" . $team['status'] . "</td>";

        $html .= "<td><a href='?view=user/addpollteam&pt_id=" . $team['pt_id'] . "'>" . _("Details") . "</a>";
        if (hasEditSeasonSeriesRight($season)) {
          $html .= "<td><input class='button' type='image' name='rempoll' src='images/remove.png' value='X' alt='X' onclick='setId(" .
            $team['pt_id'] . ", \"deletePollId" . $team['pt_id'] . "\");'/></td>";
        }

        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
      if ($pollId > 0) {
        $html .= "<p><a href=?view=user/addpollteam&season=$season&poll=$pollId>" . _("Suggest team") . "</a></p>\n";
        $html .= "<p><a href=?view=user/votepoll&season=$season&poll=$pollId>" . _("Vote") . "</a></p>\n";
        $html .= "<p><a href=?view=user/pollresult&season=$season&poll=$pollId>" . _("Results") . "</a></p>\n";
      }
    }
  }
}

showPage($title, $html);
?>