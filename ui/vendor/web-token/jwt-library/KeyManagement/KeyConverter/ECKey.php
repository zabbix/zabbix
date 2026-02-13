<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\KeyConverter;

use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\EC\ECPrivateKey;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\EC\ECPublicKey;
use Throwable;
use function array_key_exists;
use function is_string;

/**
 * @internal
 */
final class ECKey
{
    private array $values = [];

    private function __construct(array $data)
    {
        $this->loadJWK($data);
    }

    public static function createFromPEM(string $pem): self
    {
        $data = self::loadPEM($pem);

        return new self($data);
    }

    public static function toPublic(self $private): self
    {
        $data = $private->toArray();
        if (array_key_exists('d', $data)) {
            unset($data['d']);
        }

        return new self($data);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->values;
    }

    private static function loadPEM(string $data): array
    {
        $pem = PEM::fromString($data);
        try {
            $key = ECPrivateKey::fromPEM($pem);

            return [
                'kty' => 'EC',
                'crv' => self::getCurve($key->namedCurve()),
                'd' => Base64UrlSafe::encodeUnpadded($key->privateKeyOctets()),
                'x' => Base64UrlSafe::encodeUnpadded($key->publicKey()->curvePointOctets()[0]),
                'y' => Base64UrlSafe::encodeUnpadded($key->publicKey()->curvePointOctets()[1]),
            ];
        } catch (Throwable) {
        }
        try {
            $key = ECPublicKey::fromPEM($pem);
            return [
                'kty' => 'EC',
                'crv' => self::getCurve($key->namedCurve()),
                'x' => Base64UrlSafe::encodeUnpadded($key->curvePointOctets()[0]),
                'y' => Base64UrlSafe::encodeUnpadded($key->curvePointOctets()[1]),
            ];
        } catch (Throwable) {
        }
        throw new InvalidArgumentException('Unable to load the key.');
    }

    private static function getCurve(string $oid): string
    {
        $curves = self::getSupportedCurves();
        $curve = array_search($oid, $curves, true);
        if (! is_string($curve)) {
            throw new InvalidArgumentException('Unsupported OID.');
        }

        return $curve;
    }

    private static function getSupportedCurves(): array
    {
        return [
            'P-256' => '1.2.840.10045.3.1.7',
            'P-384' => '1.3.132.0.34',
            'P-521' => '1.3.132.0.35',
        ];
    }

    private function loadJWK(array $jwk): void
    {
        $keys = [
            'kty' => 'The key parameter "kty" is missing.',
            'crv' => 'Curve parameter is missing',
            'x' => 'Point parameters are missing.',
            'y' => 'Point parameters are missing.',
        ];
        foreach ($keys as $k => $v) {
            if (! array_key_exists($k, $jwk)) {
                throw new InvalidArgumentException($v);
            }
        }

        if ($jwk['kty'] !== 'EC') {
            throw new InvalidArgumentException('JWK is not an Elliptic Curve key.');
        }
        $this->values = $jwk;
    }
}
