<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use InvalidArgumentException;

/**
 * @internal
 */
final readonly class Hash
{
    /**
     * @param positive-int $length
     */
    private function __construct(
        private string $hash,
        private int $length,
        private string $t
    ) {
    }

    public static function get(string $function): self
    {
        return match ($function) {
            'sha1' => self::sha1(),
            'sha256' => self::sha256(),
            'sha384' => self::sha384(),
            'sha512' => self::sha512(),
            default => throw new InvalidArgumentException('Unsupported hash function'),
        };
    }

    public static function sha1(): self
    {
        return new self('sha1', 20, "\x30\x21\x30\x09\x06\x05\x2b\x0e\x03\x02\x1a\x05\x00\x04\x14");
    }

    public static function sha256(): self
    {
        return new self('sha256', 32, "\x30\x31\x30\x0d\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01\x05\x00\x04\x20");
    }

    public static function sha384(): self
    {
        return new self('sha384', 48, "\x30\x41\x30\x0d\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x02\x05\x00\x04\x30");
    }

    public static function sha512(): self
    {
        return new self('sha512', 64, "\x30\x51\x30\x0d\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x03\x05\x00\x04\x40");
    }

    /**
     * @return positive-int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Compute the HMAC.
     */
    public function hash(string $text): string
    {
        return hash($this->hash, $text, true);
    }

    public function name(): string
    {
        return $this->hash;
    }

    public function t(): string
    {
        return $this->t;
    }
}
