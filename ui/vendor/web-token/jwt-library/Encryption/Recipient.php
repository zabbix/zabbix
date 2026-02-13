<?php

declare(strict_types=1);

namespace Jose\Component\Encryption;

use InvalidArgumentException;
use function array_key_exists;
use function sprintf;

/**
 * @internal
 */
final readonly class Recipient
{
    public function __construct(
        private array $header,
        private ?string $encryptedKey
    ) {
    }

    /**
     * Returns the recipient header.
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * Returns the value of the recipient header parameter with the specified key.
     *
     * @param string $key The key
     *
     * @return mixed|null
     */
    public function getHeaderParameter(string $key)
    {
        if (! $this->hasHeaderParameter($key)) {
            throw new InvalidArgumentException(sprintf('The header "%s" does not exist.', $key));
        }

        return $this->header[$key];
    }

    /**
     * Returns true if the recipient header contains the parameter with the specified key.
     *
     * @param string $key The key
     */
    public function hasHeaderParameter(string $key): bool
    {
        return array_key_exists($key, $this->header);
    }

    /**
     * Returns the encrypted key.
     */
    public function getEncryptedKey(): ?string
    {
        return $this->encryptedKey;
    }
}
