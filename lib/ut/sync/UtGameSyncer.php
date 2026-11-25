<?php
declare(strict_types=1);

/**
 * Syncs Ultimate Tournament games into uo_game and uo_game_pool.
 */

readonly class UtGameSyncer {
  public function __construct(
    private UtMapRepository $mapRepo,
    private UtNaming $naming
  ) {}

  /**
   * @param UtGame[] $divGames
   * @param array<string, int> $teamMap
   * @param array<string, UtBracket> $bracketsById
   * @param array<string, int> $reservationMap
   */
  public function syncGames(
    UtSyncContext $ctx,
    int $seriesId,
    array $divGames,
    array $teamMap,
    UtPoolSyncResult $poolResult,
    array $bracketsById,
    array $reservationMap
  ): UtGameSyncResult {
    $seriesScope = $ctx->seriesScope($seriesId);
    $poolMap = $poolResult->poolMap;
    $gameSlotSchedIds = $poolResult->gameSlotSchedIds;
    $gameNameLabels = $poolResult->gameNameLabels;

    $activeGameUtIds = [];
    $activeSchedUtKeys = [];

    foreach ($divGames as $g) {
      $utGameId = $g->id;
      $activeGameUtIds[] = $utGameId;

      $bId = $g->parentBracketId;
      $poolId = ($bId !== null && isset($poolMap[$bId]))
        ? (int) $poolMap[$bId]
        : (isset($poolMap['__unassigned__']) ? (int) $poolMap['__unassigned__'] : 0);

      if ($poolId <= 0) {
        $ctx->result->warnings[] = 'Skipped game ' . $utGameId . ' (no target pool found).';
        continue;
      }

      $homeUtId = $g->home->teamId;
      $awayUtId = $g->away->teamId;

      $homeTeamSql = ($homeUtId !== null && isset($teamMap[$homeUtId])) ? (string) ((int) $teamMap[$homeUtId]) : 'NULL';
      $awayTeamSql = ($awayUtId !== null && isset($teamMap[$awayUtId])) ? (string) ((int) $teamMap[$awayUtId]) : 'NULL';

      // Reuse shared uo_moveteams scheduling_id if present, otherwise create/update per-game scheduling_name
      $schedHomeSql = 'NULL';
      if (isset($gameSlotSchedIds[$utGameId][UtGameSide::Home->value])) {
        $schedHomeSql = (string) ((int) $gameSlotSchedIds[$utGameId][UtGameSide::Home->value]);
      } elseif ($homeTeamSql === 'NULL') {
        $label = $this->deriveFallbackPlaceholderLabel($g->home, $bracketsById);
        $schedKey = $utGameId . '|home';
        $schedHomeSql = (string) $this->mapRepo->upsertSchedulingName($ctx->tournamentId, $seriesScope, $schedKey, $label);
        $activeSchedUtKeys[] = $schedKey;
      }

      $schedAwaySql = 'NULL';
      if (isset($gameSlotSchedIds[$utGameId][UtGameSide::Away->value])) {
        $schedAwaySql = (string) ((int) $gameSlotSchedIds[$utGameId][UtGameSide::Away->value]);
      } elseif ($awayTeamSql === 'NULL') {
        $label = $this->deriveFallbackPlaceholderLabel($g->away, $bracketsById);
        $schedKey = $utGameId . '|away';
        $schedAwaySql = (string) $this->mapRepo->upsertSchedulingName($ctx->tournamentId, $seriesScope, $schedKey, $label);
        $activeSchedUtKeys[] = $schedKey;
      }

      $gameNameSql = 'NULL';
      if (isset($gameNameLabels[$utGameId]) && $gameNameLabels[$utGameId] !== '') {
        $schedKey = $utGameId . '|name';
        $gameNameSql = (string) $this->mapRepo->upsertSchedulingName($ctx->tournamentId, $seriesScope, $schedKey, $gameNameLabels[$utGameId]);
        $activeSchedUtKeys[] = $schedKey;
      }

      $startDt = UtApiClient::parseTimestampToLocalDatetime($g->startTime, $ctx->timezone);
      $timeSql = $startDt !== null ? ("'" . mysql_adapt_real_escape_string($startDt) . "'") : 'NULL';

      $fieldId = $g->fieldId;
      $resSql = 'NULL';
      if ($fieldId !== null) {
        $dateKey = $startDt !== null ? substr($startDt, 0, 10) : 'undated';
        $resKey = $fieldId . '|' . $dateKey;
        if (isset($reservationMap[$resKey])) {
          $resSql = (string) ((int) $reservationMap[$resKey]);
        }
      }

      $state = $g->gameState;
      $hasStarted = ($state === UtGameState::Running || $state === UtGameState::Finished) ? 1 : 0;
      $isOngoing = ($state === UtGameState::Running) ? 1 : 0;

      if ($state === UtGameState::Scheduled && $g->score1 === 0 && $g->score2 === 0) {
        $homeScoreSql = 'NULL';
        $awayScoreSql = 'NULL';
      } else {
        $homeScoreSql = (string) $g->score1;
        $awayScoreSql = (string) $g->score2;
      }

      $mappedId = $this->mapRepo->getMappedId($ctx->tournamentId, $seriesScope, 'game', $utGameId);
      $exists = false;
      if ($mappedId !== null) {
        $row = DBQueryToRow(sprintf("SELECT game_id FROM uo_game WHERE game_id=%d", (int) $mappedId));
        if ($row) {
          $exists = true;
        }
      }

      if ($exists) {
        $gameId = (int) $mappedId;
        DBQuery(sprintf(
          "UPDATE uo_game SET hometeam=%s, visitorteam=%s, homescore=%s, visitorscore=%s, reservation=%s, time=%s, pool=%d, valid=1, hasstarted=%d, isongoing=%d, scheduling_name_home=%s, scheduling_name_visitor=%s, name=%s WHERE game_id=%d",
          $homeTeamSql,
          $awayTeamSql,
          $homeScoreSql,
          $awayScoreSql,
          $resSql,
          $timeSql,
          $poolId,
          $hasStarted,
          $isOngoing,
          $schedHomeSql,
          $schedAwaySql,
          $gameNameSql,
          $gameId
        ));
        DBQuery(sprintf("DELETE FROM uo_game_pool WHERE game=%d AND pool!=%d", $gameId, $poolId));
        DBQuery(sprintf("INSERT IGNORE INTO uo_game_pool (game, pool, timetable) VALUES (%d, %d, 1)", $gameId, $poolId));
        $ctx->result->updated->games++;
      } else {
        $gameId = (int) DBQueryInsert(sprintf(
          "INSERT INTO uo_game (hometeam, visitorteam, homescore, visitorscore, reservation, time, pool, valid, hasstarted, isongoing, scheduling_name_home, scheduling_name_visitor, name)
           VALUES (%s, %s, %s, %s, %s, %s, %d, 1, %d, %d, %s, %s, %s)",
          $homeTeamSql,
          $awayTeamSql,
          $homeScoreSql,
          $awayScoreSql,
          $resSql,
          $timeSql,
          $poolId,
          $hasStarted,
          $isOngoing,
          $schedHomeSql,
          $schedAwaySql,
          $gameNameSql
        ));
        DBQuery(sprintf("INSERT IGNORE INTO uo_game_pool (game, pool, timetable) VALUES (%d, %d, 1)", $gameId, $poolId));
        $this->mapRepo->setMappedId($ctx->tournamentId, $seriesScope, 'game', $utGameId, $gameId);
        $ctx->result->created->games++;
      }
    }

    return new UtGameSyncResult(
      activeGameUtIds: $activeGameUtIds,
      activeSchedUtKeys: $activeSchedUtKeys,
    );
  }

  /**
   * @param array<string, UtBracket> $bracketsById
   */
  private function deriveFallbackPlaceholderLabel(UtOpponent $opponent, array $bracketsById): string {
    $origin = $opponent->origin;
    if ($origin === null) {
      return $this->naming->originLabel(null);
    }
    $originId = $origin->bracketId;
    /** @var ?UtBracket $originBracket */
    $originBracket = ($originId !== null && isset($bracketsById[$originId])) ? $bracketsById[$originId] : null;
    $originType = $originBracket?->type ?? $origin->elementType;
    $originName = $originBracket?->name ?? '';
    $originY = $originBracket?->y ?? 0;
    $originSize = $originBracket?->size ?? 0;

    return $this->naming->originLabel(
      originType: $originType,
      index: $origin->index,
      poolName: $originName !== '' ? $this->naming->groupPoolName($originName, $originType === UtBracketType::SwissGroup) : '',
      y: $originY,
      size: $originSize,
      groupBlueprints: $origin->groupBlueprints,
    );
  }
}
