<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Did;

final class DidDocument
{
    private const SERVICE_TYPE = 'FairPackageManagementRepo';

    /**
     * @param array<int, object{id: string, type: string, serviceEndpoint: string}> $service
     * @param array<int, object{id: string, type: string, controller: string, publicKeyMultibase: string}> $verificationMethod
     * @param array<int, string> $alsoKnownAs
     */
    public function __construct(
        public readonly string $id,
        public readonly array $service,
        public readonly array $verificationMethod,
        public readonly array $alsoKnownAs = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id']) || !is_string($data['id'])) {
            throw new \InvalidArgumentException('DID Document must contain an "id" string');
        }

        $service = [];
        foreach ($data['service'] ?? [] as $svc) {
            $service[] = (object) $svc;
        }

        $verificationMethod = [];
        foreach ($data['verificationMethod'] ?? [] as $vm) {
            $verificationMethod[] = (object) $vm;
        }

        return new self(
            id: $data['id'],
            service: $service,
            verificationMethod: $verificationMethod,
            alsoKnownAs: $data['alsoKnownAs'] ?? [],
        );
    }

    public function getServiceEndpoint(): ?string
    {
        foreach ($this->service as $svc) {
            if ($svc->type === self::SERVICE_TYPE) {
                return $svc->serviceEndpoint;
            }
        }

        return null;
    }

    /**
     * Get signing keys with fragment IDs starting with "fair".
     *
     * @return array<int, object{id: string, type: string, publicKeyMultibase: string}>
     */
    public function getFairSigningKeys(): array
    {
        return array_values(array_filter(
            $this->verificationMethod,
            static function (object $key): bool {
                if ($key->type !== 'Multikey') {
                    return false;
                }

                $parsed = parse_url($key->id);

                return isset($parsed['fragment']) && str_starts_with($parsed['fragment'], 'fair');
            }
        ));
    }
}
