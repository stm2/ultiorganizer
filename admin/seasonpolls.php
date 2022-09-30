<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['season'])) {
  die(_("Season mandatory"));
}
$season = $_GET['season'];
$seasonInfo = SeasonInfo($season);
$title = _("Manage team polls") . ": " . utf8entities($seasonInfo['name']);
$html = "";

$serieses = SeasonSeries($season);

if (!empty($_POST['save'])) {
  // $html .= print_r($_POST, true);
  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];
    $poll = SeriesPoll($seriesId);
    if (empty($poll)) {
      $poll = emptyPoll($seriesId);
    }

    $poll['status'] = isset($_POST["status$seriesId"]) ? $_POST["status$seriesId"] : 0;
    $poll['password'] = !empty($_POST["password$seriesId"]) ? $_POST["password$seriesId"] : NULL;
    $poll['description'] = !empty($_POST["description$seriesId"]) ? $_POST["description$seriesId"] : NULL;

    // $html .= "<br />" .print_r($poll, true);
    
    if ($poll['poll_id'] == -1) {
      // $html .= "add " . $poll['poll_id'];
      if ($poll['status'] > 0)
        AddPoll($seriesId, $season, $poll);
    } else {
      SetPoll($poll['poll_id'], $seriesId, $season, $poll);
    }
  }
}

function emptyPoll($seriesId) {
  return array("poll_id" => -1, "password" => NULL, "series_id" => $seriesId, 'description' => '', 'status' => 0);
}

if (!count($serieses)) {
  $html .= "<p>" . _("No divisions.") . "</p>\n";
} else {
  $html .= "<form method='post' action='?view=admin/seasonpolls&season=$season'>";
  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];
    $html .= "<h2>" . utf8entities(U_($series['name'])) . "</h2>\n";
    $poll = SeriesPoll($seriesId);
    if (empty($poll))
      $poll = emptyPoll($seriesId);

    $pollId = $poll['poll_id'];
    $html .= "<input type='hidden' name='poll$seriesId' value='$pollId'/>";
    $html .= "<table class='formtable'>";
    $html .= "<tr><td class='infocell'>" . _("Status") . "</td><td><select class='dropdown' name='status$seriesId'>\n";
    foreach (PollStatuses() as $status => $name) {
      $selected = $poll['status'] == $status ? "selected='selected'" : "";
      $html .= "<option class='dropdown' $selected value='$status'>" . utf8entities($name) . "</option>\n";
    }
    $html .= "</select>\n";
    $html .= "</td></tr>\n";
    $html .= "<tr><td class='infocell'>" . _("Password") .
      "</td><td><input class='input' name='password$seriesId' value='" . utf8entities($poll['password']) .
      "'/></td></tr>\n";

    $teams = PollTeams($pollId);
    $html .= "<tr><td class='infocell'>" . _("Description") . "</td><td>" .
      "<textarea class='input' rows='5' cols='70' id='description' name='description$seriesId'>" .
      htmlentities($poll['description']) . "</textarea></td></tr>\n";
    if ($pollId > 0) {
      $html .= "<tr><td class='infocell'>" . _("Teams") . "</td><td>" . count($teams) . "</td></tr>\n";
    }
    $html .= "</table>\n";
    if ($pollId > 0) {
      if (HasResults($pollId) || hasEditSeriesRight($series)) {
      $html .= "<p><a href='?view=user/pollresult&season=$season&poll=$pollId'>" . _("View results") . "<a></p>";
      }
      if (CanVote(null, null, $pollId) || hasEditSeriesRight($series)) {
        $html .= "<p><a href=?view=user/votepoll&season=$season&poll=$pollId>" . _("Vote") . "</a></p>\n";
      }
    }
  }

  $html .= "<p><input id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/></p>";
  $html .= "</form>";
  $html .= "<p><a href='?view=user/teampolls&season=$season'>" . _("View teams") . "<a></p>";
}

showPage($title, $html);
?>