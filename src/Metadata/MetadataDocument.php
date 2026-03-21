<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Metadata;

final class MetadataDocument
{
    /**
     * @param array<int, ReleaseDocument> $releases
     * @param array<int, object{name: string, url?: string}> $authors
     * @param array<int, string> $keywords
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $license,
        public readonly array $releases,
        public readonly array $authors = [],
        public readonly array $keywords = [],
        public readonly ?string $description = null,
        public readonly ?string $filename = null,
        public readonly ?string $lastUpdated = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $requiredFields = ['id', 'type', 'name', 'slug', 'license'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                throw new \InvalidArgumentException(sprintf('Missing mandatory field: %s', $field));
            }
        }

        $releases = [];
        foreach ($data['releases'] ?? [] as $releaseData) {
            $releases[] = ReleaseDocument::fromArray((array) $releaseData);
        }

        if ($releases === []) {
            throw new \InvalidArgumentException('Metadata document must contain at least one release');
        }

        $authors = [];
        foreach ($data['authors'] ?? [] as $author) {
            $authors[] = (object) $author;
        }

        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            slug: $data['slug'],
            license: $data['license'],
            releases: $releases,
            authors: $authors,
            keywords: $data['keywords'] ?? [],
            description: $data['description'] ?? null,
            filename: $data['filename'] ?? null,
            lastUpdated: $data['last_updated'] ?? null,
        );
    }
}
