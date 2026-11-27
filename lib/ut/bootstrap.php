<?php
declare(strict_types=1);

/**
 * Explicit manifest loading the Ultimate Tournament sync module in dependency order.
 */

// Core helpers, enums, configuration, DTOs, API client, persistence, and naming
require_once __DIR__ . '/UtCompat.php';
require_once __DIR__ . '/UtEnums.php';
require_once __DIR__ . '/UtConfig.php';
require_once __DIR__ . '/UtSyncOptions.php';
require_once __DIR__ . '/UtSyncResult.php';
require_once __DIR__ . '/UtApiModels.php';
require_once __DIR__ . '/UtTournamentBundle.php';
require_once __DIR__ . '/UtApiClient.php';
require_once __DIR__ . '/UtMapRepository.php';
require_once __DIR__ . '/UtNaming.php';
require_once __DIR__ . '/UtBanner.php';

// Single-purpose sync modules & result objects
require_once __DIR__ . '/sync/UtSyncContext.php';
require_once __DIR__ . '/sync/UtReservationSyncer.php';
require_once __DIR__ . '/sync/UtSeriesTeamSyncer.php';
require_once __DIR__ . '/sync/UtPlannedPool.php';
require_once __DIR__ . '/sync/UtBracketPlan.php';
require_once __DIR__ . '/sync/UtBracketPlanner.php';
require_once __DIR__ . '/sync/UtPoolSyncResult.php';
require_once __DIR__ . '/sync/UtPoolSyncer.php';
require_once __DIR__ . '/sync/UtGameSyncResult.php';
require_once __DIR__ . '/sync/UtGameSyncer.php';
require_once __DIR__ . '/sync/UtDivisionPruner.php';

// Top-level orchestrator & page controllers
require_once __DIR__ . '/UtSyncService.php';
require_once __DIR__ . '/UtImportPage.php';
