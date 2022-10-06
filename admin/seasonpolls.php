<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['season'])) {
  die(_("Season mandatory"));
}
$season = $_GET['season'];
$seasonInfo = SeasonInfo($season);
$title = sprintf(_("Manage polls for %s"), utf8entities($seasonInfo['name']));
$html = "";

$serieses = SeasonSeries($season);

if (!empty($_POST['save'])) {
  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];
    $poll = SeriesPoll($seriesId);
    if (empty($poll)) {
      $poll = emptyPoll($seriesId);
    }

    foreach (PollStatuses() as $flag => $name) {
      $poll[$name] = isset($_POST[$name . $seriesId]) ? 1 : 0;
    }

    $poll['password'] = !empty($_POST["password$seriesId"]) ? $_POST["password$seriesId"] : NULL;
    $poll['description'] = !empty($_POST["description$seriesId"]) ? $_POST["description$seriesId"] : NULL;

    if ($poll['poll_id'] == -1) {
      AddPoll($seriesId, $season, $poll);
    } else {
      SetPoll($poll['poll_id'], $seriesId, $season, $poll);
    }
  }
}

function emptyPoll($seriesId) {
  $x = array("poll_id" => -1, "password" => NULL, "series_id" => $seriesId, 'description' => '');
  foreach (PollStatuses() as $flag => $name) {
    $x[$name] = 0;
  }
  return $x;
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
    foreach (PollStatuses() as $key => $value) {
      $html .= "<tr><td class='infocell'>" . PollStatusName($key) .
        ": </td><td><input class='input' type='checkbox' name='$value$seriesId' ";
      if ($poll[$value]) {
        $html .= "checked='checked'";
      }
      $html .= "/></td></tr>\n";
    }
    $html .= "<tr><td class='infocell'>" . _("Password") .
      "</td><td><input class='input' name='password$seriesId' value='" . utf8entities($poll['password']) .
      "'/></td></tr>\n";

    $options = PollOptions($pollId);
    $html .= "<tr><td class='infocell'>" . _("Description") . "</td><td>" .
      "<textarea class='input' rows='5' cols='70' name='description$seriesId'>" . htmlentities($poll['description']) .
      "</textarea></td></tr>\n";
    if ($pollId > 0) {
      $html .= "<tr><td class='infocell'>" . _("Options") . "</td><td>" . count($options) . "</td></tr>\n";
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
        $html .= "<p><a href='?view=user/pollresult&series=$seriesId&poll=$pollId'>" . _("View results") . "</a></p>\n";
      }
    }
  }

  $html .= "<p><input id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/></p>\n";
  $html .= "</form>\n";
  $html .= "<p><a href='?view=user/polls&season=$season'>" . _("View options") . "</a></p>\n";
}

showPage($title, $html);
?>