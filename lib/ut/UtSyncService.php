<?php
declare(strict_types=1);

/**
 * Idempotent sync orchestrator for Ultimate Tournament -> Ultiorganizer.
 */

readonly class UtSyncService {
  private UtMapRepository $mapRepo;
  private UtNaming $naming;
  private UtReservationSyncer $reservationSyncer;
  private UtSeriesTeamSyncer $seriesTeamSyncer;
  private UtBracketPlanner $bracketPlanner;
  private UtPoolSyncer $poolSyncer;
  private UtGameSyncer $gameSyncer;
  private UtDivisionPruner $divisionPruner;

  public function __construct(?UtMapRepository $mapRepo = null, ?UtNaming $naming = null) {
    $this->mapRepo = $mapRepo ?? new UtMapRepository();
    $this->naming = $naming ?? new UtNaming();
    $this->reservationSyncer = new UtReservationSyncer($this->mapRepo);
    $this->seriesTeamSyncer = new UtSeriesTeamSyncer($this->mapRepo);
    $this->bracketPlanner = new UtBracketPlanner($this->naming);
    $this->poolSyncer = new UtPoolSyncer($this->mapRepo, $this->naming);
    $this->gameSyncer = new UtGameSyncer($this->mapRepo, $this->naming);
    $this->divisionPruner = new UtDivisionPruner($this->mapRepo);
  }

  /**
   * Syncs selected divisions of a UT tournament into an existing Ultiorganizer season.
   */
  public function sync(UtTournamentBundle $bundle, UtSyncOptions $options): UtSyncResult {
    $this->mapRepo->ensureTable();

    $tmTournamentId = $bundle->tournament->id;
    if ($tmTournamentId === '') {
      throw new InvalidArgumentException('Tournament bundle is missing tournament.id.');
    }

    $seasonId = $options->seasonId;
    if ($seasonId === '') {
      throw new InvalidArgumentException('Target season_id is required.');
    }

    $seasonInfo = SeasonInfo($seasonId);
    if (empty($seasonInfo)) {
      throw new RuntimeException('Target Ultiorganizer season not found: ' . $seasonId);
    }

    $timezone = $options->timezone !== ''
      ? $options->timezone
      : (!empty($seasonInfo['timezone']) ? (string) $seasonInfo['timezone'] : date_default_timezone_get());
    if ($timezone === '') {
      $timezone = 'Europe/Berlin';
    }

    $divisionMappings = $options->divisionMappings;
    if (empty($divisionMappings)) {
      throw new InvalidArgumentException('At least one division mapping must be selected.');
    }

    DBTransaction();
    try {
      if (empty($seasonInfo['timezone'])) {
        DBQuery(sprintf(
          "UPDATE uo_season SET timezone='%s' WHERE season_id='%s'",
          mysql_adapt_real_escape_string($timezone),
          mysql_adapt_real_escape_string($seasonId)
        ));
      }

      $result = new UtSyncResult($seasonId);
      $ctx = new UtSyncContext($bundle->tournament, $seasonId, $timezone, $result);

      $divisionsById = self::indexById($bundle->divisions);
      $fieldsById = self::indexById($bundle->fields);
      $bracketsById = self::indexById($bundle->brackets);

      // Location & season-scoped field reservations
      $locationId = $this->reservationSyncer->resolveLocation($ctx, count($bundle->fields), $options->locationId);
      $reservationMap = $this->reservationSyncer->syncReservations($ctx, $locationId, $fieldsById, $bundle->games, $divisionMappings);

      // Process each selected UT division
      $allSyncedPoolIds = [];
      $divIndex = 0;

      foreach ($divisionMappings as $utDivId => $mapping) {
        $utDivId = (string) $utDivId;
        if (!isset($divisionsById[$utDivId])) {
          $result->warnings[] = 'Selected division not found in UT response: ' . $utDivId;
          continue;
        }
        $utDiv = $divisionsById[$utDivId];

        $seriesId = $this->seriesTeamSyncer->syncSeries($ctx, $utDiv, $divIndex++, $mapping, $options->canCreateSeries);
        $result->seriesIds[] = $seriesId;

        $divTeams = self::filterByDivision($bundle->teams, $utDivId);
        $divBrackets = self::filterByDivision($bundle->brackets, $utDivId);
        $divGames = self::filterByDivision($bundle->games, $utDivId);

        $teamMap = $this->seriesTeamSyncer->syncTeams($ctx, $seriesId, $divTeams);
        $plan = $this->bracketPlanner->plan($divBrackets, $bundle->bracketTeams, $divGames);
        $poolResult = $this->poolSyncer->syncPoolsAndMoves($ctx, $seriesId, $plan, $teamMap);

        $allSyncedPoolIds = array_merge($allSyncedPoolIds, $poolResult->allPoolIds);

        $gameResult = $this->gameSyncer->syncGames($ctx, $seriesId, $divGames, $teamMap, $poolResult, $bracketsById, $reservationMap);
        $activeSchedUtKeys = array_merge($poolResult->activeSchedUtKeys, $gameResult->activeSchedUtKeys);

        $this->divisionPruner->pruneDivision(
          $ctx,
          $seriesId,
          $gameResult->activeGameUtIds,
          $poolResult->allUtKeys,
          array_keys($teamMap),
          $activeSchedUtKeys
        );
      }

      $this->reservationSyncer->pruneUnusedReservations($ctx, array_keys($reservationMap));

      // Native standings calculation
      $allSyncedPoolIds = array_values(array_unique(array_map('intval', $allSyncedPoolIds)));
      $result->poolIds = $allSyncedPoolIds;
      if (!empty($allSyncedPoolIds)) {
        global $include_prefix;
        $prefix = isset($include_prefix) ? (string) $include_prefix : '';
        include_once $prefix . 'lib/standings.functions.php';
        foreach ($allSyncedPoolIds as $poolId) {
          ResolvePoolStandings($poolId);
        }
      }

      DBCommit();
      return $result;
    } catch (Throwable $e) {
      DBRollback();
      throw $e;
    }
  }

  private static function indexById(array $items): array {
    $indexed = [];
    foreach ($items as $item) {
      if ($item->id !== '') {
        $indexed[$item->id] = $item;
      }
    }
    return $indexed;
  }

  private static function filterByDivision(array $items, string $utDivId): array {
    return array_values(array_filter(
      $items,
      static fn(object $item): bool => $item->divisionId === $utDivId
    ));
  }
}
