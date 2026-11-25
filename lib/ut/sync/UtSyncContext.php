<?php
declare(strict_types=1);

/**
 * Shared immutable context for a single UtSyncService::sync() run.
 */

readonly class UtSyncContext {
  public string $tournamentId;
  public string $seasonScope;

  public function __construct(
    public UtTournament $tournament,
    public string $seasonId,
    public string $timezone,
    public UtSyncResult $result
  ) {
    $this->tournamentId = $tournament->id;
    $this->seasonScope = 'season:' . $seasonId;
  }

  public function seriesScope(int $seriesId): string {
    return 'series:' . $seriesId;
  }
}
