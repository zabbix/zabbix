<?php declare(strict_types=1);

namespace Facebook\WebDriver\Exception\Internal;

use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Exception thrown if an error which can only be found on runtime occurs.
 */
class RuntimeException extends \RuntimeException implements PhpWebDriverExceptionInterface
{
    public static function forError(string $message): self
    {
        return new self($message);
    }

    public static function forDriverError(Process $process): self
    {
        return new self(
            sprintf(
                'Error starting driver executable "%s": %s',
                $process->getCommandLine(),
                $process->getErrorOutput()
            )
        );
    }
}
