<?php
declare(strict_types=1);

/**
 * Page controller and UI renderer for ?view=plugins/utimport.
 */
final class UtImportPage {
  private const RED_BADGE_STYLE = 'background-color:#f8d7da;color:#842029;padding:0 4px;border-radius:2px;font-weight:bold;';
  private const YELLOW_BADGE_STYLE = 'background-color:#fff3cd;color:#856404;padding:0 4px;border-radius:2px;font-weight:bold;';

  public static function handle(): void {
    global $include_prefix;
    $prefix = (string) ($include_prefix ?? '');
    include_once $prefix . 'lib/season.functions.php';
    include_once $prefix . 'lib/series.functions.php';
    include_once $prefix . 'lib/pool.functions.php';
    include_once $prefix . 'lib/location.functions.php';
    include_once $prefix . 'lib/common.functions.php';

    $title = (string) _("Import from Ultimate Tournament");

    $requestedSeriesId = (int) ($_GET['series'] ?? ($_POST['context_series_id'] ?? 0));
    $seasonId = (string) ($_GET['season'] ?? ($_POST['target_season_id'] ?? ''));

    $isSeriesAdminOnly = false;
    $seriesInfo = null;
    if ($requestedSeriesId > 0) {
      ut_ensure_edit_series_right($requestedSeriesId, $title);
      $seriesInfo = SeriesInfo($requestedSeriesId);
      if (empty($seriesInfo)) {
        showPage($title, "<p class='warning'>" . self::t("Series not found.") . "</p>\n");
        return;
      }
      $seasonId = (string) $seriesInfo['season'];
      $isSeriesAdminOnly = !(function_exists('isSeasonAdmin') && isSeasonAdmin($seasonId)) && !(function_exists('isSuperAdmin') && isSuperAdmin());
    } elseif ($seasonId !== '') {
      ut_ensure_season_admin($seasonId, $title);
    } else {
      ut_ensure_super_admin($title);
    }

    $html = "";
    $errorMessage = "";
    $syncSummary = null;
    $bundle = null;

    $seasonInfo = $seasonId !== '' ? SeasonInfo($seasonId) : null;
    $requestedSeriesName = $seriesInfo ? (string) $seriesInfo['name'] : '';
    $headingSuffix = $seasonInfo ? ' (' . (string) $seasonInfo['name'] . ($requestedSeriesName !== '' ? ' - ' . $requestedSeriesName : '') . ')' : '';
    $html .= "<h2>" . self::e($title . $headingSuffix) . "</h2>\n";

    $actionUrl = "?view=plugins/utimport";
    if ($seasonId !== '' && isset($_GET['season'])) {
      $actionUrl .= "&amp;season=" . urlencode($seasonId);
    }
    if ($requestedSeriesId > 0) {
      $actionUrl .= "&amp;series=" . $requestedSeriesId;
    }

    $getUtId = trim((string) ($_GET['ut_id'] ?? ''));
    $utInput = trim((string) ($_POST['ut_input'] ?? $getUtId));
    $autoLoadFromGet = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $getUtId !== '' && !isset($_POST['action_load']) && !isset($_POST['action_sync']));

    if (isset($_POST['action_load']) || isset($_POST['action_sync']) || $autoLoadFromGet) {
      try {
        $config = UtConfig::load();
        $client = new UtApiClient($config);
        $tmTournamentId = UtApiClient::extractTournamentId($utInput);
        $bundle = $client->fetchTournamentBundle($tmTournamentId);

        if (isset($_POST['action_sync'])) {
          if ($seasonId === '') {
            throw new InvalidArgumentException((string) _("Please select a target season."));
          }

          $syncService = new UtSyncService();
          $syncSummary = $syncService->sync($bundle, new UtSyncOptions(
            seasonId: $seasonId,
            divisionMappings: self::buildDivisionMappings($requestedSeriesId),
            locationId: null,
            timezone: '',
            canCreateSeries: !$isSeriesAdminOnly,
          ));
        }
      } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
      }
    }

    if ($errorMessage !== '') {
      $html .= "<p class='warning'>" . self::e($errorMessage) . "</p>\n";
    }

    // Step 1 & Step 2 Form
    $html .= "<form method='post' action='$actionUrl'>\n";
    if ($requestedSeriesId > 0) {
      $html .= "<input type='hidden' name='context_series_id' value='" . $requestedSeriesId . "'/>\n";
    }

    $loadBtnHtml = "<input class='button' type='submit' name='action_load' value='" . self::t("Load tournament") . "'/>";
    $html .= "<table class='infotable'>\n";
    $html .= "<tr><td>" . self::t("Ultimate Tournament ID or URL") . ":</td>";
    $html .= "<td><input class='input' type='text' size='60' name='ut_input' value='" . self::e($utInput) . "' placeholder='e.g. abc123 or https://ultimate-tournament.app/#/tournament/abc123'/>"
      . ($seasonId !== '' ? " " . $loadBtnHtml : "")
      . "</td></tr>\n";

    if ($seasonId === '') {
      $seasonsRes = Seasons();
      $html .= "<tr><td>" . self::t("Target season") . ":</td><td><select class='dropdown' name='target_season_id'>\n";
      while ($row = mysqli_fetch_assoc($seasonsRes)) {
        $rowSeasonId = (string) $row['season_id'];
        $sel = (isset($_POST['target_season_id']) && $_POST['target_season_id'] === $rowSeasonId) ? " selected='selected'" : "";
        $html .= "<option value='" . self::e($rowSeasonId) . "'$sel>" . self::e((string) $row['name']) . " (" . self::e($rowSeasonId) . ")</option>\n";
      }
      $html .= "</select></td></tr>\n";
    } else {
      $html .= "<input type='hidden' name='target_season_id' value='" . self::e($seasonId) . "'/>\n";
    }
    $html .= "</table>\n";
    if ($seasonId === '') {
      $html .= "<p>" . $loadBtnHtml . "</p>\n";
    }

    if ($bundle !== null) {
      $focusSeriesId = $requestedSeriesId === 0 ? (int) ($_GET['focus_series'] ?? 0) : 0;
      $submittedSelectedDivIds = isset($_POST['action_sync'])
        ? array_map('strval', array_keys(array_filter(is_array($_POST['div_selected'] ?? null) ? $_POST['div_selected'] : [])))
        : null;
      $html .= self::renderStep2($bundle, $seasonId, $requestedSeriesId, $requestedSeriesName, $focusSeriesId, $submittedSelectedDivIds);
    }

    $html .= "</form>\n";

    if ($syncSummary !== null) {
      $html .= self::renderSummary($syncSummary, $isSeriesAdminOnly);
    }

    showPage($title, $html);
  }

  /** @return array<string, UtDivisionMapping> */
  private static function buildDivisionMappings(int $requestedSeriesId): array {
    $selectedDivs = isset($_POST['div_selected']) && is_array($_POST['div_selected']) ? $_POST['div_selected'] : [];
    $targetSeriesMap = isset($_POST['div_target_series']) && is_array($_POST['div_target_series']) ? $_POST['div_target_series'] : [];
    $seriesNamesMap = isset($_POST['div_series_name']) && is_array($_POST['div_series_name']) ? $_POST['div_series_name'] : [];

    $divisionMappings = [];
    foreach ($selectedDivs as $utDivId => $checked) {
      if (!$checked) {
        continue;
      }
      $utDivId = (string) $utDivId;
      if ($requestedSeriesId > 0) {
        $divisionMappings[$utDivId] = new UtDivisionMapping(targetSeriesId: $requestedSeriesId);
      } elseif (isset($targetSeriesMap[$utDivId]) && is_numeric($targetSeriesMap[$utDivId]) && (int) $targetSeriesMap[$utDivId] > 0) {
        $divisionMappings[$utDivId] = new UtDivisionMapping(targetSeriesId: (int) $targetSeriesMap[$utDivId]);
      } else {
        $divisionMappings[$utDivId] = new UtDivisionMapping(
          targetSeriesId: null,
          seriesName: isset($seriesNamesMap[$utDivId]) ? trim((string) $seriesNamesMap[$utDivId]) : '',
        );
      }
    }
    return $divisionMappings;
  }

  private static function renderSummary(UtSyncResult $syncSummary, bool $isSeriesAdminOnly): string {
    $entities = [
      'series' => _("Series"),
      'teams' => _("Teams"),
      'pools' => _("Pools"),
      'games' => _("Games"),
      'reservations' => _("Field reservations"),
    ];

    $html = "<fieldset>\n<legend><strong>" . self::t("Sync completed") . "</strong></legend>\n<ul>\n";
    foreach ($entities as $prop => $label) {
      $line = self::e((string) $label) . ": "
        . $syncSummary->created->$prop . " " . self::t("created") . ", "
        . $syncSummary->updated->$prop . " " . self::t("updated");
      if ($prop !== 'series') {
        $line .= ", " . $syncSummary->deleted->$prop . " " . self::t("deleted");
      }
      $html .= "<li>$line</li>\n";
    }
    $html .= "</ul>\n";

    if (!empty($syncSummary->warnings)) {
      $html .= "<p class='warning'>" . self::e(implode(" | ", $syncSummary->warnings)) . "</p>\n";
    }

    $seaEnc = urlencode($syncSummary->seasonId);
    $links = [
      "<a href='?view=games&amp;season=$seaEnc'>" . self::t("View games") . "</a>",
      "<a href='?view=poolstatus&amp;season=$seaEnc'>" . self::t("View pools & standings") . "</a>",
    ];
    if (!$isSeriesAdminOnly) {
      $links[] = "<a href='?view=admin/seasonadmin&amp;season=$seaEnc'>" . self::t("Season administration") . "</a>";
    }
    $html .= "<p>" . implode(" | ", $links) . "</p>\n</fieldset>\n";
    return $html;
  }

  private static function renderStep2(
    UtTournamentBundle $bundle,
    string $seasonId,
    int $requestedSeriesId,
    string $requestedSeriesName,
    int $focusSeriesId = 0,
    ?array $submittedSelectedDivIds = null,
  ): string {
    $divisions = $bundle->divisions;
    $teams = $bundle->teams;
    $games = $bundle->games;
    $tmTournamentId = $bundle->tournament->id;
    $tournamentName = $bundle->tournament->name;

    $utTournamentUrl = UtConfig::loadAppUrl() . '/#/tournament/' . rawurlencode($tmTournamentId);

    $mapRepo = new UtMapRepository();
    $mapRepo->ensureTable();
    $mappedDivisions = $mapRepo->getMappedDivisionsForTournament($tmTournamentId);
    $mappedSeriesIds = array_map(static fn(UtSeriesLink $l): int => $l->seriesId, array_values($mappedDivisions));
    $uoStatsMap = $mapRepo->getSeriesStats($mappedSeriesIds);

    $divTeamCounts = [];
    foreach ($teams as $t) {
      if ($t->divisionId !== null) {
        $divTeamCounts[$t->divisionId] = ($divTeamCounts[$t->divisionId] ?? 0) + 1;
      }
    }
    $divGameCounts = [];
    $divFinishedGameCounts = [];
    foreach ($games as $g) {
      if ($g->divisionId !== null) {
        $divGameCounts[$g->divisionId] = ($divGameCounts[$g->divisionId] ?? 0) + 1;
        if ($g->gameState === UtGameState::Finished) {
          $divFinishedGameCounts[$g->divisionId] = ($divFinishedGameCounts[$g->divisionId] ?? 0) + 1;
        }
      }
    }

    $html = "<hr/>\n";
    $html .= "<h3><a href='" . self::e($utTournamentUrl) . "' target='_blank' rel='noopener noreferrer'>" . self::e($tournamentName) . " &#8599;</a></h3>\n";
    $html .= "<p>" . self::t("Divisions") . ": " . count($divisions) . " | " . self::t("Teams") . ": " . count($teams) . " | " . self::t("Games") . ": " . count($games) . " | " . self::t("Fields") . ": " . count($bundle->fields) . "</p>\n";

    $html .= "<hr/>\n";
    $html .= "<table class='admintable' style='width:100%;'>\n";
    $html .= "<tr>"
      . "<th class='center'><input type='checkbox' id='ut_select_all' title='" . self::t("Select all") . "'/></th>"
      . "<th>" . self::t("UT Division") . "</th>"
      . "<th>" . self::t("UO Series") . "</th>"
      . "<th>" . self::t("Status") . "</th>"
      . "<th>" . self::t("Last Synced") . "</th>"
      . "</tr>\n";

    $initialCheckedCount = 0;
    foreach ($divisions as $div) {
      $dId = $div->id;
      $utStats = [
        'teams' => $divTeamCounts[$dId] ?? 0,
        'games' => $divGameCounts[$dId] ?? 0,
        'finished' => $divFinishedGameCounts[$dId] ?? 0,
      ];
      $existingLink = $mappedDivisions[$dId] ?? null;
      $uoStats = $existingLink !== null
        ? ($uoStatsMap[$existingLink->seriesId] ?? ['teams' => 0, 'games' => 0, 'finished' => 0])
        : null;

      $html .= self::renderDivisionRow(
        $div,
        $tournamentName,
        $utTournamentUrl,
        $seasonId,
        $requestedSeriesId,
        $requestedSeriesName,
        $focusSeriesId,
        $submittedSelectedDivIds,
        $existingLink,
        empty($mappedDivisions),
        count($divisions),
        $utStats,
        $uoStats,
        $initialCheckedCount,
      );
    }

    $html .= "</table>\n<hr/>\n";
    $html .= self::renderSyncButton($initialCheckedCount);
    return $html;
  }

  /**
   * @param array{teams: int, games: int, finished: int} $utStats
   * @param array{teams: int, games: int, finished: int}|null $uoStats
   */
  private static function renderDivisionRow(
    UtDivision $div,
    string $tournamentName,
    string $utTournamentUrl,
    string $seasonId,
    int $requestedSeriesId,
    string $requestedSeriesName,
    int $focusSeriesId,
    ?array $submittedSelectedDivIds,
    ?UtSeriesLink $existingLink,
    bool $noMappedDivisionsYet,
    int $totalDivisions,
    array $utStats,
    ?array $uoStats,
    int &$initialCheckedCount,
  ): string {
    $dId = $div->id;
    $dName = $div->name !== '' ? $div->name : $dId;
    $lockedToOtherSeason = $existingLink !== null && $existingLink->seasonId !== $seasonId;
    $lockedToOtherSeries = $requestedSeriesId > 0 && $existingLink !== null && $existingLink->seriesId !== $requestedSeriesId;
    $isLockedOut = $lockedToOtherSeason || $lockedToOtherSeries;

    if ($isLockedOut) {
      $checkboxAttr = " disabled='disabled'";
    } else {
      if ($submittedSelectedDivIds !== null) {
        $isChecked = in_array($dId, $submittedSelectedDivIds, true);
      } elseif ($focusSeriesId > 0) {
        $isChecked = $existingLink !== null && $existingLink->seriesId === $focusSeriesId;
      } else {
        $isChecked = $existingLink !== null
          || ($requestedSeriesId > 0 && $totalDivisions === 1)
          || ($requestedSeriesId === 0 && $noMappedDivisionsYet);
      }
      if ($isChecked) {
        $initialCheckedCount++;
      }
      $checkboxAttr = $isChecked ? " checked='checked'" : "";
    }

    $utDivUrl = $utTournamentUrl . '/division/' . rawurlencode($dId);
    $html = "<tr class='admintablerow' style='vertical-align:top;'>\n";
    $html .= "<td class='center'><input type='checkbox' class='ut-div-checkbox' name='div_selected[" . self::e($dId) . "]' value='1'$checkboxAttr/></td>\n";
    $html .= "<td><strong><a href='" . self::e($utDivUrl) . "' target='_blank' rel='noopener noreferrer'>" . self::e($dName) . " &#8599;</a></strong><br/>"
      . self::renderStatsLine($utStats['teams'], $utStats['finished'], $utStats['games'])
      . "</td>\n";

    if ($existingLink !== null && $uoStats !== null) {
      $uoSeriesUrl = '?view=poolstatus&series=' . $existingLink->seriesId;
      $html .= "<td><strong><a href='" . self::e($uoSeriesUrl) . "' target='_blank' rel='noopener noreferrer'>" . self::e($existingLink->seriesName) . "</a></strong>";
      if (!$isLockedOut && $requestedSeriesId === 0) {
        $html .= "<input type='hidden' name='div_target_series[" . self::e($dId) . "]' value='" . $existingLink->seriesId . "'/>";
      }
      $html .= "<br/>" . self::renderStatsLine($uoStats['teams'], $uoStats['finished'], $uoStats['games'], $utStats) . "</td>\n";
    } elseif ($requestedSeriesId > 0) {
      $targetName = $requestedSeriesName !== '' ? $requestedSeriesName : ('#' . $requestedSeriesId);
      $html .= "<td>" . self::e($targetName) . "</td>\n";
    } else {
      $defaultSeriesName = mb_substr($tournamentName . ' - ' . $dName, 0, 50);
      $html .= "<td><input class='input' type='text' maxlength='50' size='35' name='div_series_name[" . self::e($dId) . "]' value='" . self::e($defaultSeriesName) . "'/></td>\n";
    }

    if ($lockedToOtherSeason) {
      $statusText = sprintf((string) _("Imported in season \"%s\""), $existingLink->seasonId);
    } elseif ($lockedToOtherSeries) {
      $statusText = (string) _("Imported in another series");
    } elseif ($existingLink !== null) {
      $statusText = (string) _("Synced");
    } else {
      $statusText = (string) _("Not imported");
    }
    $html .= "<td>" . self::e($statusText) . "</td>\n";

    $lastSyncedText = ($existingLink !== null && !empty($existingLink->lastSyncedAt))
      ? (string) DefTimeFormat($existingLink->lastSyncedAt)
      : (string) _("Never");
    $html .= "<td>" . self::e($lastSyncedText) . "</td>\n</tr>\n";
    return $html;
  }

  /** @param array{teams: int, games: int, finished: int}|null $compareUt */
  private static function renderStatsLine(int $teams, int $finished, int $games, ?array $compareUt = null): string {
    $span = static function (int $val, string $label, ?int $utVal, string $mismatchStyle): string {
      $mismatch = $utVal !== null && $val !== $utVal;
      $title = $mismatch ? sprintf((string) _("$label (UT: %d)"), $utVal) : (string) _($label);
      $styleAttr = $mismatch ? " style='$mismatchStyle'" : '';
      return "<span title='" . self::e($title) . "'$styleAttr>" . $val . "</span>";
    };

    return '<small>'
      . $span($teams, 'Teams', $compareUt['teams'] ?? null, self::RED_BADGE_STYLE)
      . ' | '
      . $span($finished, 'Finished games', $compareUt['finished'] ?? null, self::YELLOW_BADGE_STYLE)
      . '/'
      . $span($games, 'Total games', $compareUt['games'] ?? null, self::RED_BADGE_STYLE)
      . '</small>';
  }

  private static function renderSyncButton(int $initialCheckedCount): string {
    $labelOne = (string) _("Sync 1 division with Ultiorganizer");
    $labelMany = (string) _("Sync %d divisions with Ultiorganizer");
    $syncBtnLabel = $initialCheckedCount === 1 ? $labelOne : sprintf($labelMany, $initialCheckedCount);
    $disabledAttr = $initialCheckedCount === 0 ? " disabled='disabled'" : "";

    $html = "<p><input class='button' id='ut_sync_submit' type='submit' name='action_sync'"
      . " value='" . self::e($syncBtnLabel) . "'"
      . " data-label-one='" . self::e($labelOne) . "'"
      . " data-label-many='" . self::e($labelMany) . "'$disabledAttr/></p>\n";
    $html .= <<<'HTML'
<script>
(() => {
  const btn = document.getElementById('ut_sync_submit');
  const form = btn?.form;
  if (!btn || !form) return;
  const selectAll = document.getElementById('ut_select_all');
  const enabledBoxes = () => [...form.querySelectorAll('input.ut-div-checkbox:not(:disabled)')];
  const sync = () => {
    const boxes = enabledBoxes();
    const count = boxes.filter((b) => b.checked).length;
    btn.value = count === 1
      ? btn.dataset.labelOne
      : btn.dataset.labelMany.replace('%d', String(count));
    btn.disabled = count === 0;
    if (selectAll) {
      selectAll.disabled = boxes.length === 0;
      selectAll.checked = boxes.length > 0 && count === boxes.length;
      selectAll.indeterminate = count > 0 && count < boxes.length;
    }
  };
  if (selectAll) {
    selectAll.addEventListener('change', () => {
      for (const box of enabledBoxes()) box.checked = selectAll.checked;
      sync();
    });
  }
  form.addEventListener('change', (e) => { if (e.target.matches('input.ut-div-checkbox')) sync(); });
  window.addEventListener('pageshow', sync);
  sync();
})();
</script>
HTML;
    return $html . "\n";
  }

  private static function e(string $value): string {
    return (string) utf8entities($value);
  }

  private static function t(string $text): string {
    return (string) utf8entities((string) _($text));
  }
}
