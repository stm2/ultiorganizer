<?php
declare(strict_types=1);

require_once __DIR__ . '/UtCompat.php';
require_once __DIR__ . '/UtConfig.php';
require_once __DIR__ . '/UtMapRepository.php';

/**
 * Renders the Ultimate Tournament attribution banner and quick-resync link on imported series pages.
 */
final class UtBanner {
  private const ALLOWED_VIEWS = ['seriesstatus', 'games', 'poolstatus'];

  public static function renderForCurrentRequest(): string {
    if ((function_exists('getPrintMode') && getPrintMode()) || !in_array((string) ($_GET['view'] ?? ''), self::ALLOWED_VIEWS, true)) {
      return '';
    }

    $seriesId = (int) ($_GET['series'] ?? 0);
    if ($seriesId <= 0 && (int) ($_GET['pool'] ?? 0) > 0) {
      $seriesId = (int) PoolSeries((int) $_GET['pool']);
    }
    if ($seriesId <= 0) {
      return '';
    }

    $link = (new UtMapRepository())->findTournamentForSeries($seriesId);
    $canEdit = $link !== null && ut_has_edit_series_right($link->seriesId);
    if ($link === null || (!$link->valid && !$canEdit)) {
      return '';
    }

    $strings = self::loadStrings();
    $utUrl = UtConfig::loadAppUrl() . '/#/tournament/' . rawurlencode($link->utTournamentId);

    $logoBytes = file_get_contents(__DIR__ . '/assets/ut-logo.png');
    $brandHtml = ($logoBytes !== false
      ? "<img src='data:image/png;base64," . base64_encode($logoBytes) . "' alt='' height='14' style='vertical-align:middle;'/> "
      : '') . 'Ultimate Tournament';

    $html = "<p style='text-align:right;padding-top:10px;'>"
      . sprintf(self::esc((string) $strings['banner_imported']), $brandHtml)
      . " <a href='" . self::esc($utUrl) . "' target='_blank' rel='noopener noreferrer' style='text-decoration:underline;'>"
      . self::esc((string) $strings['banner_open_in_ut']) . "</a>";

    if ($canEdit) {
      $isSeasonOrSuperAdmin =
        (function_exists('isSuperAdmin') && isSuperAdmin()) ||
        (function_exists('isSeasonAdmin') && isSeasonAdmin($link->seasonId));

      $params = ['season' => $link->seasonId, 'ut_id' => $link->utTournamentId];
      if ($isSeasonOrSuperAdmin) {
        $params['focus_series'] = $link->seriesId;
      } else {
        $params['series'] = $link->seriesId;
      }
      $syncUrl = '?view=plugins/utimport&' . http_build_query($params);

      $html .= " | <a href='" . self::esc($syncUrl) . "' style='text-decoration:underline;'>"
        . self::esc((string) $strings['banner_resync']) . "</a>";
    }

    return $html . "</p>\n";
  }

  /** @return array<string, mixed> */
  private static function loadStrings(): array {
    $locale = ut_get_locale();
    $lang = preg_match('/^([a-z]{2})/i', trim($locale), $m) && is_file(__DIR__ . '/lang/' . strtolower($m[1]) . '.php')
      ? strtolower($m[1])
      : 'en';
    return include __DIR__ . '/lang/' . $lang . '.php';
  }

  private static function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}
