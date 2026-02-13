<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\KeyEncryptionAlgorithm;

interface KeyAgreementWithKeyWrapping extends KeyEncryptionAlgorithm
{
    /**
     * Compute and wrap the agreement key.
     *
     * @param JWK $recipientKey The receiver's key
     * @param string $cek The CEK to wrap
     * @param int $encryption_key_length Size of the key expected for the algorithm used for data encryption
     * @param array<string, mixed> $complete_header The complete header of the JWT
     * @param array<string, mixed> $additional_header_values Set additional header values if needed
     */
    public function wrapAgreementKey(
        JWK $recipientKey,
        ?JWK $senderKey,
        string $cek,
        int $encryption_key_length,
        array $complete_header,
        array &$additional_header_values
    ): string;

    /**
     * Unwrap and compute the agreement key.
     *
     * @param JWK $recipientKey The receiver's key
     * @param string $encrypted_cek The encrypted CEK
     * @param int $encryption_key_length Size of the key expected for the algorithm used for data encryption
     * @param array<string, mixed> $complete_header The complete header of the JWT
     *
     * @return string The decrypted CEK
     */
    public function unwrapAgreementKey(
        JWK $recipientKey,
        ?JWK $senderKey,
        string $encrypted_cek,
        int $encryption_key_length,
        array $complete_header
    ): string;
}
