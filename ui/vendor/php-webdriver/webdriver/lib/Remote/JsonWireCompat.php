<?php

namespace Facebook\WebDriver\Remote;

use Facebook\WebDriver\Exception\Internal\UnexpectedResponseException;
use Facebook\WebDriver\WebDriverBy;

/**
 * Compatibility layer between W3C's WebDriver and the legacy JsonWire protocol.
 *
 * @internal
 */
abstract class JsonWireCompat
{
    /**
     * Element identifier defined in the W3C's WebDriver protocol.
     *
     * @see https://w3c.github.io/webdriver/#elements
     */
    public const WEB_DRIVER_ELEMENT_IDENTIFIER = 'element-6066-11e4-a52e-4f735466cecf';

    /**
     * @param mixed $rawElement Value is validated to by an array, exception is thrown otherwise
     * @throws UnexpectedResponseException When value of other type than array is given
     */
    public static function getElement($rawElement)
    {
        // The method intentionally accept mixed, so that assertion of the rawElement format could be done on one place
        if (!is_array($rawElement)) {
            throw UnexpectedResponseException::forElementNotArray($rawElement);
        }

        if (array_key_exists(self::WEB_DRIVER_ELEMENT_IDENTIFIER, $rawElement)) {
            // W3C's WebDriver
            return $rawElement[self::WEB_DRIVER_ELEMENT_IDENTIFIER];
        }

        // Legacy JsonWire
        return $rawElement['ELEMENT'];
    }

    /**
     * @param bool $isW3cCompliant
     *
     * @return array
     */
    public static function getUsing(WebDriverBy $by, $isW3cCompliant)
    {
        $mechanism = $by->getMechanism();
        $value = $by->getValue();

        if ($isW3cCompliant) {
            switch ($mechanism) {
                // Convert to CSS selectors
                case 'class name':
                    $mechanism = 'css selector';
                    $value = sprintf('.%s', self::escapeSelector($value));
                    break;
                case 'id':
                    $mechanism = 'css selector';
                    $value = sprintf('#%s', self::escapeSelector($value));
                    break;
                case 'name':
                    $mechanism = 'css selector';
                    $value = sprintf('[name=\'%s\']', self::escapeSelector($value));
                    break;
            }
        }

        return ['using' => $mechanism, 'value' => $value];
    }

    /**
     * Escapes a CSS selector.
     *
     * Code adapted from the Zend Escaper project.
     *
     * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
     * @see https://github.com/zendframework/zend-escaper/blob/master/src/Escaper.php
     *
     * @param string $selector
     * @return string
     */
    private static function escapeSelector($selector)
    {
        return preg_replace_callback('/[^a-z0-9]/iSu', function ($matches) {
            $chr = $matches[0];
            if (mb_strlen($chr) === 1) {
                $ord = ord($chr);
            } else {
                $chr = mb_convert_encoding($chr, 'UTF-32BE', 'UTF-8');
                $ord = hexdec(bin2hex($chr));
            }

            return sprintf('\\%X ', $ord);
        }, $selector);
    }
}
