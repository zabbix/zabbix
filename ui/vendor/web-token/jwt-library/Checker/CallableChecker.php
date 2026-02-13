<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use InvalidArgumentException;
use Override;
use function call_user_func;
use function is_callable;
use function sprintf;

/**
 * This class is responsible for checking claims and headers using a callable function.
 * @see \Jose\Tests\Component\Checker\CallableCheckerTest
 */
final class CallableChecker implements ClaimChecker, HeaderChecker
{
    /**
     * @param string     $key      The claim or header parameter name to check.
     * @param callable(mixed $value): bool $callable The callable function that will be invoked.
     */
    public function __construct(
        private readonly string $key,
        private $callable,
        private readonly bool $protectedHeaderOnly = true
    ) {
        if (! is_callable($this->callable)) {
            throw new InvalidArgumentException('The $callable argument must be a callable.');
        }
    }

    #[Override]
    public function checkClaim(mixed $value): void
    {
        if (call_user_func($this->callable, $value) !== true) {
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
        if (call_user_func($this->callable, $value) !== true) {
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
