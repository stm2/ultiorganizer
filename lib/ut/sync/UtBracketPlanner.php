<?php
declare(strict_types=1);

/**
 * Pure PHP bracket-graph planner (zero SQL): transforms UT brackets, bracketTeams,
 * and games into ordered Ultiorganizer pool specifications and playoff trees.
 */

readonly class UtBracketPlanner {
  private UtNaming $naming;

  public function __construct(?UtNaming $naming = null) {
    $this->naming = $naming ?? new UtNaming();
  }

  /**
   * @param UtBracket[] $divBrackets
   * @param UtBracketTeam[] $bracketTeams
   * @param UtGame[] $divGames
   */
  public function plan(array $divBrackets, array $bracketTeams, array $divGames): UtBracketPlan {
    /** @var array<string, UtBracket> $bracketsById */
    $bracketsById = [];
    $minX = PHP_INT_MAX;
    foreach ($divBrackets as $b) {
      $bracketsById[$b->id] = $b;
      if ($b->x < $minX) {
        $minX = $b->x;
      }
    }
    if ($minX === PHP_INT_MAX) {
      $minX = 1;
    }

    // Sort strictly by x ASC, y ASC, alphabetical name ASC, id ASC
    usort($divBrackets, static function (UtBracket $a, UtBracket $b): int {
      if ($a->x !== $b->x) {
        return $a->x <=> $b->x;
      }
      if ($a->y !== $b->y) {
        return $a->y <=> $b->y;
      }
      $cmp = strcasecmp($a->name, $b->name);
      if ($cmp !== 0) {
        return $cmp;
      }
      return strcmp($a->id, $b->id);
    });

    // Group games by parentBracket
    $gamesByBracket = [];
    $unassignedGames = [];
    foreach ($divGames as $g) {
      $bId = $g->parentBracketId;
      if ($bId === null || !isset($bracketsById[$bId])) {
        $unassignedGames[] = $g;
      } else {
        $gamesByBracket[$bId][] = $g;
      }
    }

    foreach ($gamesByBracket as &$bGames) {
      usort($bGames, static fn(UtGame $a, UtGame $b): int =>
        ($a->parentBracketIndex ?? 0) <=> ($b->parentBracketIndex ?? 0)
      );
    }
    unset($bGames);

    // Group bracketTeams by parent bracket
    $bracketTeamsByBracket = [];
    foreach ($bracketTeams as $bt) {
      $bId = $bt->parentBracketId;
      if ($bId !== null) {
        $bracketTeamsByBracket[$bId][] = $bt;
      }
    }

    // Build outgoing playable bracket edges: originBracketId => [destBracketId => true]
    $outgoingPlayable = [];
    foreach ($divGames as $g) {
      $destId = $g->parentBracketId;
      if ($destId === null || !isset($bracketsById[$destId])) {
        continue;
      }
      foreach ([$g->home, $g->away] as $opp) {
        $origId = $opp->origin?->bracketId;
        if ($origId !== null && isset($bracketsById[$origId])) {
          $outgoingPlayable[$origId][$destId] = true;
        }
      }
    }
    foreach ($bracketTeams as $bt) {
      $destId = $bt->parentBracketId;
      $origId = $bt->opponent->origin?->bracketId;
      if ($destId !== null && isset($bracketsById[$destId]) && $origId !== null && isset($bracketsById[$origId])) {
        $outgoingPlayable[$origId][$destId] = true;
      }
    }

    // Plan Ultiorganizer pools in display/placement order.
    // Ultiorganizer pool types: 1 = round-robin group, 2 = playoff / 2-team elimination,
    // 3 = swiss, 4 = cross-match / standalone multi-match elimination.
    // Playoff follower rounds (rounds 2+) are hidden (visible = false); non-initial pools have
    // continuingPool = true, and terminal pools without outgoing brackets have placementPool = true.
    $pools = [];
    $absorbedBrackets = [];

    foreach ($divBrackets as $bracket) {
      $bId = $bracket->id;
      if (isset($absorbedBrackets[$bId])) {
        continue;
      }

      $typeStr = $bracket->type;
      $bx = $bracket->x;
      $by = $bracket->y;
      $bSize = $bracket->size;

      if ($typeStr === UtBracketType::Elimination) {
        $treeRounds = $this->detectFullPlayoffTree($bId, $bracketsById, $outgoingPlayable, $absorbedBrackets);
        if ($treeRounds !== null) {
          $totalRounds = count($treeRounds);
          $rootName = $this->naming->playoffTreeName($by + 1, $by + $bSize);

          foreach ($treeRounds as $roundIdx => $roundBracketIds) {
            $roundNum = $roundIdx + 1;
            $isRoot = ($roundNum === 1);
            $isFinalRound = ($roundNum === $totalRounds);

            $roundGames = [];
            $utOriginOffsets = [];
            $gameOffset = 0;
            foreach ($roundBracketIds as $rbId) {
              $absorbedBrackets[$rbId] = true;
              $utOriginOffsets[$rbId] = $gameOffset;
              foreach ($gamesByBracket[$rbId] ?? [] as $rg) {
                $roundGames[] = $rg;
              }
              $rbSize = $bracketsById[$rbId]->size > 0 ? $bracketsById[$rbId]->size : 2;
              $gameOffset += max(1, (int) ($rbSize / 2));
            }

            $hasExtOut = false;
            if ($isFinalRound) {
              foreach ($roundBracketIds as $rbId) {
                if (!empty($outgoingPlayable[$rbId])) {
                  $hasExtOut = true;
                  break;
                }
              }
            }

            $firstBracket = $bracketsById[$roundBracketIds[0]];
            $roundX = $firstBracket->x;
            $roundY = $firstBracket->y;
            $roundSize = $firstBracket->size > 0 ? $firstBracket->size : 2;
            $utKey = $isRoot ? $bId : ($bId . '|r' . $roundNum);

            $pools[$utKey] = new UtPlannedPool(
              utKey: $utKey,
              utBracketIds: $roundBracketIds,
              name: $isRoot ? $rootName : $this->naming->playoffFollowerName($rootName, $roundY, $roundSize),
              type: UoPoolType::Playoff,
              visible: $isRoot,
              continuingPool: true,
              placementPool: ($isFinalRound && !$hasExtOut),
              sortX: $roundX,
              sortY: $by,
              sortName: $isRoot ? $rootName : ($rootName . ' ' . $roundNum),
              treeRootKey: $bId,
              treeRound: $roundNum,
              games: $roundGames,
              utOriginOffsets: $utOriginOffsets,
              utType: UtBracketType::Elimination,
            );
          }
          continue;
        }

        // Not a full multi-round tree
        $hasOutgoing = !empty($outgoingPlayable[$bId]);
        $name = mb_substr($this->naming->eliminationBase($by, $bSize), 0, 50);

        $pools[$bId] = new UtPlannedPool(
          utKey: $bId,
          utBracketIds: [$bId],
          name: $name,
          type: ($bSize === 2) ? UoPoolType::Playoff : UoPoolType::CrossMatch,
          visible: true,
          continuingPool: true,
          placementPool: !$hasOutgoing,
          sortX: $bx,
          sortY: $by,
          sortName: $name,
          treeRootKey: null,
          treeRound: 0,
          games: $gamesByBracket[$bId] ?? [],
          utOriginOffsets: [$bId => 0],
          utType: UtBracketType::Elimination,
        );
        continue;
      }

      // Group or Swiss bracket
      $rawName = $bracket->name;
      $isInitial = ($bx <= $minX);
      $isSwiss = ($typeStr === UtBracketType::SwissGroup);
      $hasOutgoing = !empty($outgoingPlayable[$bId]);
      $name = $this->naming->groupPoolName($rawName, $isSwiss);

      $pools[$bId] = new UtPlannedPool(
        utKey: $bId,
        utBracketIds: [$bId],
        name: $name,
        type: $isSwiss ? UoPoolType::Swiss : UoPoolType::Group,
        visible: true,
        continuingPool: !$isInitial,
        placementPool: !$hasOutgoing,
        sortX: $bx,
        sortY: $by,
        sortName: $rawName !== '' ? $rawName : $name,
        treeRootKey: null,
        treeRound: 0,
        games: $gamesByBracket[$bId] ?? [],
        utOriginOffsets: [$bId => 0],
        utType: $typeStr,
      );
    }

    if (!empty($unassignedGames)) {
      $utKey = '__unassigned__';
      $name = $this->naming->fallbackPoolName();

      $pools[$utKey] = new UtPlannedPool(
        utKey: $utKey,
        utBracketIds: [],
        name: $name,
        type: UoPoolType::Group,
        visible: true,
        continuingPool: false,
        placementPool: true,
        sortX: 999,
        sortY: 999,
        sortName: $name,
        treeRootKey: null,
        treeRound: 0,
        games: $unassignedGames,
        utOriginOffsets: [],
        utType: UtBracketType::Group,
      );
    }

    // Order all pools (including hidden playoff follower pools) strictly by (x, y, name)
    uasort($pools, static function (UtPlannedPool $a, UtPlannedPool $b): int {
      if ($a->sortX !== $b->sortX) {
        return $a->sortX <=> $b->sortX;
      }
      if ($a->sortY !== $b->sortY) {
        return $a->sortY <=> $b->sortY;
      }
      return strcasecmp($a->sortName, $b->sortName);
    });

    return new UtBracketPlan(
      pools: $pools,
      bracketTeamsByBracket: $bracketTeamsByBracket,
      bracketsById: $bracketsById,
    );
  }

  /**
   * Detects if an elimination bracket is the root of a complete power-of-2 multi-round
   * Ultiorganizer Playoff Tree (e.g. 4 teams / 2 rounds, 8 teams / 3 rounds, 16 teams / 4 rounds).
   *
   * @param array<string, UtBracket> $bracketsById
   * @param array<string, array<string, bool>> $outgoingPlayable
   * @param array<string, bool> $absorbedBrackets
   * @return string[][]|null
   */
  private function detectFullPlayoffTree(
    string $rootId,
    array $bracketsById,
    array $outgoingPlayable,
    array $absorbedBrackets
  ): ?array {
    $root = $bracketsById[$rootId];
    $totalTeams = $root->size;
    if (!in_array($totalTeams, [4, 8, 16], true)) {
      return null;
    }

    $rounds = [[$rootId]];
    $currentRound = [$rootId];
    $currentX = $root->x;

    while (true) {
      $firstSize = $bracketsById[$currentRound[0]]->size;
      if ($firstSize <= 2) {
        break;
      }

      $nextRoundSet = [];
      foreach ($currentRound as $bId) {
        if (empty($outgoingPlayable[$bId])) {
          return null;
        }
        foreach (array_keys($outgoingPlayable[$bId]) as $destId) {
          if (isset($absorbedBrackets[$destId])) {
            return null;
          }
          $dest = $bracketsById[$destId];
          if ($dest->type !== UtBracketType::Elimination || $dest->x !== $currentX + 1) {
            return null;
          }
          $nextRoundSet[$destId] = true;
        }
      }

      $nextRound = array_keys($nextRoundSet);
      $expectedBracketCount = count($currentRound) * 2;
      $expectedSize = (int) ($firstSize / 2);
      if (count($nextRound) !== $expectedBracketCount) {
        return null;
      }

      $sizeSum = 0;
      foreach ($nextRound as $nId) {
        $nSize = $bracketsById[$nId]->size;
        if ($nSize !== $expectedSize) {
          return null;
        }
        $sizeSum += $nSize;
      }
      if ($sizeSum !== $totalTeams) {
        return null;
      }

      usort($nextRound, static fn(string $a, string $b): int =>
        $bracketsById[$a]->y <=> $bracketsById[$b]->y
      );

      $rounds[] = $nextRound;
      $currentRound = $nextRound;
      $currentX++;
    }

    $totalRounds = count($rounds);
    if ($totalRounds < 2 || (1 << $totalRounds) !== $totalTeams) {
      return null;
    }

    return $rounds;
  }
}
