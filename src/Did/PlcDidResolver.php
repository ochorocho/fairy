<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Did;

use Composer\Util\HttpDownloader;

final class PlcDidResolver implements DidResolverInterface
{
    private const PLC_DIRECTORY_URL = 'https://plc.directory/';

    public function __construct(
        private readonly HttpDownloader $httpDownloader,
    ) {
    }

    public function resolve(string $did): DidDocument
    {
        if (!str_starts_with($did, 'did:plc:')) {
            throw new \InvalidArgumentException(sprintf('Unsupported DID method: %s', $did));
        }

        $url = self::PLC_DIRECTORY_URL . $did;
        $response = $this->httpDownloader->get($url);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf('Failed to resolve DID %s: HTTP %d', $did, $statusCode));
        }

        $data = $response->decodeJson();

        return DidDocument::fromArray($data);
    }
}
