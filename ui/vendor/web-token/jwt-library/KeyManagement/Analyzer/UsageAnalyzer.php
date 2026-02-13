<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWK;
use Override;
use function array_diff;
use function in_array;
use function is_array;
use function sprintf;

final readonly class UsageAnalyzer implements KeyAnalyzer
{
    #[Override]
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if (! $jwk->has('use')) {
            $bag->add(Message::medium('The parameter "use" should be added.'));
        } elseif (! in_array($jwk->get('use'), ['sig', 'enc'], true)) {
            $bag->add(
                Message::high(sprintf(
                    'The parameter "use" has an unsupported value "%s". Please use "sig" (signature) or "enc" (encryption).',
                    $jwk->get('use')
                ))
            );
        }
        if ($jwk->has('key_ops')) {
            $key_ops = $jwk->get('key_ops');
            if (! is_array($key_ops)) {
                $bag->add(Message::high('The parameter "key_ops" must be an array of key operation values.'));
            } else {
                $allowedOps = [
                    'sign',
                    'verify',
                    'encrypt',
                    'decrypt',
                    'wrapKey',
                    'unwrapKey',
                    'deriveKey',
                    'deriveBits',
                ];
                $unsupportedOps = array_diff($key_ops, $allowedOps);
                if ($unsupportedOps !== []) {
                    $bag->add(
                        Message::high(sprintf(
                            'The parameter "key_ops" contains unsupported values: "%s". Please use only the following values: %s.',
                            implode('", "', $unsupportedOps),
                            implode(', ', $allowedOps)
                        ))
                    );
                }
            }
        }
    }
}
