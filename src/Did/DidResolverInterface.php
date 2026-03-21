<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Did;

interface DidResolverInterface
{
    public function resolve(string $did): DidDocument;
}
