<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\KeyConverter;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\BigInteger;
use RuntimeException;
use function array_key_exists;
use function assert;
use function extension_loaded;
use function in_array;
use function is_array;
use function is_string;

/**
 * @internal
 */
final class RSAKey
{
    /**
     * @var array<array-key, string>
     */
    private array $values = [];

    /**
     * @param array<array-key, string> $data
     */
    private function __construct(array $data)
    {
        $this->loadJWK($data);
    }

    /**
     * @param array<array-key, mixed> $details
     */
    public static function createFromKeyDetails(array $details): self
    {
        $values = [
            'kty' => 'RSA',
        ];
        $keys = [
            'n' => 'n',
            'e' => 'e',
            'd' => 'd',
            'p' => 'p',
            'q' => 'q',
            'dp' => 'dmp1',
            'dq' => 'dmq1',
            'qi' => 'iqmp',
        ];
        foreach ($details as $key => $value) {
            if (in_array($key, $keys, true)) {
                assert(is_string($value), 'Invalid key.');
                $value = Base64UrlSafe::encodeUnpadded($value);
                $values[array_search($key, $keys, true)] = $value;
            }
        }

        return new self($values);
    }

    public static function createFromPEM(string $pem): self
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
        $res = openssl_pkey_get_private($pem);
        if ($res === false) {
            $res = openssl_pkey_get_public($pem);
        }
        if ($res === false) {
            throw new InvalidArgumentException('Unable to load the key.');
        }

        $details = openssl_pkey_get_details($res);
        if (! is_array($details) || ! isset($details['rsa'])) {
            throw new InvalidArgumentException('Unable to load the key.');
        }
        $data = $details['rsa'];
        if (! is_array($data)) {
            throw new InvalidArgumentException('Unable to load the key.');
        }

        return self::createFromKeyDetails($data);
    }

    public static function createFromJWK(JWK $jwk): self
    {
        return new self($jwk->all());
    }

    public function isPublic(): bool
    {
        return ! array_key_exists('d', $this->values);
    }

    public static function toPublic(self $private): self
    {
        $data = $private->toArray();
        $keys = ['p', 'd', 'q', 'dp', 'dq', 'qi'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        return new self($data);
    }

    /**
     * @return array<array-key, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function toJwk(): JWK
    {
        return new JWK($this->values);
    }

    /**
     * This method will try to add Chinese Remainder Theorem (CRT) parameters. With those primes, the decryption process
     * is really fast.
     */
    public function optimize(): void
    {
        if (array_key_exists('d', $this->values)) {
            $this->populateCRT();
        }
    }

    /**
     * @param array<array-key, string> $jwk
     */
    private function loadJWK(array $jwk): void
    {
        if (! array_key_exists('kty', $jwk)) {
            throw new InvalidArgumentException('The key parameter "kty" is missing.');
        }
        if ($jwk['kty'] !== 'RSA') {
            throw new InvalidArgumentException('The JWK is not a RSA key.');
        }

        $this->values = $jwk;
    }

    /**
     * This method adds Chinese Remainder Theorem (CRT) parameters if primes 'p' and 'q' are available. If 'p' and 'q'
     * are missing, they are computed and added to the key data.
     */
    private function populateCRT(): void
    {
        if (! array_key_exists('p', $this->values) && ! array_key_exists('q', $this->values)) {
            $d = BigInteger::createFromBinaryString(Base64UrlSafe::decodeNoPadding($this->values['d']));
            $e = BigInteger::createFromBinaryString(Base64UrlSafe::decodeNoPadding($this->values['e']));
            $n = BigInteger::createFromBinaryString(Base64UrlSafe::decodeNoPadding($this->values['n']));

            [$p, $q] = $this->findPrimeFactors($d, $e, $n);
            $this->values['p'] = Base64UrlSafe::encodeUnpadded($p->toBytes());
            $this->values['q'] = Base64UrlSafe::encodeUnpadded($q->toBytes());
        }

        if (array_key_exists('dp', $this->values) && array_key_exists('dq', $this->values) && array_key_exists(
            'qi',
            $this->values
        )) {
            return;
        }

        $one = BigInteger::createFromDecimal(1);
        $d = BigInteger::createFromBinaryString(Base64UrlSafe::decodeNoPadding($this->values['d']));
        $p = BigInteger::createFromBinaryString(Base64UrlSafe::decodeNoPadding($this->values['p']));
        $q = BigInteger::createFromBinaryString(Base64UrlSafe::decodeNoPadding($this->values['q']));

        $this->values['dp'] = Base64UrlSafe::encodeUnpadded($d->mod($p->subtract($one))->toBytes());
        $this->values['dq'] = Base64UrlSafe::encodeUnpadded($d->mod($q->subtract($one))->toBytes());
        $this->values['qi'] = Base64UrlSafe::encodeUnpadded($q->modInverse($p)->toBytes());
    }

    /**
     * @return BigInteger[]
     */
    private function findPrimeFactors(BigInteger $d, BigInteger $e, BigInteger $n): array
    {
        $zero = BigInteger::createFromDecimal(0);
        $one = BigInteger::createFromDecimal(1);
        $two = BigInteger::createFromDecimal(2);

        $k = $d->multiply($e)
            ->subtract($one);

        if ($k->isEven()) {
            $r = $k;
            $t = $zero;

            do {
                $r = $r->divide($two);
                $t = $t->add($one);
            } while ($r->isEven());

            $found = false;
            $y = null;

            for ($i = 1; $i <= 100; ++$i) {
                $g = BigInteger::random($n->subtract($one));
                $y = $g->modPow($r, $n);

                if ($y->equals($one) || $y->equals($n->subtract($one))) {
                    continue;
                }

                for ($j = $one; $j->lowerThan($t->subtract($one)); $j = $j->add($one)) {
                    $x = $y->modPow($two, $n);

                    if ($x->equals($one)) {
                        $found = true;

                        break;
                    }

                    if ($x->equals($n->subtract($one))) {
                        continue;
                    }

                    $y = $x;
                }

                $x = $y->modPow($two, $n);
                if ($x->equals($one)) {
                    $found = true;

                    break;
                }
            }
            if ($y === null) {
                throw new InvalidArgumentException('Unable to find prime factors.');
            }
            if ($found === true) {
                $p = $y->subtract($one)
                    ->gcd($n);
                $q = $n->divide($p);

                return [$p, $q];
            }
        }

        throw new InvalidArgumentException('Unable to find prime factors.');
    }
}
