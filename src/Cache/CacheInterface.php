<?php

declare(strict_types=1);

namespace Denosys\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * Extended cache interface that is PSR-16 compatible.
 * 
 * This interface extends PSR-16's CacheInterface and adds
 * additional methods for atomic increment/decrement operations.
 * 
 * @see https://www.php-fig.org/psr/psr-16/
 */
interface CacheInterface extends Psr16CacheInterface
{
    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key The cache key
     * @param int $value Amount to increment by
     * @return int The new value
     */
    public function increment(string $key, int $value = 1): int;

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key The cache key
     * @param int $value Amount to decrement by
     * @return int The new value
     */
    public function decrement(string $key, int $value = 1): int;
}
