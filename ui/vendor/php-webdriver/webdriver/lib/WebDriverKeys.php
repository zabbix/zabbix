<?php

namespace Facebook\WebDriver;

/**
 * Representations of pressable keys that aren't text.
 * These are stored in the Unicode PUA (Private Use Area) code points.
 * @see https://w3c.github.io/webdriver/#keyboard-actions
 */
class WebDriverKeys
{
    public const NULL = "\xEE\x80\x80";
    public const CANCEL = "\xEE\x80\x81";
    public const HELP = "\xEE\x80\x82";
    public const BACKSPACE = "\xEE\x80\x83";
    public const TAB = "\xEE\x80\x84";
    public const CLEAR = "\xEE\x80\x85";
    public const RETURN_KEY = "\xEE\x80\x86";
    public const ENTER = "\xEE\x80\x87";
    public const SHIFT = "\xEE\x80\x88";
    public const CONTROL = "\xEE\x80\x89";
    public const ALT = "\xEE\x80\x8A";
    public const PAUSE = "\xEE\x80\x8B";
    public const ESCAPE = "\xEE\x80\x8C";
    public const SPACE = "\xEE\x80\x8D";
    public const PAGE_UP = "\xEE\x80\x8E";
    public const PAGE_DOWN = "\xEE\x80\x8F";
    public const END = "\xEE\x80\x90";
    public const HOME = "\xEE\x80\x91";
    public const ARROW_LEFT = "\xEE\x80\x92";
    public const ARROW_UP = "\xEE\x80\x93";
    public const ARROW_RIGHT = "\xEE\x80\x94";
    public const ARROW_DOWN = "\xEE\x80\x95";
    public const INSERT = "\xEE\x80\x96";
    public const DELETE = "\xEE\x80\x97";
    public const SEMICOLON = "\xEE\x80\x98";
    public const EQUALS = "\xEE\x80\x99";
    public const NUMPAD0 = "\xEE\x80\x9A";
    public const NUMPAD1 = "\xEE\x80\x9B";
    public const NUMPAD2 = "\xEE\x80\x9C";
    public const NUMPAD3 = "\xEE\x80\x9D";
    public const NUMPAD4 = "\xEE\x80\x9E";
    public const NUMPAD5 = "\xEE\x80\x9F";
    public const NUMPAD6 = "\xEE\x80\xA0";
    public const NUMPAD7 = "\xEE\x80\xA1";
    public const NUMPAD8 = "\xEE\x80\xA2";
    public const NUMPAD9 = "\xEE\x80\xA3";
    public const MULTIPLY = "\xEE\x80\xA4";
    public const ADD = "\xEE\x80\xA5";
    public const SEPARATOR = "\xEE\x80\xA6";
    public const SUBTRACT = "\xEE\x80\xA7";
    public const DECIMAL = "\xEE\x80\xA8";
    public const DIVIDE = "\xEE\x80\xA9";
    public const F1 = "\xEE\x80\xB1";
    public const F2 = "\xEE\x80\xB2";
    public const F3 = "\xEE\x80\xB3";
    public const F4 = "\xEE\x80\xB4";
    public const F5 = "\xEE\x80\xB5";
    public const F6 = "\xEE\x80\xB6";
    public const F7 = "\xEE\x80\xB7";
    public const F8 = "\xEE\x80\xB8";
    public const F9 = "\xEE\x80\xB9";
    public const F10 = "\xEE\x80\xBA";
    public const F11 = "\xEE\x80\xBB";
    public const F12 = "\xEE\x80\xBC";
    public const META = "\xEE\x80\xBD";
    public const ZENKAKU_HANKAKU = "\xEE\x80\xC0";
    public const RIGHT_SHIFT = "\xEE\x81\x90";
    public const RIGHT_CONTROL = "\xEE\x81\x91";
    public const RIGHT_ALT = "\xEE\x81\x92";
    public const RIGHT_META = "\xEE\x81\x93";
    public const NUMPAD_PAGE_UP = "\xEE\x81\x94";
    public const NUMPAD_PAGE_DOWN = "\xEE\x81\x95";
    public const NUMPAD_END = "\xEE\x81\x96";
    public const NUMPAD_HOME = "\xEE\x81\x97";
    public const NUMPAD_ARROW_LEFT = "\xEE\x81\x98";
    public const NUMPAD_ARROW_UP = "\xEE\x81\x99";
    public const NUMPAD_ARROW_RIGHT = "\xEE\x81\x9A";
    public const NUMPAD_ARROW_DOWN = "\xEE\x81\x9B";
    public const NUMPAD_ARROW_INSERT = "\xEE\x81\x9C";
    public const NUMPAD_ARROW_DELETE = "\xEE\x81\x9D";
    // Aliases
    public const LEFT_SHIFT = self::SHIFT;
    public const LEFT_CONTROL = self::CONTROL;
    public const LEFT_ALT = self::ALT;
    public const LEFT = self::ARROW_LEFT;
    public const UP = self::ARROW_UP;
    public const RIGHT = self::ARROW_RIGHT;
    public const DOWN = self::ARROW_DOWN;
    public const COMMAND = self::META;

    /**
     * Encode input of `sendKeys()` to appropriate format according to protocol.
     *
     * @param string|array|int|float $keys
     * @param bool $isW3cCompliant
     * @return array|string
     */
    public static function encode($keys, $isW3cCompliant = false)
    {
        if (is_numeric($keys)) {
            $keys = (string) $keys;
        }

        if (is_string($keys)) {
            $keys = [$keys];
        }

        if (!is_array($keys)) {
            if (!$isW3cCompliant) {
                return [];
            }

            return '';
        }

        $encoded = [];
        foreach ($keys as $key) {
            if (is_array($key)) {
                // handle key modifiers
                $key = implode('', $key) . self::NULL; // the NULL clears the input state (eg. previous modifiers)
            }
            $encoded[] = (string) $key;
        }

        if (!$isW3cCompliant) {
            return $encoded;
        }

        return implode('', $encoded);
    }
}
