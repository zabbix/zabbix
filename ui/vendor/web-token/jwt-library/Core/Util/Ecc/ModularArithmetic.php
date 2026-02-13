<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util\Ecc;

use Brick\Math\BigInteger;

/**
 * @internal
 */
final readonly class ModularArithmetic
{
    public static function sub(BigInteger $minuend, BigInteger $subtrahend, BigInteger $modulus): BigInteger
    {
        return $minuend->minus($subtrahend)
            ->mod($modulus);
    }

    public static function mul(BigInteger $multiplier, BigInteger $muliplicand, BigInteger $modulus): BigInteger
    {
        return $multiplier->multipliedBy($muliplicand)
            ->mod($modulus);
    }

    public static function div(BigInteger $dividend, BigInteger $divisor, BigInteger $modulus): BigInteger
    {
        return self::mul($dividend, Math::inverseMod($divisor, $modulus), $modulus);
    }
}
