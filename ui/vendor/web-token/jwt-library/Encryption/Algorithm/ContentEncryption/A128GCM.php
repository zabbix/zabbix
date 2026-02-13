<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\ContentEncryption;

use Override;

final readonly class A128GCM extends AESGCM
{
    #[Override]
    public function getCEKSize(): int
    {
        return 128;
    }

    #[Override]
    public function name(): string
    {
        return 'A128GCM';
    }

    #[Override]
    protected function getMode(): string
    {
        return 'aes-128-gcm';
    }
}
