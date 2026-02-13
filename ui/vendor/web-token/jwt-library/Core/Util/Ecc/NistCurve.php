<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util\Ecc;

use Brick\Math\BigInteger;

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
final readonly class NistCurve
{
    /**
     * Returns an NIST P-256 curve.
     */
    public static function curve256(): Curve
    {
        $p = BigInteger::fromBase('ffffffff00000001000000000000000000000000ffffffffffffffffffffffff', 16);
        $a = BigInteger::fromBase('ffffffff00000001000000000000000000000000fffffffffffffffffffffffc', 16);
        $b = BigInteger::fromBase('5ac635d8aa3a93e7b3ebbd55769886bc651d06b0cc53b0f63bce3c3e27d2604b', 16);
        $x = BigInteger::fromBase('6b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c296', 16);
        $y = BigInteger::fromBase('4fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5', 16);
        $n = BigInteger::fromBase('ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551', 16);
        $generator = Point::create($x, $y, $n);

        return new Curve(256, $p, $a, $b, $generator);
    }

    /**
     * Returns an NIST P-384 curve.
     */
    public static function curve384(): Curve
    {
        $p = BigInteger::fromBase(
            'fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffeffffffff0000000000000000ffffffff',
            16
        );
        $a = BigInteger::fromBase(
            'fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffeffffffff0000000000000000fffffffc',
            16
        );
        $b = BigInteger::fromBase(
            'b3312fa7e23ee7e4988e056be3f82d19181d9c6efe8141120314088f5013875ac656398d8a2ed19d2a85c8edd3ec2aef',
            16
        );
        $x = BigInteger::fromBase(
            'aa87ca22be8b05378eb1c71ef320ad746e1d3b628ba79b9859f741e082542a385502f25dbf55296c3a545e3872760ab7',
            16
        );
        $y = BigInteger::fromBase(
            '3617de4a96262c6f5d9e98bf9292dc29f8f41dbd289a147ce9da3113b5f0b8c00a60b1ce1d7e819d7a431d7c90ea0e5f',
            16
        );
        $n = BigInteger::fromBase(
            'ffffffffffffffffffffffffffffffffffffffffffffffffc7634d81f4372ddf581a0db248b0a77aecec196accc52973',
            16
        );
        $generator = Point::create($x, $y, $n);

        return new Curve(384, $p, $a, $b, $generator);
    }

    /**
     * Returns an NIST P-521 curve.
     */
    public static function curve521(): Curve
    {
        $p = BigInteger::fromBase(
            '000001ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
            16
        );
        $a = BigInteger::fromBase(
            '000001fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffc',
            16
        );
        $b = BigInteger::fromBase(
            '00000051953eb9618e1c9a1f929a21a0b68540eea2da725b99b315f3b8b489918ef109e156193951ec7e937b1652c0bd3bb1bf073573df883d2c34f1ef451fd46b503f00',
            16
        );
        $x = BigInteger::fromBase(
            '000000c6858e06b70404e9cd9e3ecb662395b4429c648139053fb521f828af606b4d3dbaa14b5e77efe75928fe1dc127a2ffa8de3348b3c1856a429bf97e7e31c2e5bd66',
            16
        );
        $y = BigInteger::fromBase(
            '0000011839296a789a3bc0045c8a5fb42c7d1bd998f54449579b446817afbd17273e662c97ee72995ef42640c550b9013fad0761353c7086a272c24088be94769fd16650',
            16
        );
        $n = BigInteger::fromBase(
            '000001fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffa51868783bf2f966b7fcc0148f709a5d03bb5c9b8899c47aebb6fb71e91386409',
            16
        );
        $generator = Point::create($x, $y, $n);

        return new Curve(521, $p, $a, $b, $generator);
    }
}
