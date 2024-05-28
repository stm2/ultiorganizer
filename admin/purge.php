<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$title = _("Purge old stuff");
$html = "";

$seasonId = iget("season");
if (empty($seasonId)) {
  $seasonId = CurrentSeason();
}

ensureSuperAdmin($title);

function getInput($name, $value) {
  return "<input class='input' size='4' maxlength='10' name='$name' id='$name' value='$value'/>";
}

$show_list = true;

if (!isset($_POST['cancel'])) {

  function confirmDeletion($question, $success, $variables, $execute, &$html) {
    if (isset($_POST['confirm'])) {
      $entries = $execute(true);
      $html .= "<p class='responseSuccess'>$success</p>";
    } else {
      $entries = $execute(false);

      if (gettype($entries) == 'array') {
        if (count($entries) > 0) {
          debug_to_apache($entries);
          $html .= "<table class='admintable'>\n";
          $i = 0;
          foreach ($entries as $row) {
            $html .= "<tr>";
            foreach ($row as $key => $val) {
              $html .= "<th>$key</td>";
            }
            $html .= "</tr>\n";
            break;
          }
          foreach ($entries as $row) {
            $html .= "<tr>";
            foreach ($row as $key => $val) {
              $html .= "<td>$val</td>";
            }
            $html .= "</tr>\n";
            if (++$i >= 100)
              break;
          }
          $html .= "</table>";
          if (++$i >= 100)
            $html .= "<p>" . sprintf(_("... %d more entries"), count($entries) - 100) . "</p>\n";
        }

        $entries = count($entries);
      }

      if ($entries > 0) {
        $html .= "<form method='post' action='?view=admin/purge'>\n";
        $html .= "<p>" . sprintf($question, $entries) . "</p>\n";
        foreach ($variables as $name => $value) {
          $html .= "<input type='hidden' name='$name' value='$value' />\n";
        }
        $html .= "<input type='submit' name='confirm' value='" . _("Yes, delete!") . "'/>";
        $html .= "<input type='submit' name='cancel' value='" . _("Cancel") . "'/>";
        $html .= "</form>";
        return false;
      } else {
        $html .= "<p class='responseFailure'>" . _("No matching entries") . "</p>\n";
      }
    }
    return true;
  }

  if (!empty($_POST['purgelogs'])) {
    $year = $_POST['purgelogyear'];
    $question = sprintf(_("Deleting %%d log entries from %d and earlier. Are you sure?"), $year);
    $success = _("Log entries purged.");
    $execute = function ($confirmed) use ($year) {
      return DeleteLogs($year, $confirmed);
    };
    $show_list = confirmDeletion($question, $success, ['purgelogs' => 'set', 'purgelogyear' => $year], $execute, $html);
  }

  if (!empty($_POST['purgevisitors'])) {
    $freq = $_POST['purgevisitorfreq'];
    if ($freq > 0) {
      $question = sprintf(_("Deleting %%d visitor entries occuring %d times and fewer. Are you sure?"), $freq);
      $success = _("Visitor entries purged.");
      $execute = function ($confirmed) use ($freq) {
        return DeleteVisitors($freq, $confirmed);
      };
      $show_list = confirmDeletion($question, $success, ['purgevisitors' => 'set', 'purgevisitorfreq' => $freq],
        $execute, $html);
    } else {
      $html .= "<p class='responseFailure'>" . _("No matching entries") . "</p>\n";
    }
  }

  if (!empty($_POST['reducevisitors'])) {
    $question = _("Reducing %d visitor entries by 20%%. Are you sure?");
    $success = _("Entries reduced.");
    $execute = function ($confirmed) {
      return ReduceVisitors(0.8, $confirmed);
    };
    $show_list = confirmDeletion($question, $success, ['reducevisitors' => 'set'], $execute, $html);
  }

  if (!empty($_POST['purgeloads'])) {
    $freq = $_POST['purgeloadfreq'];
    if ($freq > 0) {
      $question = sprintf(_("Deleting %%d page load entries occuring %d times and fewer. Are you sure?"), $freq);
      $success = _("Load entries purged.");
      $execute = function ($confirmed) use ($freq) {
        return DeleteLoads($freq, $confirmed);
      };
      $show_list = confirmDeletion($question, $success, ['purgeloads' => 'set', 'purgeloadfreq' => $freq], $execute,
        $html);
    } else {
      $html .= "<p class='responseFailure'>" . _("No matching entries") . "</p>\n";
    }
  }
  if (!empty($_POST['reduceloads'])) {
    $question = _("Reducing %d page load entries by 20%%. Are you sure?");
    $success = _("Entries reduced.");
    $execute = function ($confirmed) {
      return ReduceLoads(0.8, $confirmed);
    };
    $show_list = confirmDeletion($question, $success, ['reduceloads' => 'set'], $execute, $html);
  }

  if (!empty($_POST['purgeinvalidloads'])) {
    $question = _("Delete %d invalid page load entries. Are you sure?");
    $success = _("Entries deleted.");
    $execute = function ($confirmed) {
      return DeleteInvalidLoads($confirmed);
    };
    $show_list = confirmDeletion($question, $success, ['purgeinvalidloads' => 'set'], $execute, $html);
  }

  if (!empty($_POST['purgeusers'])) {
    $question = _("Delete %d users without login. Are you sure?");
    $success = _("Entries deleted.");
    $execute = function ($confirmed) {
      return DeleteUsersBefore(null, $confirmed);
    };
    $show_list = confirmDeletion($question, $success, ['purgeusers' => 'set'], $execute, $html);
  }

  if (!empty($_POST['purgeusersbefore'])) {
    $year = $_POST['purgeuseryear'];
    $question = sprintf(_("Deleting %%d users from %d and earlier. Are you sure?"), $year);
    $success = _("Users purged.");
    $execute = function ($confirmed) use ($year) {
      return DeleteUsersBefore($year, $confirmed);
    };
    $show_list = confirmDeletion($question, $success, ['purgeusersbefore' => 'set', 'purgeuseryear' => $year], $execute,
      $html);
  }
} else {
  $html .= "<p class='responseSuccess'>" . _("Purge cancelled!") . "</p>\n";
}

if ($show_list) {

  $log_heading = _("Log entries");
  $purge_logs = sprintf(_("Purge  entries of %s and older"), getInput("purgelogyear", intval(date("Y")) - 2));
  $logs = LogStats();
  $logstats = "<table><tr><th>" . _("Year") . "</th><th>" . _("Entries") . "</th></tr>";
  foreach ($logs as $log) {
    $logstats .= "<tr><td>{$log['year']}</td><td>{$log['count']}</td></tr>\n";
  }
  $logstats .= "</table>\n";

  $visitor_heading = _("Visitor stats");
  $purge_visitors = sprintf(_("Purge visitor counter entries occuring %s times or fewer"),
    getInput("purgevisitorfreq", 1));
  $purge_visitors2 = _("Reduce counts by 20%");
  $logs = VisitorStats();
  $visitorstats = "<table><tr><th>" . _("Visits") . "</th><th>" . _("Visitors") . "</th></tr>";
  foreach ($logs as $visits => $visitors) {
    $visitorstats .= "<tr><td>$visits</td><td>$visitors</td></tr>\n";
  }
  $visitorstats .= "</table>\n";

  $pageload_heading = _("Page load stats");
  $purge_loads = sprintf(_("Purge page load stats occuring %s times or fewer"), getInput("purgeloadfreq", 3));
  $purge_loads2 = _("Reduce counts by 20%");

  $invalid = InvalidPageLoads();
  $purge_loads3 = sprintf(_("Remove %d invalid requests logs"), $invalid);
  $logs = PageLoadStats();
  $loadstats = "<table><tr><th>" . _("Loads") . "</th><th>" . _("Pages") . "</th></tr>";
  foreach ($logs as $visits => $visitors) {
    $loadstats .= "<tr><td>$visits</td><td>$visitors</td></tr>\n";
  }
  $loadstats .= "</table>\n";

  $season_heading = _("Seasons");
  $purge_seasons = sprintf(_("Delete seasons from %s and older"), getInput("purgeseasonyear", intval(date("Y")) - 2));
  $seasonstats = SeasonStats();
  $years = [];
  foreach ($seasonstats as $row) {
    $year = $row['end'];
    if (!isset($years[$year])) {
      $years[$year] = ['seasons' => 0, 'series' => 0];
      $lastseason = null;
    }
    if ($lastseason != $row['season_id'])
      ++$years[$year]['seasons'];
    $lastseason = $row['season_id'];
    ++$years[$year]['series'];
  }
  $seasonstats = "<table><tr><th>" . _("Year") . "</th><th>" . _("Seasons") . "</th><th>" . _("Classes") . "</th><tr>\n";
  foreach ($years as $year => $counts) {
    $seasonstats .= "<tr><td>{$year}</td><td>{$counts['seasons']}</td><td>{$counts['series']}</td></tr>\n";
  }
  $seasonstats .= "</table>\n";

  $user_heading = _("Users");
  $purge_users = sprintf(_("Delete users with no login"));
  $purge_users2 = sprintf(_("Delete users with no login after %s"), getInput("purgeuseryear", intval(date("Y")) - 2));
  $no_purge_admin = _("Adminstrator accounts are not deleted.");
  $userstats = "<table><tr><th>" . _("Year") . "</th><th>" . _("Users") . "</th><tr>\n";
  $users = GetUserYears();

  foreach ($users as $row) {
    $userstats .= "<tr><td>{$row['year']}</td><td>{$row['count']}</td></tr>\n";
  }
  $userstats .= "</table>\n";

  $purgetext = utf8entities(_("Purge..."));

  $html .= <<<EOF
<form method='post' action='?view=admin/purge'>


<h3><a href="?view=admin/eventviewer">$log_heading</a></h3>

$logstats

<p><input type='submit' id='purgelogs' name='purgelogs' value='$purgetext'/> $purge_logs</p>

<h3><a href="?view=admin/visitors">$visitor_heading</a></h3>

$visitorstats

<p><input type='submit' id='purgevisitors' name='purgevisitors' value='$purgetext'/> $purge_visitors<br />
<input type='submit' id='reducevisitors' name='reducevisitors' value='$purgetext'/> $purge_visitors2</p>

<h3><a href="?view=admin/visitors">$pageload_heading</a></h3>

$loadstats

<p><input type='submit' id='purgeloads' name='purgeloads' value='$purgetext'/> $purge_loads
<br /><input type='submit' id='reduceloads' name='reduceloads' value='$purgetext'/> $purge_loads2
<br /><input type='submit' id='purgeinvalidloads' name='purgeinvalidloads' value='$purgetext'/> $purge_loads3</p>

<h3><a href="?view=admin/users">$user_heading</a></h3>

$userstats

<p><input type='submit' id='purgeusers' name='purgeusers' value='$purgetext'/> $purge_users
<br /><input type='submit' id='purgeusersbefore' name='purgeusersbefore' value='$purgetext'/> $purge_users2
<br />$no_purge_admin</p>

<h3><a href="?view=admin/seasons">$season_heading</a></h3>

$seasonstats
<p><input type='submit' disabled='disabled' id='purgeseasons' name='purgeseasons' value='$purgetext'/> $purge_seasons</p>
EOF;

  // $html .= "<p><input class='button' type='submit' name='purge' value='" . _("Purge...") . "'/></p>";
  $html .= "</form>";
}

showPage($title, $html);
?>