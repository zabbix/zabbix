<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Override;
use function in_array;

final readonly class None implements SignatureAlgorithm
{
    #[Override]
    public function allowedKeyTypes(): array
    {
        return ['none'];
    }

    #[Override]
    public function sign(JWK $key, string $input): string
    {
        $this->checkKey($key);

        return '';
    }

    #[Override]
    public function verify(JWK $key, string $input, string $signature): bool
    {
        return $signature === '';
    }

    #[Override]
    public function name(): string
    {
        return 'none';
    }

    private function checkKey(JWK $key): void
    {
        if (! in_array($key->get('kty'), $this->allowedKeyTypes(), true)) {
            throw new InvalidArgumentException('Wrong key type.');
        }
    }
}
