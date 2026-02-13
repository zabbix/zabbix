<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\RSAKey;
use Override;
use RuntimeException;
use function extension_loaded;
use function in_array;
use function sprintf;

abstract readonly class RSAPKCS1 implements SignatureAlgorithm
{
    public function __construct()
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
    }

    #[Override]
    public function allowedKeyTypes(): array
    {
        return ['RSA'];
    }

    #[Override]
    public function verify(JWK $key, string $input, string $signature): bool
    {
        $this->checkKey($key);
        $pub = RSAKey::createFromJWK($key->toPublic());

        return openssl_verify($input, $signature, $pub->toPEM(), $this->getAlgorithm()) === 1;
    }

    #[Override]
    public function sign(JWK $key, string $input): string
    {
        $this->checkKey($key);
        if (! $key->has('d')) {
            throw new InvalidArgumentException('The key is not a private key.');
        }

        $priv = RSAKey::createFromJWK($key);

        $result = openssl_sign($input, $signature, $priv->toPEM(), $this->getAlgorithm());
        if ($result !== true) {
            throw new RuntimeException('Unable to sign');
        }

        return $signature;
    }

    abstract protected function getAlgorithm(): string;

    private function checkKey(JWK $key): void
    {
        if (! in_array($key->get('kty'), $this->allowedKeyTypes(), true)) {
            throw new InvalidArgumentException('Wrong key type.');
        }
        foreach (['n', 'e'] as $k) {
            if (! $key->has($k)) {
                throw new InvalidArgumentException(sprintf('The key parameter "%s" is missing.', $k));
            }
        }
    }
}
