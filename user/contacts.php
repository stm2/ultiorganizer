<?php
include_once $include_prefix . 'lib/season.functions.php';

$title = _("Contacts");
$html = "";

if (empty($_GET['season'])) {
  die(_("Event mandatory"));
}
$season = $_GET['season'];
$links = getEditSeasonLinks();

ensureLogin();

if (!isset($links[$season]['?view=user/contacts&amp;season=' . urlencode($season)])) {
  showUnprivileged($title, null);
}

$seasonName = SeasonName($season);
$emaillink = _("e-mail");
$emaillinku = utf8entities(_("e-mail"));

function mailtoall(array $all, string $subject) {
  global $emaillinku;
  $mailtoall = "";
  foreach ($all as $row) {
    $mailtoall .= mailto_address($row['email'], $row['name']) . ";";
  }
  $subject = utf8entities(rawurlencode($subject));
  return "<a href='mailto:$mailtoall?subject=$subject'>$emaillinku</a>";
}

$html .= "<h2>" . utf8entities(_("Contacts")) . "</h2>\n";

if(isSeasonAdmin($season)) {

$resp = SeasonTeamAdmins($season, true);

if (!empty($resp)) {
  $html .= "<p>" . sprintf(_("All %d users registered for the event"), count($resp)) . " (";
  $html .= "<a href='" . mailto_encode($resp, 'email', 'name', $seasonName) . "'>$emaillinku</a>, ";
  $html .= dm_link($resp, $seasonName) . ")</p>\n";
}
}

$html .= "<h3>" . utf8entities(_("Mail to Event Organizers")) . "</h3>\n";

$admins = SeasonAdmins($season);
$html .= "<ul>";
$all = [];
foreach ($admins as $user) {
  if (!empty($user['email'])) {
    $html .= "<li>" . utf8entities($user['name']) . " (" .
      mailto_link($user['email'], $user['name'], $emaillink, $seasonName) . ", " .
      dm_link([['email' => $user['email'], 'name' => $user['name']]], $seasonName) . ")</li>\n";
    $all[] = ['email' => $user['email'], 'name' => $user['name']];
  }
}
$subject = $seasonName;
$mailtoall = mailtoall($all, $subject);
$html .= "<li>" . _("All organizers") . "($mailtoall, " . dm_link($all, $subject) . ")</li>\n";
$html .= "</ul>\n";

$html .= "<h3>" . utf8entities(_("Mail to Division Organizers")) . "</h3>\n";
$series = SeasonSeries($season);
$html .= "<ul>";
$seasonAdmins = array();
$numSeries = 0;
foreach ($series as $row) {
  if (hasEditSeriesRight($row['series_id'])) { /* FIXME necessary? */
    $seriesName = U_($row['name']);
    $html .= "<li><b>" . utf8entities($seriesName) . "</b>\n";
    $admins = SeriesAdmins($row['series_id']);
    $all = [];
    $found = 0;
    foreach ($admins as $user) {
      if (!empty($user['email'])) {
        if ($found++ == 0)
          $html .= "  <ul>";
        $html .= "  <li>" . utf8entities($user['name']) . " (" .
          mailto_link($user['email'], $user['name'], $emaillink, $seriesName) . ", " .
          dm_link([$user['email'], $user['name']], $seriesName) . ")</li>\n";
        $all[] = ['email' => $user['email'], 'name' => $user['name']];
        $seasonAdmins[$user['email']] = $user;
      }
    }
    if ($found)
      $html .= "</ul>";
    $html .= "</li>\n";
    if (empty($all)) {
      $html .= "<li>" . utf8entities(_("No admins known")) . "</li>\n";
    } else {
      $numSeries++;
      $subject = $seriesName;
      $mailtoall = mailtoall($all, $subject);

      $html .= "<li>" . utf8entities(_("All admins")) . " ($mailtoall, " . dm_link($all, $subject) . ")</li>\n";
    }
  }
}

if ($numSeries > 0) {
  $all = [];
  foreach ($seasonAdmins as $user) {
    $all[] = ['email' => $user['email'], 'name' => $user['name']];
  }
  $subject = $seasonName;

  $mailtoall = mailtoall($all, $subject);
  $html .= "<li>" . utf8entities(_("All season admins")) . "($mailtoall, " . dm_link($all, $subject) . ")</li>";
}

$html .= "</ul>\n";

$html .= "<h3>" . utf8entities(_("Mail to Teams")) . "</h3>\n";

$html .= "<ul>";
$series = SeasonSeries($season);
foreach ($series as $row) {
  if (hasEditSeriesRight($row['series_id'])) {
    $html .= "<li><b>" . utf8entities(U_($row['name'])) . "</b>\n";

    $teams = SeriesTeams($row['series_id']);
    $found = 0;
    foreach ($teams as $team) {
      if ($found++ == 0)
        $html .= "<ul>";
      $html .= "<li>" . utf8entities($team['name']) . ": ";
      $admins = GetTeamAdmins($team['team_id']);
      if (empty($admins)) {
        $html .= "---";
      } else {
        foreach ($admins as $user) {
          if (!empty($user['email'])) {
            $subject = $row['name'] . ", " . $team['name'];
            $html .= utf8entities($team['name']) . "(" . mailto_link($user['email'], $user['name'], _("email"), $subject) .
              ", " . dm_link([$user['email'], $user['name']], $subject) . "); ";
            // $html .= " (".utf8entities($user['name']).")";
          }
        }
      }

      $html .= "</li>\n";
    }
    if ($found > 0)
      $html .= "</ul>\n";
    $html .= "</li>\n";

    $resp = SeriesTeamResponsibles($row['series_id']);
    if (!empty($resp)) {
      $subject = U_($row['name']);
      $html .= "<li>" . utf8entities(sprintf(_("All %d team admins in %s"), count($resp), $subject)) . "(<a href='" .
        mailto_encode($resp, 'email', 'name', $subject) . "'>$emaillinku</a>, " . dm_link($resp, $subject) . ")</li>";
    }
  }
}
$html .= "</ul>\n";

showPage($title, $html);
?>
