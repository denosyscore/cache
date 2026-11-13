# Changelog

## 0.22.0 – 2026-11-13

### Added

- `AtomicCounter`: `increment($key, $by, $ttlSeconds)`, `current()`,
  `expiresAt()` and `reset()`. The TTL starts with the counter and later increments do not
  extend it. `FileCache` implements it with a lock per counter. An entry
  that is not a live counter starts a new one, and reading an expired entry
  no longer deletes it outside the lock.

### Removed (breaking)

- `CacheInterface` and `decrement()`. Type against
  `Psr\SimpleCache\CacheInterface` for caching and `AtomicCounter` for
  counting. `increment()` takes a TTL instead of a fixed one-hour default.

## 0.21.0 – 2026-11-13

- Released with the framework's 0.21 line. This package has no DenoSys
  dependencies; no other changes.

## 0.20.0 – 2026-11-13

- Released with the framework's 0.20 line. This package has no DenoSys
  dependencies; no other changes.

## 0.19.0 – 2026-11-13

- Released with the framework's 0.19 line. Requires its DenoSys dependencies
  at `^0.19`; no other changes.

## 0.18.0 – 2026-11-13

- Released with the framework's 0.18 line. Requires its DenoSys dependencies
  at `^0.18`; no other changes.

## 0.17.0 – 2026-11-12

- Released with the framework's 0.17 line. Requires its DenoSys dependencies
  at `^0.17`; no other changes.

## 0.16.0 – 2026-11-12

- Released with the framework's 0.16 line; no changes to this package.

## 0.15.0 – 2026-11-12

- Released with the framework's 0.15 line; no changes to this package.

## 0.14.0 – 2026-11-12

- Released with the framework's 0.14 line; no changes to this package.

## 0.13.0 – 2026-11-12

- Released with the framework's 0.13 line; no changes to this package.

## 0.12.0 – 2026-11-12

- Released with the framework's 0.12 line; no changes to this package.

## 0.11.1 – 2026-11-12

### Fixed

- Requirements on other DenoSys packages are `^0.11`. In 0.11.0 they were
  still `^0.10`, which for a 0.x version allows only 0.10 releases, so 0.11.0
  packages resolved their siblings to 0.10 or could not be installed. Use
  0.11.1 instead of 0.11.0.

## 0.11.0 – 2026-11-12

- **Breaking:** `Denosys\Cache\CacheServiceProvider` moved to the framework as `Denosys\Framework\Providers\CacheServiceProvider`. Packages no longer ship service providers; the framework wires packages together.
- Requires no other DenoSys package.

## 0.10.0 – 2026-11-12

### Changed

- Released from the [denosyscore/framework](https://github.com/denosyscore/framework)
  monorepo, where this package now lives under `packages/cache`. All DenoSys
  packages share one version line, so the version moves from v0.2.3 to
  0.10.0. There are no API changes in this release.
- Requirements on other DenoSys packages are `^0.10`.

## 0.2.3 – 2026-11-11

### Fixed

- `FileCache::increment()` and `decrement()` hold an exclusive per-key lock
  around the read-modify-write. Concurrent increments were previously lost
  (6 processes × 150 increments stored 183 instead of 900), which let
  parallel requests slip past rate limits built on the cache.
- Incrementing an existing key keeps its expiry. A key without an expiry
  previously gained a one-hour expiry; new counters still default to one hour.
- `clear()` removes counter lock files.

### Testing

- Add a PHPUnit suite and run it in CI.
