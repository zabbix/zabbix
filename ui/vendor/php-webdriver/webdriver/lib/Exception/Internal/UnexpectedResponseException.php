<?php declare(strict_types=1);

namespace Facebook\WebDriver\Exception\Internal;

use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;

/**
 * Exception thrown on invalid or unexpected server response.
 */
class UnexpectedResponseException extends \RuntimeException implements PhpWebDriverExceptionInterface
{
    public static function forError(string $message): self
    {
        return new self($message);
    }

    public static function forElementNotArray($response): self
    {
        return new self(
            sprintf(
                "Unexpected server response for getting an element. Expected array, but the response was: '%s'\n",
                print_r($response, true)
            )
        );
    }

    public static function forJsonDecodingError(int $jsonLastError, string $rawResults): self
    {
        return new self(
            sprintf(
                "JSON decoding of remote response failed.\n" .
                "Error code: %d\n" .
                "The response: '%s'\n",
                $jsonLastError,
                $rawResults
            )
        );
    }

    public static function forCapabilitiesRetrievalError(\Exception $previousException): self
    {
        return new self(
            sprintf(
                'Existing Capabilities were not provided, and they also cannot be read from Selenium Grid'
                . ' (error: "%s"). You are probably not using Selenium Grid, so to reuse the previous session,'
                . ' Capabilities must be explicitly provided to createBySessionID() method.',
                $previousException->getMessage()
            )
        );
    }
}
