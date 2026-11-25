<?php
declare(strict_types=1);

/**
 * Syncs Ultimate Tournament locations and field/date reservations into uo_location and uo_reservation.
 */

readonly class UtReservationSyncer {
  public function __construct(private UtMapRepository $mapRepo) {}

  public function resolveLocation(
    UtSyncContext $ctx,
    int $fieldCount,
    ?int $optionLocationId
  ): int {
    if ($optionLocationId !== null && $optionLocationId > 0) {
      return $optionLocationId;
    }

    $mappedId = $this->mapRepo->getMappedId($ctx->tournamentId, $ctx->seasonScope, 'location', 'default');
    if ($mappedId !== null) {
      $existing = DBQueryToRow(sprintf("SELECT id FROM uo_location WHERE id=%d", (int) $mappedId));
      if ($existing) {
        return (int) $mappedId;
      }
    }

    $locName = mb_substr($ctx->tournament->name, 0, 50);
    $fieldsNum = max(1, $fieldCount);
    $locId = (int) DBQueryInsert(sprintf(
      "INSERT INTO uo_location (name, fields, indoor) VALUES ('%s', %d, 0)",
      mysql_adapt_real_escape_string($locName),
      $fieldsNum
    ));
    $this->mapRepo->setMappedId($ctx->tournamentId, $ctx->seasonScope, 'location', 'default', $locId);
    return $locId;
  }

  /**
   * @param array<string, UtField> $fieldsById
   * @param UtGame[] $games
   * @param array<string, UtDivisionMapping> $divisionMappings
   * @return array<string, int>
   */
  public function syncReservations(
    UtSyncContext $ctx,
    int $locationId,
    array $fieldsById,
    array $games,
    array $divisionMappings
  ): array {
    /** @var array<string, UtReservationWindow> $neededWindows */
    $neededWindows = [];
    $tFrom = UtApiClient::parseTimestampToLocalDatetime($ctx->tournament->from, $ctx->timezone);

    foreach ($games as $g) {
      $divId = $g->divisionId;
      if ($divId === null || !isset($divisionMappings[$divId])) {
        continue;
      }
      $fieldId = $g->fieldId;
      if ($fieldId === null || !isset($fieldsById[$fieldId])) {
        continue;
      }
      $fieldName = $fieldsById[$fieldId]->name;
      $startDt = UtApiClient::parseTimestampToLocalDatetime($g->startTime, $ctx->timezone);
      $endDt = UtApiClient::parseTimestampToLocalDatetime($g->endTime, $ctx->timezone);

      $dateKey = $startDt !== null ? substr($startDt, 0, 10) : 'undated';
      $resKey = $fieldId . '|' . $dateKey;

      if (!isset($neededWindows[$resKey])) {
        $neededWindows[$resKey] = new UtReservationWindow(
          fieldName: $fieldName,
          date: $dateKey === 'undated' ? ($tFrom ? substr($tFrom, 0, 10) : date('Y-m-d')) : $dateKey,
          minTime: $startDt,
          maxTime: $endDt ?: $startDt,
        );
      } else {
        $neededWindows[$resKey]->extend($startDt, $endDt);
      }
    }

    $resMap = [];
    $groupName = mb_substr($ctx->tournament->name, 0, 50);

    foreach ($neededWindows as $resKey => $window) {
      $dateStr = $window->date;
      $startTime = $window->minTime ?: ($dateStr . ' 08:00:00');
      $endTime = $window->maxTime ?: ($dateStr . ' 20:00:00');
      if ($endTime <= $startTime) {
        $endTime = (new DateTimeImmutable($startTime))->modify('+1 hour')->format('Y-m-d H:i:s');
      }
      $fieldName = mb_substr($window->fieldName, 0, 50);

      $mappedId = $this->mapRepo->getMappedId($ctx->tournamentId, $ctx->seasonScope, 'reservation', $resKey);
      $exists = false;
      if ($mappedId !== null) {
        $row = DBQueryToRow(sprintf("SELECT id FROM uo_reservation WHERE id=%d", (int) $mappedId));
        if ($row) {
          $exists = true;
        }
      }

      if ($exists) {
        DBQuery(sprintf(
          "UPDATE uo_reservation SET location=%d, fieldname='%s', reservationgroup='%s', starttime='%s', endtime='%s', date='%s 00:00:00', season='%s' WHERE id=%d",
          $locationId,
          mysql_adapt_real_escape_string($fieldName),
          mysql_adapt_real_escape_string($groupName),
          mysql_adapt_real_escape_string($startTime),
          mysql_adapt_real_escape_string($endTime),
          mysql_adapt_real_escape_string($dateStr),
          mysql_adapt_real_escape_string($ctx->seasonId),
          (int) $mappedId
        ));
        $resMap[$resKey] = (int) $mappedId;
        $ctx->result->updated->reservations++;
      } else {
        $newId = (int) DBQueryInsert(sprintf(
          "INSERT INTO uo_reservation (location, fieldname, reservationgroup, starttime, endtime, date, season)
           VALUES (%d, '%s', '%s', '%s', '%s', '%s 00:00:00', '%s')",
          $locationId,
          mysql_adapt_real_escape_string($fieldName),
          mysql_adapt_real_escape_string($groupName),
          mysql_adapt_real_escape_string($startTime),
          mysql_adapt_real_escape_string($endTime),
          mysql_adapt_real_escape_string($dateStr),
          mysql_adapt_real_escape_string($ctx->seasonId)
        ));
        $this->mapRepo->setMappedId($ctx->tournamentId, $ctx->seasonScope, 'reservation', $resKey, $newId);
        $resMap[$resKey] = $newId;
        $ctx->result->created->reservations++;
      }
    }

    return $resMap;
  }

  /**
   * @param string[] $activeResKeys
   */
  public function pruneUnusedReservations(UtSyncContext $ctx, array $activeResKeys): void {
    $activeSet = array_flip($activeResKeys);
    $mappedRes = $this->mapRepo->getAllMapped($ctx->tournamentId, $ctx->seasonScope, 'reservation');
    foreach ($mappedRes as $resKey => $uoId) {
      if (!isset($activeSet[$resKey])) {
        $resId = (int) $uoId;
        $inUse = (int) DBQueryToValue(sprintf("SELECT COUNT(*) FROM uo_game WHERE reservation=%d", $resId));
        if ($inUse === 0) {
          DBQuery(sprintf("DELETE FROM uo_reservation WHERE id=%d", $resId));
          $this->mapRepo->deleteMapped($ctx->tournamentId, $ctx->seasonScope, 'reservation', $resKey);
          $ctx->result->deleted->reservations++;
        }
      }
    }
  }
}

class UtReservationWindow {
  public function __construct(
    public readonly string $fieldName,
    public readonly string $date,
    public ?string $minTime,
    public ?string $maxTime
  ) {}

  public function extend(?string $startDt, ?string $endDt): void {
    if ($startDt !== null && ($this->minTime === null || $startDt < $this->minTime)) {
      $this->minTime = $startDt;
    }
    $candidateEnd = $endDt ?: $startDt;
    if ($candidateEnd !== null && ($this->maxTime === null || $candidateEnd > $this->maxTime)) {
      $this->maxTime = $candidateEnd;
    }
  }
}
