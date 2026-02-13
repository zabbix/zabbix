<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Override;

final readonly class ECDHES extends AbstractECDH
{
    #[Override]
    public function name(): string
    {
        return 'ECDH-ES';
    }
}
