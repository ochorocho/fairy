<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Exception\IrrecoverableDownloadException;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Fair\ComposerPlugin\Command\FairCommandProvider;
use Fair\ComposerPlugin\Did\DidResolver;
use Fair\ComposerPlugin\Repository\FairRepository;
use Fair\ComposerPlugin\Security\SignatureVerifier;

final class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;

        $composer->getRepositoryManager()->setRepositoryClass('fair', FairRepository::class);

        $io->debug('FAIR plugin activated: registered fair repository type');
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public function getCapabilities(): array
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => FairCommandProvider::class,
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PRE_FILE_DOWNLOAD => ['onPreFileDownload', 0],
            PluginEvents::POST_FILE_DOWNLOAD => ['onPostFileDownload', 0],
        ];
    }

    /**
     * Phase 1: Validate the source BEFORE any download happens.
     *
     * Verifies DID resolution, signing keys, and service endpoint.
     * Throws IrrecoverableDownloadException to block the download entirely —
     * no HTTP request is made, no file touches disk.
     */
    public function onPreFileDownload(PreFileDownloadEvent $event): void
    {
        if ($event->getType() !== 'package') {
            return;
        }

        $context = $event->getContext();
        if (!$context instanceof PackageInterface) {
            return;
        }

        $extra = $context->getExtra();
        if (!isset($extra['fair'])) {
            return;
        }

        $did = $extra['fair']['did'] ?? null;
        if (!is_string($did)) {
            throw new IrrecoverableDownloadException(
                'FAIR: Package has fair metadata but no DID — refusing download',
            );
        }

        $resolver = new DidResolver($this->composer->getLoop()->getHttpDownloader());

        try {
            $didDocument = $resolver->resolve($did);
        } catch (\Throwable $e) {
            throw new IrrecoverableDownloadException(
                sprintf('FAIR: Cannot resolve DID %s — refusing download: %s', $did, $e->getMessage()),
            );
        }

        if ($didDocument->getFairSigningKeys() === []) {
            throw new IrrecoverableDownloadException(
                sprintf('FAIR: DID %s has no signing keys — refusing download', $did),
            );
        }

        if ($didDocument->getServiceEndpoint() === null) {
            throw new IrrecoverableDownloadException(
                sprintf('FAIR: DID %s has no FAIR service endpoint — refusing download', $did),
            );
        }

        $this->io->debug(sprintf('FAIR: Pre-download validation passed for %s', $context->getName()));
    }

    /**
     * Phase 2: Verify file content AFTER download.
     *
     * Checks SHA-256 checksum and Ed25519 signature.
     * On failure: deletes the file from disk, then throws.
     */
    public function onPostFileDownload(PostFileDownloadEvent $event): void
    {
        if ($event->getType() !== 'package') {
            return;
        }

        $context = $event->getContext();
        if (!$context instanceof PackageInterface) {
            return;
        }

        $extra = $context->getExtra();
        if (!isset($extra['fair'])) {
            return;
        }

        $fairData = $extra['fair'];
        $filePath = $event->getFileName();

        if ($filePath === null || !file_exists($filePath)) {
            return;
        }

        try {
            $this->verifyFairPackage($filePath, $fairData, $context->getName());
        } catch (\RuntimeException $e) {
            // Remove unverified file from disk immediately
            if (file_exists($filePath)) {
                unlink($filePath);
                $this->io->debug('FAIR: Removed unverified file ' . $filePath);
            }
            throw $e;
        }

        $this->io->write(sprintf(
            '<warning>FAIR: Verified integrity of %s</warning>',
            $context->getName(),
        ));
    }

    /**
     * @param array<string, mixed> $fairData
     */
    private function verifyFairPackage(string $filePath, array $fairData, string $packageName): void
    {
        $verifier = new SignatureVerifier($this->io);

        $checksum = $fairData['checksum'] ?? null;
        if (is_string($checksum) && !$verifier->verifyChecksum($filePath, $checksum)) {
            throw new \RuntimeException(sprintf('FAIR: Checksum verification failed for %s', $packageName));
        }

        $signature = $fairData['signature'] ?? null;
        $did = $fairData['did'] ?? null;
        if (is_string($signature) && is_string($did)) {
            $resolver = new DidResolver($this->composer->getLoop()->getHttpDownloader());
            $didDocument = $resolver->resolve($did);

            if (!$verifier->verifySignature($filePath, $signature, $didDocument)) {
                throw new \RuntimeException(sprintf('FAIR: Signature verification failed for %s', $packageName));
            }
        }
    }
}
