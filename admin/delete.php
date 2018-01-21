<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$seasonId = iget("season");
if (empty($seasonId)) {
  $seasonId = CurrentSeason();
}

$mode = iget('mode');
$seriesId = iget('series');

function getLinkUrl($season = null, $series = null, $mode = null) {
  $url = "?view=admin/delete";
  if (! empty($season))
    $url .= "&amp;season=" . utf8entities($season);
  if (! empty($series))
    $url .= "&amp;series=" . utf8entities($series);
  if (! empty($mode))
    $url .= "&amp;mode=" . utf8entities($mode);
  return $url;
}

function getLink($label, $season = null, $series = null, $mode = null) {
  return "<a href='" . getLinkUrl($season, $series, $mode) . "'>" . $label . "</a>";
}

switch ($mode) {
case 'games':
  $title = _("Delete games");
  break;

default:
  $title = _("Delete");
}

$html = "";

if (!empty($_POST['reset_games'])) {
  foreach ($_POST['pools'] as $key => $poolId) {
    if (hasEditSeriesRight(PoolSeries($poolId))) {
      PoolUndoAllGames($poolId);
      $html .= "<p>" . sprintf(_("Pool %s has been reset."), PoolName($poolId)) . "</p>\n";
    }
  }
}

$html .= "<h2>" . $title . "</h2>\n";

if (empty($mode)) {
  $html .= "<p>" . getLink(_("Delete games ..."), $seasonId, null, 'games') . "</p>\n";
} elseif ($mode === 'games') {
  if (empty($seriesId)) {
    $series = SeasonSeries($seasonId);
    $found = 0;
    foreach ($series as $seriesRow) {
      if (hasEditSeriesRight($seriesId = $seriesRow['series_id'])) {
        $found ++;
        $html .= "<p>" . getLink(sprintf(_("Delete games in %s ..."), utf8entities($seriesRow['name'])), $seasonId, $seriesId, $mode) . "</p>\n";
      }
    }
    if ($found < 1) {
      $html .= "<p>" . _("You have no rights to delete any series.") . "</p>\n";
    }
  } else {
    $html .= "<p>" . ("Select pools to play or undo") . ":</p>\n";
    $html .= "<form method='post' id='delete_form' action='" . getLinkUrl($seasonId, $seriesId, $mode) . "'>";
    
    $html .= SeasonPoolGamesTable('delete_form', $seasonId, array( 0 => array( 'series_id' => $seriesId, 'name' => SeriesName($seriesId))));
    
    $html .= "<p><input class='button' type='submit' name='reset_games' value='" . ("Reset played games") . "'/></p>";
    $html .= "<div>";
    $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
    $html .= "<input type='hidden' name='series' value='$seriesId' />\n";
    $html .= "<input type='hidden' name='mode' value='$mode' />\n";
    $html .= "</div>\n";
    $html .= "</form>";
  }
} else if (mode == 'series' ) {
  // delete series:
  // uo_enrolledteam
  // uo_player_stats
  // uo_pool ->
  //// uo_game ->
  ////// uo_scheduling_name
  ////// uo_spirit_score
  ////// uo_game_event
  ////// uo_goal
  ////// uo_played
  ////// uo_timeout
  ////// uo_defense
  //// uo_game_pool
  //// uo_move_teams
  ////// uo_scheduling_id
  //// uo_specialranking
  ////// uo_scheduling_id
  //// uo_team_pool
  // uo_series(1)
  // uo_series_stats
  // uo_team ->
  //// uo_accreditionlog
  //// uo_player ->
  //// uo_player_stats
  //// uo_team_profile
  //// uo_team_stats
  // uo_team_stats
  // uo_user_properties
  //// userrole
  /////+playeradmin:playerid
  /////+teamadmin:teamid
  /////+accradmin:teamid
  /////+resadmin:resid
  /////+resgameadmin:resid
  /////+gameadmin:gameid
  /////+seriesadmin:seriesid
  /////  poolselector
  /////  locale
  /////  editseason id
  /////  facebookuid
  /////  facebooktoken
  /////  facebookplayer
  /////  facebookmessage
  // uo_spirit_category
  // uo_season_stats(!)
  
  
  $html .= "<form method='post' id='delete_form' action='?view=admin/delete&amp;season=$seasonId'>";
  
  $html .= "<p><input class='button' type='submit' name='delete_button' value='" . _("Delete") . "'/></p>";
  
  $html .= "</form>";
}

showPage($title, $html);

?>