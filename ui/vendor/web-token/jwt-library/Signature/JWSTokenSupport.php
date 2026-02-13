<?php

declare(strict_types=1);

namespace Jose\Component\Signature;

use InvalidArgumentException;
use Jose\Component\Checker\TokenTypeSupport;
use Jose\Component\Core\JWT;
use Override;

final class JWSTokenSupport implements TokenTypeSupport
{
    #[Override]
    public function supports(JWT $jwt): bool
    {
        return $jwt instanceof JWS;
    }

    /**
     * @param array<string, mixed> $protectedHeader
     * @param array<string, mixed> $unprotectedHeader
     */
    #[Override]
    public function retrieveTokenHeaders(JWT $jwt, int $index, array &$protectedHeader, array &$unprotectedHeader): void
    {
        if (! $jwt instanceof JWS) {
            return;
        }

        if ($index > $jwt->countSignatures()) {
            throw new InvalidArgumentException('Unknown signature index.');
        }
        $protectedHeader = $jwt->getSignature($index)
            ->getProtectedHeader();
        $unprotectedHeader = $jwt->getSignature($index)
            ->getHeader();
    }
}
