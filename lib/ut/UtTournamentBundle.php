<?php
declare(strict_types=1);

/**
 * Typed tournament bundle fetched from the Ultimate Tournament v1 REST API.
 */

readonly class UtTournamentBundle {
  /**
   * @param UtDivision[] $divisions
   * @param UtTeam[] $teams
   * @param UtField[] $fields
   * @param UtBracket[] $brackets
   * @param UtBracketTeam[] $bracketTeams
   * @param UtGame[] $games
   */
  public function __construct(
    public UtTournament $tournament,
    public array $divisions,
    public array $teams,
    public array $fields,
    public array $brackets,
    public array $bracketTeams,
    public array $games
  ) {}
}
