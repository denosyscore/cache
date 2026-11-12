# Changelog

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
