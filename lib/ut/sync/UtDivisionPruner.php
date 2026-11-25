<?php
declare(strict_types=1);

/**
 * Hard-deletes sync-owned games, pools, and teams removed from Ultimate Tournament.
 */

readonly class UtDivisionPruner {
  public function __construct(private UtMapRepository $mapRepo) {}

  /**
   * @param string[] $activeGameUtIds
   * @param string[] $activePoolUtKeys
   * @param string[] $activeTeamUtIds
   * @param string[] $activeSchedUtKeys
   */
  public function pruneDivision(
    UtSyncContext $ctx,
    int $seriesId,
    array $activeGameUtIds,
    array $activePoolUtKeys,
    array $activeTeamUtIds,
    array $activeSchedUtKeys
  ): void {
    $seriesScope = $ctx->seriesScope($seriesId);
    $activeGamesSet = array_flip($activeGameUtIds);
    $activePoolsSet = array_flip($activePoolUtKeys);
    $activeTeamsSet = array_flip($activeTeamUtIds);
    $activeSchedSet = array_flip($activeSchedUtKeys);

    $mappedGames = $this->mapRepo->getAllMapped($ctx->tournamentId, $seriesScope, 'game');
    foreach ($mappedGames as $utId => $uoId) {
      if (!isset($activeGamesSet[$utId])) {
        $gameId = (int) $uoId;
        DBQuery(sprintf("DELETE FROM uo_game_pool WHERE game=%d", $gameId));
        DBQuery(sprintf("DELETE FROM uo_game WHERE game_id=%d", $gameId));
        $this->mapRepo->deleteMapped($ctx->tournamentId, $seriesScope, 'game', $utId);
        $ctx->result->deleted->games++;
      }
    }

    $mappedPools = $this->mapRepo->getAllMapped($ctx->tournamentId, $seriesScope, 'pool');
    foreach ($mappedPools as $utId => $uoId) {
      if (!isset($activePoolsSet[$utId])) {
        $poolId = (int) $uoId;
        DBQuery(sprintf("UPDATE uo_pool SET follower=NULL WHERE follower=%d", $poolId));
        DBQuery(sprintf("DELETE FROM uo_moveteams WHERE topool=%d OR frompool=%d", $poolId, $poolId));
        DBQuery(sprintf("DELETE FROM uo_team_pool WHERE pool=%d", $poolId));
        DBQuery(sprintf("DELETE FROM uo_game_pool WHERE pool=%d", $poolId));
        DBQuery(sprintf("DELETE FROM uo_pool WHERE pool_id=%d", $poolId));
        $this->mapRepo->deleteMapped($ctx->tournamentId, $seriesScope, 'pool', $utId);
        $ctx->result->deleted->pools++;
      }
    }

    $mappedTeams = $this->mapRepo->getAllMapped($ctx->tournamentId, $seriesScope, 'team');
    foreach ($mappedTeams as $utId => $uoId) {
      if (!isset($activeTeamsSet[$utId])) {
        $teamId = (int) $uoId;
        DBQuery(sprintf("DELETE FROM uo_team_pool WHERE team=%d", $teamId));
        DBQuery(sprintf("DELETE FROM uo_team WHERE team_id=%d", $teamId));
        $this->mapRepo->deleteMapped($ctx->tournamentId, $seriesScope, 'team', $utId);
        $ctx->result->deleted->teams++;
      }
    }

    $mappedSchedNames = $this->mapRepo->getAllMapped($ctx->tournamentId, $seriesScope, 'sched_name');
    foreach ($mappedSchedNames as $utId => $uoId) {
      if (!isset($activeSchedSet[$utId])) {
        $schedId = (int) $uoId;
        $inGame = (int) DBQueryToValue(sprintf(
          "SELECT COUNT(*) FROM uo_game WHERE scheduling_name_home=%d OR scheduling_name_visitor=%d OR name=%d",
          $schedId,
          $schedId,
          $schedId
        ));
        $inMove = (int) DBQueryToValue(sprintf(
          "SELECT COUNT(*) FROM uo_moveteams WHERE scheduling_id=%d",
          $schedId
        ));
        if ($inGame === 0 && $inMove === 0) {
          DBQuery(sprintf("DELETE FROM uo_scheduling_name WHERE scheduling_id=%d", $schedId));
        }
        $this->mapRepo->deleteMapped($ctx->tournamentId, $seriesScope, 'sched_name', $utId);
      }
    }
  }
}
