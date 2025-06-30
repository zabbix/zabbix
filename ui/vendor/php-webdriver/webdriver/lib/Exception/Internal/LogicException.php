<?php declare(strict_types=1);

namespace Facebook\WebDriver\Exception\Internal;

use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;

/**
 * Exception thrown when error in program logic occurs. This includes invalid domain data and unexpected data states.
 */
class LogicException extends \LogicException implements PhpWebDriverExceptionInterface
{
    public static function forError(string $message): self
    {
        return new self($message);
    }

    public static function forInvalidHttpMethod(string $url, string $httpMethod, array $params): self
    {
        return new self(
            sprintf(
                'The http method called for "%s" is "%s", but it has to be POST' .
                ' if you want to pass the JSON params %s',
                $url,
                $httpMethod,
                json_encode($params)
            )
        );
    }
}
