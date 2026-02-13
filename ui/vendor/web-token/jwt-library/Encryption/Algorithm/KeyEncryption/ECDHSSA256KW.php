<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A256KW as Wrapper;
use Override;

final readonly class ECDHSSA256KW extends ECDHSSAESKW
{
    /**
     * NOTE: the return name was modified
     */
    #[Override]
    public function name(): string
    {
        return 'ECDH-SS+A256KW';
    }

    #[Override]
    protected function getWrapper(): Wrapper
    {
        return new Wrapper();
    }

    #[Override]
    protected function getKeyLength(): int
    {
        return 256;
    }
}
