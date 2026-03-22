<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Repository;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Process\ProcessExecutor;
use Composer\Repository\ArrayRepository;
use Composer\Util\HttpDownloader;
use Fair\ComposerPlugin\Cache\FairCache;
use Fair\ComposerPlugin\Did\DidDocument;
use Fair\ComposerPlugin\Did\DidResolver;
use Fair\ComposerPlugin\Metadata\MetadataDocument;
use Fair\ComposerPlugin\Metadata\MetadataFetcher;
use Fair\ComposerPlugin\PackageFactory;

final class FairRepository extends ArrayRepository
{
    /** @var array<string, mixed> */
    private readonly array $repoConfig;
    private readonly IOInterface $io;
    private readonly HttpDownloader $httpDownloader;
    private readonly Config $composerConfig;

    /**
     * Constructor signature must match RepositoryManager::createRepository().
     *
     * @param array<string, mixed> $repoConfig
     */
    public function __construct(
        array $repoConfig,
        IOInterface $io,
        Config $composerConfig,
        HttpDownloader $httpDownloader,
        ?EventDispatcher $eventDispatcher = null,
        ?ProcessExecutor $process = null,
    ) {
        parent::__construct();
        $this->repoConfig = $repoConfig;
        $this->io = $io;
        $this->httpDownloader = $httpDownloader;
        $this->composerConfig = $composerConfig;
    }

    public function getRepoName(): string
    {
        return 'fair (' . $this->count() . ' packages)';
    }

    protected function initialize(): void
    {
        parent::initialize();

        $resolver = new DidResolver($this->httpDownloader);
        $fetcher = new MetadataFetcher($this->httpDownloader);
        $factory = new PackageFactory();
        $cache = new FairCache($this->composerConfig);

        $didMap = $this->buildDidMap();

        foreach ($didMap as $packageName => $did) {
            try {
                $didDocument = $this->resolveDidDocument($resolver, $cache, $did);
                $serviceEndpoint = $didDocument->getServiceEndpoint();

                if ($serviceEndpoint === null) {
                    $this->io->writeError(sprintf(
                        '<warning>FAIR: DID %s has no FairPackageManagementRepo service</warning>',
                        $did,
                    ));
                    continue;
                }

                $metadata = $this->fetchMetadata($fetcher, $cache, $serviceEndpoint, $did);

                // Auto-resolve package name if using DID-only config
                if ($packageName === $did) {
                    $vendor = $this->repoConfig['vendor'] ?? 'fair';
                    $packageName = $vendor . '/' . $metadata->slug;
                }

                $packages = $factory->createPackages($metadata, $packageName, $did);
                foreach ($packages as $package) {
                    $this->addPackage($package);
                }

                $this->io->debug(sprintf(
                    'FAIR: Loaded %d versions for %s from %s',
                    count($packages),
                    $packageName,
                    $did,
                ));
            } catch (\Throwable $e) {
                $this->io->writeError(sprintf(
                    '<warning>FAIR: Failed to load %s (%s): %s</warning>',
                    $packageName,
                    $did,
                    $e->getMessage(),
                ));
            }
        }
    }

    /**
     * Build a map of package name => DID from the repository config.
     *
     * Supports two config styles:
     * 1. "packages": {"vendor/name": "did:plc:..."}
     * 2. "dids": ["did:plc:..."] with optional "vendor"
     *
     * @return array<string, string>
     */
    private function buildDidMap(): array
    {
        $map = [];

        if (isset($this->repoConfig['packages']) && is_array($this->repoConfig['packages'])) {
            foreach ($this->repoConfig['packages'] as $name => $did) {
                $map[$name] = $did;
            }
        }

        if (isset($this->repoConfig['dids']) && is_array($this->repoConfig['dids'])) {
            foreach ($this->repoConfig['dids'] as $did) {
                // Use DID as temporary key, will be replaced with slug after metadata fetch
                $map[$did] = $did;
            }
        }

        return $map;
    }

    private function resolveDidDocument(DidResolver $resolver, FairCache $cache, string $did): DidDocument
    {
        $cached = $cache->getDidDocument($did);
        if ($cached !== null) {
            $this->io->debug('FAIR: DID document cache hit for ' . $did);
            return DidDocument::fromArray(json_decode($cached, true, 512, JSON_THROW_ON_ERROR));
        }

        $this->io->debug('FAIR: Resolving DID ' . $did);
        $document = $resolver->resolve($did);
        $cache->setDidDocument($did, json_encode([
            'id' => $document->id,
            'service' => array_map(static fn (object $s): array => (array) $s, $document->service),
            'verificationMethod' => array_map(static fn (object $v): array => (array) $v, $document->verificationMethod),
            'alsoKnownAs' => $document->alsoKnownAs,
        ], JSON_THROW_ON_ERROR));

        return $document;
    }

    private function fetchMetadata(
        MetadataFetcher $fetcher,
        FairCache $cache,
        string $serviceEndpoint,
        string $did,
    ): MetadataDocument {
        $cached = $cache->getMetadata($serviceEndpoint);
        if ($cached !== null) {
            $this->io->debug('FAIR: Metadata cache hit for ' . $did);
            return MetadataDocument::fromArray(json_decode($cached, true, 512, JSON_THROW_ON_ERROR));
        }

        $this->io->debug('FAIR: Fetching metadata for ' . $did);
        $metadata = $fetcher->fetch($serviceEndpoint);
        $cache->setMetadata($serviceEndpoint, json_encode([
            'id' => $metadata->id,
            'type' => $metadata->type,
            'name' => $metadata->name,
            'slug' => $metadata->slug,
            'license' => $metadata->license,
            'description' => $metadata->description,
            'authors' => array_map(static fn (object $a): array => (array) $a, $metadata->authors),
            'keywords' => $metadata->keywords,
            'filename' => $metadata->filename,
            'last_updated' => $metadata->lastUpdated,
            'releases' => array_map(static fn ($r): array => [
                'version' => $r->version,
                'artifacts' => $r->artifacts,
                'requires' => $r->requires,
                'suggests' => $r->suggests,
                'provides' => $r->provides,
            ], $metadata->releases),
        ], JSON_THROW_ON_ERROR));

        return $metadata;
    }
}
