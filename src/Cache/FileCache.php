<?php

declare(strict_types=1);

namespace Denosys\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

final class FileCache implements CacheInterface, AtomicCounter
{
    private string $directory;

    /**
     * Create a new file cache instance.
     *
     * @param string $directory The directory to store cache files
     */
    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
        $this->ensureDirectoryExists();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        
        // An expired entry is left for the next write to replace: deleting it
        // here, outside the counter lock, could delete a newer window.
        $entry = $this->readEntry($this->getPath($key));

        return $entry === null ? $default : ($entry['value'] ?? $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        
        $path = $this->getPath($key);
        $seconds = $this->ttlToSeconds($ttl);

        $this->ensureDirectoryExists();

        return $this->writeEntry($path, $value, $seconds !== null ? time() + $seconds : null);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // Two globs rather than GLOB_BRACE, which musl-based systems lack.
        $cacheFiles = glob($this->directory . '/*.cache');
        $lockFiles = glob($this->directory . '/*.lock');

        if ($cacheFiles === false || $lockFiles === false) {
            return false;
        }

        $files = [...$cacheFiles, ...$lockFiles];

        foreach ($files as $file) {
            unlink($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        return $this->get($key) !== null;
    }

    public function increment(string $key, int $by = 1, int $ttlSeconds = 60): int
    {
        if ($ttlSeconds < 1) {
            throw new \InvalidArgumentException('A counter needs a TTL of at least 1 second.');
        }

        $this->validateKey($key);
        $this->ensureDirectoryExists();

        $path = $this->getPath($key);

        // Serialise read-modify-write across processes with an exclusive lock on a
        // companion lock file; the cache file itself is replaced by rename().
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException("Unable to lock cache key [{$key}] for increment.");
        }

        try {
            // An entry that is not a live counter (set() with another value, or no
            // expiry) starts a new counter.
            $data = $this->readEntry($path);
            $counter = $data !== null && is_int($data['value']) && is_int($data['expires_at']);
            $expiresAt = $counter ? $data['expires_at'] : time() + $ttlSeconds;
            $newValue = ($counter ? $data['value'] : 0) + $by;

            if (!$this->writeEntry($path, $newValue, $expiresAt)) {
                throw new \RuntimeException("Unable to write cache key [{$key}].");
            }

            return $newValue;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function expiresAt(string $key): ?int
    {
        $this->validateKey($key);
        $entry = $this->readEntry($this->getPath($key));

        return $entry !== null && is_int($entry['value']) ? $entry['expires_at'] : null;
    }

    public function current(string $key): int
    {
        $value = $this->get($key, 0);

        return is_int($value) ? $value : 0;
    }

    public function reset(string $key): void
    {
        $this->delete($key);
    }

    /**
     * Read a live entry, or null when it is missing, unreadable or expired.
     *
     * @return array{value: mixed, expires_at: int|null}|null
     */
    private function readEntry(string $path): ?array
    {
        // A missing entry is the common case; check first so it raises no
        // warning. The @ covers only a delete racing between the two calls.
        if (!is_file($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        $data = $contents === false ? null : json_decode($contents, true);

        if (!is_array($data) || (isset($data['expires_at']) && $data['expires_at'] < time())) {
            return null;
        }

        return ['value' => $data['value'] ?? null, 'expires_at' => $data['expires_at'] ?? null];
    }

    private function writeEntry(string $path, mixed $value, ?int $expiresAt): bool
    {
        $json = json_encode(['value' => $value, 'expires_at' => $expiresAt]);
        if ($json === false) {
            return false;
        }

        $tempPath = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
            return false;
        }

        return rename($tempPath, $path);
    }

    private function getPath(string $key): string
    {
        // Create a safe filename from the key
        $hash = sha1($key);
        return $this->directory . '/' . $hash . '.cache';
    }

    /**
     * Ensure the cache directory exists.
     */
    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * Convert TTL to seconds.
     */
    private function ttlToSeconds(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            return (int) (new \DateTime())->setTimestamp(0)->add($ttl)->getTimestamp();
        }

        return $ttl;
    }

    /**
     * Validate cache key according to PSR-16 spec.
     *
     * @throws InvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidCacheKeyException('Cache key cannot be empty.');
        }

        // PSR-16 reserved characters: {}()/\@:
        if (preg_match('/[{}()\\/\\\\@:]/', $key)) {
            throw new InvalidCacheKeyException(
                'Cache key contains reserved characters: {}()/\\@:'
            );
        }
    }
}
