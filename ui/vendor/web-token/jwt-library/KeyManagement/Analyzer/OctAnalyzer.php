<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Override;
use function is_string;
use function strlen;

final readonly class OctAnalyzer implements KeyAnalyzer
{
    #[Override]
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if ($jwk->get('kty') !== 'oct') {
            return;
        }
        $k = $jwk->get('k');
        if (! is_string($k)) {
            $bag->add(Message::high('The key is not valid'));

            return;
        }
        $k = Base64UrlSafe::decodeNoPadding($k);
        $kLength = 8 * strlen($k);
        if ($kLength < 128) {
            $bag->add(Message::high('The key length is less than 128 bits.'));
        }
    }
}
