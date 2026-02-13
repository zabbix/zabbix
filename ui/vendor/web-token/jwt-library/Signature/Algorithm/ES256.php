<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use Override;

final readonly class ES256 extends ECDSA
{
    #[Override]
    public function name(): string
    {
        return 'ES256';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha256';
    }

    #[Override]
    protected function getSignaturePartLength(): int
    {
        return 64;
    }
}
