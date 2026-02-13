<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use InvalidArgumentException;
use function sprintf;

/**
 * This class is a factory to create Header Checker Managers. It allows to add header parameter checkers and token type
 * supports. The factory is responsible to create a Header Checker Manager with the header parameter checkers found based
 * on the alias. If the alias is not supported, an InvalidArgumentException is thrown.
 * @see \Jose\Tests\Component\Checker\HeaderCheckerManagerFactoryTest
 */
class HeaderCheckerManagerFactory
{
    /**
     * @var HeaderChecker[]
     */
    private array $checkers = [];

    /**
     * @var TokenTypeSupport[]
     */
    private array $tokenTypes = [];

    /**
     * This method creates a Header Checker Manager and populate it with the header parameter checkers found based on
     * the alias. If the alias is not supported, an InvalidArgumentException is thrown.
     *
     * @param string[] $aliases
     */
    public function create(array $aliases): HeaderCheckerManager
    {
        $checkers = [];
        foreach ($aliases as $alias) {
            if (! isset($this->checkers[$alias])) {
                throw new InvalidArgumentException(sprintf(
                    'The header checker with the alias "%s" is not supported.',
                    $alias
                ));
            }
            $checkers[] = $this->checkers[$alias];
        }

        return new HeaderCheckerManager($checkers, $this->tokenTypes);
    }

    /**
     * This method adds a header parameter checker to this factory. The checker is uniquely identified by an alias. This
     * allows the same header parameter checker to be added twice (or more) using several configuration options.
     */
    public function add(string $alias, HeaderChecker $checker): void
    {
        $this->checkers[$alias] = $checker;
    }

    /**
     * This method adds a token type support to this factory.
     */
    public function addTokenTypeSupport(TokenTypeSupport $tokenType): void
    {
        $this->tokenTypes[] = $tokenType;
    }

    /**
     * Returns all header parameter checker aliases supported by this factory.
     *
     * @return string[]
     */
    public function aliases(): array
    {
        return array_keys($this->checkers);
    }

    /**
     * Returns all header parameter checkers supported by this factory.
     *
     * @return HeaderChecker[]
     */
    public function all(): array
    {
        return $this->checkers;
    }
}
