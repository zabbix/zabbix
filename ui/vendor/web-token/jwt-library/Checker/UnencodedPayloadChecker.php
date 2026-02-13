<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Override;
use function is_bool;

/**
 * This class is a header parameter checker. When the "b64" is present, it will check if the value is a boolean or not.
 *
 * The use of this checker will allow the use of token with unencoded payload.
 */
final class UnencodedPayloadChecker implements HeaderChecker
{
    private const HEADER_NAME = 'b64';

    #[Override]
    public function checkHeader(mixed $value): void
    {
        if (! is_bool($value)) {
            throw new InvalidHeaderException('"b64" must be a boolean.', self::HEADER_NAME, $value);
        }
    }

    #[Override]
    public function supportedHeader(): string
    {
        return self::HEADER_NAME;
    }

    #[Override]
    public function protectedHeaderOnly(): bool
    {
        return true;
    }
}
