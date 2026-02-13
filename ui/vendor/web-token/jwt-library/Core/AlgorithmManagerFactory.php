<?php

declare(strict_types=1);

namespace Jose\Component\Core;

use InvalidArgumentException;
use function is_string;
use function sprintf;

/**
 * @see \Jose\Tests\Component\Core\AlgorithmManagerFactoryTest
 */
final class AlgorithmManagerFactory
{
    private array $algorithms = [];

    /**
     * @param Algorithm[] $algorithms
     */
    public function __construct(iterable $algorithms = [])
    {
        foreach ($algorithms as $algorithm) {
            $this->add($algorithm->name(), $algorithm);
        }
    }

    /**
     * Adds an algorithm.
     *
     * Each algorithm is identified by an alias hence it is allowed to have the same algorithm twice (or more). This can
     * be helpful when an algorithm have several configuration options.
     */
    public function add(string $alias, Algorithm $algorithm): void
    {
        $this->algorithms[$alias] = $algorithm;
    }

    /**
     * Returns the list of aliases.
     *
     * @return string[]
     */
    public function aliases(): array
    {
        return array_keys($this->algorithms);
    }

    /**
     * Returns all algorithms supported by this factory. This is an associative array. Keys are the aliases of the
     * algorithms.
     *
     * @return Algorithm[]
     */
    public function all(): array
    {
        return $this->algorithms;
    }

    /**
     * Create an algorithm manager using the given aliases.
     *
     * @param string[] $aliases
     */
    public function create(array $aliases): AlgorithmManager
    {
        $algorithms = [];
        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                throw new InvalidArgumentException('Invalid alias');
            }
            if (! isset($this->algorithms[$alias])) {
                throw new InvalidArgumentException(sprintf(
                    'The algorithm with the alias "%s" is not supported.',
                    $alias
                ));
            }
            $algorithms[] = $this->algorithms[$alias];
        }

        return new AlgorithmManager($algorithms);
    }
}
