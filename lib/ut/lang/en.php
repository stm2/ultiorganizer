<?php
declare(strict_types=1);

return [
  'group' => 'Pool %s',
  'swiss_group' => 'Swiss Draw %s',
  'round_final' => 'Final',
  'round_semi' => 'Semifinal',
  'round_quarter' => 'Quarterfinal',
  'round_eighth' => 'Knockout 1-16',
  'round_ko' => 'Knockout %d-%d',
  'fallback_pool' => 'Games',
  'default_pool_name' => 'Pool',
  'pool_rank' => '%s of %s',
  'winner_of' => 'Winner of %s',
  'loser_of' => 'Loser of %s',
  'shell_ordinals' => [
    1 => 'first',
    2 => 'second',
    3 => 'third',
    4 => 'fourth',
    5 => 'fifth',
    6 => 'sixth',
    7 => 'seventh',
    8 => 'eighth',
    9 => 'ninth',
    10 => 'tenth',
    11 => 'eleventh',
    12 => 'twelfth',
  ],
  'tbd' => 'TBD',
  'banner_imported' => 'This tournament was synced from %s.',
  'banner_open_in_ut' => 'Open in app',
  'banner_resync' => 'Settings',
  'rank_with_suffix' => static function (int $n): string {
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) {
      return $n . 'th';
    }
    return match ($n % 10) {
      1 => $n . 'st',
      2 => $n . 'nd',
      3 => $n . 'rd',
      default => $n . 'th',
    };
  },
];
