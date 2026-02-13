<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\KeyEncryptionAlgorithm;

interface KeyWrapping extends KeyEncryptionAlgorithm
{
    /**
     * Encrypt the CEK.
     *
     * @param JWK $key The key used to wrap the CEK
     * @param string $cek The CEK to encrypt
     * @param array<string, mixed> $completeHeader The complete header of the JWT
     * @param array<string, mixed> $additionalHeader The complete header of the JWT
     */
    public function wrapKey(JWK $key, string $cek, array $completeHeader, array &$additionalHeader): string;

    /**
     * Decrypt de CEK.
     *
     * @param JWK $key The key used to wrap the CEK
     * @param string $encrypted_cek The CEK to decrypt
     * @param array<string, mixed> $completeHeader The complete header of the JWT
     */
    public function unwrapKey(JWK $key, string $encrypted_cek, array $completeHeader): string;
}
