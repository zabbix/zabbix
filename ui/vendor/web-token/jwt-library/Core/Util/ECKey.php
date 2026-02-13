<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use RuntimeException;
use function extension_loaded;
use function is_array;
use function is_string;
use function sprintf;
use const OPENSSL_KEYTYPE_EC;
use const STR_PAD_LEFT;

/**
 * @internal
 */
final readonly class ECKey
{
    public static function convertToPEM(JWK $jwk): string
    {
        if ($jwk->has('d')) {
            return self::convertPrivateKeyToPEM($jwk);
        }

        return self::convertPublicKeyToPEM($jwk);
    }

    public static function convertPublicKeyToPEM(JWK $jwk): string
    {
        $der = match ($jwk->get('crv')) {
            'P-256' => self::p256PublicKey(),
            'secp256k1' => self::p256KPublicKey(),
            'P-384' => self::p384PublicKey(),
            'P-521' => self::p521PublicKey(),
            default => throw new InvalidArgumentException('Unsupported curve.'),
        };
        $der .= self::getKey($jwk);
        $pem = '-----BEGIN PUBLIC KEY-----' . "\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");

        return $pem . ('-----END PUBLIC KEY-----' . "\n");
    }

    public static function convertPrivateKeyToPEM(JWK $jwk): string
    {
        $der = match ($jwk->get('crv')) {
            'P-256' => self::p256PrivateKey($jwk),
            'secp256k1' => self::p256KPrivateKey($jwk),
            'P-384' => self::p384PrivateKey($jwk),
            'P-521' => self::p521PrivateKey($jwk),
            default => throw new InvalidArgumentException('Unsupported curve.'),
        };
        $der .= self::getKey($jwk);
        $pem = '-----BEGIN EC PRIVATE KEY-----' . "\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");

        return $pem . ('-----END EC PRIVATE KEY-----' . "\n");
    }

    /**
     * Creates a EC key with the given curve and additional values.
     *
     * @param string $curve The curve
     * @param array $values values to configure the key
     */
    public static function createECKey(string $curve, array $values = []): JWK
    {
        $jwk = self::createECKeyUsingOpenSSL($curve);
        $values = array_merge($values, $jwk);

        return new JWK($values);
    }

    private static function getNistCurveSize(string $curve): int
    {
        return match ($curve) {
            'P-256', 'secp256k1' => 256,
            'P-384' => 384,
            'P-521' => 521,
            default => throw new InvalidArgumentException(sprintf('The curve "%s" is not supported.', $curve)),
        };
    }

    private static function createECKeyUsingOpenSSL(string $curve): array
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
        $key = openssl_pkey_new([
            'curve_name' => self::getOpensslCurveName($curve),
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 2048, // Not used for EC keys. See https://github.com/php/php-src/pull/19103
        ]);
        if ($key === false) {
            throw new RuntimeException('Unable to create the key');
        }
        $result = openssl_pkey_export($key, $out);
        if ($result === false) {
            throw new RuntimeException('Unable to create the key');
        }
        $res = openssl_pkey_get_private($out);
        if ($res === false) {
            throw new RuntimeException('Unable to create the key');
        }
        $details = openssl_pkey_get_details($res);
        if ($details === false) {
            throw new InvalidArgumentException('Unable to get the key details');
        }
        $nistCurveSize = self::getNistCurveSize($curve);

        return [
            'kty' => 'EC',
            'crv' => $curve,
            'd' => Base64UrlSafe::encodeUnpadded(
                str_pad((string) $details['ec']['d'], (int) ceil($nistCurveSize / 8), "\0", STR_PAD_LEFT)
            ),
            'x' => Base64UrlSafe::encodeUnpadded(
                str_pad((string) $details['ec']['x'], (int) ceil($nistCurveSize / 8), "\0", STR_PAD_LEFT)
            ),
            'y' => Base64UrlSafe::encodeUnpadded(
                str_pad((string) $details['ec']['y'], (int) ceil($nistCurveSize / 8), "\0", STR_PAD_LEFT)
            ),
        ];
    }

    private static function getOpensslCurveName(string $curve): string
    {
        return match ($curve) {
            'P-256' => 'prime256v1',
            'secp256k1' => 'secp256k1',
            'P-384' => 'secp384r1',
            'P-521' => 'secp521r1',
            default => throw new InvalidArgumentException(sprintf('The curve "%s" is not supported.', $curve)),
        };
    }

    private static function p256PublicKey(): string
    {
        return pack(
            'H*',
            '3059' // SEQUENCE, length 89
            . '3013' // SEQUENCE, length 19
            . '0607' // OID, length 7
            . '2a8648ce3d0201' // 1.2.840.10045.2.1 = EC Public Key
            . '0608' // OID, length 8
            . '2a8648ce3d030107' // 1.2.840.10045.3.1.7 = P-256 Curve
            . '0342' // BIT STRING, length 66
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p256KPublicKey(): string
    {
        return pack(
            'H*',
            '3056' // SEQUENCE, length 86
            . '3010' // SEQUENCE, length 16
            . '0607' // OID, length 7
            . '2a8648ce3d0201' // 1.2.840.10045.2.1 = EC Public Key
            . '0605' // OID, length 8
            . '2B8104000A' // 1.3.132.0.10 secp256k1
            . '0342' // BIT STRING, length 66
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p384PublicKey(): string
    {
        return pack(
            'H*',
            '3076' // SEQUENCE, length 118
            . '3010' // SEQUENCE, length 16
            . '0607' // OID, length 7
            . '2a8648ce3d0201' // 1.2.840.10045.2.1 = EC Public Key
            . '0605' // OID, length 5
            . '2b81040022' // 1.3.132.0.34 = P-384 Curve
            . '0362' // BIT STRING, length 98
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p521PublicKey(): string
    {
        return pack(
            'H*',
            '30819b' // SEQUENCE, length 154
            . '3010' // SEQUENCE, length 16
            . '0607' // OID, length 7
            . '2a8648ce3d0201' // 1.2.840.10045.2.1 = EC Public Key
            . '0605' // OID, length 5
            . '2b81040023' // 1.3.132.0.35 = P-521 Curve
            . '038186' // BIT STRING, length 134
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p256PrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (! is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 32, "\0", STR_PAD_LEFT));
        if (! is_array($d) || ! isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }

        return pack(
            'H*',
            '3077' // SEQUENCE, length 87+length($d)=32
            . '020101' // INTEGER, 1
            . '0420'   // OCTET STRING, length($d) = 32
            . $d[1]
            . 'a00a' // TAGGED OBJECT #0, length 10
            . '0608' // OID, length 8
            . '2a8648ce3d030107' // 1.3.132.0.34 = P-256 Curve
            . 'a144' //  TAGGED OBJECT #1, length 68
            . '0342' // BIT STRING, length 66
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p256KPrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (! is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 32, "\0", STR_PAD_LEFT));
        if (! is_array($d) || ! isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }

        return pack(
            'H*',
            '3074' // SEQUENCE, length 84+length($d)=32
            . '020101' // INTEGER, 1
            . '0420'   // OCTET STRING, length($d) = 32
            . $d[1]
            . 'a007' // TAGGED OBJECT #0, length 7
            . '0605' // OID, length 5
            . '2b8104000a' //  1.3.132.0.10 secp256k1
            . 'a144' //  TAGGED OBJECT #1, length 68
            . '0342' // BIT STRING, length 66
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p384PrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (! is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 48, "\0", STR_PAD_LEFT));
        if (! is_array($d) || ! isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }

        return pack(
            'H*',
            '3081a4' // SEQUENCE, length 116 + length($d)=48
            . '020101' // INTEGER, 1
            . '0430'   // OCTET STRING, length($d) = 30
            . $d[1]
            . 'a007' // TAGGED OBJECT #0, length 7
            . '0605' // OID, length 5
            . '2b81040022' // 1.3.132.0.34 = P-384 Curve
            . 'a164' //  TAGGED OBJECT #1, length 100
            . '0362' // BIT STRING, length 98
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function p521PrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (! is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 66, "\0", STR_PAD_LEFT));
        if (! is_array($d) || ! isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }

        return pack(
            'H*',
            '3081dc' // SEQUENCE, length 154 + length($d)=66
            . '020101' // INTEGER, 1
            . '0442'   // OCTET STRING, length(d) = 66
            . $d[1]
            . 'a007' // TAGGED OBJECT #0, length 7
            . '0605' // OID, length 5
            . '2b81040023' // 1.3.132.0.35 = P-521 Curve
            . 'a18189' //  TAGGED OBJECT #1, length 137
            . '038186' // BIT STRING, length 134
            . '00' // prepend with NUL - pubkey will follow
        );
    }

    private static function getKey(JWK $jwk): string
    {
        $crv = $jwk->get('crv');
        if (! is_string($crv)) {
            throw new InvalidArgumentException('Unable to get the curve');
        }
        $nistCurveSize = self::getNistCurveSize($crv);
        $length = (int) ceil($nistCurveSize / 8);
        $x = $jwk->get('x');
        if (! is_string($x)) {
            throw new InvalidArgumentException('Unable to get the public key');
        }
        $y = $jwk->get('y');
        if (! is_string($y)) {
            throw new InvalidArgumentException('Unable to get the public key');
        }
        $binX = ltrim(Base64UrlSafe::decodeNoPadding($x), "\0");
        $binY = ltrim(Base64UrlSafe::decodeNoPadding($y), "\0");

        return "\04"
            . str_pad($binX, $length, "\0", STR_PAD_LEFT)
            . str_pad($binY, $length, "\0", STR_PAD_LEFT)
        ;
    }
}
