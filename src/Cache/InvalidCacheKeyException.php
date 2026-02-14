<?php

declare(strict_types=1);

namespace CFXP\Core\Cache;

use Psr\SimpleCache\InvalidArgumentException;

/**
 * Exception thrown when cache key is invalid according to PSR-16 spec.
 */
class InvalidCacheKeyException extends \InvalidArgumentException implements InvalidArgumentException
{
}
