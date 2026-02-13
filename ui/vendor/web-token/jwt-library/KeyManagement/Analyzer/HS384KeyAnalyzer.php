<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Override;

final readonly class HS384KeyAnalyzer extends HSKeyAnalyzer
{
    #[Override]
    protected function getAlgorithmName(): string
    {
        return 'HS384';
    }

    #[Override]
    protected function getMinimumKeySize(): int
    {
        return 384;
    }
}
