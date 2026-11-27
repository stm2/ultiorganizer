<?php
declare(strict_types=1);

/**
 * Persists planned pools, playoff follower links, uo_team_pool, and uo_moveteams.
 */

readonly class UtPoolSyncer {
  public function __construct(
    private UtMapRepository $mapRepo,
    private UtNaming $naming
  ) {}

  /**
   * @param array<string, int> $teamMap
   */
  public function syncPoolsAndMoves(
    UtSyncContext $ctx,
    int $seriesId,
    UtBracketPlan $plan,
    array $teamMap
  ): UtPoolSyncResult {
    $seriesScope = $ctx->seriesScope($seriesId);
    $pools = $plan->pools;
    $bracketsById = $plan->bracketsById;
    $bracketTeamsByBracket = $plan->bracketTeamsByBracket;

    $poolMap = [];
    $allUtKeys = [];
    $allPoolIds = [];
    /** @var array<string, UtPlannedPool> $poolByBracketId */
    $poolByBracketId = [];

    $idx = 0;
    foreach ($pools as $pool) {
      $allFinished = !empty($pool->games);
      foreach ($pool->games as $g) {
        if ($g->gameState !== UtGameState::Finished) {
          $allFinished = false;
          break;
        }
      }

      $ordering = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
      $color = ut_pool_color($idx, $seriesScope);
      $idx++;

      $poolId = $this->upsertPool(
        $ctx,
        $seriesId,
        $pool,
        $ordering,
        $allFinished,
        $color
      );

      $pool->poolId = $poolId;
      $allUtKeys[] = $pool->utKey;
      $allPoolIds[] = $poolId;

      if ($pool->utKey === '__unassigned__') {
        $poolMap['__unassigned__'] = $poolId;
      }
      foreach ($pool->utBracketIds as $rbId) {
        $poolMap[$rbId] = $poolId;
        $poolByBracketId[$rbId] = $pool;
      }
    }

    // Link playoff follower pools (uo_pool.follower)
    $treeRounds = [];
    foreach ($pools as $pool) {
      if ($pool->treeRootKey !== null) {
        $treeRounds[$pool->treeRootKey][$pool->treeRound] = $pool->poolId;
      }
    }
    foreach ($treeRounds as $roundsMap) {
      ksort($roundsMap);
      $roundPoolList = array_values($roundsMap);
      for ($i = 0; $i < count($roundPoolList) - 1; $i++) {
        DBQuery(sprintf("UPDATE uo_pool SET follower=%d WHERE pool_id=%d", (int) $roundPoolList[$i + 1], (int) $roundPoolList[$i]));
      }
      if (!empty($roundPoolList)) {
        DBQuery(sprintf("UPDATE uo_pool SET follower=NULL WHERE pool_id=%d", (int) end($roundPoolList)));
      }
    }

    // Clear previous uo_moveteams targeting any of these pools before rebuilding
    if (!empty($allPoolIds)) {
      $inList = implode(',', array_map('intval', $allPoolIds));
      DBQuery("DELETE FROM uo_moveteams WHERE topool IN ($inList)");
    }

    $usedFromMoves = [];
    $gameSlotSchedIds = [];
    $gameNameLabels = [];
    $activeSchedUtKeys = [];

    foreach ($pools as $pool) {
      $poolId = (int) $pool->poolId;

      $bTeams = [];
      foreach ($pool->utBracketIds as $rbId) {
        if (isset($bracketTeamsByBracket[$rbId])) {
          $bTeams = array_merge($bTeams, $bracketTeamsByBracket[$rbId]);
        }
      }
      $orderedUoTeamIds = $this->resolvePoolTeamOrder($pool->utType, $pool->games, $bTeams, $teamMap);
      $this->syncPoolTeams($poolId, $orderedUoTeamIds);

      /** @var UtMoveSlot[] $slots */
      $slots = [];
      if ($pool->type === UoPoolType::Playoff || $pool->type === UoPoolType::CrossMatch) {
        $torank = 1;
        foreach ($pool->games as $mIdx => $g) {
          $gId = $g->id;
          $br = $g->parentBracketId !== null ? ($bracketsById[$g->parentBracketId] ?? null) : null;

          if ($br !== null) {
            $gLabel = $this->naming->eliminationGameLabel(
              $br->y,
              $br->size,
              $g->parentBracketIndex ?? $mIdx
            );
            if ($gLabel !== '') {
              $gameNameLabels[$gId] = $gLabel;
            }
          }

          $slots[] = new UtMoveSlot($torank++, $g->home, $gId, UtGameSide::Home);
          $slots[] = new UtMoveSlot($torank++, $g->away, $gId, UtGameSide::Away);
        }
      } elseif ($pool->continuingPool) {
        usort($bTeams, static fn(UtBracketTeam $a, UtBracketTeam $b): int => $a->order <=> $b->order);
        $torank = 1;
        foreach ($bTeams as $bt) {
          $slots[] = new UtMoveSlot($torank++, $bt->opponent);
        }
      }

      $originSchedIdCache = [];

      foreach ($slots as $slot) {
        $opp = $slot->opponent;
        $torank = $slot->torank;
        $origin = $opp->origin;
        $origId = $origin?->bracketId;
        $origIdx = $origin?->index ?? 0;

        if ($origId !== null && isset($poolByBracketId[$origId])) {
          $originPool = $poolByBracketId[$origId];
          $originBracket = $bracketsById[$origId];
          $origType = $originPool->utType;
          $origPoolName = $originPool->name;
          $origY = $originBracket->y;
          $origSize = $originBracket->size;
          $fromPoolId = (int) $originPool->poolId;
          $gameOffset = (int) ($originPool->utOriginOffsets[$origId] ?? 0);

          if ($origType === UtBracketType::Elimination) {
            // UT indexes match winners 0..half-1 top-to-bottom and losers half..size-1 mirrored
            // bottom-to-top; Ultiorganizer assigns odd placings (1, 3, 5...) to winners and even
            // placings (2, 4, 6...) to losers, offset by game_offset*2 when brackets share a round pool.
            $half = (int) floor($origSize / 2);
            if ($origSize > 0 && $origIdx >= $half) {
              $localGameIdx = $origSize - $origIdx - 1;
              $localPlacing = ($localGameIdx * 2) + 2;
            } else {
              $localGameIdx = $origIdx;
              $localPlacing = ($localGameIdx * 2) + 1;
            }
            $fromPlacing = ($gameOffset * 2) + $localPlacing;
          } else {
            $fromPlacing = $origIdx + 1;
          }
        } else {
          // Synthetic frompool=0 move when originating from a virtual groupShell/swissShell
          // (no single source uo_pool).
          $origType = $origId !== null && isset($bracketsById[$origId])
            ? $bracketsById[$origId]->type
            : $origin?->elementType;
          $origPoolName = '';
          $origY = 0;
          $origSize = 0;
          $fromPoolId = 0;
          $fromPlacing = ($poolId * 100) + $torank;
        }

        $groupBlueprints = $origin?->groupBlueprints ?? [];
        $label = $this->naming->originLabel(
          originType: $origType,
          index: $origIdx,
          poolName: $origPoolName,
          y: $origY,
          size: $origSize,
          groupBlueprints: $groupBlueprints,
        );
        $schedKey = $pool->utKey . '|slot|' . $torank;
        $schedId = $this->mapRepo->upsertSchedulingName($ctx->tournamentId, $seriesScope, $schedKey, $label);
        $activeSchedUtKeys[] = $schedKey;

        if ($origId !== null) {
          $originSchedIdCache[$origId . '|' . $origIdx] = $schedId;
        }

        $utTeamId = $opp->teamId;
        $isMoved = ($utTeamId !== null && isset($teamMap[$utTeamId])) ? 1 : 0;

        // Fall back to a synthetic frompool=0 move if (frompool, fromplacing) would collide.
        $moveKey = $fromPoolId . '|' . $fromPlacing;
        if ($fromPoolId > 0 && isset($usedFromMoves[$moveKey])) {
          $fromPoolId = 0;
          $fromPlacing = ($poolId * 100) + $torank;
          $moveKey = $fromPoolId . '|' . $fromPlacing;
        }
        $usedFromMoves[$moveKey] = true;

        DBQuery(sprintf(
          "INSERT INTO uo_moveteams (frompool, topool, fromplacing, torank, ismoved, scheduling_id)
           VALUES (%d, %d, %d, %d, %d, %d)
           ON DUPLICATE KEY UPDATE topool=VALUES(topool), torank=VALUES(torank), ismoved=VALUES(ismoved), scheduling_id=VALUES(scheduling_id)",
          $fromPoolId,
          $poolId,
          $fromPlacing,
          $torank,
          $isMoved,
          $schedId
        ));

        if ($slot->gameId !== null && $slot->side !== null) {
          $gameSlotSchedIds[$slot->gameId][$slot->side->value] = $schedId;
        }
      }

      if ($pool->type === UoPoolType::Group && $pool->continuingPool) {
        foreach ($pool->games as $g) {
          $gId = $g->id;
          foreach ([UtGameSide::Home->value => $g->home, UtGameSide::Away->value => $g->away] as $side => $opp) {
            $origId = $opp->origin?->bracketId;
            $origIdx = $opp->origin?->index ?? 0;
            if ($origId !== null && isset($originSchedIdCache[$origId . '|' . $origIdx])) {
              $gameSlotSchedIds[$gId][$side] = $originSchedIdCache[$origId . '|' . $origIdx];
            }
          }
        }
      }
    }

    return new UtPoolSyncResult(
      poolMap: $poolMap,
      allUtKeys: $allUtKeys,
      allPoolIds: $allPoolIds,
      gameSlotSchedIds: $gameSlotSchedIds,
      gameNameLabels: $gameNameLabels,
      activeSchedUtKeys: $activeSchedUtKeys,
    );
  }

  private function upsertPool(
    UtSyncContext $ctx,
    int $seriesId,
    UtPlannedPool $pool,
    string $ordering,
    bool $played,
    string $color
  ): int {
    $seriesScope = $ctx->seriesScope($seriesId);
    $visible = $pool->visible ? 1 : 0;
    $continuingpool = $pool->continuingPool ? 1 : 0;
    $placementpool = $pool->placementPool ? 1 : 0;
    $playedInt = $played ? 1 : 0;

    $mappedId = $this->mapRepo->getMappedId($ctx->tournamentId, $seriesScope, 'pool', $pool->utKey);
    $exists = false;
    if ($mappedId !== null) {
      $row = DBQueryToRow(sprintf("SELECT pool_id FROM uo_pool WHERE pool_id=%d AND series=%d", (int) $mappedId, $seriesId));
      if ($row) {
        $exists = true;
      }
    }

    if ($exists) {
      DBQuery(sprintf(
        "UPDATE uo_pool SET name='%s', ordering='%s', visible=%d, continuingpool=%d, placementpool=%d, played=%d, series=%d, type=%d WHERE pool_id=%d",
        mysql_adapt_real_escape_string($pool->name),
        mysql_adapt_real_escape_string($ordering),
        $visible,
        $continuingpool,
        $placementpool,
        $playedInt,
        $seriesId,
        $pool->type->value,
        (int) $mappedId
      ));
      $ctx->result->updated->pools++;
      return (int) $mappedId;
    }

    $newId = (int) DBQueryInsert(sprintf(
      "INSERT INTO uo_pool (name, ordering, visible, continuingpool, placementpool, played, series, type, color)
       VALUES ('%s', '%s', %d, %d, %d, %d, %d, %d, '%s')",
      mysql_adapt_real_escape_string($pool->name),
      mysql_adapt_real_escape_string($ordering),
      $visible,
      $continuingpool,
      $placementpool,
      $playedInt,
      $seriesId,
      $pool->type->value,
      mysql_adapt_real_escape_string($color)
    ));
    $this->mapRepo->setMappedId($ctx->tournamentId, $seriesScope, 'pool', $pool->utKey, $newId);
    $ctx->result->created->pools++;
    return $newId;
  }

  /**
   * @param UtGame[] $bGames
   * @param UtBracketTeam[] $bTeams
   * @param array<string, int> $teamMap
   * @return int[]
   */
  private function resolvePoolTeamOrder(UtBracketType $typeStr, array $bGames, array $bTeams, array $teamMap): array {
    $orderedIds = [];
    $seen = [];

    if ($typeStr === UtBracketType::Elimination) {
      foreach ($bGames as $g) {
        foreach ([$g->home->teamId, $g->away->teamId] as $utTeamId) {
          if ($utTeamId !== null && isset($teamMap[$utTeamId])) {
            $uoId = (int) $teamMap[$utTeamId];
            if (!isset($seen[$uoId])) {
              $seen[$uoId] = true;
              $orderedIds[] = $uoId;
            }
          }
        }
      }
      return $orderedIds;
    }

    usort($bTeams, static fn(UtBracketTeam $a, UtBracketTeam $b): int => $a->order <=> $b->order);
    foreach ($bTeams as $bt) {
      $utTeamId = $bt->opponent->teamId;
      if ($utTeamId !== null && isset($teamMap[$utTeamId])) {
        $uoId = (int) $teamMap[$utTeamId];
        if (!isset($seen[$uoId])) {
          $seen[$uoId] = true;
          $orderedIds[] = $uoId;
        }
      }
    }

    foreach ($bGames as $g) {
      foreach ([$g->home->teamId, $g->away->teamId] as $utTeamId) {
        if ($utTeamId !== null && isset($teamMap[$utTeamId])) {
          $uoId = (int) $teamMap[$utTeamId];
          if (!isset($seen[$uoId])) {
            $seen[$uoId] = true;
            $orderedIds[] = $uoId;
          }
        }
      }
    }

    return $orderedIds;
  }

  /**
   * @param int[] $orderedUoTeamIds
   */
  private function syncPoolTeams(int $poolId, array $orderedUoTeamIds): void {
    $existingRows = DBQueryToArray(sprintf("SELECT team FROM uo_team_pool WHERE pool=%d", $poolId));
    $existingSet = [];
    foreach ($existingRows as $r) {
      $existingSet[(int) $r['team']] = true;
    }

    $desiredSet = [];
    $rank = 1;
    foreach ($orderedUoTeamIds as $uoTeamId) {
      $uoTeamId = (int) $uoTeamId;
      $desiredSet[$uoTeamId] = true;
      if (isset($existingSet[$uoTeamId])) {
        DBQuery(sprintf("UPDATE uo_team_pool SET `rank`=%d WHERE pool=%d AND team=%d", $rank, $poolId, $uoTeamId));
      } else {
        DBQuery(sprintf("INSERT INTO uo_team_pool (team, pool, `rank`, activerank) VALUES (%d, %d, %d, %d)", $uoTeamId, $poolId, $rank, $rank));
      }
      $rank++;
    }

    foreach ($existingSet as $oldTeamId => $_) {
      if (!isset($desiredSet[$oldTeamId])) {
        DBQuery(sprintf("DELETE FROM uo_team_pool WHERE pool=%d AND team=%d", $poolId, (int) $oldTeamId));
      }
    }
  }
}

readonly class UtMoveSlot {
  public function __construct(
    public int $torank,
    public UtOpponent $opponent,
    public ?string $gameId = null,
    public ?UtGameSide $side = null
  ) {}
}
