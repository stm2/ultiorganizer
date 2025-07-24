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
$emaillinku = '';
if (hasViewUsersRight()) {
  $emaillinku = utf8entities(_("e-mail"));
}


function mailtoall(array $all, string $subject) {
  global $emaillinku;
  if (empty($emaillinku)) {
    return "";
  }

  $mailtoall = "";
  foreach ($all as $row) {
    $mailtoall .= mailto_address($row['email'], $row['name']) . ";";
  }
  $subject = utf8entities(rawurlencode($subject));
  return "<a href='mailto:$mailtoall?subject=$subject'>$emaillinku</a>, ";
}

function mailtoone(string $email, string $name, string $subject) {
  global $emaillinku;
  if (empty($emaillinku)) {
    return "";
  }

  if (!empty($emaillinku)) {
    $mailto = "<a href='" . mailto_encode($email, $name, null, $subject) . "'>$emaillinku</a>, ";
  }
  return $mailto;
}

function user_item(array $user, string $subject) {
  $html = "<li class='contact-user'>";
  $html .= "<input type='checkbox' class='contact-user' name='recipients[]' value='" . $user['userid'] . "' />";
  $html .= utf8entities($user['name']) . " ("
    . mailtoone($user['email'], $user['name'], $subject)
    . dm_link([['email' => $user['email'], 'name' => $user['name']]], $subject) . ") ";
  $html .= "</li>\n";
  return $html;
}

function users_item(array $users, string $subject) {
  $html = "<li class='contact-category'>";
  $html .= "<input type='checkbox' class='contact-category' name='recipients[]' value='" . implode(',', array_column($users, 'userid')) . "' />";
  $html .= utf8entities(_("All organizers")) . " ("
    . mailtoall($users, $subject)
    . dm_link($users, $subject) . ") ";
  $html .= "</li>\n";
  return $html;
}

$html .= "<h2>" . utf8entities(_("Contacts")) . "</h2>\n";

if (isSeasonAdmin($season)) {

  $resp = SeasonTeamAdmins($season, true);

  if (!empty($resp)) {
    $html .= "<p>" . sprintf(_("All %d users registered for the event"), count($resp)) . " ("
      . mailtoall($resp, $seasonName)
      . dm_link($resp, $seasonName) . ")</p>\n";
  }
}

$html .= "<h3>" . utf8entities(_("Mail to Event Organizers")) . "</h3>\n";

$html .= "<form action='?view=admin/sendmessage' method='post'>\n";

$admins = SeasonAdmins($season);
$html .= "<ul>";
$all = [];
$all_ids = [];
foreach ($admins as $user) {
  if (!empty($user['email'])) {
    $html .= user_item($user, $seasonName, true);
    $all[] = $user;
    $all_ids[] = $user['userid'];
  }
}
$subject = $seasonName;

if (count($all) > 1) {
  $html .= users_item($all, $seasonName, true);
}
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
    $all_ids = [];
    $html .= "  <ul>";
    $found = 0;
    foreach ($admins as $user) {
      if (!empty($user['email'])) {
        $found++;

        $html .= user_item($user, $seriesName, true);

        $all[] = $user;
        $seasonAdmins[$user['email']] = $user;
      }
    }
    if (empty($all)) {
      $html .= "<li>" . utf8entities(_("No admins known")) . "</li>\n";
    } else {
      $numSeries++;
      $subject = $seriesName;

      if (count($all) > 1) {
        $html .= users_item($all, $subject);
      }
    }
    $html .= "</ul>";
    $html .= "</li>\n";
  }
}

if ($numSeries > 0) {
  $all = [];
  $all_ids = [];
  foreach ($seasonAdmins as $user) {
    $all[] = ['email' => $user['email'], 'name' => $user['name']];
    $all_ids[] = $user['userid'];
  }
  $subject = $seasonName;

  if (count($all) > 1) {
    $html .= users_item($all, $subject);
  }
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
    $respUserObjs = [];
    foreach ($teams as $team) {
      if ($found++ == 0)
        $html .= "<ul>";
      $html .= "<li>" . utf8entities($team['name']) . ": ";
      $admins = GetTeamAdmins($team['team_id']);
      $teamUserObjs = [];
      if (empty($admins)) {
        $html .= "---";
      } else {
        foreach ($admins as $user) {
          if (!empty($user['email'])) {
            $subject = $row['name'] . ", " . $team['name'];
            $html .= user_item($user, $subject);

            $teamUserObjs[] = $user;
            $respUserObjs[] = $user;
          }
        }
      }
      $subject = $team['name'];
      if (!empty($teamUserObjs) && count($teamUserObjs) > 1) {
        $html .= users_item($teamUserObjs, $subject);
      }

      $html .= "</li>\n";
    }
    if ($found > 0) {
      $html .= "</ul>\n";
    }
    $html .= "</li>\n";

    if (!empty($respUserObjs) && count($respUserObjs) > 1) {
      $html .= users_item($respUserObjs, $subject);
    }
  }
}
$html .= "</ul>\n";
$html .= "<p><input class='button' type='submit' value='" . utf8entities(_("Send message to selected")) . "' /></p>\n";
$html .= "</form>\n";

showPage($title, $html);
