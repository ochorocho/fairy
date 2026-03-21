<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit;

use Fair\ComposerPlugin\Metadata\MetadataDocument;
use Fair\ComposerPlugin\PackageFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageFactoryTest extends TestCase
{
    private function loadMetadata(): MetadataDocument
    {
        $data = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/metadata-document.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return MetadataDocument::fromArray($data);
    }

    #[Test]
    public function createsPackagesFromMetadata(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        self::assertCount(2, $packages);
    }

    #[Test]
    public function packageHasCorrectName(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        self::assertSame('fair/git-updater', $packages[0]->getName());
    }

    #[Test]
    public function packageHasCorrectVersion(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        self::assertSame('12.24.1', $packages[0]->getPrettyVersion());
    }

    #[Test]
    public function packageHasDistUrl(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        self::assertSame('https://api.github.com/repos/afragen/git-updater/zipball/12.24.1', $packages[0]->getDistUrl());
        self::assertSame('zip', $packages[0]->getDistType());
    }

    #[Test]
    public function packageHasCorrectType(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        self::assertSame('wordpress-plugin', $packages[0]->getType());
    }

    #[Test]
    public function packageHasLicense(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        self::assertSame(['GPL-3.0-or-later'], $packages[0]->getLicense());
    }

    #[Test]
    public function packageHasPhpRequirement(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        $requires = $packages[0]->getRequires();
        self::assertArrayHasKey('php', $requires);
        self::assertSame('>=8.0', $requires['php']->getPrettyConstraint());
    }

    #[Test]
    public function packageHasFairExtra(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        $extra = $packages[0]->getExtra();
        self::assertArrayHasKey('fair', $extra);
        self::assertSame('did:plc:afjf7gsjzsqmgc7dlhb553mv', $extra['fair']['did']);
        self::assertStringStartsWith('sha256:', $extra['fair']['checksum']);
        self::assertNotEmpty($extra['fair']['signature']);
    }

    #[Test]
    public function packageHasAuthors(): void
    {
        $factory = new PackageFactory();
        $metadata = $this->loadMetadata();
        $packages = $factory->createPackages($metadata, 'fair/git-updater', 'did:plc:afjf7gsjzsqmgc7dlhb553mv');

        $authors = $packages[0]->getAuthors();
        self::assertCount(1, $authors);
        self::assertSame('Andy Fragen', $authors[0]['name']);
    }
}
