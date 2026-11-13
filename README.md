# denosyscore/cache

> **Read-only split.** This package is developed in [denosyscore/framework](https://github.com/denosyscore/framework) under `packages/cache`. Open issues and pull requests there.

Cache abstractions and file cache implementation

## Installation

```bash
composer require denosyscore/cache
```

## Quick start

A PSR-16 file cache with atomic counters. It needs no container or configuration.

<!-- quickstart -->
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Denosys\Cache\FileCache;

$cache = new FileCache(sys_get_temp_dir() . '/my-app-cache');

$cache->set('greeting', 'Hello', 60);          // PSR-16: TTL in seconds
echo $cache->get('greeting', 'missing'), "\n";  // Hello

$cache->increment('visits', 1, 3600);           // atomic across processes; lives an hour
echo $cache->increment('visits', 2), "\n";      // 3
echo $cache->current('visits'), "\n";           // 3

$cache->clear();
```

## Counters

`FileCache` implements PSR-16 and `AtomicCounter`. `increment($key, $by,
$ttlSeconds)` locks the counter, so concurrent processes never lose a count.
The TTL is set when the counter starts and later increments do not extend
it; an expired counter starts again. `current()` reads a counter (0 when
missing), `expiresAt()` says when its window ends, and `reset()` removes it. Type against
`Psr\SimpleCache\CacheInterface` for caching and `AtomicCounter` for
counting.

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
