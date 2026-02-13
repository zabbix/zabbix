<?php

declare(strict_types=1);

namespace Jose\Component\Core;

interface Algorithm
{
    /**
     * Returns the name of the algorithm.
     */
    public function name(): string;

    /**
     * Returns the key types suitable for this algorithm (e.g. "oct", "RSA"...).
     *
     * @return string[]
     */
    public function allowedKeyTypes(): array;
}
