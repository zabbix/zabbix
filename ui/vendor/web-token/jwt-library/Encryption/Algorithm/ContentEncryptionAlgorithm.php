<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm;

use Jose\Component\Core\Algorithm;

interface ContentEncryptionAlgorithm extends Algorithm
{
    /**
     * This method encrypts the data using the given CEK, IV, AAD and protected header. The variable $tag is populated
     * on success.
     *
     * @param string $data The data to encrypt
     * @param string $cek The content encryption key
     * @param string $iv The Initialization Vector
     * @param string|null $aad Additional Additional Authenticated Data
     * @param string $encoded_protected_header The Protected Header encoded in Base64Url
     * @param string $tag Tag
     */
    public function encryptContent(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $encoded_protected_header,
        ?string &$tag = null
    ): string;

    /**
     * This method tries to decrypt the data using the given CEK, IV, AAD, protected header and tag.
     *
     * @param string $data The data to decrypt
     * @param string $cek The content encryption key
     * @param string $iv The Initialization Vector
     * @param string|null $aad Additional Additional Authenticated Data
     * @param string $encoded_protected_header The Protected Header encoded in Base64Url
     * @param string $tag Tag
     */
    public function decryptContent(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $encoded_protected_header,
        string $tag
    ): string;

    /**
     * Returns the size of the IV used by this encryption method.
     */
    public function getIVSize(): int;

    /**
     * Returns the size of the CEK used by this encryption method.
     */
    public function getCEKSize(): int;
}
