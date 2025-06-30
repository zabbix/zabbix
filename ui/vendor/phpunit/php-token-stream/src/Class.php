<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class PHP_Token_CLASS extends PHP_Token_INTERFACE
{
    /**
     * @var bool
     */
    private $anonymous = false;

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->name !== null) {
            return $this->name;
        }

        $next = $this->tokenStream[$this->id + 1];

        if ($next instanceof PHP_Token_WHITESPACE) {
            $next = $this->tokenStream[$this->id + 2];
        }

        if ($next instanceof PHP_Token_STRING) {
            $this->name =(string) $next;

            return $this->name;
        }

        if ($next instanceof PHP_Token_OPEN_CURLY ||
            $next instanceof PHP_Token_EXTENDS ||
            $next instanceof PHP_Token_IMPLEMENTS) {
            $this->name = \sprintf(
                'AnonymousClass:%s#%s',
                $this->getLine(),
                $this->getId()
            );

            $this->anonymous = true;

            return $this->name;
        }
    }

    public function isAnonymous()
    {
        return $this->anonymous;
    }
}
