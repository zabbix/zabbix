<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\KeyConverter;

use Brick\Math\BigInteger;
use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use OpenSSLCertificate;
use ParagonIE\Sodium\Core\Ed25519;
use RuntimeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PrivateKey;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKey;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\RSA\RSASSAPSSPrivateKey;
use Throwable;
use function array_key_exists;
use function assert;
use function count;
use function extension_loaded;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use const E_ERROR;
use const E_PARSE;
use const OPENSSL_KEYTYPE_EC;
use const OPENSSL_KEYTYPE_RSA;
use const OPENSSL_RAW_DATA;
use const PREG_PATTERN_ORDER;

/**
 * @internal
 */
final readonly class KeyConverter
{
    /**
     * @return array<array-key, mixed>
     */
    public static function loadKeyFromCertificateFile(string $file): array
    {
        if (! file_exists($file)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist.', $file));
        }
        $content = file_get_contents($file);
        if (! is_string($content)) {
            throw new InvalidArgumentException(sprintf('File "%s" cannot be read.', $file));
        }

        return self::loadKeyFromCertificate($content);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function loadKeyFromCertificate(string $certificate): array
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }

        $errorReporting = error_reporting(E_ERROR | E_PARSE);
        try {
            $res = openssl_x509_read($certificate);
            if ($res === false) {
                throw new InvalidArgumentException('Unable to load the certificate.');
            }
        } catch (Throwable) {
            $certificate = self::convertDerToPem($certificate);
            $res = openssl_x509_read($certificate);
        } finally {
            error_reporting($errorReporting);
        }
        if ($res === false) {
            throw new InvalidArgumentException('Unable to load the certificate.');
        }

        return self::loadKeyFromX509Resource($res);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function loadKeyFromX509Resource(OpenSSLCertificate $res): array
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
        $key = openssl_pkey_get_public($res);
        if ($key === false) {
            throw new InvalidArgumentException('Unable to load the certificate.');
        }
        $details = openssl_pkey_get_details($key);
        if (! is_array($details)) {
            throw new InvalidArgumentException('Unable to load the certificate');
        }
        if (isset($details['key'])) {
            $values = self::loadKeyFromPEM($details['key']);
            openssl_x509_export($res, $out);
            $x5c = preg_replace('#-.*-#', '', (string) $out);
            $x5c = preg_replace('~\R~', '', (string) $x5c);
            if (! is_string($x5c)) {
                throw new InvalidArgumentException('Unable to load the certificate');
            }
            $x5c = trim($x5c);

            $x5tsha1 = openssl_x509_fingerprint($res, 'sha1', true);
            $x5tsha256 = openssl_x509_fingerprint($res, 'sha256', true);
            if (! is_string($x5tsha1) || ! is_string($x5tsha256)) {
                throw new InvalidArgumentException('Unable to compute the certificate fingerprint');
            }

            $values['x5c'] = [$x5c];
            $values['x5t'] = Base64UrlSafe::encodeUnpadded($x5tsha1);
            $values['x5t#256'] = Base64UrlSafe::encodeUnpadded($x5tsha256);

            return $values;
        }

        throw new InvalidArgumentException('Unable to load the certificate');
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function loadFromKeyFile(string $file, ?string $password = null): array
    {
        $content = file_get_contents($file);
        if (! is_string($content)) {
            throw new InvalidArgumentException('Unable to load the key from the file.');
        }

        return self::loadFromKey($content, $password);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function loadFromKey(string $key, ?string $password = null): array
    {
        try {
            return self::loadKeyFromDER($key, $password);
        } catch (Throwable) {
            return self::loadKeyFromPEM($key, $password);
        }
    }

    /**
     * Be careful! The certificate chain is loaded, but it is NOT VERIFIED by any mean! It is mandatory to verify the
     * root CA or intermediate  CA are trusted. If not done, it may lead to potential security issues.
     *
     * @param array<array-key, mixed> $x5c
     * @return array<array-key, mixed>
     */
    public static function loadFromX5C(array $x5c): array
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
        if (count($x5c) === 0) {
            throw new InvalidArgumentException('The certificate chain is empty');
        }
        foreach ($x5c as $id => $cert) {
            assert(is_string($cert), 'Invalid certificate chain');
            $x5c[$id] = '-----BEGIN CERTIFICATE-----' . "\n" . chunk_split(
                $cert,
                64,
                "\n"
            ) . '-----END CERTIFICATE-----';
            $x509 = openssl_x509_read($x5c[$id]);
            if ($x509 === false) {
                throw new InvalidArgumentException('Unable to load the certificate chain');
            }
            $parsed = openssl_x509_parse($x509);
            if ($parsed === false) {
                throw new InvalidArgumentException('Unable to load the certificate chain');
            }
        }

        return self::loadKeyFromCertificate(reset($x5c));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function loadKeyFromDER(string $der, ?string $password = null): array
    {
        $pem = self::convertDerToPem($der);

        return self::loadKeyFromPEM($pem, $password);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function loadKeyFromPEM(string $pem, ?string $password = null): array
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }

        if (preg_match('#DEK-Info: (.+),(.+)#', $pem, $matches) === 1) {
            $pem = self::decodePem($pem, $matches, $password);
        }

        if (preg_match('#BEGIN ENCRYPTED PRIVATE KEY(.+)(.+)#', $pem) === 1) {
            $decrypted = openssl_pkey_get_private($pem, $password);
            if ($decrypted === false) {
                throw new InvalidArgumentException('Unable to decrypt the key.');
            }
            openssl_pkey_export($decrypted, $pem);
        }

        self::sanitizePEM($pem);
        $res = openssl_pkey_get_private($pem);
        if ($res === false) {
            $res = openssl_pkey_get_public($pem);
        }
        if ($res === false) {
            throw new InvalidArgumentException('Unable to load the key.');
        }

        $details = openssl_pkey_get_details($res);
        if (! is_array($details) || ! array_key_exists('type', $details)) {
            throw new InvalidArgumentException('Unable to get details of the key');
        }

        return match ($details['type']) {
            OPENSSL_KEYTYPE_EC => self::tryToLoadECKey($details, $pem),
            OPENSSL_KEYTYPE_RSA => RSAKey::createFromPEM($pem)->toArray(),
            4 => self::tryToLoadX25519Key($details), // OPENSSL_KEYTYPE_X25519
            5 => self::tryToLoadED25519Key($details), // OPENSSL_KEYTYPE_ED25519
            6 => self::tryToLoadX448Key($details), // OPENSSL_KEYTYPE_X448
            7 => self::tryToLoadED448Key($details), // OPENSSL_KEYTYPE_ED448
            -1 => self::tryToLoadOtherKeyTypes($details, $pem),
            default => throw new InvalidArgumentException('Unsupported key type'),
        };
    }

    /**
     * This method tries to load Ed448, X488, Ed25519 and X25519 keys.
     *
     * @param array{type: int, key: string} $details
     *
     * @return array<array-key, mixed>
     */
    private static function tryToLoadECKey(array $details, string $input): array
    {
        try {
            return ECKey::createFromPEM($input)->toArray();
        } catch (Throwable) {
            // no break
        }
        try {
            return self::tryToLoadOtherKeyTypes($details, $input);
        } catch (Throwable) {
            // no break
        }
        throw new InvalidArgumentException('Unable to load the key.');
    }

    /**
     * @param array{bits: int, type: int, key: string, x25519: array{pub_key?: string, priv_key?: string}} $input
     *
     * @return array<array-key, mixed>
     */
    private static function tryToLoadX25519Key(array $input): array
    {
        $values = [
            'kty' => 'OKP',
            'crv' => 'X25519',
        ];
        if (array_key_exists('pub_key', $input['x25519'])) {
            $values['x'] = Base64UrlSafe::encodeUnpadded($input['x25519']['pub_key']);
        } else {
            $values['x'] = self::tryToLoadOtherKeyTypes($input, $input['key'])['x'];
        }
        if (array_key_exists('priv_key', $input['x25519'])) {
            $values['d'] = Base64UrlSafe::encodeUnpadded($input['x25519']['priv_key']);
        }

        return $values;
    }

    /**
     * @param array{bits: int, type: int, key: string, ed25519: array{pub_key?: string, priv_key?: string}} $input
     *
     * @return array<array-key, mixed>
     */
    private static function tryToLoadED25519Key(array $input): array
    {
        $values = [
            'kty' => 'OKP',
            'crv' => 'Ed25519',
        ];
        if (array_key_exists('pub_key', $input['ed25519'])) {
            $values['x'] = Base64UrlSafe::encodeUnpadded($input['ed25519']['pub_key']);
        } else {
            $values['x'] = self::tryToLoadOtherKeyTypes($input, $input['key'])['x'];
        }
        if (array_key_exists('priv_key', $input['ed25519'])) {
            $values['d'] = Base64UrlSafe::encodeUnpadded($input['ed25519']['priv_key']);
        }

        return $values;
    }

    /**
     * @param array{bits: int, type: int, key: string, x448: array{pub_key?: string, priv_key?: string}} $input
     *
     * @return array<array-key, mixed>
     */
    private static function tryToLoadX448Key(array $input): array
    {
        $values = [
            'kty' => 'OKP',
            'crv' => 'X448',
        ];
        if (array_key_exists('pub_key', $input['x448'])) {
            $values['x'] = Base64UrlSafe::encodeUnpadded($input['x448']['pub_key']);
        } else {
            $values['x'] = self::tryToLoadOtherKeyTypes($input, $input['key'])['x'];
        }
        if (array_key_exists('priv_key', $input['x448'])) {
            $values['d'] = Base64UrlSafe::encodeUnpadded($input['x448']['priv_key']);
        }

        return $values;
    }

    /**
     * @param array{bits: int, type: int, key: string, ed448: array{pub_key?: string, priv_key?: string}} $input
     *
     * @return array<array-key, mixed>
     */
    private static function tryToLoadED448Key(array $input): array
    {
        $values = [
            'kty' => 'OKP',
            'crv' => 'Ed448',
        ];
        if (array_key_exists('pub_key', $input['ed448'])) {
            $values['x'] = Base64UrlSafe::encodeUnpadded($input['ed448']['pub_key']);
        } else {
            $values['x'] = self::tryToLoadOtherKeyTypes($input, $input['key'])['x'];
        }
        if (array_key_exists('priv_key', $input['ed448'])) {
            $values['d'] = Base64UrlSafe::encodeUnpadded($input['ed448']['priv_key']);
        }

        return $values;
    }

    /**
     * This method tries to load Ed448, X488, Ed25519 and X25519 keys.
     * Only needed on PHP8.3 and earlier.
     *
     * @param array{key: string} $details
     *
     * @return array<array-key, mixed>
     */
    private static function tryToLoadOtherKeyTypes(array $details, string $input): array
    {
        $pem = PEM::fromString($input);
        return match ($pem->type()) {
            PEM::TYPE_PUBLIC_KEY => self::loadPublicKey($pem),
            PEM::TYPE_PRIVATE_KEY => self::loadPrivateKey($details, $pem),
            default => throw new InvalidArgumentException('Unsupported key type'),
        };
    }

    /**
     * @param array{key: string} $details
     *
     * @return array<string, mixed>
     */
    private static function loadPrivateKey(array $details, PEM $pem): array
    {
        try {
            $key = PrivateKey::fromPEM($pem);
            switch ($key->algorithmIdentifier()->oid()) {
                case AlgorithmIdentifier::OID_RSASSA_PSS_ENCRYPTION:
                    assert($key instanceof RSASSAPSSPrivateKey);
                    return [
                        'kty' => 'RSA',
                        'n' => self::convertDecimalToBas64Url($key->modulus()),
                        'e' => self::convertDecimalToBas64Url($key->publicExponent()),
                        'd' => self::convertDecimalToBas64Url($key->privateExponent()),
                        'dp' => self::convertDecimalToBas64Url($key->exponent1()),
                        'dq' => self::convertDecimalToBas64Url($key->exponent2()),
                        'p' => self::convertDecimalToBas64Url($key->prime1()),
                        'q' => self::convertDecimalToBas64Url($key->prime2()),
                        'qi' => self::convertDecimalToBas64Url($key->coefficient()),
                    ];
                case AlgorithmIdentifier::OID_ED25519:
                case AlgorithmIdentifier::OID_ED448:
                case AlgorithmIdentifier::OID_X25519:
                case AlgorithmIdentifier::OID_X448:
                    $curve = self::getCurve($key->algorithmIdentifier()->oid());
                    $publicKey = PEM::fromString($details['key']);
                    /** @var UnspecifiedType $publicKeyBits */
                    $publicKeyBits = Sequence::fromDER($publicKey->data())->at(1);
                    return [
                        'kty' => 'OKP',
                        'crv' => $curve,
                        'x' => Base64UrlSafe::encodeUnpadded($publicKeyBits->asBitString()->string()),
                        'd' => Base64UrlSafe::encodeUnpadded($key->privateKeyData()),
                    ];
                default:
                    throw new InvalidArgumentException('Unsupported key type');
            }
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Unable to load the key.', 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadPublicKey(PEM $pem): array
    {
        $key = PublicKey::fromPEM($pem);
        switch ($key->algorithmIdentifier()->oid()) {
            case AlgorithmIdentifier::OID_ED25519:
            case AlgorithmIdentifier::OID_ED448:
            case AlgorithmIdentifier::OID_X25519:
            case AlgorithmIdentifier::OID_X448:
                $curve = self::getCurve($key->algorithmIdentifier()->oid());
                self::checkType($curve);
                return [
                    'kty' => 'OKP',
                    'crv' => $curve,
                    'x' => Base64UrlSafe::encodeUnpadded((string) $key->subjectPublicKey()),
                ];
            default:
                throw new InvalidArgumentException('Unsupported key type');
        }
    }

    private static function convertDecimalToBas64Url(string $decimal): string
    {
        return Base64UrlSafe::encodeUnpadded(BigInteger::fromBase($decimal, 10)->toBytes());
    }

    private static function checkType(string $curve): void
    {
        $curves = ['Ed448ph', 'Ed25519ph', 'Ed448', 'Ed25519', 'X448', 'X25519'];
        in_array($curve, $curves, true) || throw new InvalidArgumentException('Unsupported key type.');
    }

    /**
     * This method modifies the PEM to get 64 char lines and fix bug with old OpenSSL versions.
     */
    private static function getCurve(string $oid): string
    {
        return match ($oid) {
            '1.3.101.115' => 'Ed448ph',
            '1.3.101.114' => 'Ed25519ph',
            '1.3.101.113' => 'Ed448',
            '1.3.101.112' => 'Ed25519',
            '1.3.101.111' => 'X448',
            '1.3.101.110' => 'X25519',
            default => throw new InvalidArgumentException('Unsupported key type.'),
        };
    }

    /**
     * This method modifies the PEM to get 64 char lines and fix bug with old OpenSSL versions.
     */
    private static function sanitizePEM(string &$pem): void
    {
        $number = preg_match_all('#(-.*-)#', $pem, $matches, PREG_PATTERN_ORDER);
        if ($number !== 2) {
            throw new InvalidArgumentException('Unable to load the key');
        }

        $ciphertext = preg_replace('#-.*-|\r|\n| #', '', $pem);

        $pem = $matches[0][0] . "\n";
        $pem .= chunk_split($ciphertext ?? '', 64, "\n");
        $pem .= $matches[0][1] . "\n";
    }

    /**
     * @param string[] $matches
     */
    private static function decodePem(string $pem, array $matches, ?string $password = null): string
    {
        if ($password === null) {
            throw new InvalidArgumentException('Password required for encrypted keys.');
        }

        $iv = pack('H*', trim($matches[2]));
        $iv_sub = substr($iv, 0, 8);
        $symkey = pack('H*', md5($password . $iv_sub));
        $symkey .= pack('H*', md5($symkey . $password . $iv_sub));
        $key = preg_replace('#^(?:Proc-Type|DEK-Info): .*#m', '', $pem);
        $ciphertext = base64_decode(preg_replace('#-.*-|\r|\n#', '', $key ?? '') ?? '', true);
        if (! is_string($ciphertext)) {
            throw new InvalidArgumentException('Unable to encode the data.');
        }

        $decoded = openssl_decrypt($ciphertext, strtolower($matches[1]), $symkey, OPENSSL_RAW_DATA, $iv);
        if ($decoded === false) {
            throw new RuntimeException('Unable to decrypt the key');
        }
        $number = preg_match_all('#-{5}.*-{5}#', $pem, $result);
        if ($number !== 2) {
            throw new InvalidArgumentException('Unable to load the key');
        }

        $pem = $result[0][0] . "\n";
        $pem .= chunk_split(base64_encode($decoded), 64);

        return $pem . ($result[0][1] . "\n");
    }

    private static function convertDerToPem(string $der_data): string
    {
        $pem = chunk_split(base64_encode($der_data), 64, "\n");

        return '-----BEGIN CERTIFICATE-----' . "\n" . $pem . '-----END CERTIFICATE-----' . "\n";
    }
}
