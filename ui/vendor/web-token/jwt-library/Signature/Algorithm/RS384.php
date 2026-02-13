<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use Override;

final readonly class RS384 extends RSAPKCS1
{
    #[Override]
    public function name(): string
    {
        return 'RS384';
    }

    #[Override]
    protected function getAlgorithm(): string
    {
        return 'sha384';
    }
}
