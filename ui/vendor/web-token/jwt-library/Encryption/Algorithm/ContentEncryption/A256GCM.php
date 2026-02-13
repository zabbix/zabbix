<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\ContentEncryption;

use Override;

final readonly class A256GCM extends AESGCM
{
    #[Override]
    public function getCEKSize(): int
    {
        return 256;
    }

    #[Override]
    public function name(): string
    {
        return 'A256GCM';
    }

    #[Override]
    protected function getMode(): string
    {
        return 'aes-256-gcm';
    }
}
