<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A192KW as Wrapper;
use Override;

final readonly class ECDHSSA192KW extends ECDHSSAESKW
{
    #[Override]
    public function name(): string
    {
        return 'ECDH-SS+A192KW';
    }

    #[Override]
    protected function getWrapper(): Wrapper
    {
        return new Wrapper();
    }

    #[Override]
    protected function getKeyLength(): int
    {
        return 192;
    }
}
