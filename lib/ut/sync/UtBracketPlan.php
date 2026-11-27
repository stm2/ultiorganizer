<?php
declare(strict_types=1);

/**
 * Typed output of UtBracketPlanner::plan().
 */

readonly class UtBracketPlan {
  /**
   * @param array<string, UtPlannedPool> $pools
   * @param array<string, UtBracketTeam[]> $bracketTeamsByBracket
   * @param array<string, UtBracket> $bracketsById
   */
  public function __construct(
    public array $pools,
    public array $bracketTeamsByBracket,
    public array $bracketsById
  ) {}
}
