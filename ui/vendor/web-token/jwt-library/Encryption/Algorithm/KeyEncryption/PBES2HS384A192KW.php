<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A192KW as Wrapper;
use Override;

final readonly class PBES2HS384A192KW extends PBES2AESKW
{
    #[Override]
    public function name(): string
    {
        return 'PBES2-HS384+A192KW';
    }

    #[Override]
    protected function getWrapper(): Wrapper
    {
        return new Wrapper();
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha384';
    }

    #[Override]
    protected function getKeySize(): int
    {
        return 24;
    }
}
