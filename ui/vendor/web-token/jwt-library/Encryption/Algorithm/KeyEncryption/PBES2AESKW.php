<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A128KW;
use AESKW\A192KW;
use AESKW\A256KW;
use AESKW\Wrapper as WrapperInterface;
use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Override;
use RuntimeException;
use function in_array;
use function is_int;
use function is_string;

abstract readonly class PBES2AESKW implements KeyWrapping
{
    public function __construct(
        private readonly int $salt_size = 64,
        private readonly int $nb_count = 4096
    ) {
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
        $password = $this->getKey($key);
        $this->checkHeaderAlgorithm($completeHeader);
        $wrapper = $this->getWrapper();
        $hash_algorithm = $this->getHashAlgorithm();
        $key_size = $this->getKeySize();
        $salt = random_bytes($this->salt_size);

        // We set header parameters
        $additionalHeader['p2s'] = Base64UrlSafe::encodeUnpadded($salt);
        $additionalHeader['p2c'] = $this->nb_count;

        $derived_key = hash_pbkdf2(
            $hash_algorithm,
            $password,
            $completeHeader['alg'] . "\x00" . $salt,
            $this->nb_count,
            $key_size,
            true
        );

        return $wrapper::wrap($derived_key, $cek);
    }

    /**
     * @param array<string, mixed> $completeHeader
     */
    #[Override]
    public function unwrapKey(JWK $key, string $encrypted_cek, array $completeHeader): string
    {
        $password = $this->getKey($key);
        $this->checkHeaderAlgorithm($completeHeader);
        $this->checkHeaderAdditionalParameters($completeHeader);
        $wrapper = $this->getWrapper();
        $hash_algorithm = $this->getHashAlgorithm();
        $key_size = $this->getKeySize();
        $p2s = $completeHeader['p2s'];
        is_string($p2s) || throw new InvalidArgumentException('Invalid salt.');
        $salt = $completeHeader['alg'] . "\x00" . Base64UrlSafe::decodeNoPadding($p2s);
        $count = $completeHeader['p2c'];
        is_int($count) || throw new InvalidArgumentException('Invalid counter.');

        $derived_key = hash_pbkdf2($hash_algorithm, $password, $salt, $count, $key_size, true);

        return $wrapper::unwrap($derived_key, $encrypted_cek);
    }

    #[Override]
    public function getKeyManagementMode(): string
    {
        return self::MODE_WRAP;
    }

    protected function getKey(JWK $key): string
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

    /**
     * @param array<string, mixed> $header
     */
    protected function checkHeaderAlgorithm(array $header): void
    {
        if (! isset($header['alg'])) {
            throw new InvalidArgumentException('The header parameter "alg" is missing.');
        }
        if (! is_string($header['alg'])) {
            throw new InvalidArgumentException('The header parameter "alg" is not valid.');
        }
    }

    /**
     * @param array<string, mixed> $header
     */
    protected function checkHeaderAdditionalParameters(array $header): void
    {
        if (! isset($header['p2s'])) {
            throw new InvalidArgumentException('The header parameter "p2s" is missing.');
        }
        if (! is_string($header['p2s'])) {
            throw new InvalidArgumentException('The header parameter "p2s" is not valid.');
        }
        if (! isset($header['p2c'])) {
            throw new InvalidArgumentException('The header parameter "p2c" is missing.');
        }
        if (! is_int($header['p2c']) || $header['p2c'] <= 0) {
            throw new InvalidArgumentException('The header parameter "p2c" is not valid.');
        }
    }

    abstract protected function getWrapper(): A256KW|A128KW|A192KW;

    abstract protected function getHashAlgorithm(): string;

    abstract protected function getKeySize(): int;
}
