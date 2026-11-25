<?php
declare(strict_types=1);

/**
 * Fork compatibility helpers for the Ultimate Tournament sync module.
 */

function ut_has_edit_series_right(int $seriesId): bool {
  if (function_exists('hasEditSeriesRight')) {
    return (bool) hasEditSeriesRight($seriesId);
  }
  if (function_exists('isSuperAdmin') && isSuperAdmin()) {
    return true;
  }
  if (function_exists('SeriesSeasonId') && function_exists('isSeasonAdmin')) {
    $season = SeriesSeasonId($seriesId);
    if (isSeasonAdmin($season)) {
      return true;
    }
  }
  return isset($_SESSION['userproperties']['userrole']['seriesadmin'][$seriesId]);
}

function ut_ensure_super_admin(string $title): void {
  if (function_exists('ensureSuperAdmin')) {
    ensureSuperAdmin($title);
    return;
  }
  if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    die('Insufficient user rights');
  }
}

function ut_ensure_season_admin(string $seasonId, string $title): void {
  if (function_exists('ensureSeasonAdmin')) {
    ensureSeasonAdmin($seasonId, $title);
    return;
  }
  if (!function_exists('isSeasonAdmin') || !isSeasonAdmin($seasonId)) {
    die('Insufficient user rights');
  }
}

function ut_ensure_edit_series_right(int $seriesId, string $title): void {
  if (function_exists('ensureEditSeriesRight')) {
    ensureEditSeriesRight($seriesId, $title);
    return;
  }
  if (!ut_has_edit_series_right($seriesId)) {
    die('Insufficient user rights');
  }
}

function ut_pool_color(int $index, string $seed): string {
  if (function_exists('PoolColor')) {
    return (string) PoolColor($index, $seed);
  }
  return 'FFFFFF';
}
