<?php
include_once $include_prefix . 'lib/season.functions.php';

$title = _("Contacts");
$html = "";

if (empty($_GET['season'])) {
  die(_("Event mandatory"));
}
$season = $_GET['season'];
$links = getEditSeasonLinks();
if (!isset($links[$season]['?view=user/contacts&amp;season=' . $season])) {
  die(_("Inadequate user rights"));
}

$seasonName = SeasonName($season);

$html .= "<h2>" . _("Contacts") . "</h2>\n";

$resp = SeasonTeamAdmins($season, true);

$html .= "<div><a href='" . mailto_encode($resp, 'email', 'name', $seasonName) . "'>" . _("Mail to everyone registered for the event") . "</a></div>";

$html .= "<h3>" . _("Mail to Event Organizers") . "</h3>\n";
$admins = SeasonAdmins($season);
$html .= "<ul>";
$all = "";
foreach ($admins as $user) {
  if (!empty($user['email'])) {
    $html .= "<li>" . mailto_link($user['email'], $user['name'], null, $seasonName) . "</li>\n";
    $all .= mailto_address($user['email'], $user['name']) . ";";
  }
}
$subject = utf8entities(rawurlencode($seasonName));
$html .= "<li><a href='mailto:$all?subject=$subject'>" . _("All organizers") . "</a></li>\n";
$html .= "</ul>\n";

$html .= "<h3>" . _("Mail to Division Organizers") . "</h3>\n";
$series = SeasonSeries($season);
$html .= "<ul>";
$seasonAdmins = array();
$numSeries = 0;
foreach ($series as $row) {
  if (hasEditSeriesRight($row['series_id'])) { /* FIXME necessary? */
    $seriesName = U_($row['name']);
    $html .= "<li><b>" . utf8entities($seriesName) . "</b>\n";
    $admins = SeriesAdmins($row['series_id']);
    $html .= "  <ul>";
    $all = "";
    foreach ($admins as $user) {
      if (!empty($user['email'])) {
        $html .= "  <li>" . mailto_link($user['email'], $user['name'], null, $seriesName) . " (" . utf8entities($user['name']) . ")</li>\n";
        $all .= mailto_address($user['email'], $user['name']) . ";";
        $seasonAdmins[$user['email']] = $user;
      }
    }
    $html .= "</ul></li>\n";
    if (empty($all)) {
      $html .= "<li>" . _("No admins known") . "</li>\n";
    } else {
      $numSeries ++;
      $subject = utf8entities(rawurlencode($seriesName));
      $html .= "<li><a href='mailto:$all?subject=$subject'>" . _("All admins") . "</a></li>\n";
    }
  }
}

if ($numSeries > 0) {
  $first = false;
  $all = "";
  foreach ($seasonAdmins as $user) {
    $all .= mailto_address($user['email'], $user['name']). ";";
  }
  $subject = $subject = utf8entities(rawurlencode($seasonName));
  $html .= "<li><a href='mailto:$all?subject='>" . _("All season admins") . "</a></li>";
}

$html .= "</ul>\n";

$html .= "<h3>" . _("Mail to Teams") . "</h3>\n";

$series = SeasonSeries($season);
foreach ($series as $row) {
  if (hasEditSeriesRight($row['series_id'])) {
    $html .= "<p><b>" . utf8entities(U_($row['name'])) . "</b></p>\n";
    
    $teams = SeriesTeams($row['series_id']);
    $html .= "<ul>";
    foreach ($teams as $team) {
      $html .= "<li>" . utf8entities($team['name']) . ": ";
      $admins = GetTeamAdmins($team['team_id']);
      if (empty($admins)) {
        $html .= "---";
      } else {
        foreach ($admins as $user) {
          if (!empty($user['email'])) {
            $html .= mailto_link($user['email'], $user['name'], null, $row['name'].", ".$team['name']);
            // $html .= " (".utf8entities($user['name']).")";
          }
        }
      }
      
      $html .= "</li>\n";
    }
    
    $resp = SeriesTeamResponsibles($row['series_id']);
    if (!empty($resp))
      $html .= "<li><a href='" . mailto_encode($resp, 'email', 'name', U_($row['name'])) . "'>" . _("Mail to teams in") . " " . U_($row['name']) . "</a></li>";
    
    $html .= "</ul>\n";
  }
}

showPage($title, $html);
?>