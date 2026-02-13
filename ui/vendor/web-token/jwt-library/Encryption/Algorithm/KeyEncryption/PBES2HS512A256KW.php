<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A256KW as Wrapper;
use Override;

final readonly class PBES2HS512A256KW extends PBES2AESKW
{
    #[Override]
    public function name(): string
    {
        return 'PBES2-HS512+A256KW';
    }

    #[Override]
    protected function getWrapper(): Wrapper
    {
        return new Wrapper();
    }

    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha512';
    }

    #[Override]
    protected function getKeySize(): int
    {
        return 32;
    }
}
