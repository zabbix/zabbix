<?php

declare(strict_types=1);

namespace Jose\Component\NestedToken;

use InvalidArgumentException;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\JWE;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSLoader;
use function is_string;

class NestedTokenLoader
{
    public function __construct(
        private readonly JWELoader $jweLoader,
        private readonly JWSLoader $jwsLoader
    ) {
    }

    /**
     * This method will try to load, decrypt and verify the token. In case of failure, an exception is thrown, otherwise
     * returns the JWS and populates the $signature variable.
     */
    public function load(string $token, JWKSet $encryptionKeySet, JWKSet $signatureKeySet, ?int &$signature = null): JWS
    {
        $recipient = null;
        $jwe = $this->jweLoader->loadAndDecryptWithKeySet($token, $encryptionKeySet, $recipient);
        $this->checkContentTypeHeader($jwe, $recipient);
        if ($jwe->getPayload() === null) {
            throw new InvalidArgumentException('The token has no payload.');
        }

        return $this->jwsLoader->loadAndVerifyWithKeySet($jwe->getPayload(), $signatureKeySet, $signature);
    }

    private function checkContentTypeHeader(JWE $jwe, int $recipient): void
    {
        $cty = match (true) {
            $jwe->hasSharedProtectedHeaderParameter('cty') => $jwe->getSharedProtectedHeaderParameter('cty'),
            $jwe->hasSharedHeaderParameter('cty') => $jwe->getSharedHeaderParameter('cty'),
            $jwe->getRecipient($recipient)
                ->hasHeaderParameter('cty') => $jwe->getRecipient($recipient)
                ->getHeaderParameter('cty'),
            default => throw new InvalidArgumentException('The token is not a nested token.'),
        };
        if (! is_string($cty)) {
            throw new InvalidArgumentException('Invalid "cty" header parameter.');
        }

        if (strcasecmp($cty, 'jwt') !== 0) {
            throw new InvalidArgumentException('The token is not a nested token.');
        }
    }
}
