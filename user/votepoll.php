<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

function compareOptions($t1, $t2) {
  global $info;

  $r1 = $info['rank'][$t1['option_id']];
  $r2 = $info['rank'][$t2['option_id']];
  return $r2 - $r1;
}

if (empty($_GET['series']) || empty($_GET['poll'])) {
  die(_("Series and poll mandatory"));
}
$seriesId = $_GET['series'];
$pollId = $_GET['poll'];

$poll = PollInfo($pollId);

if ($seriesId != $poll['series_id'])
  die("Invalid series and poll");

$series = SeriesInfo($poll['series_id']);
$info = array();

$title = _("Vote") . ": " . utf8entities($series['name']);
$html = "";
$error = "";
$feedback = "";

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

if (!CanVote($user, $name, $pollId) && !hasEditSeriesRight($seriesId)) {
  $html .= "<h2>$title</h2>";
  $html .= "<p>" . _("You cannot vote for this poll") . "</p>";
} else if (empty($name)) {
  $html .= "<h2>$title</h2>";
  $html .= "<form method='post' action='?view=user/votepoll&series=$seriesId&poll=$pollId'>";
  $html .= "<p>" . _("Enter your name (public)") . ": <input class='input' type='text' name='name'/></p>\n";
  $html .= "<input id='doname' class='button' name='doname' type='submit' value='" . _("Vote") . "'/></form>\n";
} else {
  $options = PollOptions($pollId);

  if (!empty($_POST['vote'])) {
    $info['poll_id'] = $pollId;
    $info['name'] = $name;
    $info['rank'] = array();
    foreach ($options as $option) {
      $optionId = $option['option_id'];
      if (!isset($_POST["rank$optionId"])) {
        $error .= sprintf(_("Missing vote for %s"), $option['name']);
      } else {
        $info['rank'][$optionId] = (int) $_POST["rank$optionId"];
      }
    }

    $oldPassword = VotePassword($pollId, $name);
    $votePassword = empty($_POST['votepassword']) ? null : $_POST['votepassword'];

    if (!empty($oldPassword) && $votePassword != $oldPassword) {
      if (empty($_POST['override']) || !hasEditSeriesRight($seriesId)) {
        $error .= _("Wrong password");
      }
    }
    if ($user == 'anonymous' && empty($votePassword)) {
      $error .= _("Passwort cannot be empty. The password can be used to change your vote later.");
    }

    $info['user_id'] = $user == 'anonymous' ? -1 : UserId($user);

    if (empty($error)) {
      InsertVote($pollId, $info['user_id'], $name, $votePassword, $info['rank']);
      $feedback .= _("Vote has been saved");
    }
  } else if (!empty($_POST['delete'])) {
    $oldPassword = VotePassword($pollId, $name);
    $votePassword = empty($_POST['votepassword']) ? null : $_POST['votepassword'];

    if (!empty($oldPassword) && $votePassword != $oldPassword) {
      $error .= _("Wrong password");
    }
    if (empty($error)) {
      DeleteVote($pollId, UserId($user), $name, $votePassword);
      $feedback .= _("Vote has been deleted.");
    }
    $info['rank'] = PollRanks($pollId, $name, $options);
    foreach ($options as $option) {
      if ($info['rank'][$option['option_id']] === null) {
        $info['rank'][$option['option_id']] = ""; // random_int(1, 2 * count($option));
      }
    }
  } else {
    $oldPassword = VotePassword($pollId, $name);
    $info['rank'] = PollRanks($pollId, $name, $options);
    foreach ($options as $option) {
      if ($info['rank'][$option['option_id']] === null) {
        $info['rank'][$option['option_id']] = ""; // random_int(1, 2 * count($option));
      }
    }
  }

  if (!empty($error))
    $html .= "<div class='warning'>" . _("Error") . ": $error</div>";

  if (!empty($feedback))
    $html .= "<div class='warning'>" . $feedback . "</div>";

  $html .= "<h2>$title</h2>\n";
  if (!empty($poll['description'])) {
    $html .= "<div id='poll_description'><p>" . $poll['description'] . "</p></div>";
  }

  $html .= "<form method='post' action='?view=user/votepoll&series=$seriesId&poll=$pollId'>";

  if (hasEditSeriesRight($seriesId)) {
    $html .= "<table>";
    $html .= "<tr><td class='infocell'>" . _("Name") . "</td>";
    $html .= "<td><input class='input' type='text' name='name' value='$name'/></td></tr></table>\n";
  } else {
    $html .= "<input type='hidden' name='name' value='$name'/>";
  }

  $html .= "<table class='poll_vote'><tbody id='poll_vote'>";

  $maxl = 60;
  $min = 0;
  $max = Math . max(2 * count($options), 100);

  mergesort($options, 'compareOptions');

  foreach ($options as $option) {
    $optionId = $option['option_id'];
    $rank = $info['rank'][$optionId];
    if (empty($rank))
      $rank = 0;
    $html .= "<tr class='rank_item' id='rank_item$optionId'><td>";
    $html .= "<span class='rank_item_name'>" . utf8entities($option['name']) . "</span>";
    $html .= " <span class='rank_item_mentor'>" . utf8entities($option['mentor']) . "</span><br />";

    if (!empty($option['description'])) {
      $html .= "<span class='rank_item_description'>";
      if (strlen($option['description']) > $maxl)
        $html .= utf8entities(substr($option['description'], 0, $maxl)) . "...</span>";
      else
        $html .= utf8entities($option['description']) . "</span>";
    }
    $html .= " <a href='?view=user/addpolloption&series=$seriesId&option_id=$optionId' rel='noopener' target='_blank'>" .
      _("Details") . "</a>";

    $html .= " </td><td><input style='text-align:right;' class='input' type='number' size='2' maxlength='3' min='$min' max='$max' id='rank$optionId' name='rank$optionId'";
    $html .= "  onchange='changeRank(this, \"$rank\", \"$optionId\"); this.oldvalue = this.value;'";
    $html .= "  value='" . $rank . "'/>\n";

    $html .= '</td></tr>';
  }

  $html .= "</tbody></table>\n";
  if ($user == 'anonymous' || !empty($oldPassword)) {
    $html .= "<p>" . sprintf(_("Enter a password for %s's vote (needed if you change your vote later)"), $name) .
      ": <input class='input' type='password' name='votepassword' value='" . utf8entities($votePassword) . "'/>&nbsp;\n";
    if (hasEditSeriesRight($seriesId)) {
      $html .= "<input class='input' type='checkbox' name='override'/>" . _("Override") . "</p>";
    }
  }

  $html .= "<p><input class='button' name='vote' type='submit' value='" . _("Vote") . "'/>&nbsp;";
  $html .= "<input class='button' name='delete' type='submit' value='" . _("Delete") . "'/></p>";
  $html .= "</form>\n";

  $script = <<<EOT
<script type="text/javascript">
<!--

  function findPrevious(elm) {
    do {
      elm = elm.previousSibling;
    } while (elm && elm.nodeType != 1);
   return elm;
  }

  function changeRank(elem, original, optionId){
    if (elem.oldvalue == null) elem.oldvalue = original;
    var list = document.getElementById('poll_vote');
    var items = list.childNodes;
    var itemsArr = [];
    for (var i in items) {
        if (items[i].nodeType == 1) { // get rid of the whitespace text nodes
            itemsArr.push(items[i]);
        }
    }

    itemsArr.sort(function(a, b) {
      var idA = a.id.substring(9); // rank_itemID
      var rankA = document.getElementById('rank' + idA);
      var vA = parseInt(rankA.value);
      var idB = b.id.substring(9); // rank_itemID
      var rankB = document.getElementById('rank' + idB);
      var vB = parseInt(rankB.value);
      if (!isFinite(vA)) vA = 0;
      if (!isFinite(vB)) vB = 0;
      return vB - vA;
    });

    for (i = 0; i < itemsArr.length; ++i) {
      var id = itemsArr[i].id.substring(9); // rank_itemID
      var rank = document.getElementById('rank' + id)
      rank.oldvalue = rank.value; 
      list.appendChild(itemsArr[i]);
    }
  }
//-->
</script>

EOT;

  addHeaderText($script);
}

showPage($title, $html);
?>
