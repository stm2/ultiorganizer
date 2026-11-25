<?php
declare(strict_types=1);

/**
 * Typed output of UtPoolSyncer::syncPoolsAndMoves().
 */

readonly class UtPoolSyncResult {
  /**
   * @param array<string, int> $poolMap
   * @param string[] $allUtKeys
   * @param int[] $allPoolIds
   * @param array<string, array<string, int>> $gameSlotSchedIds
   * @param array<string, string> $gameNameLabels
   * @param string[] $activeSchedUtKeys
   */
  public function __construct(
    public array $poolMap,
    public array $allUtKeys,
    public array $allPoolIds,
    public array $gameSlotSchedIds,
    public array $gameNameLabels,
    public array $activeSchedUtKeys
  ) {}
}
