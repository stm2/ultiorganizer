<?php
$title = _("Restore anonymous user settings");

$html = "<h2>$title</h2>";

$restore = array('result' => 0);

if (isSuperAdmin() && isset($_POST['restore'])) {
  $overrideId = isset($_POST['override_id']) ? $_POST['override_id'] : 0;
  $restore = RestoreAnonymousUser($overrideId);
}

if (!empty($restore['log'])) {
  $html .= "<ul>";
  foreach ($restore['log'] as $message) {
    $html .= "<li>" . $message . "</li>\n";
  }
  $html .= "</ul>\n";
}

$html .= "<form method='post' action='?view=admin/dbanonymous'>\n";
if ($restore['result'] == 1) {
  $html .= "<p>" . _("Restoration complete.") . "</p>";
} else {
  $html .= "<form method='post' action='?view=admin/dbanonymous'>\n";
  if ($restore['result'] == 0) {
    $html .= "<p>" .
      _(
        "This resets the settings needed when there is no logged in user. Doing this should cause no trouble. Are you sure?") .
      "</p>\n";
  } else if ($restore['result'] > 1) {
    $html .= "<p>" . _("Override and continue?") . "</p>";
    $html .= "<input type='hidden' name='override_id' value='" . $restore['result'] . "' />";
  } else if ($restore['result'] < 0) {
    $html .= "<p>" . _("This looks like a permanent error. Try again?") . "</p>";
  }
  $html .= "<input class='button' type='submit' name='restore' value='" . _("Restore") . "'/>";
  $html .= "</form>\n";
}
$html .= "<br />";
$html .= "<p><a href='?view=admin/dbadmin'>" . _("Take me back") . "</a></p>\n";

showPage($title, $html);
?>
