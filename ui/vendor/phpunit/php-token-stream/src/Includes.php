<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class PHP_Token_Includes extends PHP_Token
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->name === null) {
            $this->process();
        }

        return $this->name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        if ($this->type === null) {
            $this->process();
        }

        return $this->type;
    }

    private function process(): void
    {
        $tokens = $this->tokenStream->tokens();

        if ($tokens[$this->id + 2] instanceof PHP_Token_CONSTANT_ENCAPSED_STRING) {
            $this->name = \trim((string) $tokens[$this->id + 2], "'\"");
            $this->type = \strtolower(
                \str_replace('PHP_Token_', '', PHP_Token_Util::getClass($tokens[$this->id]))
            );
        }
    }
}
