<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWK;

final class KeyAnalyzerManager
{
    /**
     * @var KeyAnalyzer[]
     */
    private array $analyzers = [];

    /**
     * Adds a Key Analyzer to the manager.
     */
    public function add(KeyAnalyzer $analyzer): void
    {
        $this->analyzers[] = $analyzer;
    }

    /**
     * This method will analyze the JWK object using all analyzers. It returns a message bag that may contains messages.
     */
    public function analyze(JWK $jwk): MessageBag
    {
        $bag = new MessageBag();
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($jwk, $bag);
        }

        return $bag;
    }
}
