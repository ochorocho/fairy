<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Did;

use Fair\ComposerPlugin\Did\DidResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DidResolverTest extends TestCase
{
    #[Test]
    public function isSupportedReturnsTrueForPlc(): void
    {
        self::assertTrue(DidResolver::isSupported('did:plc:afjf7gsjzsqmgc7dlhb553mv'));
    }

    #[Test]
    public function isSupportedReturnsTrueForWeb(): void
    {
        self::assertTrue(DidResolver::isSupported('did:web:example.com'));
    }

    #[Test]
    public function isSupportedReturnsFalseForUnknownMethod(): void
    {
        self::assertFalse(DidResolver::isSupported('did:key:z6Mk'));
        self::assertFalse(DidResolver::isSupported('not-a-did'));
    }

    #[Test]
    public function resolveThrowsOnUnsupportedMethod(): void
    {
        $downloader = $this->createMock(\Composer\Util\HttpDownloader::class);
        $resolver = new DidResolver($downloader);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported DID method/');
        $resolver->resolve('did:key:z6Mk');
    }
}
