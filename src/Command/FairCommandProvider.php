<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Command;

use Composer\Plugin\Capability\CommandProvider;

final class FairCommandProvider implements CommandProvider
{
    public function getCommands(): array
    {
        return [
            new FairRequireCommand(),
        ];
    }
}
