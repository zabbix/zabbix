<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\ContentEncryption;

use Override;

final readonly class A192CBCHS384 extends AESCBCHS
{
    #[Override]
    public function getCEKSize(): int
    {
        return 384;
    }

    #[Override]
    public function name(): string
    {
        return 'A192CBC-HS384';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha384';
    }

    #[Override]
    protected function getMode(): string
    {
        return 'aes-192-cbc';
    }
}
