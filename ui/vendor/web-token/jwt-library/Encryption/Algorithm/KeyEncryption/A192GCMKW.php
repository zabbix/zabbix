<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Override;

final readonly class A192GCMKW extends AESGCMKW
{
    #[Override]
    public function name(): string
    {
        return 'A192GCMKW';
    }

    #[Override]
    protected function getKeySize(): int
    {
        return 192;
    }
}
