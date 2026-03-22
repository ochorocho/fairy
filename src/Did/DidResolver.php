<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Did;

use Composer\Util\HttpDownloader;

/**
 * Dispatches DID resolution to the correct method-specific resolver.
 *
 * Supported DID methods:
 *   did:plc:  — resolved via the PLC Directory (https://plc.directory/)
 *   did:web:  — resolved via HTTPS did.json documents (W3C did:web spec)
 *
 * This is the single entry point for all DID resolution in FAIR.
 */
final class DidResolver implements DidResolverInterface
{
    public function __construct(
        private readonly HttpDownloader $httpDownloader,
    ) {
    }

    public function resolve(string $did): DidDocument
    {
        return $this->getResolver($did)->resolve($did);
    }

    /**
     * Returns true if the given string looks like a supported DID.
     */
    public static function isSupported(string $did): bool
    {
        return str_starts_with($did, 'did:plc:') || str_starts_with($did, 'did:web:');
    }

    private function getResolver(string $did): DidResolverInterface
    {
        if (str_starts_with($did, 'did:plc:')) {
            return new PlcDidResolver($this->httpDownloader);
        }

        if (str_starts_with($did, 'did:web:')) {
            return new WebDidResolver($this->httpDownloader);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported DID method: %s. Supported methods: did:plc:, did:web:',
            $did,
        ));
    }
}
