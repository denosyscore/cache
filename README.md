# denosyscore/cache

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

$cache->increment('visits');                    // atomic across processes
echo $cache->increment('visits', 2), "\n";      // 3

$cache->clear();
```

## Included Modules

- src/Cache/*

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l

## CI Workflows

- CI: composer validation + PHP syntax lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
