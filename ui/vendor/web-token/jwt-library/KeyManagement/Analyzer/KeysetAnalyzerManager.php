<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWKSet;

final class KeysetAnalyzerManager
{
    /**
     * @var KeysetAnalyzer[]
     */
    private array $analyzers = [];

    /**
     * Adds a Keyset Analyzer to the manager.
     */
    public function add(KeysetAnalyzer $analyzer): void
    {
        $this->analyzers[] = $analyzer;
    }

    /**
     * This method will analyze the JWKSet object using all analyzers. It returns a message bag that may contains
     * messages.
     */
    public function analyze(JWKSet $jwkset): MessageBag
    {
        $bag = new MessageBag();
        foreach ($this->analyzers as $analyzer) {
            $analyzer->analyze($jwkset, $bag);
        }

        return $bag;
    }
}
