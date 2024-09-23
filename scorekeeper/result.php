<?php
$html = "";
$errors = "";
$saved = isset($_GET['saved']) ? 1 : 0;
$game = isset($_GET['g']) ? $_GET['g'] : "";

$canSave = true;
$gameId = 0;
if (!empty($game))
  $gameId = (int) substr($game, 0, -1);
if (!empty($_POST['save'])) {
  $game = intval($_POST['game']);
  $gameId = (int) substr($game, 0, -1);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  $errors = CheckGameResult($game, $home, $away);
  if (!empty($errors))
    $errors .= "<p>" . _("Error: Could not save result.") . "</p>\n";
} else if (!empty($_POST['confirm'])) {
  $game = intval($_POST['game']);
  $gameId = (int) substr($game, 0, -1);
  $home = intval($_POST['home']);
  $away = intval($_POST['away']);
  $errors = CheckGameResult($game, $home, $away);
  if (empty($errors)) {
    $ok = GameSetResult($gameId, $home, $away, true, false);
    if ($ok)
      header("location:?view=result&saved=1");
    else
      $errors .= "<p>" . _("Error: Could not save result.") . "</p>\n";
  }
} else {
  if ($game > 0 && $gameId > 0 && !empty($errors)) {
    $gameId = 0;
    $canSave = false;
  }
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Add result with game id") . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";
$html .= "<div data-role='content'>\n";

$html .= $errors;

$html .= "<form action='?view=result' method='post' data-ajax='false'>\n";
if (!empty($_POST['cancel'])) {
  $html .= "<p class='warning'>" . _("Result not saved!") . "</p>";
}
if ($saved) {
  $html .= "<p>" . _("Result saved!") . "</p>";
}

function game_line($game, $game_result) {
  $html = "";
  $html .= "<p>";
  $html .= sprintf(_("Game ID %s"), $game) . "<br />\n";
  $html .= ShortDate($game_result['time']) . " " . DefHourFormat($game_result['time']) . " ";
  if (!empty($game_result['fieldname'])) {
    $html .= _("on field") . " " . utf8entities($game_result['fieldname']);
  }
  $html .= "<br/>\n";
  $html .= U_($game_result['seriesname']) . ", " . U_($game_result['poolname']);
  $html .= "<br />";
  $html .= utf8entities($game_result['hometeamname']);
  $html .= " - ";
  $html .= utf8entities($game_result['visitorteamname']);
  $html .= " ";
  $html .= "</p>\n";
  return $html;
}

if (!empty($_POST['save']) && empty($errors)) {
  $html .= "<p>";
  $html .= "<input class='input' type='hidden' id='game' name='game' value='$game'/> ";
  $html .= "<input class='input' type='hidden' id='home' name='home' value='$home'/> ";
  $html .= "<input class='input' type='hidden' id='away' name='away' value='$away'/> ";
  $game_result = GameInfo($gameId);
  $html .= game_line($game, $game_result);

  if (GameHasStarted($game_result)) {
    $html .= "<br/>";
    $html .= _("Game is already played. Result:") . " " . intval($game_result['homescore']) . " - " .
      $game_result['visitorscore'] . ".";
    $html .= "<br/><br/>";
    $html .= "<span style='font-weight:bold'>" . _("Change result to") . " $home - $away?" . "</span>";
  } else {
    $html .= "<span style='font-weight:bold'> $home - $away</span>";
  }

  $html .= "<br/><br/>";
  if ($home == $away) {
    $html .= _("No winner?");
  } else {
    $html .= _("Winner is") . " <span style='font-weight:bold'>";
    if ($home > $away) {
      $html .= utf8entities($game_result['hometeamname']);
    } else {
      $html .= utf8entities($game_result['visitorteamname']);
    }
    $html .= "?</span> ";
  }
  $html .= "<br/><br/><input type='submit' name='confirm' data-ajax='false' value='" . _("Confirm") . "'/> ";
  $html .= "<input type='submit' name='cancel' data-ajax='false' value='" . _("Cancel") . "'/>";
  $html .= "</p>";
} else {
  $game_result = GameInfo($gameId);
  $homescore = $visitorscore = '';
  if (!empty($game_result)) {
    $html .= game_line($game, $game_result);
    if ($canSave) {
      $homescore = $game_result['homescore'];
      $visitorscore = $game_result['visitorscore'];
    }
  }

  if (!$canSave)
    $game = '';

  $html .= "<label for='game'>" . _("Game # from Scoresheet") . ":</label>";
  $html .= "<input type='number' id='game' name='game' size='6' maxlength='7' value='$game' onkeyup='validNumber(this);'/><br />";

  $html .= "<label for='home'>" . _("Home team goals") . ":</label>";
  $html .= "<input type='number' id='home' name='home' size='3' maxlength='3' value='$homescore' onkeyup='validNumber(this);'/><br /> ";

  $html .= "<label for='away'>" . _("Visitor team goals") . ":</label>";
  $html .= "<input type='number' id='away' name='away' size='3' maxlength='3' value='$visitorscore' onkeyup='validNumber(this);'/><br /> ";

  $html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Submit...") . "'/><br /><br />";
  $html .= "<a href='?view=login' data-role='button' data-ajax='false'>" . _("Games list") . "</a><br />";
}

$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";
echo $html;
?>
<script type="text/javascript">
<!--
document.getElementById('game').setAttribute( "autocomplete","off" );
document.getElementById('home').setAttribute( "autocomplete","off" );
document.getElementById('away').setAttribute( "autocomplete","off" );


function validNumber(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '');
	}
//-->
</script>
