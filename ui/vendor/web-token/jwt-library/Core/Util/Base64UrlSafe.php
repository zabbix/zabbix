<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use InvalidArgumentException;
use RangeException;
use SensitiveParameter;
use SodiumException;
use function extension_loaded;
use function pack;
use function rtrim;
use function sodium_base642bin;
use function sodium_bin2base64;
use function strlen;
use function substr;
use function unpack;
use const SODIUM_BASE64_VARIANT_URLSAFE;
use const SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING;

/**
 *  Copyright (c) 2016 - 2022 Paragon Initiative Enterprises.
 *  Copyright (c) 2014 Steve "Sc00bz" Thomas (steve at tobtu dot com)
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

final readonly class Base64UrlSafe
{
    public static function encode(#[SensitiveParameter] string $binString): string
    {
        if (extension_loaded('sodium')) {
            try {
                return sodium_bin2base64($binString, SODIUM_BASE64_VARIANT_URLSAFE);
            } catch (SodiumException $ex) {
                throw new RangeException($ex->getMessage(), $ex->getCode(), $ex);
            }
        }
        return static::doEncode($binString, true);
    }

    public static function encodeUnpadded(#[SensitiveParameter] string $src): string
    {
        if (extension_loaded('sodium')) {
            try {
                return sodium_bin2base64($src, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            } catch (SodiumException $ex) {
                throw new RangeException($ex->getMessage(), $ex->getCode(), $ex);
            }
        }
        return static::doEncode($src, false);
    }

    public static function decode(#[SensitiveParameter] string $encodedString, bool $strictPadding = false): string
    {
        $srcLen = self::safeStrlen($encodedString);
        if ($srcLen === 0) {
            return '';
        }

        if ($strictPadding) {
            if (($srcLen & 3) === 0) {
                if ($encodedString[$srcLen - 1] === '=') {
                    $srcLen--;
                    if ($encodedString[$srcLen - 1] === '=') {
                        $srcLen--;
                    }
                }
            }
            if (($srcLen & 3) === 1) {
                throw new RangeException('Incorrect padding');
            }
            if ($encodedString[$srcLen - 1] === '=') {
                throw new RangeException('Incorrect padding');
            }
            if (extension_loaded('sodium')) {
                try {
                    return sodium_base642bin(
                        self::safeSubstr($encodedString, 0, $srcLen),
                        SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
                    );
                } catch (SodiumException $ex) {
                    throw new RangeException($ex->getMessage(), $ex->getCode(), $ex);
                }
            }
        } else {
            $encodedString = rtrim($encodedString, '=');
            $srcLen = self::safeStrlen($encodedString);
        }

        $err = 0;
        $dest = '';
        for ($i = 0; $i + 4 <= $srcLen; $i += 4) {
            /** @var array<int, int> $chunk */
            $chunk = unpack('C*', self::safeSubstr($encodedString, $i, 4));
            $c0 = static::decode6Bits($chunk[1]);
            $c1 = static::decode6Bits($chunk[2]);
            $c2 = static::decode6Bits($chunk[3]);
            $c3 = static::decode6Bits($chunk[4]);

            $dest .= pack(
                'CCC',
                ((($c0 << 2) | ($c1 >> 4)) & 0xff),
                ((($c1 << 4) | ($c2 >> 2)) & 0xff),
                ((($c2 << 6) | $c3) & 0xff)
            );
            $err |= ($c0 | $c1 | $c2 | $c3) >> 8;
        }

        if ($i < $srcLen) {
            /** @var array<int, int> $chunk */
            $chunk = unpack('C*', self::safeSubstr($encodedString, $i, $srcLen - $i));
            $c0 = static::decode6Bits($chunk[1]);

            if ($i + 2 < $srcLen) {
                $c1 = static::decode6Bits($chunk[2]);
                $c2 = static::decode6Bits($chunk[3]);
                $dest .= pack('CC', ((($c0 << 2) | ($c1 >> 4)) & 0xff), ((($c1 << 4) | ($c2 >> 2)) & 0xff));
                $err |= ($c0 | $c1 | $c2) >> 8;
                if ($strictPadding) {
                    $err |= ($c2 << 6) & 0xff;
                }
            } elseif ($i + 1 < $srcLen) {
                $c1 = static::decode6Bits($chunk[2]);
                $dest .= pack('C', ((($c0 << 2) | ($c1 >> 4)) & 0xff));
                $err |= ($c0 | $c1) >> 8;
                if ($strictPadding) {
                    $err |= ($c1 << 4) & 0xff;
                }
            } elseif ($strictPadding) {
                $err |= 1;
            }
        }
        $check = ($err === 0);
        if (! $check) {
            throw new RangeException('Base64::decode() only expects characters in the correct base64 alphabet');
        }
        return $dest;
    }

    public static function decodeNoPadding(#[SensitiveParameter] string $encodedString): string
    {
        $srcLen = self::safeStrlen($encodedString);
        if ($srcLen === 0) {
            return '';
        }
        if (($srcLen & 3) === 0) {
            if ($encodedString[$srcLen - 1] === '=' || $encodedString[$srcLen - 2] === '=') {
                throw new InvalidArgumentException("decodeNoPadding() doesn't tolerate padding");
            }
        }
        return static::decode($encodedString, true);
    }

    private static function doEncode(#[SensitiveParameter] string $src, bool $pad = true): string
    {
        $dest = '';
        $srcLen = self::safeStrlen($src);
        for ($i = 0; $i + 3 <= $srcLen; $i += 3) {
            /** @var array<int, int> $chunk */
            $chunk = unpack('C*', self::safeSubstr($src, $i, 3));
            $b0 = $chunk[1];
            $b1 = $chunk[2];
            $b2 = $chunk[3];

            $dest .=
                static::encode6Bits($b0 >> 2) .
                static::encode6Bits((($b0 << 4) | ($b1 >> 4)) & 63) .
                static::encode6Bits((($b1 << 2) | ($b2 >> 6)) & 63) .
                static::encode6Bits($b2 & 63);
        }

        if ($i < $srcLen) {
            /** @var array<int, int> $chunk */
            $chunk = unpack('C*', self::safeSubstr($src, $i, $srcLen - $i));
            $b0 = $chunk[1];
            if ($i + 1 < $srcLen) {
                $b1 = $chunk[2];
                $dest .=
                    static::encode6Bits($b0 >> 2) .
                    static::encode6Bits((($b0 << 4) | ($b1 >> 4)) & 63) .
                    static::encode6Bits(($b1 << 2) & 63);
                if ($pad) {
                    $dest .= '=';
                }
            } else {
                $dest .=
                    static::encode6Bits($b0 >> 2) .
                    static::encode6Bits(($b0 << 4) & 63);
                if ($pad) {
                    $dest .= '==';
                }
            }
        }
        return $dest;
    }

    private static function decode6Bits(int $src): int
    {
        $ret = -1;
        $ret += (((0x40 - $src) & ($src - 0x5b)) >> 8) & ($src - 64);
        $ret += (((0x60 - $src) & ($src - 0x7b)) >> 8) & ($src - 70);
        $ret += (((0x2f - $src) & ($src - 0x3a)) >> 8) & ($src + 5);
        $ret += (((0x2c - $src) & ($src - 0x2e)) >> 8) & 63;

        return $ret + ((((0x5e - $src) & ($src - 0x60)) >> 8) & 64);
    }

    private static function encode6Bits(int $src): string
    {
        $diff = 0x41;
        $diff += ((25 - $src) >> 8) & 6;
        $diff -= ((51 - $src) >> 8) & 75;
        $diff -= ((61 - $src) >> 8) & 13;
        $diff += ((62 - $src) >> 8) & 49;

        return pack('C', $src + $diff);
    }

    private static function safeStrlen(#[SensitiveParameter] string $str): int
    {
        return strlen($str);
    }

    private static function safeSubstr(#[SensitiveParameter] string $str, int $start = 0, $length = null): string
    {
        if ($length === 0) {
            return '';
        }
        return substr($str, $start, $length);
    }
}
