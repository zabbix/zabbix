<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\Wrapper as WrapperInterface;
use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Override;
use RuntimeException;
use function in_array;
use function is_string;

abstract readonly class AESKW implements KeyWrapping
{
    public function __construct()
    {
        if (! interface_exists(WrapperInterface::class)) {
            throw new RuntimeException('Please install "spomky-labs/aes-key-wrap" to use AES-KW algorithms');
        }
    }

    #[Override]
    public function allowedKeyTypes(): array
    {
        return ['oct'];
    }

    /**
     * @param array<string, mixed> $completeHeader
     * @param array<string, mixed> $additionalHeader
     */
    #[Override]
    public function wrapKey(JWK $key, string $cek, array $completeHeader, array &$additionalHeader): string
    {
        $k = $this->getKey($key);
        $wrapper = $this->getWrapper();

        return $wrapper::wrap($k, $cek);
    }

    /**
     * @param array<string, mixed> $completeHeader
     */
    #[Override]
    public function unwrapKey(JWK $key, string $encrypted_cek, array $completeHeader): string
    {
        $k = $this->getKey($key);
        $wrapper = $this->getWrapper();

        return $wrapper::unwrap($k, $encrypted_cek);
    }

    #[Override]
    public function getKeyManagementMode(): string
    {
        return self::MODE_WRAP;
    }

    abstract protected function getWrapper(): WrapperInterface;

    private function getKey(JWK $key): string
    {
        if (! in_array($key->get('kty'), $this->allowedKeyTypes(), true)) {
            throw new InvalidArgumentException('Wrong key type.');
        }
        if (! $key->has('k')) {
            throw new InvalidArgumentException('The key parameter "k" is missing.');
        }
        $k = $key->get('k');
        if (! is_string($k)) {
            throw new InvalidArgumentException('The key parameter "k" is invalid.');
        }

        return Base64UrlSafe::decodeNoPadding($k);
    }
}
