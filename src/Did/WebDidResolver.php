<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Did;

use Composer\Util\HttpDownloader;

/**
 * Resolves did:web DIDs by fetching a did.json document over HTTPS.
 *
 * Resolution rules per the W3C did:web spec:
 *   did:web:example.com              → https://example.com/.well-known/did.json
 *   did:web:example.com:path:to:pkg  → https://example.com/path/to/pkg/did.json
 *
 * AspireCloud uses this format:
 *   did:web:api.aspiredev.org:packages:typo3-extension:my-extension
 *   → https://api.aspiredev.org/packages/typo3-extension/my-extension/did.json
 */
final class WebDidResolver implements DidResolverInterface
{
    public function __construct(
        private readonly HttpDownloader $httpDownloader,
    ) {
    }

    public function resolve(string $did): DidDocument
    {
        if (!str_starts_with($did, 'did:web:')) {
            throw new \InvalidArgumentException(sprintf('Unsupported DID method for WebDidResolver: %s', $did));
        }

        $url = $this->toUrl($did);
        $response = $this->httpDownloader->get($url);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf('Failed to resolve DID %s: HTTP %d', $did, $statusCode));
        }

        return DidDocument::fromArray($response->decodeJson());
    }

    /**
     * Convert a did:web DID to its document URL.
     *
     * did:web:example.com              → https://example.com/.well-known/did.json
     * did:web:example.com:path:to:pkg  → https://example.com/path/to/pkg/did.json
     * did:web:localhost%3A8080         → http://localhost:8080/.well-known/did.json
     *
     * Per the W3C did:web spec the domain component MUST be percent-decoded before
     * constructing the URL (e.g. %3A → : for port numbers).
     * localhost/127.0.0.1 uses http:// to support local development without TLS.
     */
    public function toUrl(string $did): string
    {
        $identifier = substr($did, strlen('did:web:'));
        $parts = explode(':', $identifier);
        $domain = rawurldecode((string) array_shift($parts));

        // Strip port for scheme decision, keep full domain (host:port) in URL.
        $host = explode(':', $domain)[0];
        $scheme = ($host === 'localhost' || $host === '127.0.0.1') ? 'http' : 'https';

        if ($parts === []) {
            return $scheme . '://' . $domain . '/.well-known/did.json';
        }

        return $scheme . '://' . $domain . '/' . implode('/', array_map('rawurldecode', $parts)) . '/did.json';
    }
}
