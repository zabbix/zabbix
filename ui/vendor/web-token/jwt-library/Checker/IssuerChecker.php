<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Override;
use function in_array;
use function is_string;

/**
 * This class is a header parameter and claim checker.
 *
 * When the "iss" header parameter or claim is present, it will check if the value is within the allowed ones.
 */
final readonly class IssuerChecker implements ClaimChecker, HeaderChecker
{
    private const CLAIM_NAME = 'iss';

    public function __construct(
        private array $issuers,
        private bool $protectedHeader = false
    ) {
    }

    #[Override]
    public function checkClaim(mixed $value): void
    {
        $this->checkValue($value, InvalidClaimException::class);
    }

    #[Override]
    public function checkHeader(mixed $value): void
    {
        $this->checkValue($value, InvalidHeaderException::class);
    }

    #[Override]
    public function supportedClaim(): string
    {
        return self::CLAIM_NAME;
    }

    #[Override]
    public function supportedHeader(): string
    {
        return self::CLAIM_NAME;
    }

    #[Override]
    public function protectedHeaderOnly(): bool
    {
        return $this->protectedHeader;
    }

    private function checkValue(mixed $value, string $class): void
    {
        if (! is_string($value)) {
            throw new $class('Invalid value.', self::CLAIM_NAME, $value);
        }
        if (! in_array($value, $this->issuers, true)) {
            throw new $class('Unknown issuer.', self::CLAIM_NAME, $value);
        }
    }
}
