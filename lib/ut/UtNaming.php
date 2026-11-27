<?php
declare(strict_types=1);

/**
 * Configurable pool, game, and placeholder naming templates for UT -> Ultiorganizer sync.
 * Auto-selects German or English from the user's active session locale (getSessionLocale()),
 * falling back to Ultiorganizer's default locale (GetDefaultLocale()),
 * matching Ultimate Tournament's detailed localization (intl_de.arb / intl_en.arb).
 */

readonly class UtNaming {
  private array $templates;

  public function __construct(array $templates = [], ?string $locale = null) {
    $language = self::detectLanguage($locale);
    $preset = self::loadPreset($language);
    $this->templates = array_merge($preset, $templates);
  }

  private static function detectLanguage(?string $locale = null): string {
    if ($locale === null || $locale === '') {
      $locale = ut_get_locale();
    }
    if (preg_match('/^([a-z]{2})/i', trim($locale), $matches)) {
      $code = strtolower($matches[1]);
      if (is_file(__DIR__ . '/lang/' . $code . '.php')) {
        return $code;
      }
    }
    return 'en';
  }

  private static function loadPreset(string $language): array {
    $file = __DIR__ . '/lang/' . $language . '.php';
    if (!is_file($file)) {
      $file = __DIR__ . '/lang/en.php';
    }
    $data = include $file;
    return is_array($data) ? $data : [];
  }

  public function rankWithSuffix(int $n): string {
    if (isset($this->templates['rank_with_suffix']) && is_callable($this->templates['rank_with_suffix'])) {
      $fn = $this->templates['rank_with_suffix'];
      return (string) $fn($n);
    }
    return (string) $n;
  }

  public function groupPoolName(string $rawName, bool $isSwiss = false): string {
    $name = trim($rawName);
    if ($name === '') {
      return mb_substr((string) $this->templates['default_pool_name'], 0, 50);
    }
    $tpl = $isSwiss ? $this->templates['swiss_group'] : $this->templates['group'];
    return mb_substr(sprintf($tpl, $name), 0, 50);
  }

  public function playoffTreeName(int $fromPlace, int $toPlace, bool $isPlacement = true): string {
    if (!$isPlacement) {
      return mb_substr(sprintf($this->templates['pool_crossover'], $fromPlace, $toPlace), 0, 50);
    }
    if ($fromPlace === 1) {
      if ($toPlace <= 2) {
        return mb_substr((string) $this->templates['round_final'], 0, 50);
      }
      return mb_substr(sprintf($this->templates['pool_top'], $toPlace), 0, 50);
    }
    return mb_substr(sprintf($this->templates['pool_placement'], $fromPlace, $toPlace), 0, 50);
  }

  public function eliminationPoolName(int $y, int $size, bool $isPlacement): string {
    $effectiveSize = max(2, $size);
    $fromPlace = $y + 1;
    $toPlace = $y + $effectiveSize;

    if ($isPlacement) {
      if ($y === 0) {
        if ($effectiveSize === 2) {
          return mb_substr((string) $this->templates['round_final'], 0, 50);
        }
        return mb_substr(sprintf($this->templates['pool_top'], $toPlace), 0, 50);
      }
      return mb_substr(sprintf($this->templates['pool_placement'], $fromPlace, $toPlace), 0, 50);
    }

    if ($y === 0) {
      $named = match ($effectiveSize) {
        2 => $this->templates['round_final'],
        4 => $this->templates['round_semi'],
        8 => $this->templates['round_quarter'],
        default => null,
      };
      if ($named !== null) {
        return mb_substr((string) $named, 0, 50);
      }
    }

    return mb_substr(sprintf($this->templates['pool_crossover'], $fromPlace, $toPlace), 0, 50);
  }

  /**
   * Exact port of Flutter's EliminationBracketElement._buildBase(detailed: true).
   */
  public function eliminationBase(int $y, int $size): string {
    if ($y === 0) {
      $named = match ($size) {
        2 => $this->templates['round_final'],
        4 => $this->templates['round_semi'],
        8 => $this->templates['round_quarter'],
        16 => $this->templates['round_eighth'],
        default => null,
      };
      if ($named !== null) {
        return (string) $named;
      }
    }

    return sprintf($this->templates['round_ko'], $y + 1, $y + max(2, $size));
  }

  public function eliminationGameLabel(int $y, int $size, int $matchIdx): string {
    $base = $this->eliminationBase($y, $size);
    if ($size === 2) {
      return mb_substr($base, 0, 100);
    }
    return mb_substr($base . ' ' . ($matchIdx + 1), 0, 100);
  }

  public function playoffFollowerName(string $rootName, int $roundY, int $roundSize): string {
    return mb_substr($rootName . ' (' . $this->eliminationBase($roundY, $roundSize) . ')', 0, 50);
  }

  public function fallbackPoolName(): string {
    return mb_substr((string) $this->templates['fallback_pool'], 0, 50);
  }

  /**
   * Exact port of Flutter's GroupShellMixin.indexToShellIndexes().
   *
   * @return array{groupIndex: int, rankIndex: int}
   */
  public function indexToShellIndexes(int $targetIndex, array $groupBlueprints): array {
    $remaining = [];
    foreach ($groupBlueprints as $idx => $bp) {
      $remaining[] = [
        'orig_index' => (int) $idx,
        'size' => (int) ($bp['size'] ?? 0),
      ];
    }

    $resultIndex = 0;
    $groupPos = 0;
    $rankIndex = 0;

    while (!empty($remaining)) {
      if ($resultIndex === $targetIndex) {
        return [
          'groupIndex' => $remaining[$groupPos]['orig_index'],
          'rankIndex' => $rankIndex,
        ];
      }

      $resultIndex++;
      $groupPos++;
      if ($groupPos >= count($remaining)) {
        $groupPos = 0;
        $rankIndex++;
        $remaining = array_values(array_filter(
          $remaining,
          static fn(array $el): bool => $rankIndex < $el['size']
        ));
      }
    }

    return ['groupIndex' => 0, 'rankIndex' => 0];
  }

  public function groupShellLabel(int $index, array $groupBlueprints): string {
    if (empty($groupBlueprints)) {
      return $this->rankWithSuffix($index + 1);
    }

    $res = $this->indexToShellIndexes($index, $groupBlueprints);
    $groupNum = $res['groupIndex'] + 1;
    $rankNum = $res['rankIndex'] + 1;
    $ordinals = $this->templates['shell_ordinals'];
    $rankWord = $ordinals[$rankNum] ?? $this->rankWithSuffix($rankNum);

    return mb_substr($this->rankWithSuffix($groupNum) . ' ' . $rankWord, 0, 100);
  }

  public function originLabel(
    ?UtBracketType $originType,
    int $index = 0,
    string $poolName = '',
    int $y = 0,
    int $size = 0,
    array $groupBlueprints = []
  ): string {
    if ($originType === UtBracketType::GroupShell || $originType === UtBracketType::SwissShell) {
      return $this->groupShellLabel($index, $groupBlueprints);
    }

    if ($originType === UtBracketType::Group || $originType === UtBracketType::SwissGroup) {
      $poolLabel = $poolName !== '' ? $poolName : (string) $this->templates['default_pool_name'];
      return mb_substr(sprintf($this->templates['pool_rank'], $this->rankWithSuffix($index + 1), $poolLabel), 0, 100);
    }

    if ($originType === UtBracketType::Elimination) {
      // UT indexes winners 0..half-1 top-to-bottom and losers half..size-1 mirrored bottom-to-top.
      $half = (int) floor($size / 2);
      $isWinner = ($index < $half);
      $matchIdx = $isWinner ? $index : max(0, $size - $index - 1);
      $gameLabel = $this->eliminationGameLabel($y, $size, $matchIdx);
      $tpl = $isWinner ? $this->templates['winner_of'] : $this->templates['loser_of'];
      return mb_substr(sprintf($tpl, $gameLabel), 0, 100);
    }

    return (string) $this->templates['tbd'];
  }
}
