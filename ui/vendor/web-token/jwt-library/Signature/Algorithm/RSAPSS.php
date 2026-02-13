<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\RSAKey;
use Jose\Component\Signature\Algorithm\Util\RSA as JoseRSA;
use Override;
use function in_array;
use function sprintf;

abstract readonly class RSAPSS implements SignatureAlgorithm
{
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

        return JoseRSA::verify($pub, $input, $signature, $this->getAlgorithm(), JoseRSA::SIGNATURE_PSS);
    }

    /**
     * @return non-empty-string
     */
    #[Override]
    public function sign(JWK $key, string $input): string
    {
        $this->checkKey($key);
        if (! $key->has('d')) {
            throw new InvalidArgumentException('The key is not a private key.');
        }

        $priv = RSAKey::createFromJWK($key);

        return JoseRSA::sign($priv, $input, $this->getAlgorithm(), JoseRSA::SIGNATURE_PSS);
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
