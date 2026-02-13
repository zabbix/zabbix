<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWK;

interface KeyAnalyzer
{
    /**
     * This method will analyse the key and add messages to the message bag if needed.
     */
    public function analyze(JWK $jwk, MessageBag $bag): void;
}
