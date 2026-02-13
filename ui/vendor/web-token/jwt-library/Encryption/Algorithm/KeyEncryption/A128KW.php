<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A128KW as Wrapper;
use AESKW\Wrapper as WrapperInterface;
use Override;

final readonly class A128KW extends AESKW
{
    #[Override]
    public function name(): string
    {
        return 'A128KW';
    }

    #[Override]
    protected function getWrapper(): WrapperInterface
    {
        return new Wrapper();
    }
}
