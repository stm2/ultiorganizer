<?php
declare(strict_types=1);

/**
 * Typed summary object returned by UtSyncService::sync().
 */

class UtEntityCounts {
  public int $series = 0;
  public int $teams = 0;
  public int $pools = 0;
  public int $games = 0;
  public int $reservations = 0;
}

class UtSyncResult {
  /** @var int[] */
  public array $seriesIds = [];
  /** @var int[] */
  public array $poolIds = [];
  /** @var string[] */
  public array $warnings = [];

  public readonly UtEntityCounts $created;
  public readonly UtEntityCounts $updated;
  public readonly UtEntityCounts $deleted;

  public function __construct(public readonly string $seasonId) {
    $this->created = new UtEntityCounts();
    $this->updated = new UtEntityCounts();
    $this->deleted = new UtEntityCounts();
  }
}
