<?php
declare(strict_types=1);

/**
 * Typed readonly models hydrated from the Ultimate Tournament v1 REST API JSON.
 */

readonly class UtTournament {
  public function __construct(
    public string $id,
    public string $name,
    public mixed $from = null
  ) {}

  public static function fromArray(array $data): self {
    $name = (string) ($data['name'] ?? '');
    return new self(
      id: (string) ($data['id'] ?? ''),
      name: $name !== '' ? $name : 'Ultimate Tournament',
      from: $data['from'] ?? null,
    );
  }
}

readonly class UtDivision {
  public function __construct(
    public string $id,
    public string $name
  ) {}

  public static function fromArray(array $data): self {
    return new self(
      id: (string) ($data['id'] ?? ''),
      name: (string) ($data['name'] ?? ''),
    );
  }
}

readonly class UtTeam {
  public function __construct(
    public string $id,
    public string $name,
    public ?string $divisionId,
    public ?int $order
  ) {}

  public static function fromArray(array $data): self {
    return new self(
      id: (string) ($data['id'] ?? ''),
      name: (string) ($data['name'] ?? 'Team'),
      divisionId: isset($data['division']) ? UtApiClient::extractRefId($data['division']['ref'] ?? $data['division']) : null,
      order: isset($data['order']) ? (int) $data['order'] : null,
    );
  }
}

readonly class UtField {
  public function __construct(
    public string $id,
    public string $name
  ) {}

  public static function fromArray(array $data): self {
    $id = (string) ($data['id'] ?? '');
    return new self(
      id: $id,
      name: (string) ($data['name'] ?? ('Field ' . $id)),
    );
  }
}

readonly class UtBracket {
  public function __construct(
    public string $id,
    public ?string $divisionId,
    public UtBracketType $type,
    public string $name,
    public int $x,
    public int $y,
    public int $size
  ) {}

  public static function fromArray(array $data): self {
    $el = isset($data['element']) && is_array($data['element']) ? $data['element'] : [];
    return new self(
      id: (string) ($data['id'] ?? ''),
      divisionId: isset($data['division']) ? UtApiClient::extractRefId($data['division']['ref'] ?? $data['division']) : null,
      type: UtBracketType::from((string) ($el['type'] ?? '')),
      name: (string) ($el['name'] ?? ''),
      x: (int) ($el['x'] ?? 0),
      y: (int) ($el['y'] ?? 0),
      size: (int) ($el['size'] ?? 0),
    );
  }
}

readonly class UtBracketOrigin {
  public function __construct(
    public ?string $bracketId,
    public int $index,
    public ?UtBracketType $elementType,
    public array $groupBlueprints
  ) {}

  public static function fromArray(array $data): self {
    $el = isset($data['element']) && is_array($data['element']) ? $data['element'] : [];
    return new self(
      bracketId: isset($data['ref']) ? UtApiClient::extractRefId($data['ref']) : null,
      index: (int) ($data['index'] ?? 0),
      elementType: isset($el['type']) ? UtBracketType::from((string) $el['type']) : null,
      groupBlueprints: isset($el['groupBlueprints']) && is_array($el['groupBlueprints']) ? $el['groupBlueprints'] : [],
    );
  }
}

readonly class UtOpponent {
  public function __construct(
    public ?string $teamId,
    public ?UtBracketOrigin $origin
  ) {}

  public static function fromArray(?array $data): self {
    if ($data === null) {
      return new self(null, null);
    }
    $originData = isset($data['bracket']['origin']) && is_array($data['bracket']['origin'])
      ? $data['bracket']['origin']
      : null;

    return new self(
      teamId: isset($data['team']) ? UtApiClient::extractRefId($data['team']['ref'] ?? $data['team']) : null,
      origin: $originData !== null ? UtBracketOrigin::fromArray($originData) : null,
    );
  }
}

readonly class UtBracketTeam {
  public function __construct(
    public int $order,
    public ?string $parentBracketId,
    public UtOpponent $opponent
  ) {}

  public static function fromArray(array $data): self {
    $oppData = isset($data['opponent']) && is_array($data['opponent']) ? $data['opponent'] : null;
    $parentRef = isset($oppData['bracket']['parent']) ? ($oppData['bracket']['parent']['ref'] ?? $oppData['bracket']['parent']) : null;
    return new self(
      order: (int) ($data['order'] ?? 0),
      parentBracketId: $parentRef !== null ? UtApiClient::extractRefId($parentRef) : null,
      opponent: UtOpponent::fromArray($oppData),
    );
  }
}

readonly class UtGame {
  public function __construct(
    public string $id,
    public ?string $divisionId,
    public ?string $parentBracketId,
    public ?int $parentBracketIndex,
    public ?string $fieldId,
    public UtOpponent $home,
    public UtOpponent $away,
    public mixed $startTime,
    public mixed $endTime,
    public UtGameState $gameState,
    public int $score1,
    public int $score2
  ) {}

  public static function fromArray(array $data): self {
    $parentBracket = isset($data['parentBracket']) && is_array($data['parentBracket']) ? $data['parentBracket'] : [];
    return new self(
      id: (string) ($data['id'] ?? ''),
      divisionId: isset($data['division']) ? UtApiClient::extractRefId($data['division']['ref'] ?? $data['division']) : null,
      parentBracketId: isset($parentBracket['ref']) ? UtApiClient::extractRefId($parentBracket['ref']) : null,
      parentBracketIndex: isset($parentBracket['index']) ? (int) $parentBracket['index'] : null,
      fieldId: isset($data['field']) ? UtApiClient::extractRefId($data['field']['ref'] ?? $data['field']) : null,
      home: UtOpponent::fromArray(isset($data['opponent1']) && is_array($data['opponent1']) ? $data['opponent1'] : null),
      away: UtOpponent::fromArray(isset($data['opponent2']) && is_array($data['opponent2']) ? $data['opponent2'] : null),
      startTime: $data['startTime'] ?? null,
      endTime: $data['endTime'] ?? null,
      gameState: UtGameState::from((string) ($data['gameState'] ?? '')),
      score1: (int) ($data['score1'] ?? 0),
      score2: (int) ($data['score2'] ?? 0),
    );
  }
}
