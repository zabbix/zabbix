<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use Override;

final readonly class ES384 extends ECDSA
{
    #[Override]
    public function name(): string
    {
        return 'ES384';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha384';
    }

    #[Override]
    protected function getSignaturePartLength(): int
    {
        return 96;
    }
}
