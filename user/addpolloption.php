<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

if (empty($_GET['series']) || (empty($_GET['poll']) && empty($_POST['poll']))) {
  die(_("Division and poll mandatory"));
}
$seriesId = $_GET['series'];

$edit = isset($_GET['edit']) ? $_GET['edit'] : 1;

$optionId = 0;
$pollId = 0;
$userId = $_SESSION['uid'];

$name = "";

if (!empty($_POST['name'])) {
  $name = $_POST['name'];
}

if (isset($_SESSION['uid'])) {
  $user = $_SESSION['uid'];
  if (empty($name))
    $name = VoteName($pollId, UserId($user));
} else {
  $user = "anonymous";
}

$html = '';
$error = '';
$feedback = '';

$backurl = isset($_SERVER['HTTP_REFERER']) ? utf8entities($_SERVER['HTTP_REFERER']) : '';

// option parameters
$info = array('option_id' => 0, 'poll_id' => 0, 'user_id' => UserId($userId), 'name' => '', 'mentor' => '',
  'description' => '', 'status' => 0);

$userinfo = UserInfo($userId);
if (!empty($userinfo['name']))
  $info['mentor'] = $userinfo['name'];

if (!empty($_GET['option_id']))
  $optionId = $_GET['option_id'];

if (!empty($_GET['poll']))
  $pollId = $_GET['poll'];
if (empty($pollId) && !empty($_POST['poll']))
  $pollId = $_POST['poll'];

$suggestive = CanSuggest($user, $name, $pollId);

if ($edit && !($suggestive && IsVisible($pollId)) && !hasEditSeriesRight($seriesId)) {
  $title = _("Add option");
  $html .= "<h2>$title</h2>";
  $html .= "<p>" . _("You cannot suggest options for this poll.") . "</p>";
} else {

  // process itself on submit
  if ($edit && !empty($_POST['add'])) {
    $backurl = utf8entities($_POST['backurl']);
    $info['poll_id'] = $_POST['poll'];
    $pollId = $info['poll_id'];
    $poll = PollInfo($info['poll_id']);
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
    if (HasPollOption($pollId, $info['name']))
      $error .= "<p>" . _('Name already exists.') . "</p>";
    if (empty($info['mentor']))
      $error .= "<p>" . _('Mentor cannot be empty.') . "</p>";
    if (!empty($poll['password']) && $poll['password'] != $_POST['poll_password'] && !hasEditSeriesRight($seriesId))
      $error .= "<p>" . _('Wrong poll password') . "</p>";

    if (empty($error)) {
      $optionId = AddPollOption($info);
      if (empty($optionId))
        $error .= "Could not add option " . $info['name'];
      else {
        header("location:?view=user/polls&series=$seriesId");
      }
    }
  } else if ($edit && !empty($_POST['save'])) {
    // TODO rights
    $backurl = utf8entities($_POST['backurl']);
    $info['option_id'] = $_POST['option_id'];
    $optionId = $info['option_id'];
    $info['poll_id'] = $_POST['poll'];
    $pollId = $info['poll_id'];

    $poll = PollInfo($info['poll_id']);
    if (hasEditSeriesRight($seriesId)) {
      $info['user_id'] = UserId($_POST['user']);
    }
    $option = PollOption($optionId);

    $info['name'] = $_POST['name'];
    $info['mentor'] = $_POST['mentor'];
    $info['description'] = $_POST['description'];

    if (empty($option))
      $error .= _('Cannot update, option not found.');

    if (empty($info['name']))
      $error .= _('Name cannot be empty.');
    if ($info['name'] != $option['name'] && HasPollOption($pollId, $info['name']))
      $error .= "<p>" . _('Name already exists.') . "</p>";
    if (empty($info['mentor']))
      $error .= _('Mentor cannot be empty.');
    if (!empty($poll['password']) && $poll['password'] != $_POST['poll_password'] && !hasEditSeriesRight($seriesId))
      $error .= _('Wrong poll password');

    if (empty($error)) {
      SetPollOption($optionId, $info);
      $feedback .= _("Option has been updated.");
    }
  } else if ($edit && !empty($_POST['delete'])) {
    $backurl = utf8entities($_POST['backurl']);
    $info['option_id'] = $_POST['option_id'];
    $optionId = $info['option_id'];
    $info['poll_id'] = $_POST['poll'];
    $pollId = $info['poll_id'];

    $poll = PollInfo($info['poll_id']);
    if (!empty($poll) && hasEditSeriesRight($seriesId)) {
      if (DeletePollOption($optionId) !== -1) {
        $feedback .= _("Option has been deleted.");
      } else {
        $error .= _("Error deleting poll");
      }
    } else {
      $error .= _("Cannot delete poll");
    }
  }

  if (!empty($error))
    $html .= "<div class='warning'>" . _("Error") . ": $error</div>";

  if (!empty($feedback))
    $html .= "<p>" . $feedback . "</p>";

  if (!$suggestive)
    $html .= "<p>" . _("Poll not open for suggestions.") . "</p>";

  if (!empty($optionId)) {
    $info = PollOption($optionId);
    $poll = PollInfo($info['poll_id']);
    if (empty($info) || empty($poll))
      die("No such option or poll");
    $series = SeriesInfo($seriesId);
    $title = _("Edit option");
    $html .= "<h2>" . sprintf(_("Edit option for %s"), $series['name']) . "</h2>\n";
  } else if (!empty($pollId)) {
    $poll = PollInfo($pollId);
    if (empty($poll))
      die("No such poll");
    $series = SeriesInfo($seriesId);
    $title = _("Add option");
    $html .= "<h2>" . sprintf(_("Add option for %s"), $series['name']) . "</h2>\n";
  } else {
    $title = "";
  }

  if (empty($title)) {
    $html = "bad request";
  } else {

    if (strlen($info['name']) > 0) {
      $title .= ": " . $info['name'];
    }

    if ($optionId) {
      $info = PollOption($optionId);
    }

    $html .= "<form method='post' action='?view=user/addpolloption&amp;series=$seriesId'>\n";
    $html .= "<input type='hidden' name='backurl' value='$backurl'/>\n";
    $html .= "<input type='hidden' name='option_id' value='$optionId'/>\n";
    $html .= "<input type='hidden' name='poll' value='$pollId'/>\n";
    $html .= "<table class='formtable'>\n";

    $disabled = $edit ? "" : "disabled='disabled'";

    $html .= "<tr><td class='infocell'><label for='name'>" . _("Option Name") .
      "</label>: </td><td><input class='input' id='name' name='name' $disabled value='" . utf8entities($info['name']) . "'/></td></tr>\n";
    if (hasEditSeriesRight($poll['series_id']) && $edit)
      $disabled = "";
    else
      $disabled = "disabled='disabled'";

    $user = UserName($info['user_id']);
    if ($user !== -1) {
      $html .= "<tr><td class='infocell'><label for='user'>" . _("User") . "</label>: </td><td><input class='input' id='user' name='user' $disabled value='" .
        utf8entities($user) . "'/></td></tr>\n";
    }

    $disabled = $edit ? "" : "disabled='disabled'";

    $html .= "<tr><td class='infocell'><label for='mentor'>" . _("Mentor (public)") .
      "</label>: </td><td><input class='input' id='mentor' name='mentor' $disabled value='" . utf8entities($info['mentor']) .
      "'/></td></tr>\n";
    $html .= "<tr><td class='infocell'><label for='description'>" . htmlentities(_("Comment (you can use <b>, <em>, and <br /> tags)")) .
      "</label>:</td>
    <td><textarea class='input' maxlength='255' rows='10' cols='70' id='description' name='description'  $disabled>" .
      htmlentities($info['description']) . "</textarea></td></tr>\n";
    if (!empty($poll['password']) && $edit && !hasEditSeriesRight($seriesId)) {
      $html .= "<tr><td class='infocell'><label for='name'>" . _("Poll Password") .
        "</label>: </td><td><input class='input' type='password' id='poll_password' name='poll_password'/>&nbsp;</td></tr>\n";
    }
    $html .= "<table>\n";
    if ($edit) {
      if ($optionId) {
        $html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/></p>\n";
      } else {
        $html .= "<p><input class='button' name='add' type='submit' value='" . _("Add") . "'/></p>\n";
      }
      if (hasEditSeriesRight($seriesId)) {
        $html .= "<p><input class='button' name='delete' type='submit' value='" . _("Delete") . "'/></p>\n";
      }
    }
    $html .= "</form>\n";
  }
}

showPage($title, $html);

?>
