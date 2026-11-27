<?php
declare(strict_types=1);

/**
 * Value object representing a planned Ultiorganizer `uo_pool` derived from UT brackets/games.
 *
 * Conceptually, one `uo_pool` corresponds 1:1 to one UT bracket — with the key exception that
 * when UT elimination brackets form a complete power-of-2 multi-round playoff tree (4/8/16 teams),
 * multiple UT brackets in rounds 2+ (e.g., Final + 3rd-place match) are squished into a single
 * hidden Ultiorganizer follower `uo_pool` and chained via `uo_pool.follower` (plus an optional
 * `'__unassigned__'` fallback pool for games without a bracket).
 */

class UtPlannedPool {
  /** Assigned `uo_pool.pool_id` after persistence in UtPoolSyncer. */
  public ?int $poolId = null;

  public function __construct(
    /** Stable mapping key in `uo_ut_map`: `<bracketId>`, `<rootId>|r<round>`, or `'__unassigned__'`. */
    public readonly string $utKey,
    /** All UT bracket IDs merged into this pool (multiple for squished playoff follower rounds). */
    public readonly array $utBracketIds,
    public readonly string $name,
    public readonly UoPoolType $type,
    public readonly bool $visible,
    /** True if teams enter this pool via `uo_moveteams` (false for initial round-1 pools). */
    public readonly bool $continuingPool,
    /** True if no playable bracket follows this pool (used for final division standings). */
    public readonly bool $placementPool,
    public readonly int $sortX,
    public readonly int $sortY,
    public readonly string $sortName,
    /** Root bracket UT ID when part of a merged playoff tree (used to link `uo_pool.follower`). */
    public readonly ?string $treeRootKey,
    /** 1-based round index within the playoff tree (0 when standalone). */
    public readonly int $treeRound,
    /** @var UtGame[] Ordered UtGame objects belonging to this pool. */
    public readonly array $games,
    /** Maps each merged UT bracket ID to its 0-based match offset inside this pool (used for `fromplacing`). */
    public readonly array $utOriginOffsets,
    public readonly UtBracketType $utType
  ) {}
}
