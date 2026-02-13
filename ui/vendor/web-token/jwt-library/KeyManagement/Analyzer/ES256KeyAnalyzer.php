<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\Util\Ecc\Curve;
use Jose\Component\Core\Util\Ecc\NistCurve;
use Override;

final readonly class ES256KeyAnalyzer extends ESKeyAnalyzer
{
    #[Override]
    protected function getAlgorithmName(): string
    {
        return 'ES256';
    }

    #[Override]
    protected function getCurveName(): string
    {
        return 'P-256';
    }

    #[Override]
    protected function getCurve(): Curve
    {
        return NistCurve::curve256();
    }

    #[Override]
    protected function getKeySize(): int
    {
        return 256;
    }
}
