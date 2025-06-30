<?php declare(strict_types=1);

namespace Facebook\WebDriver\Exception\Internal;

use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;

/**
 * The driver server process is unexpectedly no longer available.
 */
class DriverServerDiedException extends \RuntimeException implements PhpWebDriverExceptionInterface
{
    public function __construct(?\Exception $previous = null)
    {
        parent::__construct('The driver server has died.', 0, $previous);
    }
}
