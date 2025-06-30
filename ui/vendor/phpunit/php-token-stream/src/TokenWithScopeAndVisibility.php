<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class PHP_TokenWithScopeAndVisibility extends PHP_TokenWithScope
{
    /**
     * @return string
     */
    public function getVisibility()
    {
        $tokens = $this->tokenStream->tokens();

        for ($i = $this->id - 2; $i > $this->id - 7; $i -= 2) {
            if (isset($tokens[$i]) &&
                ($tokens[$i] instanceof PHP_Token_PRIVATE ||
                    $tokens[$i] instanceof PHP_Token_PROTECTED ||
                    $tokens[$i] instanceof PHP_Token_PUBLIC)) {
                return \strtolower(
                    \str_replace('PHP_Token_', '', PHP_Token_Util::getClass($tokens[$i]))
                );
            }

            if (isset($tokens[$i]) &&
                !($tokens[$i] instanceof PHP_Token_STATIC ||
                    $tokens[$i] instanceof PHP_Token_FINAL ||
                    $tokens[$i] instanceof PHP_Token_ABSTRACT)) {
                // no keywords; stop visibility search
                break;
            }
        }
    }

    /**
     * @return string
     */
    public function getKeywords()
    {
        $keywords = [];
        $tokens   = $this->tokenStream->tokens();

        for ($i = $this->id - 2; $i > $this->id - 7; $i -= 2) {
            if (isset($tokens[$i]) &&
                ($tokens[$i] instanceof PHP_Token_PRIVATE ||
                    $tokens[$i] instanceof PHP_Token_PROTECTED ||
                    $tokens[$i] instanceof PHP_Token_PUBLIC)) {
                continue;
            }

            if (isset($tokens[$i]) &&
                ($tokens[$i] instanceof PHP_Token_STATIC ||
                    $tokens[$i] instanceof PHP_Token_FINAL ||
                    $tokens[$i] instanceof PHP_Token_ABSTRACT)) {
                $keywords[] = \strtolower(
                    \str_replace('PHP_Token_', '', PHP_Token_Util::getClass($tokens[$i]))
                );
            }
        }

        return \implode(',', $keywords);
    }
}
