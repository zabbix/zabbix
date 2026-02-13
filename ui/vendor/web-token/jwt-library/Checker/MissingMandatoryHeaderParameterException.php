<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Exception;

class MissingMandatoryHeaderParameterException extends Exception
{
    /**
     * @param string[] $parameters
     */
    public function __construct(
        string $message,
        private readonly array $parameters
    ) {
        parent::__construct($message);
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
