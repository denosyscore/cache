<?php

declare(strict_types=1);

namespace Denosys\Cache;

use DateInterval;
use Psr\SimpleCache\InvalidArgumentException;

class FileCache implements CacheInterface
{
    /** Expiry for counters created by increment() or decrement(). */
    private const DEFAULT_COUNTER_TTL = 3600;

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
        
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return $default;
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return $default;
        }

        // Check if expired
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'] ?? $default;
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

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int
    {
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
            $data = $this->readEntry($path);
            $expiresAt = $data === null ? time() + self::DEFAULT_COUNTER_TTL : $data['expires_at'];
            $newValue = (int) ($data['value'] ?? 0) + $value;

            if (!$this->writeEntry($path, $newValue, $expiresAt)) {
                throw new \RuntimeException("Unable to write cache key [{$key}].");
            }

            return $newValue;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * Get the full path for a cache key.
     */
    /**
     * Read a live entry, or null when it is missing, unreadable or expired.
     *
     * @return array{value: mixed, expires_at: int|null}|null
     */
    private function readEntry(string $path): ?array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
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
