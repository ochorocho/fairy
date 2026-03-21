<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Security;

use Composer\IO\IOInterface;
use Fair\ComposerPlugin\Did\DidDocument;

final class SignatureVerifier
{
    public function __construct(
        private readonly IOInterface $io,
    ) {
    }

    /**
     * Verify the SHA-256 checksum of a downloaded file.
     *
     * @param string $checksum Expected checksum in format "sha256:{hex}"
     */
    public function verifyChecksum(string $filePath, string $checksum): bool
    {
        if (!str_starts_with($checksum, 'sha256:')) {
            $this->io->writeError('<warning>Unsupported checksum format: ' . $checksum . '</warning>');
            return false;
        }

        $expectedHash = substr($checksum, 7);
        $actualHash = hash_file('sha256', $filePath);

        if ($actualHash !== $expectedHash) {
            $this->io->writeError(sprintf(
                '<error>Checksum mismatch: expected %s, got %s</error>',
                $expectedHash,
                $actualHash,
            ));
            return false;
        }

        $this->io->debug('FAIR: SHA-256 checksum verified');

        return true;
    }

    /**
     * Verify the Ed25519 signature of a downloaded file.
     *
     * The signature is Base64URL-encoded (no padding) in the metadata.
     * The file's SHA-256 hash is signed, not the file contents directly.
     */
    public function verifySignature(string $filePath, string $signatureBase64Url, DidDocument $didDocument): bool
    {
        $signingKeys = $didDocument->getFairSigningKeys();
        if ($signingKeys === []) {
            $this->io->writeError('<error>No FAIR signing keys found in DID document</error>');
            return false;
        }

        $signature = sodium_base642bin($signatureBase64Url, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        // The FAIR reference implementation (WordPress) signs the SHA-384 hash of the file
        $fileHash = hash_file('sha384', $filePath, true);

        foreach ($signingKeys as $key) {
            try {
                $publicKey = KeyDecoder::decodeEd25519PublicKey($key->publicKeyMultibase);
            } catch (\InvalidArgumentException $e) {
                $this->io->debug('FAIR: Skipping key ' . $key->id . ': ' . $e->getMessage());
                continue;
            }

            if (sodium_crypto_sign_verify_detached($signature, $fileHash, $publicKey)) {
                $this->io->debug('FAIR: Ed25519 signature verified with key ' . $key->id);
                return true;
            }
        }

        $this->io->writeError('<error>FAIR: Signature verification failed - no key could verify the signature</error>');

        return false;
    }
}
