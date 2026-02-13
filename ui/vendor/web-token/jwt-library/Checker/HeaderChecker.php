<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

/**
 * This interface defines the contract for a header checker.
 */
interface HeaderChecker
{
    /**
     * Checks if the given value matches the header parameter of the token.
     */
    public function checkHeader(mixed $value): void;

    /**
     * Retrieves the supported header for the token.
     */
    public function supportedHeader(): string;

    /**
     * Returns a boolean value indicating whether the requested resource can only be accessed with a protected header.
     */
    public function protectedHeaderOnly(): bool;
}
