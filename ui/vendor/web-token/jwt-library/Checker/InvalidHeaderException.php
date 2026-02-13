<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Exception;

/**
 * This exception is thrown by header checkers when a header check failed.
 */
class InvalidHeaderException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $header,
        private readonly mixed $value
    ) {
        parent::__construct($message);
    }

    /**
     * Returns the header parameter that caused the exception.
     */
    public function getHeader(): string
    {
        return $this->header;
    }

    /**
     * Returns the header parameter value that caused the exception.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
