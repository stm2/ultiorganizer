<?php
include_once 'lib/search.functions.php';
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
  if (!empty($season))
    $url .= "&amp;season=" . utf8entities($season);
  if (!empty($series))
    $url .= "&amp;series=" . utf8entities($series);
  if (!empty($mode))
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

case 'pools':
  $title = _("Select pools for deletion (experimental!)");
  break;

default:
  $title = _("Delete");
}

$html = "";

$continue = true;

if (!empty($_POST['reset_games'])) {
  foreach ($_POST['pools'] as $key => $poolId) {
    if (hasEditSeriesRight(PoolSeries($poolId))) {
      PoolUndoAllGames($poolId);
      $html .= "<p>" . sprintf(_("Pool %s has been reset."), PoolName($poolId)) . "</p>\n";
    }
  }
}

if (isset($_POST['deletepools'])) {
  $continue = false;
  $html .= "<h2>" . _("Confirm deletion") . "</h2>";

  if (count($_POST['pools'])) {
    $html .= "<form method='post' id='delete_form' action='" . getLinkUrl($seasonId, $seriesId, $mode) . "'>";
    foreach ($_POST['pools'] as $poolId) {
      if (hasEditSeriesRight(PoolSeries($poolId))) {
        $html .= "<input type='hidden' name='pools[]' value='$poolId' />\n";
      }
    }
    $html .= "<ul>";
  }

  foreach ($_POST['pools'] as $poolId) {
    $name = PoolName($poolId);
    if (!hasEditSeriesRight(PoolSeries($poolId))) {
      $html .= "<p>" . sprintf(_("Insufficent rights to delete pool %s"), $name) . "</p>\n";
    } else {

      $query = sprintf("SELECT count(*) FROM uo_gameevent WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      $pevents = DBQueryToValue($query);
      $query = sprintf("SELECT count(*) FROM uo_defense WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      $pevents += DBQueryToValue($query);
      $query = sprintf("SELECT count(*) FROM uo_timeout WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      $pevents += DBQueryToValue($query);
      $query = sprintf("SELECT count(*) FROM uo_goal WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      $pevents += DBQueryToValue($query);

      $query = sprintf("SELECT count(*) FROM uo_played WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      $pdata = DBQueryToValue($query);
      $query = sprintf(
        "SELECT count(*) FROM uo_spirit_score WHERE game_id IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      $pdata += DBQueryToValue($query);

      // //// TODO uo_scheduling_name

      $query = sprintf("SELECT count(*) FROM uo_specialranking WHERE frompool = %d", (int) $poolId);
      $pdata += DBQueryToValue($query);

      $query = sprintf("SELECT count(*) FROM uo_game WHERE pool = %d", (int) $poolId);
      $pgames = DBQueryToValue($query);

      $query = sprintf("SELECT count(*) FROM uo_game_pool WHERE pool = %d", (int) $poolId);
      $pmoved = DBQueryToValue($query);

      $query = sprintf("SELECT count(*) FROM uo_team_pool WHERE pool = %d", (int) $poolId);
      $pteams = DBQueryToValue($query);

      $query = sprintf("SELECT count(*) FROM uo_moveteams WHERE frompool=%d OR topool=%d", (int) $poolId, (int) $poolId);
      $ptransfers = DBQueryToValue($query);

      $html .= "<li>" .
        sprintf(
          _("Pool %s with %d games, %d moved games, %d moved teams, %d transfers, %d game events, and %d other data"),
          $name, $pgames, $pmoved, $pteams, $ptransfers, $pevents, $pdata) . "</li>\n";
    }
  }
  if (count($_POST['pools'])) {
    $html .= "</li>";

    $html .= "<br /><p>" .
      sprintf(
        _(
          "Are your sure you want to delete this data? This might have undesirable side effects, especially if the tournament is running!")) .
      "</p><br />\n";

    $html .= "<p><input class='button' type='submit' name='deletepoolsconfirm' value='" . _("Delete pools") . "'/></p>";
    $html .= "<div>";
    $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
    $html .= "<input type='hidden' name='series' value='$seriesId' />\n";
    $html .= "<input type='hidden' name='mode' value='$mode' />\n";
    $html .= "</div>\n";
    $html .= "</form>";
  } else {
    $html .= "<p>" . _("No pools selected") . "</p>";
  }
} else if (isset($_POST['deletepoolsconfirm'])) {

  foreach ($_POST['pools'] as $poolId) {
    if (!hasEditSeriesRight(PoolSeries($poolId))) {
      $html .= "<p>" . sprintf(_("Insufficent rights to delete pool %s"), $name) . "</p>\n";
    } else {
      $name = PoolName($poolId);

      $query = sprintf("DELETE FROM uo_gameevent WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_defense WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_timeout WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_goal WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_played WHERE game IN (SELECT game_id FROM uo_game WHERE pool=%d)", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_spirit_score WHERE game_id IN (SELECT game_id FROM uo_game WHERE pool=%d)",
        (int) $poolId);
      DBQuery($query);

      // //// TODO uo_scheduling_name
      $query = sprintf("DELETE FROM uo_game WHERE pool = %d", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_game_pool WHERE pool = %d", (int) $poolId);
      DBQuery($query);

      $query = sprintf("UPDATE uo_team SET pool=NULL WHERE pool = %d", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_team_pool WHERE pool = %d", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_specialranking WHERE frompool = %d", (int) $poolId);
      DBQuery($query);

      $query = sprintf("UPDATE  uo_pool SET follower=NULL WHERE follower = %d", (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_moveteams WHERE frompool=%d OR topool=%d", (int) $poolId, (int) $poolId);
      DBQuery($query);

      $query = sprintf("DELETE FROM uo_pool WHERE pool_id=%d", (int) $poolId);
      DBQuery($query);

      $html .= "<p>" . sprintf(_("Deleted pool %s."), $name) . "</p>";
    }
  }
}

if ($continue) {

  $html .= "<h2>" . $title . "</h2>\n";

  if (empty($mode)) {
    $html .= "<p>" . getLink(_("Delete games ..."), $seasonId, null, 'games') . "</p>\n";
    $html .= "<p>" . getLink(_("Delete pools ..."), $seasonId, null, 'pools') . "</p>\n";
  } elseif ($mode === 'games') {
    if (empty($seriesId)) {
      $series = SeasonSeries($seasonId);
      $found = 0;
      foreach ($series as $seriesRow) {
        if (hasEditSeriesRight($seriesId = $seriesRow['series_id'])) {
          $found++;
          $html .= "<p>" .
            getLink(sprintf(_("Delete games in %s ..."), utf8entities($seriesRow['name'])), $seasonId, $seriesId, $mode) .
            "</p>\n";
        }
      }
      if ($found < 1) {
        $html .= "<p>" . _("You have no rights to delete any divisions.") . "</p>\n";
      }
    } else {
      $html .= "<p>" . _("Select pools to play or undo") . ":</p>\n";
      $html .= "<form method='post' id='delete_form' action='" . getLinkUrl($seasonId, $seriesId, $mode) . "'>";

      $html .= SeasonPoolGamesTable('delete_form', $seasonId,
        array(0 => array('series_id' => $seriesId, 'name' => SeriesName($seriesId))), true);

      $html .= "<p><input class='button' type='submit' name='reset_games' value='" . _("Reset played games") . "'/></p>";
      $html .= "<div>";
      $html .= "<input type='hidden' name='season' value='$seasonId' />\n";
      $html .= "<input type='hidden' name='series' value='$seriesId' />\n";
      $html .= "<input type='hidden' name='mode' value='$mode' />\n";
      $html .= "</div>\n";
      $html .= "</form>";
    }
  } else if ($mode == 'pools') {

    $target = "view=admin/delete";
    $html .= SearchPool($target, array('season' => $seasonId, 'series' => $seriesId, 'mode' => $mode),
      array('deletepools' => _("Select"), 'cancel' => _("Cancel")));
  } else if ($mode == 'series') {
    // TODO
    // delete series:
    // uo_enrolledteam
    // uo_player_stats
    // uo_pool ->
    // // uo_game ->
    // //// uo_scheduling_name
    // //// uo_spirit_score
    // //// uo_game_event
    // //// uo_goal
    // //// uo_played
    // //// uo_timeout
    // //// uo_defense
    // // uo_game_pool
    // // uo_move_teams
    // //// uo_scheduling_id
    // // uo_specialranking
    // //// uo_scheduling_id
    // // uo_team_pool
    // uo_series(1)
    // uo_series_stats
    // uo_team ->
    // // uo_accreditionlog
    // // uo_player ->
    // // uo_player_stats
    // // uo_team_profile
    // // uo_team_stats
    // uo_team_stats
    // uo_user_properties
    // // userrole
    // ///+playeradmin:playerid
    // ///+teamadmin:teamid
    // ///+accradmin:teamid
    // ///+resadmin:resid
    // ///+resgameadmin:resid
    // ///+gameadmin:gameid
    // ///+seriesadmin:seriesid
    // /// poolselector
    // /// locale
    // /// editseason id
    // /// facebookuid
    // /// facebooktoken
    // /// facebookplayer
    // /// facebookmessage
    // uo_spirit_category
    // uo_season_stats(!)

    // DeleteSeries($seriesId);

    // $html .= "<form method='post' id='delete_form' action='?view=admin/delete&amp;season=$seasonId'>";

    // $html .= "<p><input class='button' type='submit' name='delete_button' value='" . _("Delete") . "'/></p>";

    // $html .= "</form>";
  }
}

showPage($title, $html);

?>