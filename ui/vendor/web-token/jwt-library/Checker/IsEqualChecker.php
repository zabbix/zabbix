<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Override;
use function sprintf;

/**
 * This class implements a claim and header checker that checks if the value is equal to the expected value.
 * @see \Jose\Tests\Component\Checker\IsEqualCheckerTest
 */
final readonly class IsEqualChecker implements ClaimChecker, HeaderChecker
{
    /**
     * @param string $key                 The claim or header parameter name to check.
     * @param bool   $protectedHeaderOnly [optional] Whether the header parameter MUST be protected.
     *                                    This option has no effect for claim checkers.
     */
    public function __construct(
        private string $key,
        private mixed $value,
        private bool $protectedHeaderOnly = true
    ) {
    }

    #[Override]
    public function checkClaim(mixed $value): void
    {
        if ($value !== $this->value) {
            throw new InvalidClaimException(sprintf('The "%s" claim is invalid.', $this->key), $this->key, $value);
        }
    }

    #[Override]
    public function supportedClaim(): string
    {
        return $this->key;
    }

    #[Override]
    public function checkHeader(mixed $value): void
    {
        if ($value !== $this->value) {
            throw new InvalidHeaderException(sprintf('The "%s" header is invalid.', $this->key), $this->key, $value);
        }
    }

    #[Override]
    public function supportedHeader(): string
    {
        return $this->key;
    }

    #[Override]
    public function protectedHeaderOnly(): bool
    {
        return $this->protectedHeaderOnly;
    }
}
