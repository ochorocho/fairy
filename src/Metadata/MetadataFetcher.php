<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Metadata;

use Composer\Util\HttpDownloader;

final class MetadataFetcher
{
    private const ACCEPT_HEADER = 'application/json+fair;q=1.0, application/json;q=0.8';

    public function __construct(
        private readonly HttpDownloader $httpDownloader,
    ) {
    }

    public function fetch(string $serviceEndpoint): MetadataDocument
    {
        $response = $this->httpDownloader->get($serviceEndpoint, [
            'http' => [
                'header' => ['Accept: ' . self::ACCEPT_HEADER],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf(
                'Failed to fetch FAIR metadata from %s: HTTP %d',
                $serviceEndpoint,
                $statusCode,
            ));
        }

        $data = $response->decodeJson();

        return MetadataDocument::fromArray($data);
    }
}
