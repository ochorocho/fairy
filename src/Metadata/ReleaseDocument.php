<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Metadata;

final class ReleaseDocument
{
    /**
     * @param object{package?: array<int, object{url: string, signature?: string, checksum?: string, 'content-type'?: string}>} $artifacts
     * @param array<string, string> $requires
     * @param array<string, string> $suggests
     * @param array<int, mixed> $provides
     */
    public function __construct(
        public readonly string $version,
        public readonly object $artifacts,
        public readonly array $requires = [],
        public readonly array $suggests = [],
        public readonly array $provides = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['version']) || !is_string($data['version'])) {
            throw new \InvalidArgumentException('Missing mandatory field: version');
        }

        if (!isset($data['artifacts'])) {
            throw new \InvalidArgumentException('Missing mandatory field: artifacts');
        }

        $artifacts = is_array($data['artifacts']) ? (object) $data['artifacts'] : $data['artifacts'];

        // Convert package artifacts to objects
        if (isset($artifacts->package) && is_array($artifacts->package)) {
            $artifacts->package = array_map(
                static fn (mixed $pkg): object => is_array($pkg) ? (object) $pkg : $pkg,
                $artifacts->package,
            );
        }

        return new self(
            version: $data['version'],
            artifacts: $artifacts,
            requires: (array) ($data['requires'] ?? []),
            suggests: (array) ($data['suggests'] ?? []),
            provides: (array) ($data['provides'] ?? []),
        );
    }

    public function getPackageUrl(): ?string
    {
        if (!isset($this->artifacts->package) || !is_array($this->artifacts->package)) {
            return null;
        }

        $first = $this->artifacts->package[0] ?? null;

        return $first?->url ?? null;
    }

    public function getPackageChecksum(): ?string
    {
        if (!isset($this->artifacts->package) || !is_array($this->artifacts->package)) {
            return null;
        }

        $first = $this->artifacts->package[0] ?? null;

        return $first?->checksum ?? null;
    }

    public function getPackageSignature(): ?string
    {
        if (!isset($this->artifacts->package) || !is_array($this->artifacts->package)) {
            return null;
        }

        $first = $this->artifacts->package[0] ?? null;

        return $first?->signature ?? null;
    }
}
