<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWKSet;
use Override;

final class MixedKeyTypes implements KeysetAnalyzer
{
    #[Override]
    public function analyze(JWKSet $jwkset, MessageBag $bag): void
    {
        if ($jwkset->count() === 0) {
            return;
        }

        $hasSymmetricKeys = false;
        $hasAsymmetricKeys = false;

        foreach ($jwkset as $jwk) {
            switch ($jwk->get('kty')) {
                case 'oct':
                    $hasSymmetricKeys = true;

                    break;

                case 'OKP':
                case 'RSA':
                case 'EC':
                    $hasAsymmetricKeys = true;

                    break;
            }
        }

        if ($hasAsymmetricKeys && $hasSymmetricKeys) {
            $bag->add(Message::medium('This key set mixes symmetric and asymmetric keys.'));
        }
    }
}
