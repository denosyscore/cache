<?php

declare(strict_types=1);

use Denosys\Cache\FileCache;
use PHPUnit\Framework\TestCase;

final class FileCacheIncrementTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/denosys-cache-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testConcurrentIncrementsAreNotLost(): void
    {
        $processes = 6;
        $perProcess = 150;
        $script = sprintf(
            'require %s; $c = new Denosys\Cache\FileCache(%s); for ($i = 0; $i < %d; $i++) { $c->increment("hits", 1, 60); }',
            var_export(dirname(__DIR__) . '/vendor/autoload.php', true),
            var_export($this->directory, true),
            $perProcess,
        );

        $handles = [];
        for ($p = 0; $p < $processes; $p++) {
            $handles[] = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $handles[count($handles) - 1] = [$handles[count($handles) - 1], $pipes];
        }
        foreach ($handles as [$process, $pipes]) {
            stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            self::assertSame(0, proc_close($process), $errors ?: 'increment process failed');
        }

        self::assertSame($processes * $perProcess, (new FileCache($this->directory))->get('hits'));
    }

    public function testAnEntryThatIsNotACounterStartsANewCounter(): void
    {
        $cache = new FileCache($this->directory);
        $cache->set('text', 'abc');
        $cache->set('forever', 5);

        self::assertSame(1, $cache->increment('text', 1, 60));
        self::assertSame(1, $cache->increment('forever', 1, 60));

        $read = static fn (string $directory, string $key): array => json_decode((string) file_get_contents($directory . '/' . sha1($key) . '.cache'), true);
        self::assertEqualsWithDelta(time() + 60, $read($this->directory, 'forever')['expires_at'], 2);
    }

    public function testReadingAnExpiredCounterDoesNotDeleteANewWindow(): void
    {
        $cache = new FileCache($this->directory);
        $cache->increment('hits', 1, 1);
        sleep(2);

        self::assertSame(0, $cache->current('hits'));
        self::assertTrue(is_file($this->directory . '/' . sha1('hits') . '.cache'), 'reads leave expired files for the next writer');
        self::assertSame(1, $cache->increment('hits', 1, 60));
        self::assertSame(1, $cache->current('hits'));
    }

    public function testClearRemovesCounterLockFiles(): void
    {
        $cache = new FileCache($this->directory);
        $cache->increment('hits');

        self::assertTrue($cache->clear());
        self::assertSame([], glob($this->directory . '/*') ?: []);
    }
}
