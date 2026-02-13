<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\ContentEncryption;

use Override;

final readonly class A128CBCHS256 extends AESCBCHS
{
    #[Override]
    public function getCEKSize(): int
    {
        return 256;
    }

    #[Override]
    public function name(): string
    {
        return 'A128CBC-HS256';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha256';
    }

    #[Override]
    protected function getMode(): string
    {
        return 'aes-128-cbc';
    }
}
