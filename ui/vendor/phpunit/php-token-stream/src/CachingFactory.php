<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class PHP_Token_Stream_CachingFactory
{
    /**
     * @var array
     */
    protected static $cache = [];

    /**
     * @param string $filename
     *
     * @return PHP_Token_Stream
     */
    public static function get($filename)
    {
        if (!isset(self::$cache[$filename])) {
            self::$cache[$filename] = new PHP_Token_Stream($filename);
        }

        return self::$cache[$filename];
    }

    /**
     * @param string $filename
     */
    public static function clear($filename = null)/*: void*/
    {
        if (\is_string($filename)) {
            unset(self::$cache[$filename]);
        } else {
            self::$cache = [];
        }
    }
}
