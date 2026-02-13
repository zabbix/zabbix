<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Serializer;

use function array_key_exists;

abstract readonly class Serializer implements JWSSerializer
{
    /**
     * @param array<string, mixed> $protectedHeader
     */
    protected function isPayloadEncoded(array $protectedHeader): bool
    {
        return ! array_key_exists('b64', $protectedHeader) || $protectedHeader['b64'] === true;
    }
}
