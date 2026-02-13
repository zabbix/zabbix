<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util\Ecc;

use Brick\Math\BigInteger;
use function strlen;
use const STR_PAD_LEFT;

/**
 * Copyright (C) 2012 Matyas Danter.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * @internal
 */
final readonly class Point
{
    private function __construct(
        private BigInteger $x,
        private BigInteger $y,
        private BigInteger $order,
        private bool $infinity = false
    ) {
    }

    public static function create(BigInteger $x, BigInteger $y, ?BigInteger $order = null): self
    {
        return new self($x, $y, $order ?? BigInteger::zero());
    }

    public static function infinity(): self
    {
        $zero = BigInteger::zero();

        return new self($zero, $zero, $zero, true);
    }

    public function isInfinity(): bool
    {
        return $this->infinity;
    }

    public function getOrder(): BigInteger
    {
        return $this->order;
    }

    public function getX(): BigInteger
    {
        return $this->x;
    }

    public function getY(): BigInteger
    {
        return $this->y;
    }

    public static function cswap(self $a, self $b, int $cond): void
    {
        self::cswapBigInteger($a->x, $b->x, $cond);
        self::cswapBigInteger($a->y, $b->y, $cond);
        self::cswapBigInteger($a->order, $b->order, $cond);
        self::cswapBoolean($a->infinity, $b->infinity, $cond);
    }

    private static function cswapBoolean(bool &$a, bool &$b, int $cond): void
    {
        $sa = BigInteger::of((int) $a);
        $sb = BigInteger::of((int) $b);

        self::cswapBigInteger($sa, $sb, $cond);

        $a = (bool) $sa->toBase(10);
        $b = (bool) $sb->toBase(10);
    }

    private static function cswapBigInteger(BigInteger &$sa, BigInteger &$sb, int $cond): void
    {
        $size = max(strlen($sa->toBase(2)), strlen($sb->toBase(2)));
        $mask = (string) (1 - $cond);
        $mask = str_pad('', $size, $mask, STR_PAD_LEFT);
        $mask = BigInteger::fromBase($mask, 2);
        $taA = $sa->and($mask);
        $taB = $sb->and($mask);
        $sa = $sa->xor($sb)
            ->xor($taB);
        $sb = $sa->xor($sb)
            ->xor($taA);
        $sa = $sa->xor($sb)
            ->xor($taB);
    }
}
