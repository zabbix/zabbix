<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Algorithm;

use Override;

final readonly class PS256 extends RSAPSS
{
    #[Override]
    public function name(): string
    {
        return 'PS256';
    }

    #[Override]
    protected function getAlgorithm(): string
    {
        return 'sha256';
    }
}
