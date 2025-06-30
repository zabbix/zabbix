<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class PHP_Token_NAMESPACE extends PHP_TokenWithScope
{
    /**
     * @return string
     */
    public function getName()
    {
        $tokens    = $this->tokenStream->tokens();
        $namespace = (string) $tokens[$this->id + 2];

        for ($i = $this->id + 3;; $i += 2) {
            if (isset($tokens[$i]) &&
                $tokens[$i] instanceof PHP_Token_NS_SEPARATOR) {
                $namespace .= '\\' . $tokens[$i + 1];
            } else {
                break;
            }
        }

        return $namespace;
    }
}
