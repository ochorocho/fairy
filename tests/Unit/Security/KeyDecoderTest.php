<?php

declare(strict_types=1);

namespace Fair\ComposerPlugin\Tests\Unit\Security;

use Fair\ComposerPlugin\Security\KeyDecoder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KeyDecoderTest extends TestCase
{
    #[Test]
    public function decodesEd25519PublicKeyFromMultibase(): void
    {
        // This is the real key from the git-updater DID document
        $multibase = 'z6MkiAezCpuZLSjo58uTXyXcvKgw3jMf6BvkuVMQ4SYfuFZm';

        $rawKey = KeyDecoder::decodeEd25519PublicKey($multibase);

        self::assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($rawKey));
    }

    #[Test]
    public function throwsOnNonEd25519Key(): void
    {
        // "z" prefix + short data that decodes but doesn't have Ed25519 multicodec prefix
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not an Ed25519 multicodec key');

        KeyDecoder::decodeEd25519PublicKey('z111111');
    }

    #[Test]
    public function decodedKeyCanBeUsedForVerification(): void
    {
        // Generate a test keypair
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        // Encode as multibase (z prefix + Base58BTC of ed25519 multicodec prefix + key)
        $multicodecKey = "\xed\x01" . $publicKey;
        $encoded = 'z' . $this->base58btcEncode($multicodecKey);

        // Decode and verify it matches
        $decoded = KeyDecoder::decodeEd25519PublicKey($encoded);
        self::assertSame($publicKey, $decoded);

        // Sign and verify
        $message = 'test message';
        $signature = sodium_crypto_sign_detached($message, $secretKey);
        self::assertTrue(sodium_crypto_sign_verify_detached($signature, $message, $decoded));
    }

    private function base58btcEncode(string $data): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $bytes = array_map('ord', str_split($data));

        $leadingZeroes = 0;
        while ($bytes !== [] && $bytes[0] === 0) {
            $leadingZeroes++;
            array_shift($bytes);
        }

        $converted = $this->convertBase($bytes, 256, 58);

        if ($leadingZeroes > 0) {
            $converted = array_merge(array_fill(0, $leadingZeroes, 0), $converted);
        }

        return implode('', array_map(static fn (int $i): string => $alphabet[$i], $converted));
    }

    /**
     * @param array<int, int> $source
     * @return array<int, int>
     */
    private function convertBase(array $source, int $sourceBase, int $targetBase): array
    {
        $result = [];
        while (($count = count($source)) > 0) {
            $quotient = [];
            $remainder = 0;
            for ($i = 0; $i < $count; $i++) {
                $accumulator = $source[$i] + $remainder * $sourceBase;
                $digit = intdiv($accumulator, $targetBase);
                $remainder = $accumulator % $targetBase;
                if ($quotient !== [] || $digit > 0) {
                    $quotient[] = $digit;
                }
            }
            array_unshift($result, $remainder);
            $source = $quotient;
        }

        return $result;
    }
}
