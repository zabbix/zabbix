<?php

namespace Facebook\WebDriver;

/**
 * The platforms supported by WebDriver.
 *
 * @codeCoverageIgnore
 */
class WebDriverPlatform
{
    public const ANDROID = 'ANDROID';
    /** @deprecated ANY has no meaning in W3C WebDriver, see https://github.com/php-webdriver/php-webdriver/pull/731 */
    public const ANY = 'ANY';
    public const LINUX = 'LINUX';
    public const MAC = 'MAC';
    public const UNIX = 'UNIX';
    public const VISTA = 'VISTA';
    public const WINDOWS = 'WINDOWS';
    public const XP = 'XP';

    private function __construct()
    {
    }
}
