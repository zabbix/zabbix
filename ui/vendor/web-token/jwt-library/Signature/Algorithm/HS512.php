<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Override;
use function strlen;

final readonly class HS512 extends HMAC
{
    #[Override]
    public function name(): string
    {
        return 'HS512';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha512';
    }

    #[Override]
    protected function getKey(JWK $key): string
    {
        $k = parent::getKey($key);
        if (strlen($k) < 64) {
            throw new InvalidArgumentException('Invalid key length.');
        }

        return $k;
    }
}
