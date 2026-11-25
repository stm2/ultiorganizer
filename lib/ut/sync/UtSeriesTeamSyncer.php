<?php
declare(strict_types=1);

/**
 * Syncs Ultimate Tournament divisions and teams into uo_series and uo_team.
 */

readonly class UtSeriesTeamSyncer {
  public function __construct(private UtMapRepository $mapRepo) {}

  public function syncSeries(
    UtSyncContext $ctx,
    UtDivision $utDiv,
    int $orderIndex,
    UtDivisionMapping $mapping,
    bool $canCreateSeries
  ): int {
    $utDivId = $utDiv->id;
    $targetSeriesId = $mapping->targetSeriesId;
    $customName = trim($mapping->seriesName);

    $existingLink = $this->mapRepo->findSeriesForDivision($ctx->tournamentId, $utDivId);
    if ($existingLink !== null) {
      if ($existingLink->seasonId !== $ctx->seasonId || $targetSeriesId !== $existingLink->seriesId) {
        $divLabel = $utDiv->name !== '' ? $utDiv->name : $utDivId;
        throw new InvalidArgumentException(sprintf(
          'Division "%s" is already imported into series "%s" (#%d, season "%s") and cannot be imported into a different series.',
          $divLabel,
          $existingLink->seriesName,
          $existingLink->seriesId,
          $existingLink->seasonId
        ));
      }
    } else {
      $this->mapRepo->deleteDivisionMappings($ctx->tournamentId, $utDivId);
    }

    $defaultName = $ctx->tournament->name . ' - ' . ($utDiv->name !== '' ? $utDiv->name : 'Division');
    $newName = mb_substr($customName !== '' ? $customName : $defaultName, 0, 50);

    if ($targetSeriesId !== null && $targetSeriesId > 0) {
      $seriesId = $targetSeriesId;
      $existing = SeriesInfo($seriesId);
      if (empty($existing) || (string) $existing['season'] !== $ctx->seasonId) {
        throw new RuntimeException('Selected target series ' . $seriesId . ' does not belong to season ' . $ctx->seasonId);
      }
      if (!ut_has_edit_series_right($seriesId)) {
        throw new RuntimeException('Insufficient rights to edit series ' . $seriesId);
      }
      if ($customName !== '' && $customName !== $existing['name']) {
        DBQuery(sprintf(
          "UPDATE uo_series SET name='%s', valid=1 WHERE series_id=%d",
          mysql_adapt_real_escape_string(mb_substr($customName, 0, 50)),
          $seriesId
        ));
      }
      $this->mapRepo->setMappedId($ctx->tournamentId, $ctx->seasonScope, 'division', $utDivId, $seriesId);
      $ctx->result->updated->series++;
      return $seriesId;
    }

    if (!$canCreateSeries) {
      throw new RuntimeException('You do not have permission to create a new series in season ' . $ctx->seasonId);
    }

    $ordering = chr(65 + ($orderIndex % 26));
    $seriesId = (int) DBQueryInsert(sprintf(
      "INSERT INTO uo_series (name, type, ordering, season, valid) VALUES ('%s', '', '%s', '%s', 1)",
      mysql_adapt_real_escape_string($newName),
      mysql_adapt_real_escape_string($ordering),
      mysql_adapt_real_escape_string($ctx->seasonId)
    ));
    $this->mapRepo->setMappedId($ctx->tournamentId, $ctx->seasonScope, 'division', $utDivId, $seriesId);
    $ctx->result->created->series++;
    return $seriesId;
  }

  /**
   * @param UtTeam[] $divTeams
   * @return array<string, int>
   */
  public function syncTeams(
    UtSyncContext $ctx,
    int $seriesId,
    array $divTeams
  ): array {
    $seriesScope = $ctx->seriesScope($seriesId);
    $teamMap = [];
    usort($divTeams, static fn(UtTeam $a, UtTeam $b): int => ($a->order ?? 0) <=> ($b->order ?? 0));

    foreach ($divTeams as $idx => $t) {
      $utTeamId = $t->id;
      $name = mb_substr($t->name, 0, 50);
      $abbr = '';
      $rank = $t->order !== null ? ($t->order + 1) : ($idx + 1);

      $mappedId = $this->mapRepo->getMappedId($ctx->tournamentId, $seriesScope, 'team', $utTeamId);
      $exists = false;
      if ($mappedId !== null) {
        $row = DBQueryToRow(sprintf("SELECT team_id FROM uo_team WHERE team_id=%d AND series=%d", (int) $mappedId, $seriesId));
        if ($row) {
          $exists = true;
        }
      }

      if ($exists) {
        DBQuery(sprintf(
          "UPDATE uo_team SET name='%s', abbreviation='%s', `rank`=%d, valid=1, series=%d WHERE team_id=%d",
          mysql_adapt_real_escape_string($name),
          mysql_adapt_real_escape_string($abbr),
          $rank,
          $seriesId,
          (int) $mappedId
        ));
        $teamMap[$utTeamId] = (int) $mappedId;
        $ctx->result->updated->teams++;
      } else {
        $newId = (int) DBQueryInsert(sprintf(
          "INSERT INTO uo_team (name, abbreviation, `rank`, valid, series) VALUES ('%s', '%s', %d, 1, %d)",
          mysql_adapt_real_escape_string($name),
          mysql_adapt_real_escape_string($abbr),
          $rank,
          $seriesId
        ));
        $this->mapRepo->setMappedId($ctx->tournamentId, $seriesScope, 'team', $utTeamId, $newId);
        $teamMap[$utTeamId] = $newId;
        $ctx->result->created->teams++;
      }
    }

    return $teamMap;
  }
}
