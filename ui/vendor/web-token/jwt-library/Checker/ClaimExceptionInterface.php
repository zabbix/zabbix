<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use Throwable;

/**
 * Represents an interface for claim exceptions.
 *
 * This interface extends from the Throwable interface, allowing
 * the claim exceptions to be thrown and caught like any other exception.
 */
interface ClaimExceptionInterface extends Throwable
{
}
