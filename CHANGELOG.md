# Changelog

## Unreleased

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
