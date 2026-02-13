<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Override;
use function in_array;
use function is_array;
use function is_string;

/**
 * Represents a class that checks the audience claim and header in a JWT token.
 */
final readonly class AudienceChecker implements ClaimChecker, HeaderChecker
{
    private const CLAIM_NAME = 'aud';

    public function __construct(
        private string $audience,
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
        if (is_string($value) && $value !== $this->audience) {
            throw new $class('Bad audience.', self::CLAIM_NAME, $value);
        }
        if (is_array($value) && ! in_array($this->audience, $value, true)) {
            throw new $class('Bad audience.', self::CLAIM_NAME, $value);
        }
        if (! is_array($value) && ! is_string($value)) {
            throw new $class('Bad audience.', self::CLAIM_NAME, $value);
        }
    }
}
