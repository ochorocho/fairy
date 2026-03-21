<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Metadata;

use Fair\ComposerPlugin\Metadata\MetadataDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetadataDocumentTest extends TestCase
{
    private function loadFixture(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../Fixtures/metadata-document.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    #[Test]
    public function fromArrayParsesMetadata(): void
    {
        $doc = MetadataDocument::fromArray($this->loadFixture());

        self::assertSame('did:plc:afjf7gsjzsqmgc7dlhb553mv', $doc->id);
        self::assertSame('wp-plugin', $doc->type);
        self::assertSame('Git Updater', $doc->name);
        self::assertSame('git-updater', $doc->slug);
        self::assertSame('GPL-3.0-or-later', $doc->license);
        self::assertCount(2, $doc->releases);
        self::assertCount(1, $doc->authors);
        self::assertSame('Andy Fragen', $doc->authors[0]->name);
    }

    #[Test]
    public function fromArrayThrowsOnMissingField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing mandatory field: slug');

        MetadataDocument::fromArray([
            'id' => 'did:plc:test',
            'type' => 'wp-plugin',
            'name' => 'Test',
            'license' => 'MIT',
            'releases' => [['version' => '1.0.0', 'artifacts' => ['package' => [['url' => 'https://example.com']]]]],
        ]);
    }

    #[Test]
    public function fromArrayThrowsOnNoReleases(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one release');

        MetadataDocument::fromArray([
            'id' => 'did:plc:test',
            'type' => 'wp-plugin',
            'name' => 'Test',
            'slug' => 'test',
            'license' => 'MIT',
            'releases' => [],
        ]);
    }
}
