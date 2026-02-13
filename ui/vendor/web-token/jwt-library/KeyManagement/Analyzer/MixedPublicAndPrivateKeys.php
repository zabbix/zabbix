<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWKSet;
use Override;

final readonly class MixedPublicAndPrivateKeys implements KeysetAnalyzer
{
    #[Override]
    public function analyze(JWKSet $jwkset, MessageBag $bag): void
    {
        if ($jwkset->count() === 0) {
            return;
        }

        $hasPublicKeys = false;
        $hasPrivateKeys = false;

        foreach ($jwkset as $jwk) {
            switch ($jwk->get('kty')) {
                case 'OKP':
                case 'RSA':
                case 'EC':
                    if ($jwk->has('d')) {
                        $hasPrivateKeys = true;
                    } else {
                        $hasPublicKeys = true;
                    }

                    break;
            }
        }

        if ($hasPrivateKeys && $hasPublicKeys) {
            $bag->add(Message::high('This key set mixes public and private keys.'));
        }
    }
}
