<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PostFileDownloadEvent;
use Fair\ComposerPlugin\Did\PlcDidResolver;
use Fair\ComposerPlugin\Repository\FairRepository;
use Fair\ComposerPlugin\Security\SignatureVerifier;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;

        $extra = $composer->getPackage()->getExtra();
        $fairConfig = $extra['fair-repositories'] ?? [];

        if ($fairConfig === []) {
            $io->debug('FAIR plugin activated: no fair-repositories configured');
            return;
        }

        $repositoryManager = $composer->getRepositoryManager();
        $config = $composer->getConfig();
        $httpDownloader = $composer->getLoop()->getHttpDownloader();

        foreach ($fairConfig as $name => $repoConfig) {
            $repoConfig = is_array($repoConfig) ? $repoConfig : [];
            $io->debug(sprintf('FAIR: Creating repository "%s"', is_string($name) ? $name : 'fair'));

            $repository = new FairRepository(
                $repoConfig,
                $io,
                $config,
                $httpDownloader,
            );

            $repositoryManager->addRepository($repository);
        }

        $io->debug('FAIR plugin activated: registered FAIR repositories');
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::POST_FILE_DOWNLOAD => ['onPostFileDownload', 0],
        ];
    }

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

        $verifier = new SignatureVerifier($this->io);

        // Verify checksum
        $checksum = $fairData['checksum'] ?? null;
        if (is_string($checksum)) {
            if (!$verifier->verifyChecksum($filePath, $checksum)) {
                throw new \RuntimeException(sprintf(
                    'FAIR: Checksum verification failed for %s',
                    $context->getName(),
                ));
            }
        }

        // Verify signature
        $signature = $fairData['signature'] ?? null;
        $did = $fairData['did'] ?? null;
        if (is_string($signature) && is_string($did)) {
            $resolver = new PlcDidResolver($this->composer->getLoop()->getHttpDownloader());
            $didDocument = $resolver->resolve($did);

            if (!$verifier->verifySignature($filePath, $signature, $didDocument)) {
                throw new \RuntimeException(sprintf(
                    'FAIR: Signature verification failed for %s',
                    $context->getName(),
                ));
            }
        }

        $this->io->write(sprintf(
            '<info>FAIR: Verified integrity of %s</info>',
            $context->getName(),
        ));
    }
}
