<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

$teamId = 0;
$pollId = 0;
$userId = $_SESSION['uid'];

$html = '';
$error = '';

$backurl = isset($_SERVER['HTTP_REFERER']) ? utf8entities($_SERVER['HTTP_REFERER']) : '';

// season parameters
$info = array('pt_id' => 0, 'poll_id' => 0, 'user_id' => UserId($userId), 'name' => '', 'mentor' => '',
  'description' => '', 'status' => 0);

$userinfo = UserInfo($userId);
if (!empty($userinfo['name']))
  $info['mentor'] = $userinfo['name'];

if (!empty($_GET['pt_id']))
  $teamId = $_GET['pt_id'];

if (!empty($_GET['poll']))
  // FIXME
  $pollId = $_GET['poll'];

// process itself on submit
if (!empty($_POST['add'])) {
  // TODO duplicate names
  // TODO poll status

  $backurl = utf8entities($_POST['backurl']);
  $info['poll_id'] = $_POST['poll'];
  $pollId = $info['poll_id'];
  $poll = PollInfo($info['poll_id']);
  $seriesId = $poll['series_id'];
  $seasonId = SeriesInfo($seriesId)['season'];
  if (hasEditSeriesRight($seriesId))
    $info['user_id'] = UserId($_POST['user']);
  else
    $info['user_id'] = UserId($userId);
  $info['name'] = $_POST['name'];
  $info['mentor'] = $_POST['mentor'];
  $info['description'] = $_POST['description'];
  $info['status'] = 0;
  if (empty($info['name']))
    $error .= "<p>" . _('Name cannot be empty.') . "</p>";
  if (HasPollTeam($info['name']))
    $error .= "<p>" . _('Name already exists.') . "</p>";
  if (empty($info['mentor']))
    $error .= "<p>" . _('Mentor cannot be empty.') . "</p>";
  if (!empty($poll['password']) && $poll['password'] != $_POST['poll_password']) // && !hasEditSeriesRight($seriesId))
    $error .= "<p>" . _('Wrong password') . "</p>";

  if (empty($error)) {
    $teamId = AddPollTeam($info);
    if (empty($teamId))
      $error .= "Could not add team " . $info['name'];
    else
      header("location:?view=user/teampolls&season=$seasonId");
  }
} else if (!empty($_POST['save'])) {
  // TODO rights
  $backurl = utf8entities($_POST['backurl']);
  $info['pt_id'] = $_POST['pt_id'];
  $teamId = $info['pt_id'];
  $info['poll_id'] = $_POST['poll'];
  $pollId = $info['poll_id'];

  $poll = PollInfo($info['poll_id']);
  $seriesId = SeriesInfo($poll['series_id']);
  if (hasEditSeriesRight($seriesId)) {
    $info['user_id'] = UserId($_POST['user']);
  }
  $team = TeamInfo($teamId);

  $info['name'] = $_POST['name'];
  $info['mentor'] = $_POST['mentor'];
  $info['description'] = $_POST['description'];

  if (empty($team))
    $error .= _('Cannot update poll, not found.');

  if (empty($info['name']))
    $error .= _('Name cannot be empty.');
  if ($info['name'] != $poll['name'] && HasPollTeam($info['name']))
    $error .= "<p>" . _('Name already exists.') . "</p>";
  if (empty($info['mentor']))
    $error .= _('Mentor cannot be empty.');
  if (!empty($poll['password']) && $poll['password'] != $_POST['poll_password'] && !hasEditSeriesRight($seriesId))
    $error .= _('Wrong password');

  if (empty($error)) {
    SetPollTeam($teamId, $info);
  }
}

if (!empty($error))
  $html .= "<div class='warning'>" . _("Error") . ": $error</div>";

if (!empty($teamId)) {
  // TODO, TODO add $series_id
  $info = PollTeam($teamId);
  $poll = PollInfo($info['poll_id']);
  $seriesId = SeriesInfo($poll['series_id']);
  $title = _("Edit team");
  $html .= "<h2>" . sprintf(_("Edit team for %s"), $seriesId['name']) . "</h2>\n";
} else if (!empty($pollId)) {
  $poll = PollInfo($pollId);
  $seriesId = SeriesInfo($poll['series_id']);
  $title = _("Add team");
  $html .= "<h2>" . sprintf(_("Add team for %s"), $seriesId['name']) . "</h2>\n";
} else {
  $title = "";
}

if (empty($title)) {
  showPage($title, "bad request");
} else {

  if (strlen($info['name']) > 0) {
    $title .= ": " . $sp['name'];
  }

  if ($teamId) {
    $info = PollTeam($teamId);
  }

  $param = "";
  $html .= "<form method='post' action='?view=user/addpollteam$param'><table class='formtable'>";
  $html .= "<input type='hidden' name='backurl' value='$backurl'/>";
  $html .= "<input type='hidden' name='pt_id' value='$teamId'/>";
  $html .= "<input type='hidden' name='poll' value='$pollId'/>";
  $html .= "<tr><td class='infocell'>" . _("Name") . ": </td><td><input class='input' name='name' value='" .
    utf8entities($info['name']) . "'/></td></tr>";
  if (hasEditSeriesRight($poll['series_id']))
    $disabled = "";
  else
    $disabled = "disabled='disabled'";

  $user = UserName($info['user_id']);
  $html .= "<tr><td class='infocell'>" . _("User") . ": </td><td><input class='input' name='user' $disabled value='" .
    utf8entities($user) . "'/></td></tr>";

  $html .= "<tr><td class='infocell'>" . _("Mentor") . ": </td><td><input class='input' name='mentor' value='" .
    utf8entities($info['mentor']) . "'/></td></tr>";
  $html .= "<tr><td class='infocell''>" .
    htmlentities(_("Comment (you can use <b>, <em>, and <br /> tags)")) .
    ":</td>
    <td><textarea class='input' rows='10' cols='70' id='description' name='description'>" .
    htmlentities($info['description']) . "</textarea></td></tr>";
  if (!empty($poll['password'])) // && !hasEditSeriesRight($seriesId))
    $html .= "<tr><td class='infocell'>" . _("Poll Password") .
      ": </td><td><input class='input' type='password' name='poll_password'/>&nbsp;</td></tr>";
  $html .= "<table>\n";
  if ($teamId) {
    $html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/></p>\n";
  } else {
    $html .= "<p><input class='button' name='add' type='submit' value='" . _("Add") . "'/></p>\n";
  }

  showPage($title, $html);
}
?>
