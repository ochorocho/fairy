<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Command;

use Composer\Command\BaseCommand;
use Composer\Factory;
use Composer\Installer;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\DependencyResolver\Request;
use Fair\ComposerPlugin\Did\DidResolver;
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
            ->setDescription('Require a FAIR package by its Decentralized Identifier (DID)')
            ->setDefinition([
                new InputArgument('did', InputArgument::REQUIRED, 'The DID of the package (e.g. did:plc:abc123)'),
                new InputOption('vendor', null, InputOption::VALUE_REQUIRED, 'Vendor prefix for the package name', 'fair'),
                new InputOption('constraint', null, InputOption::VALUE_REQUIRED, 'Version constraint', '*'),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be done'),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();

        /** @var string $did */
        $did = $input->getArgument('did');
        /** @var string $vendor */
        $vendor = $input->getOption('vendor');
        /** @var string $constraint */
        $constraint = $input->getOption('constraint');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!DidResolver::isSupported($did)) {
            $io->writeError('<error>Unsupported DID format. Supported methods: did:plc:, did:web:</error>');
            return 1;
        }

        $composer = $this->requireComposer();
        $httpDownloader = $composer->getLoop()->getHttpDownloader();

        // Step 1: Resolve DID
        $io->write(sprintf('  - Resolving <info>%s</info>...', $did));
        $resolver = new DidResolver($httpDownloader);
        try {
            $didDocument = $resolver->resolve($did);
        } catch (\Throwable $e) {
            $io->writeError(sprintf('<error>Failed to resolve DID: %s</error>', $e->getMessage()));
            return 1;
        }

        $serviceEndpoint = $didDocument->getServiceEndpoint();
        if ($serviceEndpoint === null) {
            $io->writeError('<error>DID has no FairPackageManagementRepo service endpoint.</error>');
            return 1;
        }

        // Step 2: Fetch metadata to get slug
        $io->write(sprintf('  - Fetching metadata from <info>%s</info>...', $serviceEndpoint));
        $fetcher = new MetadataFetcher($httpDownloader);
        try {
            $metadata = $fetcher->fetch($serviceEndpoint);
        } catch (\Throwable $e) {
            $io->writeError(sprintf('<error>Failed to fetch FAIR metadata: %s</error>', $e->getMessage()));
            return 1;
        }

        $packageName = $vendor . '/' . $metadata->slug;
        $latestVersion = $metadata->releases[0]->version ?? 'unknown';

        $io->write(sprintf(
            '  - Found <info>%s</info> (<comment>%s</comment>) — latest: <comment>%s</comment>',
            $metadata->name,
            $packageName,
            $latestVersion,
        ));

        if ($dryRun) {
            $io->write('');
            $io->write('<comment>Dry run — no changes written.</comment>');
            $io->write(sprintf('  repositories: { type: fair, packages: { %s: %s } }', $packageName, $did));
            $io->write(sprintf('  require:       %s: %s', $packageName, $constraint));
            return 0;
        }

        // Step 3: Update composer.json
        $composerJsonPath = Factory::getComposerFile();
        $json = new JsonFile($composerJsonPath);

        $contents = file_get_contents($composerJsonPath);
        if ($contents === false) {
            $io->writeError('<error>Could not read composer.json</error>');
            return 1;
        }

        $manipulator = new JsonManipulator($contents);
        $decoded = $json->read();

        // Find an existing fair repository to append to, or create a new one.
        $fairRepoIndex = $this->findFairRepositoryIndex($decoded);

        if ($fairRepoIndex !== null) {
            $existingPackages = $decoded['repositories'][$fairRepoIndex]['packages'] ?? [];
            $existingPackages[$packageName] = $did;

            $updatedRepo = $decoded['repositories'][$fairRepoIndex];
            $updatedRepo['packages'] = $existingPackages;

            $manipulator->removeListItem('repositories', $fairRepoIndex);
            $manipulator->addRepository('', $updatedRepo);
        } else {
            $manipulator->addRepository('', [
                'type'     => 'fair',
                'packages' => [$packageName => $did],
            ]);
        }

        $manipulator->addLink('require', $packageName, $constraint);

        if (false === file_put_contents($composerJsonPath, $manipulator->getContents())) {
            $io->writeError('<error>Could not write composer.json</error>');
            return 1;
        }

        $io->write(sprintf(
            '  - <info>composer.json</info> updated: added <comment>%s</comment> (<comment>%s</comment>)',
            $packageName,
            $did,
        ));

        // Step 4: Install the new package
        $io->write('');
        $io->write(sprintf('Running <info>composer update %s</info>...', $packageName));
        $io->write('');

        $this->resetComposer();
        $composer = $this->requireComposer();

        $install = Installer::create($io, $composer);
        $install
            ->setUpdate(true)
            ->setUpdateAllowList([$packageName])
            ->setUpdateAllowTransitiveDependencies(Request::UPDATE_LISTED_WITH_TRANSITIVE_DEPS);

        return $install->run();
    }

    /**
     * Find the array index of an existing "fair" type repository in composer.json,
     * or null if none exists yet.
     *
     * @param array<string, mixed> $decoded
     */
    private function findFairRepositoryIndex(array $decoded): ?int
    {
        foreach ($decoded['repositories'] ?? [] as $index => $repo) {
            if (is_array($repo) && ($repo['type'] ?? '') === 'fair') {
                return (int) $index;
            }
        }

        return null;
    }
}
