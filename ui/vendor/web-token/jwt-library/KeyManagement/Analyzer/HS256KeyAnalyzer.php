<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Override;

final readonly class HS256KeyAnalyzer extends HSKeyAnalyzer
{
    #[Override]
    protected function getAlgorithmName(): string
    {
        return 'HS256';
    }

    #[Override]
    protected function getMinimumKeySize(): int
    {
        return 256;
    }
}
