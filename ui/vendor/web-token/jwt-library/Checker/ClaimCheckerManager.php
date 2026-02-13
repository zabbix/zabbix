<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use function array_key_exists;
use function count;
use function sprintf;

/**
 * This class manages claim checkers and performs claim checks.
 * @see \Jose\Tests\Component\Checker\ClaimCheckerManagerTest
 */
class ClaimCheckerManager
{
    /**
     * @var ClaimChecker[]
     */
    private array $checkers = [];

    /**
     * @param ClaimChecker[] $checkers
     */
    public function __construct(iterable $checkers)
    {
        foreach ($checkers as $checker) {
            $this->add($checker);
        }
    }

    /**
     * This method returns all checkers handled by this manager.
     *
     * @return ClaimChecker[]
     */
    public function getCheckers(): array
    {
        return $this->checkers;
    }

    /**
     * This method checks all the claims passed as argument. All claims are checked against the claim checkers. If one
     * fails, the InvalidClaimException is thrown.
     *
     * This method returns an array with all checked claims. It is up to the implementor to decide use the claims that
     * have not been checked.
     *
     * @param string[] $mandatoryClaims
     */
    public function check(array $claims, array $mandatoryClaims = []): array
    {
        $this->checkMandatoryClaims($mandatoryClaims, $claims);
        $checkedClaims = [];
        foreach ($this->checkers as $claim => $checker) {
            if (array_key_exists($claim, $claims)) {
                $checker->checkClaim($claims[$claim]);
                $checkedClaims[$claim] = $claims[$claim];
            }
        }

        return $checkedClaims;
    }

    private function add(ClaimChecker $checker): void
    {
        $claim = $checker->supportedClaim();
        $this->checkers[$claim] = $checker;
    }

    /**
     * @param string[] $mandatoryClaims
     */
    private function checkMandatoryClaims(array $mandatoryClaims, array $claims): void
    {
        if (count($mandatoryClaims) === 0) {
            return;
        }
        $diff = array_keys(array_diff_key(array_flip($mandatoryClaims), $claims));
        if (count($diff) !== 0) {
            throw new MissingMandatoryClaimException(sprintf(
                'The following claims are mandatory: %s.',
                implode(', ', $diff)
            ), $diff);
        }
    }
}
