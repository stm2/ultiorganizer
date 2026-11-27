<?php
declare(strict_types=1);

/**
 * Typed output of UtGameSyncer::syncGames().
 */

readonly class UtGameSyncResult {
  /**
   * @param string[] $activeGameUtIds
   * @param string[] $activeSchedUtKeys
   */
  public function __construct(
    public array $activeGameUtIds,
    public array $activeSchedUtKeys
  ) {}
}
