<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption\Util;

use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use function strlen;
use const STR_PAD_LEFT;

/**
 * @internal
 *
 * @see https://tools.ietf.org/html/rfc7518#section-4.6.2
 */
final readonly class ConcatKDF
{
    /**
     * Key Derivation Function.
     *
     * @param string $Z Shared secret
     * @param string $algorithm Encryption algorithm
     * @param int $encryption_key_size Size of the encryption key
     * @param string $apu Agreement PartyUInfo (information about the producer)
     * @param string $apv Agreement PartyVInfo (information about the recipient)
     */
    public static function generate(
        string $Z,
        string $algorithm,
        int $encryption_key_size,
        string $apu = '',
        string $apv = ''
    ): string {
        $apu = ! self::isEmpty($apu) ? Base64UrlSafe::decodeNoPadding($apu) : '';
        $apv = ! self::isEmpty($apv) ? Base64UrlSafe::decodeNoPadding($apv) : '';
        $encryption_segments = [
            self::toInt32Bits(1),                                  // Round number 1
            $Z,                                                          // Z (shared secret)
            self::toInt32Bits(strlen($algorithm)) . $algorithm, // Size of algorithm's name and algorithm
            self::toInt32Bits(strlen($apu)) . $apu,             // PartyUInfo
            self::toInt32Bits(strlen($apv)) . $apv,             // PartyVInfo
            self::toInt32Bits($encryption_key_size),                     // SuppPubInfo (the encryption key size)
            '',                                                          // SuppPrivInfo
        ];

        $input = implode('', $encryption_segments);
        $hash = hash('sha256', $input, true);

        return substr($hash, 0, $encryption_key_size / 8);
    }

    /**
     * Convert an integer into a 32 bits string.
     *
     * @param int $value Integer to convert
     */
    private static function toInt32Bits(int $value): string
    {
        $result = hex2bin(str_pad(dechex($value), 8, '0', STR_PAD_LEFT));
        if ($result === false) {
            throw new InvalidArgumentException('Invalid result');
        }

        return $result;
    }

    private static function isEmpty(?string $value): bool
    {
        return $value === null || $value === '';
    }
}
