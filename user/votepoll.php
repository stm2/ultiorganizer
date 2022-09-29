<?php
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/poll.functions.php';

function compareTeams($t1, $t2) {
  global $ranks;
  $r1 = $ranks[$t1['pt_id']];
  $r2 = $ranks[$t2['pt_id']];
  return $r2 - $r1;
}

if (empty($_GET['season']) || empty($_GET['poll'])) {
  die(_("Season and poll mandatory"));
}
$season = $_GET['season'];
$pollId = $_GET['poll'];

$poll = PollInfo($pollId);
$series = SeriesInfo($poll['series_id']);
$info = array();

$title = _("Vote") . ": " . utf8entities($series['name']);
$html = "";
$error = "";

$name = "";

if (!empty($_GET['name'])) {
  $name = $_GET['name'];
}

if (isset($_SESSION['uid'])) {
  $user = $_SESSION['uid'];
  if (empty($name))
    $name = VoteName($pollId, $user);
} else {
  $user = "anonymous";
}


if (!CanVote($user, $name, $pollId)) {
  $html .= "<h2>$title</h2>";
  $html .= "<p>" . _("You cannot vote for this poll") . "</p>";
} else if (empty($name)) {
  $html .= "<h2>$title</h2>";
  $html .= "<form method='post' action='?view=user/votepoll&season=$season&poll=$pollId'>";
  $html .= "<p>" . _("Enter your name (public)") . ": <input class='input' type='text' name='name'/></p>\n";
  $html .= "<input id='doname' class='button' name='doname' type='submit' value='" . _("Vote") . "'/></form>\n";
} else {
  $teams = PollTeams($pollId);

  if (!empty($_GET['vote'])) {
    $info['poll_id'] = $pollId;
    $info['name'] = $name;
    $info['rank'] = array();
    foreach ($teams as $team) {
      $teamId = $team['pt_id'];
      if (!isset($_GET["rank$teamId"])) {
        $error .= sprintf(_("Missing vote for %s"), $team['name']);
      } else {
        $info['rank'][$teamId] = $_GET["rank$teamId"];
      }
    }

    $oldPassword = VotePassword($pollId, $name);
    $votePassword = empty($_GET['votepassword']) ? null : $_GET['votepassword'];

    if (!empty($oldPassword) && $votePassword != $oldPassword) {
      $error .= _("Wrong password");
    }
    if ($user == 'anonymous' && empty($votePassword)) {
      $error .= _("Passwort cannot be empty. The password can be used to change your vote later.");
    }
      

    $info['user_id'] = $user == 'anonymous' ? -1 : UserId($user);

    if (empty($error)) {
      InsertVote($pollId, $user, $name, $votePassword, $info['rank']);
    }
  } else {
    $info['rank'] = PollRanks($pollId, $name, $teams);
      foreach ($teams as $team) {
        if ($info['rank'][$team['pt_id']] === null) {
          $info['rank'][$team['pt_id']] = ""; //random_int(1, 2 * count($team));
      }
    }
  }
  
//   $error .= "p" . print_r($_POST, true);
//   $error .= "i" . print_r($info, true);

  if (!empty($error))
    $html .= "<div class='warning'>" . _("Error") . ": $error</div>";

  $html .= "<h2>$title</h2>\n";
  if (!empty($poll['description'])) {
    $html .= "<div id='series_description'><p>" . $poll['description'] . "</p></div>";
  }

  $html .= "<form method='post' action='?view=user/votepoll&season=$season&poll=$pollId'>";

  $html .= "<table class='ranking'><tr><td class='ranking_column'><div class='worklist'><table class='ranking'><tbody id='ranking'>";

  $maxl = 60;
  $rank = 0;
  $max = 5 * count($teams);

  mergesort($teams, compareTeams);

  foreach ($teams as $team) {
    $teamId = $team['pt_id'];
    $rank++;
    $rank = $info['rank'][$teamId];
    $html .= "<tr class='rank_item' id='rank_item$teamId'><td>";
    $html .= "<span class='rank_item_name'>" . utf8entities($team['name']) . "</span>";
    $html .= " <span class='rank_item_mentor'>" . utf8entities($team['mentor']) . "</span><br />";

    $html .= "<span class='rank_item_description'>";
    if (strlen($team['description']) > $maxl)
      $html .= utf8entities(substr($team['description'], 0, $maxl)) . "...</span>";
    else
      $html .= utf8entities($team['description']) . "</span>";
    $html .= " <a href='?view=user/addpollteam&pt_id=$teamId' rel='noopener' target='_blank'>" . _("Details") . "</a>";

    $html .= " </td><td><input style='text-align:right;' class='input' type='number' size='2' maxlength='3' min='-1' max='$max' id='rank$teamId' name='rank$teamId'";
    $html .= "  onchange='changeRank(this, $rank, $teamId); this.oldvalue = this.value;'";
    $html .= "  value='" . $rank . "'/>\n";

    $html .= '</td></tr>';
  }

  $html .= "</tbody></table></div></td></tr></table>\n";
  $html .= "<input type='hidden' name='name' value='$name'/>";
  if ($user == 'anonymous' || !empty($oldPassword)) {
    $html .= "<p>" . sprintf(_("Enter a password for %s's vote (needed if you change your vote later)"), $name) .
      ": <input class='input' type='password' name='votepassword' value='" . utf8entities($votePassword) . "'/></p>\n";
  }
  $html .= "<p><input id='save' class='button' name='vote' type='submit' value='" . _("Vote") . "'/></p>";

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

  function changeRank(elem, original, teamId){
    if (elem.oldvalue == null) elem.oldvalue = original;
    var list = document.getElementById('ranking');
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
      /*if (vA == vB && a != b) {
        if (teamId == idA)
          return vA > parseInt(rankA.oldvalue) ? -1 : 1;
        if (teamId == idB)
          return vB > parseInt(rankB.oldvalue) ? 1 : -1;
      }*/
      return vB - vA;
    });

    for (i = 0; i < itemsArr.length; ++i) {
      var id = itemsArr[i].id.substring(9); // rank_itemID
      var rank = document.getElementById('rank' + id)
      // rank.value = i + 1;
      // rank.oldvalue = i + 1;
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
