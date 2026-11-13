<?php

declare(strict_types=1);

namespace Denosys\Cache;

use InvalidArgumentException;

/**
 * A counter that concurrent processes can increment without losing counts,
 * such as attempts in a rate limit window.
 */
interface AtomicCounter
{
    /**
     * Adds $by and returns the new value. A missing or expired counter starts
     * from zero and lives $ttlSeconds; later increments do not extend it.
     *
     * @throws InvalidArgumentException when $ttlSeconds is below 1
     */
    public function increment(string $key, int $by = 1, int $ttlSeconds = 60): int;

    /** When the counter's window ends (a Unix time), or null when it is missing or expired. */
    public function expiresAt(string $key): ?int;

    /** The current value, or 0 when the counter is missing or expired. */
    public function current(string $key): int;

    public function reset(string $key): void;
}
