<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Cache;

use Composer\Config;
use Fair\ComposerPlugin\Cache\FairCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FairCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/fair-cache-test-' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createCache(): FairCache
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('cache-dir')->willReturn($this->tempDir);

        return new FairCache($config);
    }

    #[Test]
    public function didDocumentCacheRoundTrip(): void
    {
        $cache = $this->createCache();
        $did = 'did:plc:test123';
        $json = '{"id":"did:plc:test123"}';

        self::assertNull($cache->getDidDocument($did));

        $cache->setDidDocument($did, $json);
        self::assertSame($json, $cache->getDidDocument($did));
    }

    #[Test]
    public function metadataCacheRoundTrip(): void
    {
        $cache = $this->createCache();
        $endpoint = 'https://example.com/metadata';
        $json = '{"slug":"test"}';

        self::assertNull($cache->getMetadata($endpoint));

        $cache->setMetadata($endpoint, $json);
        self::assertSame($json, $cache->getMetadata($endpoint));
    }
}
