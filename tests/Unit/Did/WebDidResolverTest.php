<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Did;

use Fair\ComposerPlugin\Did\WebDidResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebDidResolverTest extends TestCase
{
    private WebDidResolver $resolver;

    protected function setUp(): void
    {
        // We only test toUrl() here, which needs no HTTP
        $downloader = $this->createMock(\Composer\Util\HttpDownloader::class);
        $this->resolver = new WebDidResolver($downloader);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urlProvider(): array
    {
        return [
            'root domain'          => ['did:web:example.com', 'https://example.com/.well-known/did.json'],
            'domain with path'     => ['did:web:example.com:packages:my-lib', 'https://example.com/packages/my-lib/did.json'],
            'DDEV mock server'     => ['did:web:fair-mapper.ddev.site', 'https://fair-mapper.ddev.site/.well-known/did.json'],
            'AspireCloud TYPO3'    => [
                'did:web:api.aspiredev.org:packages:typo3-extension:my-extension',
                'https://api.aspiredev.org/packages/typo3-extension/my-extension/did.json',
            ],
            'TER domain'           => ['did:web:ter.typo3.org:ext:news', 'https://ter.typo3.org/ext/news/did.json'],
            'localhost plain'      => ['did:web:localhost', 'http://localhost/.well-known/did.json'],
            'localhost with port'  => ['did:web:localhost%3A8080', 'http://localhost:8080/.well-known/did.json'],
            '127.0.0.1'           => ['did:web:127.0.0.1', 'http://127.0.0.1/.well-known/did.json'],
        ];
    }

    #[Test]
    #[DataProvider('urlProvider')]
    public function toUrlGeneratesCorrectUrl(string $did, string $expectedUrl): void
    {
        self::assertSame($expectedUrl, $this->resolver->toUrl($did));
    }

    #[Test]
    public function resolveThrowsOnUnsupportedMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->resolve('did:plc:abc123');
    }
}
