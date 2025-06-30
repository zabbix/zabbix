<?php declare(strict_types=1);

namespace Facebook\WebDriver\Exception\Internal;

use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;

/**
 * Exception class thrown when a filesystem related operation failure happens.
 */
class IOException extends \LogicException implements PhpWebDriverExceptionInterface
{
    public static function forFileError(string $message, string $path): self
    {
        return new self(sprintf($message . ' ("%s")', $path));
    }
}
