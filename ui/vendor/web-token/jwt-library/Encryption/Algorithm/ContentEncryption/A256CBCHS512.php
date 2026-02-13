<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\ContentEncryption;

use Override;

final readonly class A256CBCHS512 extends AESCBCHS
{
    #[Override]
    public function getCEKSize(): int
    {
        return 512;
    }

    #[Override]
    public function name(): string
    {
        return 'A256CBC-HS512';
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha512';
    }

    #[Override]
    protected function getMode(): string
    {
        return 'aes-256-cbc';
    }
}
