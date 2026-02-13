<?php

declare(strict_types=1);

namespace Jose\Component\Checker;

use InvalidArgumentException;
use Jose\Component\Core\JWT;
use function array_key_exists;
use function count;
use function is_array;
use function sprintf;

/**
 * This class is a factory to create Header Checker Managers.
 *
 * It allows to add header parameter checkers and token type supports.
 * The factory is responsible to create a Header Checker Manager with the header parameter checkers found based
 */
class HeaderCheckerManager
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
     * @param HeaderChecker[] $checkers
     * @param TokenTypeSupport[] $tokenTypes
     */
    public function __construct(iterable $checkers, iterable $tokenTypes)
    {
        foreach ($checkers as $checker) {
            $this->add($checker);
        }
        foreach ($tokenTypes as $tokenType) {
            $this->addTokenTypeSupport($tokenType);
        }
    }

    /**
     * This method returns all checkers handled by this manager.
     *
     * @return HeaderChecker[]
     */
    public function getCheckers(): array
    {
        return $this->checkers;
    }

    /**
     * This method checks all the header parameters passed as argument. All header parameters are checked against the
     * header parameter checkers. If one fails, the InvalidHeaderException is thrown.
     *
     * @param string[] $mandatoryHeaderParameters
     */
    public function check(JWT $jwt, int $index, array $mandatoryHeaderParameters = []): void
    {
        foreach ($this->tokenTypes as $tokenType) {
            if ($tokenType->supports($jwt)) {
                $protected = [];
                $unprotected = [];
                $tokenType->retrieveTokenHeaders($jwt, $index, $protected, $unprotected);
                $this->checkDuplicatedHeaderParameters($protected, $unprotected);
                $this->checkMandatoryHeaderParameters($mandatoryHeaderParameters, $protected, $unprotected);
                $this->checkHeaders($protected, $unprotected);

                return;
            }
        }

        throw new InvalidArgumentException('Unsupported token type.');
    }

    private function addTokenTypeSupport(TokenTypeSupport $tokenType): void
    {
        $this->tokenTypes[] = $tokenType;
    }

    private function add(HeaderChecker $checker): void
    {
        $header = $checker->supportedHeader();
        $this->checkers[$header] = $checker;
    }

    private function checkDuplicatedHeaderParameters(array $header1, array $header2): void
    {
        $inter = array_intersect_key($header1, $header2);
        if (count($inter) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'The header contains duplicated entries: %s.',
                implode(', ', array_keys($inter))
            ));
        }
    }

    /**
     * @param string[] $mandatoryHeaderParameters
     */
    private function checkMandatoryHeaderParameters(
        array $mandatoryHeaderParameters,
        array $protected,
        array $unprotected
    ): void {
        if (count($mandatoryHeaderParameters) === 0) {
            return;
        }
        $diff = array_keys(
            array_diff_key(array_flip($mandatoryHeaderParameters), array_merge($protected, $unprotected))
        );
        if (count($diff) !== 0) {
            throw new MissingMandatoryHeaderParameterException(sprintf(
                'The following header parameters are mandatory: %s.',
                implode(', ', $diff)
            ), $diff);
        }
    }

    private function checkHeaders(array $protected, array $header): void
    {
        $checkedHeaderParameters = [];
        foreach ($this->checkers as $headerParameter => $checker) {
            if ($checker->protectedHeaderOnly()) {
                if (array_key_exists($headerParameter, $protected)) {
                    $checker->checkHeader($protected[$headerParameter]);
                    $checkedHeaderParameters[] = $headerParameter;
                } elseif (array_key_exists($headerParameter, $header)) {
                    throw new InvalidHeaderException(sprintf(
                        'The header parameter "%s" must be protected.',
                        $headerParameter
                    ), $headerParameter, $header[$headerParameter]);
                }
            } else {
                if (array_key_exists($headerParameter, $protected)) {
                    $checker->checkHeader($protected[$headerParameter]);
                    $checkedHeaderParameters[] = $headerParameter;
                } elseif (array_key_exists($headerParameter, $header)) {
                    $checker->checkHeader($header[$headerParameter]);
                    $checkedHeaderParameters[] = $headerParameter;
                }
            }
        }
        $this->checkCriticalHeader($protected, $header, $checkedHeaderParameters);
    }

    private function checkCriticalHeader(array $protected, array $header, array $checkedHeaderParameters): void
    {
        if (array_key_exists('crit', $protected)) {
            if (! is_array($protected['crit'])) {
                throw new InvalidHeaderException(
                    'The header "crit" must be a list of header parameters.',
                    'crit',
                    $protected['crit']
                );
            }
            $diff = array_diff($protected['crit'], $checkedHeaderParameters);
            if (count($diff) !== 0) {
                throw new InvalidHeaderException(sprintf(
                    'One or more header parameters are marked as critical, but they are missing or have not been checked: %s.',
                    implode(', ', array_values($diff))
                ), 'crit', $protected['crit']);
            }
        } elseif (array_key_exists('crit', $header)) {
            throw new InvalidHeaderException('The header parameter "crit" must be protected.', 'crit', $header['crit']);
        }
    }
}
