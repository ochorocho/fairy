<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Did;

use Fair\ComposerPlugin\Did\DidDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DidDocumentTest extends TestCase
{
    private function loadFixture(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/../../Fixtures/did-document.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    #[Test]
    public function fromArrayParsesDidDocument(): void
    {
        $doc = DidDocument::fromArray($this->loadFixture());

        self::assertSame('did:plc:afjf7gsjzsqmgc7dlhb553mv', $doc->id);
        self::assertCount(1, $doc->service);
        self::assertCount(1, $doc->verificationMethod);
    }

    #[Test]
    public function getServiceEndpointReturnsCorrectUrl(): void
    {
        $doc = DidDocument::fromArray($this->loadFixture());
        $endpoint = $doc->getServiceEndpoint();

        self::assertSame(
            'https://fair.git-updater.com/wp-json/fair-beacon/v1/packages/did:plc:afjf7gsjzsqmgc7dlhb553mv',
            $endpoint,
        );
    }

    #[Test]
    public function getServiceEndpointReturnsNullForMissingService(): void
    {
        $doc = new DidDocument(
            id: 'did:plc:test',
            service: [(object) ['id' => '#other', 'type' => 'OtherService', 'serviceEndpoint' => 'https://example.com']],
            verificationMethod: [],
        );

        self::assertNull($doc->getServiceEndpoint());
    }

    #[Test]
    public function getFairSigningKeysFiltersCorrectly(): void
    {
        $doc = DidDocument::fromArray($this->loadFixture());
        $keys = $doc->getFairSigningKeys();

        self::assertCount(1, $keys);
        self::assertSame('z6MkiAezCpuZLSjo58uTXyXcvKgw3jMf6BvkuVMQ4SYfuFZm', $keys[0]->publicKeyMultibase);
    }

    #[Test]
    public function getFairSigningKeysExcludesNonFairKeys(): void
    {
        $doc = new DidDocument(
            id: 'did:plc:test',
            service: [],
            verificationMethod: [
                (object) [
                    'id' => 'did:plc:test#atproto',
                    'type' => 'Multikey',
                    'publicKeyMultibase' => 'z6MkiSomeOtherKey',
                ],
                (object) [
                    'id' => 'did:plc:test#fair_abc123',
                    'type' => 'Multikey',
                    'publicKeyMultibase' => 'z6MkiFairKey',
                ],
            ],
        );

        $keys = $doc->getFairSigningKeys();
        self::assertCount(1, $keys);
        self::assertSame('z6MkiFairKey', $keys[0]->publicKeyMultibase);
    }

    #[Test]
    public function fromArrayThrowsOnMissingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain an "id" string');

        DidDocument::fromArray(['service' => [], 'verificationMethod' => []]);
    }
}
