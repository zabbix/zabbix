<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use Override;

final readonly class ES512 extends ECDSA
{
    #[Override]
    public function name(): string
    {
        return 'ES512';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha512';
    }

    #[Override]
    protected function getSignaturePartLength(): int
    {
        return 132;
    }
}
