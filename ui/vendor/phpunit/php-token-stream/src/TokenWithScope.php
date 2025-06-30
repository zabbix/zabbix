<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class PHP_TokenWithScope extends PHP_Token
{
    /**
     * @var int
     */
    protected $endTokenId;

    /**
     * Get the docblock for this token.
     *
     * This method will fetch the docblock belonging to the current token. The
     * docblock must be placed on the line directly above the token to be
     * recognized.
     *
     * @return null|string Returns the docblock as a string if found
     */
    public function getDocblock()
    {
        $tokens            = $this->tokenStream->tokens();
        $currentLineNumber = $tokens[$this->id]->getLine();
        $prevLineNumber    = $currentLineNumber - 1;

        for ($i = $this->id - 1; $i; $i--) {
            if (!isset($tokens[$i])) {
                return;
            }

            if ($tokens[$i] instanceof PHP_Token_FUNCTION ||
                $tokens[$i] instanceof PHP_Token_CLASS ||
                $tokens[$i] instanceof PHP_Token_TRAIT) {
                // Some other trait, class or function, no docblock can be
                // used for the current token
                break;
            }

            $line = $tokens[$i]->getLine();

            if ($line == $currentLineNumber ||
                ($line == $prevLineNumber &&
                    $tokens[$i] instanceof PHP_Token_WHITESPACE)) {
                continue;
            }

            if ($line < $currentLineNumber &&
                !$tokens[$i] instanceof PHP_Token_DOC_COMMENT) {
                break;
            }

            return (string) $tokens[$i];
        }
    }

    /**
     * @return int
     */
    public function getEndTokenId()
    {
        $block  = 0;
        $i      = $this->id;
        $tokens = $this->tokenStream->tokens();

        while ($this->endTokenId === null && isset($tokens[$i])) {
            if ($tokens[$i] instanceof PHP_Token_OPEN_CURLY ||
                $tokens[$i] instanceof PHP_Token_DOLLAR_OPEN_CURLY_BRACES ||
                $tokens[$i] instanceof PHP_Token_CURLY_OPEN) {
                $block++;
            } elseif ($tokens[$i] instanceof PHP_Token_CLOSE_CURLY) {
                $block--;

                if ($block === 0) {
                    $this->endTokenId = $i;
                }
            } elseif (($this instanceof PHP_Token_FUNCTION ||
                    $this instanceof PHP_Token_NAMESPACE) &&
                $tokens[$i] instanceof PHP_Token_SEMICOLON) {
                if ($block === 0) {
                    $this->endTokenId = $i;
                }
            }

            $i++;
        }

        if ($this->endTokenId === null) {
            $this->endTokenId = $this->id;
        }

        return $this->endTokenId;
    }

    /**
     * @return int
     */
    public function getEndLine()
    {
        return $this->tokenStream[$this->getEndTokenId()]->getLine();
    }
}
