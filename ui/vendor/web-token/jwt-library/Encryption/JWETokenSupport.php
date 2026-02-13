<?php

declare(strict_types=1);

namespace Jose\Component\Encryption;

use Jose\Component\Checker\TokenTypeSupport;
use Jose\Component\Core\JWT;
use Override;

final class JWETokenSupport implements TokenTypeSupport
{
    #[Override]
    public function supports(JWT $jwt): bool
    {
        return $jwt instanceof JWE;
    }

    /**
     * @param array<string, mixed> $protectedHeader
     * @param array<string, mixed> $unprotectedHeader
     */
    #[Override]
    public function retrieveTokenHeaders(JWT $jwt, int $index, array &$protectedHeader, array &$unprotectedHeader): void
    {
        if (! $jwt instanceof JWE) {
            return;
        }
        $protectedHeader = $jwt->getSharedProtectedHeader();
        $unprotectedHeader = $jwt->getSharedHeader();
        $recipient = $jwt->getRecipient($index)
            ->getHeader();

        $unprotectedHeader = array_merge($unprotectedHeader, $recipient);
    }
}
