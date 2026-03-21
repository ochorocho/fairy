<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Metadata;

use Fair\ComposerPlugin\Metadata\ReleaseDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleaseDocumentTest extends TestCase
{
    #[Test]
    public function fromArrayParsesRelease(): void
    {
        $release = ReleaseDocument::fromArray([
            'version' => '12.24.1',
            'requires' => ['env:php' => '>=8.0'],
            'suggests' => ['env:wp' => '>=7.0.4'],
            'provides' => [],
            'artifacts' => [
                'package' => [
                    [
                        'url' => 'https://example.com/package.zip',
                        'signature' => 'abc123',
                        'checksum' => 'sha256:deadbeef',
                    ],
                ],
            ],
        ]);

        self::assertSame('12.24.1', $release->version);
        self::assertSame('https://example.com/package.zip', $release->getPackageUrl());
        self::assertSame('sha256:deadbeef', $release->getPackageChecksum());
        self::assertSame('abc123', $release->getPackageSignature());
        self::assertSame(['env:php' => '>=8.0'], $release->requires);
    }

    #[Test]
    public function fromArrayThrowsOnMissingVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReleaseDocument::fromArray(['artifacts' => ['package' => []]]);
    }

    #[Test]
    public function fromArrayThrowsOnMissingArtifacts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReleaseDocument::fromArray(['version' => '1.0.0']);
    }

    #[Test]
    public function getPackageUrlReturnsNullWithoutPackageArtifact(): void
    {
        $release = ReleaseDocument::fromArray([
            'version' => '1.0.0',
            'artifacts' => ['banner' => []],
        ]);

        self::assertNull($release->getPackageUrl());
    }
}
