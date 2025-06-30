<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class PHP_Token
{
    /**
     * @var string
     */
    protected $text;

    /**
     * @var int
     */
    protected $line;

    /**
     * @var PHP_Token_Stream
     */
    protected $tokenStream;

    /**
     * @var int
     */
    protected $id;

    /**
     * @param string $text
     * @param int    $line
     * @param int    $id
     */
    public function __construct($text, $line, PHP_Token_Stream $tokenStream, $id)
    {
        $this->text        = $text;
        $this->line        = $line;
        $this->tokenStream = $tokenStream;
        $this->id          = $id;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->text;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
