<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Brick\Math\BigInteger;
use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\Ecc\Curve;
use Jose\Component\Core\Util\Ecc\EcDH;
use Jose\Component\Core\Util\Ecc\NistCurve;
use Jose\Component\Core\Util\Ecc\PrivateKey;
use Jose\Component\Core\Util\ECKey;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Util\ConcatKDF;
use Override;
use RuntimeException;
use Throwable;
use function array_key_exists;
use function extension_loaded;
use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

abstract readonly class AbstractECDH implements KeyAgreement
{
    #[Override]
    public function allowedKeyTypes(): array
    {
        return ['EC', 'OKP'];
    }

    /**
     * @param array<string, mixed> $complete_header
     * @param array<string, mixed> $additional_header_values
     */
    #[Override]
    public function getAgreementKey(
        int $encryptionKeyLength,
        string $algorithm,
        JWK $recipientKey,
        ?JWK $senderKey,
        array $complete_header = [],
        array &$additional_header_values = []
    ): string {
        if ($recipientKey->has('d')) {
            [$public_key, $private_key] = $this->getKeysFromPrivateKeyAndHeader($recipientKey, $complete_header);
        } else {
            [$public_key, $private_key] = $this->getKeysFromPublicKey(
                $recipientKey,
                $senderKey,
                $additional_header_values
            );
        }

        $agreed_key = $this->calculateAgreementKey($private_key, $public_key);

        $apu = array_key_exists('apu', $complete_header) ? $complete_header['apu'] : '';
        is_string($apu) || throw new InvalidArgumentException('Invalid APU.');
        $apv = array_key_exists('apv', $complete_header) ? $complete_header['apv'] : '';
        is_string($apv) || throw new InvalidArgumentException('Invalid APU.');

        return ConcatKDF::generate($agreed_key, $algorithm, $encryptionKeyLength, $apu, $apv);
    }

    #[Override]
    public function getKeyManagementMode(): string
    {
        return self::MODE_AGREEMENT;
    }

    protected function calculateAgreementKey(JWK $private_key, JWK $public_key): string
    {
        $crv = $public_key->get('crv');
        if (! is_string($crv)) {
            throw new InvalidArgumentException('Invalid key parameter "crv"');
        }
        switch ($crv) {
            case 'P-256':
            case 'P-384':
            case 'P-521':
                $curve = $this->getCurve($crv);
                if (function_exists('openssl_pkey_derive')) {
                    try {
                        $publicPem = ECKey::convertPublicKeyToPEM($public_key);
                        $privatePem = ECKey::convertPrivateKeyToPEM($private_key);

                        $res = openssl_pkey_derive($publicPem, $privatePem);
                        if ($res === false) {
                            throw new RuntimeException('Unable to derive the key');
                        }

                        return $res;
                    } catch (Throwable) {
                        //Does nothing. Will fallback to the pure PHP function
                    }
                }
                $x = $public_key->get('x');
                if (! is_string($x)) {
                    throw new InvalidArgumentException('Invalid key parameter "x"');
                }
                $y = $public_key->get('y');
                if (! is_string($y)) {
                    throw new InvalidArgumentException('Invalid key parameter "y"');
                }
                $d = $private_key->get('d');
                if (! is_string($d)) {
                    throw new InvalidArgumentException('Invalid key parameter "d"');
                }

                $rec_x = $this->convertBase64ToBigInteger($x);
                $rec_y = $this->convertBase64ToBigInteger($y);
                $sen_d = $this->convertBase64ToBigInteger($d);

                $priv_key = PrivateKey::create($sen_d);
                $pub_key = $curve->getPublicKeyFrom($rec_x, $rec_y);

                return $this->convertDecToBin(EcDH::computeSharedKey($curve, $pub_key, $priv_key));

            case 'X25519':
                $this->checkSodiumExtensionIsAvailable();
                $x = $public_key->get('x');
                if (! is_string($x)) {
                    throw new InvalidArgumentException('Invalid key parameter "x"');
                }
                $d = $private_key->get('d');
                if (! is_string($d)) {
                    throw new InvalidArgumentException('Invalid key parameter "d"');
                }
                $sKey = Base64UrlSafe::decodeNoPadding($d);
                $recipientPublickey = Base64UrlSafe::decodeNoPadding($x);

                return sodium_crypto_scalarmult($sKey, $recipientPublickey);

            default:
                throw new InvalidArgumentException(sprintf('The curve "%s" is not supported', $crv));
        }
    }

    /**
     * @param array<string, mixed> $additional_header_values
     * @return JWK[]
     */
    protected function getKeysFromPublicKey(
        JWK $recipient_key,
        ?JWK $senderKey,
        array &$additional_header_values
    ): array {
        $this->checkKey($recipient_key, false);
        $public_key = $recipient_key;

        $crv = $public_key->get('crv');
        if (! is_string($crv)) {
            throw new InvalidArgumentException('Invalid key parameter "crv"');
        }
        $private_key = match ($crv) {
            'P-256', 'P-384', 'P-521' => $senderKey ?? ECKey::createECKey($crv),
            'X25519' => $senderKey ?? $this->createOKPKey('X25519'),
            default => throw new InvalidArgumentException(sprintf('The curve "%s" is not supported', $crv)),
        };
        $epk = $private_key->toPublic()
            ->all();
        $additional_header_values['epk'] = $epk;

        return [$public_key, $private_key];
    }

    /**
     * @param array<string, mixed> $complete_header
     * @return JWK[]
     */
    protected function getKeysFromPrivateKeyAndHeader(JWK $recipient_key, array $complete_header): array
    {
        $this->checkKey($recipient_key, true);
        $private_key = $recipient_key;
        $public_key = $this->getPublicKey($complete_header);
        if ($private_key->get('crv') !== $public_key->get('crv')) {
            throw new InvalidArgumentException('Curves are different');
        }

        return [$public_key, $private_key];
    }

    /**
     * @param array<string, mixed> $complete_header
     */
    private function getPublicKey(array $complete_header): JWK
    {
        if (! isset($complete_header['epk'])) {
            throw new InvalidArgumentException('The header parameter "epk" is missing.');
        }
        if (! is_array($complete_header['epk'])) {
            throw new InvalidArgumentException('The header parameter "epk" is not an array of parameters');
        }
        $public_key = new JWK($complete_header['epk']);
        $this->checkKey($public_key, false);

        return $public_key;
    }

    private function checkKey(JWK $key, bool $is_private): void
    {
        if (! in_array($key->get('kty'), $this->allowedKeyTypes(), true)) {
            throw new InvalidArgumentException('Wrong key type.');
        }
        foreach (['x', 'crv'] as $k) {
            if (! $key->has($k)) {
                throw new InvalidArgumentException(sprintf('The key parameter "%s" is missing.', $k));
            }
        }

        $crv = $key->get('crv');
        if (! is_string($crv)) {
            throw new InvalidArgumentException('Invalid key parameter "crv"');
        }
        switch ($crv) {
            case 'P-256':
            case 'P-384':
            case 'P-521':
                if (! $key->has('y')) {
                    throw new InvalidArgumentException('The key parameter "y" is missing.');
                }

                break;

            case 'X25519':
                break;

            default:
                throw new InvalidArgumentException(sprintf('The curve "%s" is not supported', $crv));
        }
        if ($is_private === true && ! $key->has('d')) {
            throw new InvalidArgumentException('The key parameter "d" is missing.');
        }
    }

    private function getCurve(string $crv): Curve
    {
        return match ($crv) {
            'P-256' => NistCurve::curve256(),
            'P-384' => NistCurve::curve384(),
            'P-521' => NistCurve::curve521(),
            default => throw new InvalidArgumentException(sprintf('The curve "%s" is not supported', $crv)),
        };
    }

    private function convertBase64ToBigInteger(string $value): BigInteger
    {
        $data = unpack('H*', Base64UrlSafe::decodeNoPadding($value));
        if (! is_array($data) || ! isset($data[1]) || ! is_string($data[1])) {
            throw new InvalidArgumentException('Unable to convert base64 to integer');
        }

        return BigInteger::fromBase($data[1], 16);
    }

    private function convertDecToBin(BigInteger $dec): string
    {
        if ($dec->compareTo(BigInteger::zero()) < 0) {
            throw new InvalidArgumentException('Unable to convert negative integer to string');
        }
        $hex = $dec->toBase(16);

        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $bin = hex2bin($hex);
        if ($bin === false) {
            throw new InvalidArgumentException('Unable to convert integer to string');
        }

        return $bin;
    }

    /**
     * @param string $curve The curve
     */
    private function createOKPKey(string $curve): JWK
    {
        $this->checkSodiumExtensionIsAvailable();

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

        return new JWK([
            'kty' => 'OKP',
            'crv' => $curve,
            'x' => Base64UrlSafe::encodeUnpadded($x),
            'd' => Base64UrlSafe::encodeUnpadded($d),
        ]);
    }

    private function checkSodiumExtensionIsAvailable(): void
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('The extension "sodium" is not available. Please install it to use this method');
        }
    }
}
