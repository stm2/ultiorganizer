<?php
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['series']))
  if (empty($_POST['series']))
    die(_("Division mandatory"));
  else
    $seriesId = $_POST['series'];
else
  $seriesId = $_GET['series'];

$series = SeriesInfo($seriesId);
if (empty($series))
  die(_("Division not found"));

$pollId = isset($_GET['poll']) ? $_GET['poll'] : -1;

$poll = PollInfo($pollId);
if (empty($poll)) {
  $poll = emptyPoll($seriesId);
}

$title = _("Manage poll");
$html = "";

function emptyPoll($seriesId) {
  $x = array("poll_id" => -1, "name" => "", "password" => NULL, "series_id" => $seriesId, 'description' => '');
  foreach (PollStatuses() as $flag => $name) {
    $x[$name] = 0;
  }
  return $x;
}

if (!empty($_POST['save'])) {
  foreach (PollStatuses() as $flag => $name) {
    $poll[$name] = isset($_POST[$name]) ? 1 : 0;
  }

  $poll['name'] = !empty($_POST["name"]) ? $_POST["name"] : "";

  $poll['password'] = !empty($_POST["poll_password"]) ? $_POST["poll_password"] : NULL;
  $poll['series_id'] = $seriesId;
  $poll['description'] = !empty($_POST["description"]) ? $_POST["description"] : NULL;

  if ($poll['poll_id'] == -1) {
    $pollId = AddPoll($seriesId, $poll);
  } else {
    SetPoll($poll['poll_id'], $seriesId, $poll);
  }
}

$html .= "<form method='post' action='?view=admin/addseasonpoll&amp;series=$seriesId&amp;poll=$pollId'>";

if ($pollId == -1) {
  $html .= "<h2>" . _("Add poll") . "</h2>\n";
} else {
  $html .= "<h2>" . _("Edit poll") . "</h2>\n";
}

$html .= "<h3>" . utf8entities(U_($series['name'])) . "</h3>";

$html .= "<input type='hidden' name='poll' value='$pollId'/>";
$html .= "<table class='formtable'>";

$html .= "<tr><td class='infocell'><label for='name'>" . _("Name") . "</label></td><td><input class='input' id='name' name='name' value='" .
  utf8entities($poll['name']) . "'/></td></tr>\n";

foreach (PollStatuses() as $key => $value) {
  $html .= "<tr><td class='infocell'><label for='$value'>" . PollStatusName($key) .
    "</label>: </td><td><input class='input' type='checkbox' id='$value' name='$value' ";
  if ($poll[$value]) {
    $html .= "checked='checked'";
  }
  $html .= "/></td></tr>\n";
}
$html .= "<tr><td class='infocell'><label for='poll_password'>" . _("Password") . "</label></td><td><input class='input' id='poll_password' name='poll_password' value='" .
  utf8entities($poll['password']) . "'/></td></tr>\n";

$voters = PollVoters($pollId);
$options = PollOptions($pollId);
$html .= "<tr><td class='infocell'><label for='description'>" . _("Description") . "</label></td><td>" .
  "<textarea class='input' rows='5' cols='70' id='description' name='description'>" . htmlentities($poll['description']) .
  "</textarea></td></tr>\n";
if ($pollId > 0) {
  $html .= "<tr><td class='infocell'>" . _("Options") . "</td><td>" . count($options) . "</td></tr>\n";
  $html .= "<tr><td class='infocell'>" . _("Voters") . "</td><td>" . $voters . "</td></tr>\n";
}
$html .= "</table>\n";

if ($pollId == -1) {
  $html .= "<p><input class='button' name='save' type='submit' value='" . _("Add") . "'/></p>\n";
} else {
  $html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/></p>\n";
}
$html .= "</form>\n";

showPage($title, $html);
?>