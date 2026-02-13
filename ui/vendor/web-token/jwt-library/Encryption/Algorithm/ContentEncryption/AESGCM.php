<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\ContentEncryption;

use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Encryption\Algorithm\ContentEncryptionAlgorithm;
use Override;
use RuntimeException;
use function extension_loaded;
use const OPENSSL_RAW_DATA;

abstract readonly class AESGCM implements ContentEncryptionAlgorithm
{
    public function __construct()
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
    }

    #[Override]
    public function allowedKeyTypes(): array
    {
        return []; //Irrelevant
    }

    #[Override]
    public function encryptContent(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $encoded_protected_header,
        ?string &$tag = null
    ): string {
        $calculated_aad = $encoded_protected_header;
        if ($aad !== null) {
            $calculated_aad .= '.' . Base64UrlSafe::encodeUnpadded($aad);
        }
        $tag = '';
        $result = openssl_encrypt($data, $this->getMode(), $cek, OPENSSL_RAW_DATA, $iv, $tag, $calculated_aad);
        if ($result === false) {
            throw new RuntimeException('Unable to encrypt the content');
        }

        return $result;
    }

    #[Override]
    public function decryptContent(
        string $data,
        string $cek,
        string $iv,
        ?string $aad,
        string $encoded_protected_header,
        string $tag
    ): string {
        $calculated_aad = $encoded_protected_header;
        if ($aad !== null) {
            $calculated_aad .= '.' . Base64UrlSafe::encodeUnpadded($aad);
        }

        $result = openssl_decrypt($data, $this->getMode(), $cek, OPENSSL_RAW_DATA, $iv, $tag, $calculated_aad);
        if ($result === false) {
            throw new RuntimeException('Unable to decrypt the content');
        }

        return $result;
    }

    #[Override]
    public function getIVSize(): int
    {
        return 96;
    }

    abstract protected function getMode(): string;
}
