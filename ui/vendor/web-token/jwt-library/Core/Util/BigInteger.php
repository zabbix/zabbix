<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use Brick\Math\BigInteger as BrickBigInteger;
use InvalidArgumentException;
use function chr;
use function strlen;

/**
 * @internal
 */
final readonly class BigInteger
{
    private function __construct(
        private BrickBigInteger $value
    ) {
    }

    public static function createFromBinaryString(string $value): self
    {
        $res = unpack('H*', $value);
        if ($res === false) {
            throw new InvalidArgumentException('Unable to convert the value');
        }
        $data = current($res);

        return new self(BrickBigInteger::fromBase($data, 16));
    }

    public static function createFromDecimal(int $value): self
    {
        return new self(BrickBigInteger::of($value));
    }

    public static function createFromBigInteger(BrickBigInteger $value): self
    {
        return new self($value);
    }

    /**
     * Converts a BigInteger to a binary string.
     */
    public function toBytes(): string
    {
        if ($this->value->isEqualTo(BrickBigInteger::zero())) {
            return '';
        }

        $temp = $this->value->toBase(16);
        $temp = 0 !== (strlen($temp) & 1) ? '0' . $temp : $temp;
        $temp = hex2bin($temp);
        if ($temp === false) {
            throw new InvalidArgumentException('Unable to convert the value into bytes');
        }

        return ltrim($temp, chr(0));
    }

    /**
     * Adds two BigIntegers.
     */
    public function add(self $y): self
    {
        $value = $this->value->plus($y->value);

        return new self($value);
    }

    /**
     * Subtracts two BigIntegers.
     */
    public function subtract(self $y): self
    {
        $value = $this->value->minus($y->value);

        return new self($value);
    }

    /**
     * Multiplies two BigIntegers.
     */
    public function multiply(self $x): self
    {
        $value = $this->value->multipliedBy($x->value);

        return new self($value);
    }

    /**
     * Divides two BigIntegers.
     */
    public function divide(self $x): self
    {
        $value = $this->value->dividedBy($x->value);

        return new self($value);
    }

    /**
     * Performs modular exponentiation.
     */
    public function modPow(self $e, self $n): self
    {
        $value = $this->value->modPow($e->value, $n->value);

        return new self($value);
    }

    /**
     * Performs modular exponentiation.
     */
    public function mod(self $d): self
    {
        $value = $this->value->mod($d->value);

        return new self($value);
    }

    public function modInverse(self $m): self
    {
        return new self($this->value->modInverse($m->value));
    }

    /**
     * Compares two numbers.
     */
    public function compare(self $y): int
    {
        return $this->value->compareTo($y->value);
    }

    public function equals(self $y): bool
    {
        return $this->value->isEqualTo($y->value);
    }

    public static function random(self $y): self
    {
        return new self(BrickBigInteger::randomRange(0, $y->value));
    }

    public function gcd(self $y): self
    {
        return new self($this->value->gcd($y->value));
    }

    public function lowerThan(self $y): bool
    {
        return $this->value->isLessThan($y->value);
    }

    public function isEven(): bool
    {
        return $this->value->isEven();
    }

    public function get(): BrickBigInteger
    {
        return $this->value;
    }
}
