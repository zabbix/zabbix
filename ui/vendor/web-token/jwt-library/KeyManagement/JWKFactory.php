<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\ECKey;
use Jose\Component\KeyManagement\KeyConverter\KeyConverter;
use Jose\Component\KeyManagement\KeyConverter\RSAKey;
use OpenSSLCertificate;
use RuntimeException;
use Throwable;
use function array_key_exists;
use function extension_loaded;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;
use const JSON_THROW_ON_ERROR;
use const OPENSSL_KEYTYPE_RSA;

/**
 * @see \Jose\Tests\Component\KeyManagement\JWKFactoryTest
 */
class JWKFactory
{
    /**
     * Creates a RSA key with the given key size and additional values.
     *
     * @param int $size The key size in bits
     * @param array<string, mixed> $values values to configure the key
     */
    public static function createRSAKey(int $size, array $values = []): JWK
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
        if ($size % 8 !== 0) {
            throw new InvalidArgumentException('Invalid key size.');
        }
        if ($size < 512) {
            throw new InvalidArgumentException('Key length is too short. It needs to be at least 512 bits.');
        }

        $key = openssl_pkey_new([
            'private_key_bits' => $size,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false) {
            throw new InvalidArgumentException('Unable to create the key');
        }
        $details = openssl_pkey_get_details($key);
        if (! is_array($details)) {
            throw new InvalidArgumentException('Unable to create the key');
        }
        $rsa = RSAKey::createFromKeyDetails($details['rsa']);
        $values = array_merge($values, $rsa->toArray());

        return new JWK($values);
    }

    /**
     * Creates a EC key with the given curve and additional values.
     *
     * @param string $curve The curve
     * @param array<string, mixed> $values values to configure the key
     */
    public static function createECKey(string $curve, array $values = []): JWK
    {
        return ECKey::createECKey($curve, $values);
    }

    /**
     * Creates a octet key with the given key size and additional values.
     *
     * @param int $size The key size in bits
     * @param array<string, mixed> $values values to configure the key
     */
    public static function createOctKey(int $size, array $values = []): JWK
    {
        if ($size % 8 !== 0) {
            throw new InvalidArgumentException('Invalid key size.');
        }

        return self::createFromSecret(random_bytes($size / 8), $values);
    }

    /**
     * Creates a OKP key with the given curve and additional values.
     *
     * @param string $curve The curve
     * @param array<string, mixed> $values values to configure the key
     */
    public static function createOKPKey(string $curve, array $values = []): JWK
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('The extension "sodium" is not available. Please install it to use this method');
        }

        switch ($curve) {
            case 'X25519':
                $keyPair = sodium_crypto_box_keypair();
                $d = sodium_crypto_box_secretkey($keyPair);
                $x = sodium_crypto_box_publickey($keyPair);

                break;

            case 'Ed25519':
                $keyPair = sodium_crypto_sign_keypair();
                $secret = sodium_crypto_sign_secretkey($keyPair);
                $secretLength = strlen($secret);
                $d = substr($secret, 0, -$secretLength / 2);
                $x = sodium_crypto_sign_publickey($keyPair);

                break;

            default:
                throw new InvalidArgumentException(sprintf('Unsupported "%s" curve', $curve));
        }

        $values = [
            ...$values,
            'kty' => 'OKP',
            'crv' => $curve,
            'd' => Base64UrlSafe::encodeUnpadded($d),
            'x' => Base64UrlSafe::encodeUnpadded($x),
        ];

        return new JWK($values);
    }

    /**
     * Creates a none key with the given additional values. Please note that this key type is not part of any
     * specification. It is used to prevent the use of the "none" algorithm with other key types.
     *
     * @param array<string, mixed> $values values to configure the key
     */
    public static function createNoneKey(array $values = []): JWK
    {
        $values = [
            ...$values,
            'kty' => 'none',
            'alg' => 'none',
            'use' => 'sig',
        ];

        return new JWK($values);
    }

    /**
     * Creates a key from a Json string.
     */
    public static function createFromJsonObject(string $value): JWK|JWKSet
    {
        $json = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($json)) {
            throw new InvalidArgumentException('Invalid key or key set.');
        }

        return self::createFromValues($json);
    }

    /**
     * Creates a key or key set from the given input.
     */
    public static function createFromValues(array $values): JWK|JWKSet
    {
        if (array_key_exists('keys', $values) && is_array($values['keys'])) {
            return JWKSet::createFromKeyData($values);
        }

        return new JWK($values);
    }

    /**
     * This method create a JWK object using a shared secret.
     */
    public static function createFromSecret(string $secret, array $additional_values = []): JWK
    {
        $values = array_merge(
            $additional_values,
            [
                'kty' => 'oct',
                'k' => Base64UrlSafe::encodeUnpadded($secret),
            ]
        );

        return new JWK($values);
    }

    /**
     * This method will try to load a X.509 certificate and convert it into a public key.
     */
    public static function createFromCertificateFile(string $file, array $additional_values = []): JWK
    {
        $values = KeyConverter::loadKeyFromCertificateFile($file);
        $values = array_merge($values, $additional_values);

        return new JWK($values);
    }

    /**
     * Extract a keyfrom a key set identified by the given index .
     */
    public static function createFromKeySet(JWKSet $jwkset, int|string $index): JWK
    {
        return $jwkset->get($index);
    }

    /**
     * This method will try to load a PKCS#12 file and convert it into a public key.
     */
    public static function createFromPKCS12CertificateFile(
        string $file,
        string $secret = '',
        array $additional_values = []
    ): JWK {
        try {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                throw new RuntimeException('Unable to read the file.');
            }
            openssl_pkcs12_read($content, $certs, $secret);
            if (! is_array($certs) || ! array_key_exists('pkey', $certs)) {
                throw new RuntimeException('Unable to load the certificates.');
            }

            return self::createFromKey($certs['pkey'], null, $additional_values);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Unable to load the certificates.', $throwable->getCode(), $throwable);
        }
    }

    /**
     * This method will try to convert a X.509 certificate into a public key.
     */
    public static function createFromCertificate(string $certificate, array $additional_values = []): JWK
    {
        $values = KeyConverter::loadKeyFromCertificate($certificate);
        $values = array_merge($values, $additional_values);

        return new JWK($values);
    }

    /**
     * This method will try to convert a X.509 certificate resource into a public key.
     */
    public static function createFromX509Resource(OpenSSLCertificate $res, array $additional_values = []): JWK
    {
        $values = KeyConverter::loadKeyFromX509Resource($res);
        $values = array_merge($values, $additional_values);

        return new JWK($values);
    }

    /**
     * This method will try to load and convert a key file into a JWK object. If the key is encrypted, the password must
     * be set.
     */
    public static function createFromKeyFile(string $file, ?string $password = null, array $additional_values = []): JWK
    {
        $values = KeyConverter::loadFromKeyFile($file, $password);
        $values = array_merge($values, $additional_values);

        return new JWK($values);
    }

    /**
     * This method will try to load and convert a key into a JWK object. If the key is encrypted, the password must be
     * set.
     */
    public static function createFromKey(string $key, ?string $password = null, array $additional_values = []): JWK
    {
        $values = KeyConverter::loadFromKey($key, $password);
        $values = array_merge($values, $additional_values);

        return new JWK($values);
    }

    /**
     * This method will try to load and convert a X.509 certificate chain into a public key.
     *
     * Be careful! The certificate chain is loaded, but it is NOT VERIFIED by any mean! It is mandatory to verify the
     * root CA or intermediate  CA are trusted. If not done, it may lead to potential security issues.
     */
    public static function createFromX5C(array $x5c, array $additional_values = []): JWK
    {
        $values = KeyConverter::loadFromX5C($x5c);
        $values = array_merge($values, $additional_values);

        return new JWK($values);
    }
}
