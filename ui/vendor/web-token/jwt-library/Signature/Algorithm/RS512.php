<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use Override;

final readonly class RS512 extends RSAPKCS1
{
    #[Override]
    public function name(): string
    {
        return 'RS512';
    }

    #[Override]
    protected function getAlgorithm(): string
    {
        return 'sha512';
    }
}
