<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class PHP_Token_FUNCTION extends PHP_TokenWithScopeAndVisibility
{
    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var int
     */
    protected $ccn;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $signature;

    /**
     * @var bool
     */
    private $anonymous = false;

    /**
     * @return array
     */
    public function getArguments()
    {
        if ($this->arguments !== null) {
            return $this->arguments;
        }

        $this->arguments = [];
        $tokens          = $this->tokenStream->tokens();
        $typeDeclaration = null;

        // Search for first token inside brackets
        $i = $this->id + 2;

        while (!$tokens[$i - 1] instanceof PHP_Token_OPEN_BRACKET) {
            $i++;
        }

        while (!$tokens[$i] instanceof PHP_Token_CLOSE_BRACKET) {
            if ($tokens[$i] instanceof PHP_Token_STRING) {
                $typeDeclaration = (string) $tokens[$i];
            } elseif ($tokens[$i] instanceof PHP_Token_VARIABLE) {
                $this->arguments[(string) $tokens[$i]] = $typeDeclaration;
                $typeDeclaration                       = null;
            }

            $i++;
        }

        return $this->arguments;
    }

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->name !== null) {
            return $this->name;
        }

        $tokens = $this->tokenStream->tokens();

        $i = $this->id + 1;

        if ($tokens[$i] instanceof PHP_Token_WHITESPACE) {
            $i++;
        }

        if ($tokens[$i] instanceof PHP_Token_AMPERSAND) {
            $i++;
        }

        if ($tokens[$i + 1] instanceof PHP_Token_OPEN_BRACKET) {
            $this->name = (string) $tokens[$i];
        } elseif ($tokens[$i + 1] instanceof PHP_Token_WHITESPACE && $tokens[$i + 2] instanceof PHP_Token_OPEN_BRACKET) {
            $this->name = (string) $tokens[$i];
        } else {
            $this->anonymous = true;

            $this->name = \sprintf(
                'anonymousFunction:%s#%s',
                $this->getLine(),
                $this->getId()
            );
        }

        if (!$this->isAnonymous()) {
            for ($i = $this->id; $i; --$i) {
                if ($tokens[$i] instanceof PHP_Token_NAMESPACE) {
                    $this->name = $tokens[$i]->getName() . '\\' . $this->name;

                    break;
                }

                if ($tokens[$i] instanceof PHP_Token_INTERFACE) {
                    break;
                }
            }
        }

        return $this->name;
    }

    /**
     * @return int
     */
    public function getCCN()
    {
        if ($this->ccn !== null) {
            return $this->ccn;
        }

        $this->ccn = 1;
        $end       = $this->getEndTokenId();
        $tokens    = $this->tokenStream->tokens();

        for ($i = $this->id; $i <= $end; $i++) {
            switch (\get_class($tokens[$i])) {
                case PHP_Token_IF::class:
                case PHP_Token_ELSEIF::class:
                case PHP_Token_FOR::class:
                case PHP_Token_FOREACH::class:
                case PHP_Token_WHILE::class:
                case PHP_Token_CASE::class:
                case PHP_Token_CATCH::class:
                case PHP_Token_BOOLEAN_AND::class:
                case PHP_Token_LOGICAL_AND::class:
                case PHP_Token_BOOLEAN_OR::class:
                case PHP_Token_LOGICAL_OR::class:
                case PHP_Token_QUESTION_MARK::class:
                    $this->ccn++;

                    break;
            }
        }

        return $this->ccn;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        if ($this->signature !== null) {
            return $this->signature;
        }

        if ($this->isAnonymous()) {
            $this->signature = 'anonymousFunction';
            $i               = $this->id + 1;
        } else {
            $this->signature = '';
            $i               = $this->id + 2;
        }

        $tokens = $this->tokenStream->tokens();

        while (isset($tokens[$i]) &&
            !$tokens[$i] instanceof PHP_Token_OPEN_CURLY &&
            !$tokens[$i] instanceof PHP_Token_SEMICOLON) {
            $this->signature .= $tokens[$i++];
        }

        $this->signature = \trim($this->signature);

        return $this->signature;
    }

    /**
     * @return bool
     */
    public function isAnonymous()
    {
        return $this->anonymous;
    }
}
