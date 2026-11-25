<?php
declare(strict_types=1);

/**
 * Repository for the self-bootstrapping uo_ut_map ID mapping table and shared scheduling names.
 */

readonly class UtSeriesLink {
  public function __construct(
    public string $utTournamentId,
    public string $seasonId,
    public int $seriesId,
    public string $seriesName,
    public bool $valid,
    public ?string $lastSyncedAt = null,
  ) {}
}

class UtMapRepository {
  private static ?bool $tableExistsCache = null;

  public function tableExists(): bool {
    if (self::$tableExistsCache === null) {
      self::$tableExistsCache = DBQueryRowCount("SHOW TABLES LIKE 'uo_ut_map'") > 0;
    }
    return self::$tableExistsCache;
  }

  public function ensureTable(): void {
    $sql = "CREATE TABLE IF NOT EXISTS `uo_ut_map` (
      `ut_tournament_id` varchar(64) NOT NULL,
      `scope_key` varchar(64) NOT NULL DEFAULT '',
      `entity_type` varchar(32) NOT NULL,
      `ut_id` varchar(128) NOT NULL,
      `uo_id` varchar(64) NOT NULL,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`ut_tournament_id`, `scope_key`, `entity_type`, `ut_id`),
      INDEX `idx_uo_lookup` (`ut_tournament_id`, `scope_key`, `entity_type`, `uo_id`),
      INDEX `idx_scope_entity_uo` (`scope_key`, `entity_type`, `uo_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci";
    DBQuery($sql);
    self::$tableExistsCache = true;
  }

  public function findTournamentForSeries(int $seriesId): ?UtSeriesLink {
    if ($seriesId <= 0 || !$this->tableExists()) {
      return null;
    }

    $seriesRow = DBQueryToRow(sprintf(
      "SELECT series_id, season, name, valid FROM uo_series WHERE series_id=%d",
      $seriesId
    ));
    if (!$seriesRow || empty($seriesRow['season'])) {
      return null;
    }

    $seasonId = (string) $seriesRow['season'];
    $mapRow = DBQueryToRow(sprintf(
      "SELECT ut_tournament_id, updated_at
       FROM uo_ut_map
       WHERE scope_key='%s'
         AND entity_type='division'
         AND uo_id='%s'
       LIMIT 1",
      mysql_adapt_real_escape_string('season:' . $seasonId),
      mysql_adapt_real_escape_string((string) $seriesId)
    ));
    if (!$mapRow || empty($mapRow['ut_tournament_id'])) {
      return null;
    }

    return new UtSeriesLink(
      utTournamentId: (string) $mapRow['ut_tournament_id'],
      seasonId: $seasonId,
      seriesId: (int) $seriesRow['series_id'],
      seriesName: (string) ($seriesRow['name'] ?? ''),
      valid: ((int) ($seriesRow['valid'] ?? 0)) === 1,
      lastSyncedAt: isset($mapRow['updated_at']) ? (string) $mapRow['updated_at'] : null,
    );
  }

  public function getMappedId(string $tournamentId, string $scopeKey, string $entityType, string $utId): ?string {
    $query = sprintf(
      "SELECT uo_id FROM uo_ut_map WHERE ut_tournament_id='%s' AND scope_key='%s' AND entity_type='%s' AND ut_id='%s'",
      mysql_adapt_real_escape_string($tournamentId),
      mysql_adapt_real_escape_string($scopeKey),
      mysql_adapt_real_escape_string($entityType),
      mysql_adapt_real_escape_string($utId)
    );
    $row = DBQueryToRow($query);
    return ($row && isset($row['uo_id'])) ? (string) $row['uo_id'] : null;
  }

  public function setMappedId(string $tournamentId, string $scopeKey, string $entityType, string $utId, int|string $uoId): void {
    $query = sprintf(
      "INSERT INTO uo_ut_map (ut_tournament_id, scope_key, entity_type, ut_id, uo_id)
       VALUES ('%s', '%s', '%s', '%s', '%s')
       ON DUPLICATE KEY UPDATE uo_id=VALUES(uo_id), updated_at=CURRENT_TIMESTAMP",
      mysql_adapt_real_escape_string($tournamentId),
      mysql_adapt_real_escape_string($scopeKey),
      mysql_adapt_real_escape_string($entityType),
      mysql_adapt_real_escape_string($utId),
      mysql_adapt_real_escape_string((string) $uoId)
    );
    DBQuery($query);
  }

  /**
   * Returns [ut_id => uo_id] for all mapped rows of a given tournament, scope, and type.
   */
  public function getAllMapped(string $tournamentId, string $scopeKey, string $entityType): array {
    $query = sprintf(
      "SELECT ut_id, uo_id FROM uo_ut_map WHERE ut_tournament_id='%s' AND scope_key='%s' AND entity_type='%s'",
      mysql_adapt_real_escape_string($tournamentId),
      mysql_adapt_real_escape_string($scopeKey),
      mysql_adapt_real_escape_string($entityType)
    );
    $rows = DBQueryToArray($query);
    $map = [];
    foreach ($rows as $row) {
      $map[(string) $row['ut_id']] = (string) $row['uo_id'];
    }
    return $map;
  }

  public function deleteMapped(string $tournamentId, string $scopeKey, string $entityType, string $utId): void {
    $query = sprintf(
      "DELETE FROM uo_ut_map WHERE ut_tournament_id='%s' AND scope_key='%s' AND entity_type='%s' AND ut_id='%s'",
      mysql_adapt_real_escape_string($tournamentId),
      mysql_adapt_real_escape_string($scopeKey),
      mysql_adapt_real_escape_string($entityType),
      mysql_adapt_real_escape_string($utId)
    );
    DBQuery($query);
  }

  public function findSeriesForDivision(string $tournamentId, string $utDivisionId): ?UtSeriesLink {
    if (!$this->tableExists()) {
      return null;
    }

    $row = DBQueryToRow(sprintf(
      "SELECT m.ut_tournament_id, m.updated_at, s.season, s.series_id, s.name, s.valid
       FROM uo_ut_map m
       INNER JOIN uo_series s ON s.series_id = CAST(m.uo_id AS UNSIGNED)
       WHERE m.ut_tournament_id='%s'
         AND m.entity_type='division'
         AND m.ut_id='%s'
       LIMIT 1",
      mysql_adapt_real_escape_string($tournamentId),
      mysql_adapt_real_escape_string($utDivisionId)
    ));
    if (!$row) {
      return null;
    }

    return new UtSeriesLink(
      utTournamentId: (string) $row['ut_tournament_id'],
      seasonId: (string) $row['season'],
      seriesId: (int) $row['series_id'],
      seriesName: (string) ($row['name'] ?? ''),
      valid: ((int) ($row['valid'] ?? 0)) === 1,
      lastSyncedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
    );
  }

  /**
   * Returns [ut_division_id => UtSeriesLink] for all divisions of this tournament currently mapped to an existing UO series.
   *
   * @return array<string, UtSeriesLink>
   */
  public function getMappedDivisionsForTournament(string $tournamentId): array {
    if (!$this->tableExists()) {
      return [];
    }

    $rows = DBQueryToArray(sprintf(
      "SELECT m.ut_id, m.ut_tournament_id, m.updated_at, s.season, s.series_id, s.name, s.valid
       FROM uo_ut_map m
       INNER JOIN uo_series s ON s.series_id = CAST(m.uo_id AS UNSIGNED)
       WHERE m.ut_tournament_id='%s'
         AND m.entity_type='division'",
      mysql_adapt_real_escape_string($tournamentId)
    ));
    $map = [];
    foreach ($rows as $row) {
      $map[(string) $row['ut_id']] = new UtSeriesLink(
        utTournamentId: (string) $row['ut_tournament_id'],
        seasonId: (string) $row['season'],
        seriesId: (int) $row['series_id'],
        seriesName: (string) ($row['name'] ?? ''),
        valid: ((int) ($row['valid'] ?? 0)) === 1,
        lastSyncedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
      );
    }
    return $map;
  }

  /**
   * Returns [series_id => ['teams' => int, 'games' => int, 'finished' => int]] for the given UO series IDs.
   *
   * @param int[] $seriesIds
   * @return array<int, array{teams: int, games: int, finished: int}>
   */
  public function getSeriesStats(array $seriesIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $seriesIds), static fn(int $id): bool => $id > 0)));
    if (empty($ids)) {
      return [];
    }

    $stats = [];
    foreach ($ids as $id) {
      $stats[$id] = ['teams' => 0, 'games' => 0, 'finished' => 0];
    }

    $inSql = implode(',', $ids);
    $teamRows = DBQueryToArray(
      "SELECT series AS series_id, COUNT(team_id) AS team_count
       FROM uo_team
       WHERE series IN ($inSql)
       GROUP BY series"
    );
    foreach ($teamRows as $row) {
      $sid = (int) $row['series_id'];
      if (isset($stats[$sid])) {
        $stats[$sid]['teams'] = (int) $row['team_count'];
      }
    }

    $gameRows = DBQueryToArray(
      "SELECT p.series AS series_id,
              COUNT(g.game_id) AS total_games,
              SUM(CASE WHEN g.hasstarted = 1 AND g.isongoing = 0 THEN 1 ELSE 0 END) AS finished_games
       FROM uo_pool p
       INNER JOIN uo_game g ON g.pool = p.pool_id
       WHERE p.series IN ($inSql)
       GROUP BY p.series"
    );
    foreach ($gameRows as $row) {
      $sid = (int) $row['series_id'];
      if (isset($stats[$sid])) {
        $stats[$sid]['games'] = (int) $row['total_games'];
        $stats[$sid]['finished'] = (int) $row['finished_games'];
      }
    }

    return $stats;
  }

  public function deleteDivisionMappings(string $tournamentId, string $utDivisionId): void {
    DBQuery(sprintf(
      "DELETE FROM uo_ut_map WHERE ut_tournament_id='%s' AND entity_type='division' AND ut_id='%s'",
      mysql_adapt_real_escape_string($tournamentId),
      mysql_adapt_real_escape_string($utDivisionId)
    ));
  }

  /**
   * Creates or updates a uo_scheduling_name entry for placeholder slots or elimination game labels.
   */
  public function upsertSchedulingName(string $tournamentId, string $seriesScope, string $utKey, string $label): int {
    $cleanLabel = mb_substr(trim($label) ?: 'TBD', 0, 100);
    $mappedId = $this->getMappedId($tournamentId, $seriesScope, 'sched_name', $utKey);
    if ($mappedId !== null) {
      $row = DBQueryToRow(sprintf("SELECT scheduling_id FROM uo_scheduling_name WHERE scheduling_id=%d", (int) $mappedId));
      if ($row) {
        DBQuery(sprintf(
          "UPDATE uo_scheduling_name SET name='%s' WHERE scheduling_id=%d",
          mysql_adapt_real_escape_string($cleanLabel),
          (int) $mappedId
        ));
        return (int) $mappedId;
      }
    }

    $newId = (int) DBQueryInsert(sprintf(
      "INSERT INTO uo_scheduling_name (name) VALUES ('%s')",
      mysql_adapt_real_escape_string($cleanLabel)
    ));
    $this->setMappedId($tournamentId, $seriesScope, 'sched_name', $utKey, $newId);
    return $newId;
  }
}
