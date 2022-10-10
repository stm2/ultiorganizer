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

$statuses = PollStatuses();

if (!empty($_POST['save'])) {
  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];
    $polls = SeriesPolls($seriesId);
    foreach ($polls as $poll) {
      $pollId = $poll['poll_id'];
      foreach ($statuses as $flag => $name) {
        $poll[$name] = isset($_POST[$name . $pollId]) ? 1 : 0;
      }

      SetPoll($poll['poll_id'], $seriesId, $poll);
    }
  }
}

if (!count($serieses)) {
  $html .= "<p>" . _("No divisions.") . "</p>\n";
} else {
  $html .= "<form method='post' action='?view=admin/seasonpolls&season=$season'>";
  $html .= "<table class='admintable'>\n";


  $html .= "<tr><th></th><th>" . _("Name") . "</th>
      <th>" . _("Division") . "</th>
      <th class='center' title='" . _("Visible") . "'>" . _("poll_v") . "</th>
      <th class='center' title='" . _("Option entry") . "'>" . _("poll_o") . "</th>
      <th class='center' title='" . _("Voting") . "'>" . _("poll_t") . "</th>
      <th class='center' title='" . _("Results") . "'>" . _("poll_r") . "</th>
      <th>" . _("Options") . "</th>
      <th>" . _("Voters") . "</th>
      <th>" . _("Operations") . "</th>
      </tr>\n";
  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];

    $polls = SeriesPolls($seriesId);
    foreach ($polls as $poll) {
      $pollId = $poll['poll_id'];

      $html .= "<tr  class='admintablerow'><td>";
      $html .= "<input type='hidden' name='poll$pollId' value='$pollId'/>";
      $html .= $poll['name'] . "</td>";
      $html .= "<td>" . $series['name'] . "</td>";

      foreach ($statuses as $key => $value) {
        $name = $html .= "<td><input class='input' type='checkbox' name='$value$pollId' ";
        if ($poll[$value]) {
          $html .= "checked='checked' ";
        }
        $html .= "aria-label='" . PollStatusName($key) . "' ";
        $html .= "/></td>\n";
      }

      $options = PollOptions($pollId);
      $voters = PollVoters($pollId);

      $html .= "<td>" . count($options) . "</td>";
      $html .= "<td>" . $voters . "</td>";

      $html .= "<td><a href='?view=admin/addseasonpoll&amp;&series=$seriesId&poll=$pollId'><img class='deletebutton' src='images/settings.png' alt='E' title='" .
        _("Edit details") . "'/></a>";

      $html .= "&nbsp;<a href='?view=admin/deletepoll&poll=$pollId&series=$seriesId'/><img class='deletebutton' src='images/remove.png' alt='X' title='" .
        _("Delete") . "'/></a>";

      $html .= "&nbsp;<a href=?view=user/polls&series=$seriesId&poll=$pollId><img width='16' class='deletebutton' src='images/options.png' alt='O' title='" .
        _("Options") . "'/></a>\n";
      $html .= "&nbsp;<a href=?view=user/votepoll&series=$seriesId&poll=$pollId><img width='16' class='deletebutton' src='images/vote.png' alt='V' title='" .
        _("Vote") . "'/></a>\n";
      $html .= "&nbsp;<a href='?view=user/pollresult&series=$seriesId&poll=$pollId'><img width='16' class='deletebutton' src='images/result.png' alt='R' title='" .
        _("Results") . "'/></a>\n";

      $html .= "</td></tr>\n";
    }
  }
  $html .= "</table>\n";

  $html .= "<p><input id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/></p>\n";
  $html .= "</form>\n";

  $html .= "<br />";

  $html .= "<form method='post' action='?view=admin/addseasonpoll'>";

  $html .= "<p><label>" . _("Select series") . ": <select class='dropdown' name='series'>\n";
  foreach ($serieses as $series) {
    $seriesId = $series['series_id'];
    $html .= "<option class='dropdown' value='$seriesId'>" . utf8entities(U_($series['name'])) . "</option>\n";
  }
  $html .= "</select></label>\n";

  $html .= "<input class='button' name='add' type='submit' value='" . _("Add poll") . "'/></p>\n";
  $html .= "</form>\n";
}

showPage($title, $html);
?>