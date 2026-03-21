<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin;

use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\Constraint;
use Fair\ComposerPlugin\Metadata\MetadataDocument;
use Fair\ComposerPlugin\Metadata\ReleaseDocument;

final class PackageFactory
{
    private const TYPE_MAP = [
        'wp-plugin' => 'wordpress-plugin',
        'wp-theme' => 'wordpress-theme',
    ];

    private readonly VersionParser $versionParser;

    public function __construct()
    {
        $this->versionParser = new VersionParser();
    }

    /**
     * @return array<int, CompletePackage>
     */
    public function createPackages(MetadataDocument $metadata, string $packageName, string $did): array
    {
        $packages = [];

        foreach ($metadata->releases as $release) {
            $package = $this->createPackage($metadata, $release, $packageName, $did);
            if ($package !== null) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    private function createPackage(
        MetadataDocument $metadata,
        ReleaseDocument $release,
        string $packageName,
        string $did,
    ): ?CompletePackage {
        $distUrl = $release->getPackageUrl();
        if ($distUrl === null) {
            return null;
        }

        try {
            $normalizedVersion = $this->versionParser->normalize($release->version);
        } catch (\UnexpectedValueException) {
            return null;
        }

        $package = new CompletePackage($packageName, $normalizedVersion, $release->version);

        $package->setDistUrl($distUrl);
        $package->setDistType('zip');

        $composerType = self::TYPE_MAP[$metadata->type] ?? 'library';
        $package->setType($composerType);

        $package->setLicense([$metadata->license]);

        if ($metadata->description !== null) {
            $package->setDescription($metadata->description);
        }

        if ($metadata->keywords !== []) {
            $package->setKeywords($metadata->keywords);
        }

        $authors = [];
        foreach ($metadata->authors as $author) {
            $entry = ['name' => $author->name];
            if (isset($author->url)) {
                $entry['homepage'] = $author->url;
            }
            $authors[] = $entry;
        }
        if ($authors !== []) {
            $package->setAuthors($authors);
        }

        $requires = $this->buildRequires($packageName, $release);
        if ($requires !== []) {
            $package->setRequires($requires);
        }

        $extra = [
            'fair' => [
                'did' => $did,
                'checksum' => $release->getPackageChecksum(),
                'signature' => $release->getPackageSignature(),
            ],
        ];
        $package->setExtra($extra);

        return $package;
    }

    /**
     * @return array<string, Link>
     */
    private function buildRequires(string $packageName, ReleaseDocument $release): array
    {
        $links = [];

        foreach ($release->requires as $key => $constraintString) {
            if ($key === 'env:php') {
                $prettyConstraint = $constraintString;
                try {
                    $constraint = $this->versionParser->parseConstraints($prettyConstraint);
                } catch (\UnexpectedValueException) {
                    continue;
                }
                $links['php'] = new Link($packageName, 'php', $constraint, Link::TYPE_REQUIRE, $prettyConstraint);
            }
        }

        return $links;
    }
}
