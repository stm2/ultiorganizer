<?php
declare(strict_types=1);

/**
 * Backed enums for Ultiorganizer pool types and Ultimate Tournament API constants.
 */

enum UoPoolType: int {
  case Group = 1;
  case Playoff = 2;
  case Swiss = 3;
  case CrossMatch = 4;
}

enum UtBracketType: string {
  case Teams = 'teams';
  case GroupShell = 'groupShell';
  case Group = 'group';
  case Elimination = 'elimination';
  case Result = 'result';
  case SwissShell = 'swissShell';
  case SwissGroup = 'swissGroup';
}

enum UtGameState: string {
  case Scheduled = 'SCHEDULED';
  case Running = 'RUNNING';
  case Finished = 'FINISHED';
}

enum UtGameSide: string {
  case Home = 'home';
  case Away = 'away';
}
