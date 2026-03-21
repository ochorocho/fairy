<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Command;

use Composer\Command\BaseCommand;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Fair\ComposerPlugin\Did\PlcDidResolver;
use Fair\ComposerPlugin\Metadata\MetadataFetcher;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class FairRequireCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('fair:require')
            ->setDescription('Require a FAIR package by DID')
            ->setDefinition([
                new InputArgument('did', InputArgument::REQUIRED, 'The DID of the package (e.g. did:plc:abc123)'),
                new InputOption('vendor', null, InputOption::VALUE_REQUIRED, 'Vendor prefix for the package name', 'fair'),
                new InputOption('constraint', null, InputOption::VALUE_REQUIRED, 'Version constraint', '*'),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be done'),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $did = $input->getArgument('did');
        $vendor = $input->getOption('vendor');
        $constraint = $input->getOption('constraint');
        $dryRun = $input->getOption('dry-run');
        $io = $this->getIO();

        if (!str_starts_with($did, 'did:plc:')) {
            $io->writeError('<error>Invalid DID format. Expected did:plc:...</error>');
            return 1;
        }

        // Step 1: Resolve DID
        $io->write(sprintf('<info>Resolving DID %s...</info>', $did));
        $composer = $this->requireComposer();
        $httpDownloader = $composer->getLoop()->getHttpDownloader();

        $resolver = new PlcDidResolver($httpDownloader);
        try {
            $didDocument = $resolver->resolve($did);
        } catch (\Throwable $e) {
            $io->writeError(sprintf('<error>Failed to resolve DID: %s</error>', $e->getMessage()));
            return 1;
        }

        $serviceEndpoint = $didDocument->getServiceEndpoint();
        if ($serviceEndpoint === null) {
            $io->writeError('<error>DID has no FairPackageManagementRepo service</error>');
            return 1;
        }

        // Step 2: Fetch metadata to get slug
        $io->write(sprintf('<info>Fetching metadata from %s...</info>', $serviceEndpoint));
        $fetcher = new MetadataFetcher($httpDownloader);
        try {
            $metadata = $fetcher->fetch($serviceEndpoint);
        } catch (\Throwable $e) {
            $io->writeError(sprintf('<error>Failed to fetch metadata: %s</error>', $e->getMessage()));
            return 1;
        }

        $packageName = $vendor . '/' . $metadata->slug;
        $latestVersion = $metadata->releases[0]->version ?? 'unknown';

        $io->write(sprintf('<info>Found package: %s (%s) - %s</info>', $metadata->name, $packageName, $latestVersion));

        if ($dryRun) {
            $io->write('<comment>Dry run — no changes made.</comment>');
            return 0;
        }

        // Step 3: Update composer.json
        $composerJsonPath = $composer->getConfig()->getConfigSource()->getName();
        // The config source name is for config, we need the project root composer.json
        $composerJsonPath = getcwd() . '/composer.json';

        $contents = file_get_contents($composerJsonPath);
        if ($contents === false) {
            $io->writeError('<error>Could not read composer.json</error>');
            return 1;
        }

        $manipulator = new JsonManipulator($contents);

        // Add to extra.fair-repositories
        $json = json_decode($contents, true);
        $fairRepos = $json['extra']['fair-repositories'] ?? [];

        // Find or create a repository entry that contains this DID
        $repoName = 'fair';
        $existingPackages = $fairRepos[$repoName]['packages'] ?? [];
        $existingPackages[$packageName] = $did;

        $fairRepos[$repoName] = ['packages' => $existingPackages];

        $manipulator->addSubNode('extra', 'fair-repositories', $fairRepos);

        // Add to require
        $manipulator->addLink('require', $packageName, $constraint);

        file_put_contents($composerJsonPath, $manipulator->getContents());
        $io->write(sprintf('<info>Added %s (%s) to composer.json</info>', $packageName, $did));

        // Step 4: Run composer update as subprocess so it re-reads composer.json
        $io->write('<info>Running composer update...</info>');

        $composerBin = getenv('COMPOSER_BINARY') ?: (PHP_BINARY . ' ' . escapeshellarg($_SERVER['argv'][0]));
        $cmd = sprintf(
            '%s update %s --with-all-dependencies',
            $composerBin,
            escapeshellarg($packageName),
        );

        $exitCode = 0;
        passthru($cmd, $exitCode);

        return $exitCode;
    }
}
