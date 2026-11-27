<?php
declare(strict_types=1);

/**
 * Typed configuration objects passed into UtSyncService::sync().
 */

readonly class UtDivisionMapping {
  public function __construct(
    /** Target `uo_series.series_id`, or null to create a new series. */
    public ?int $targetSeriesId = null,
    public string $seriesName = ''
  ) {}
}

readonly class UtSyncOptions {
  /**
   * @param array<string, UtDivisionMapping> $divisionMappings
   */
  public function __construct(
    public string $seasonId,
    public array $divisionMappings,
    /** Target `uo_location.id`, or null to auto-create/reuse the tournament location. */
    public ?int $locationId = null,
    public string $timezone = '',
    public bool $canCreateSeries = false
  ) {}
}
