<?php

namespace Facebook\WebDriver\Remote;

/**
 * All the browsers supported by selenium.
 *
 * @codeCoverageIgnore
 */
class WebDriverBrowserType
{
    public const FIREFOX = 'firefox';
    public const FIREFOX_PROXY = 'firefoxproxy';
    public const FIREFOX_CHROME = 'firefoxchrome';
    public const GOOGLECHROME = 'googlechrome';
    public const SAFARI = 'safari';
    public const SAFARI_PROXY = 'safariproxy';
    public const OPERA = 'opera';
    public const MICROSOFT_EDGE = 'MicrosoftEdge';
    public const IEXPLORE = 'iexplore';
    public const IEXPLORE_PROXY = 'iexploreproxy';
    public const CHROME = 'chrome';
    public const KONQUEROR = 'konqueror';
    public const MOCK = 'mock';
    public const IE_HTA = 'iehta';
    public const ANDROID = 'android';
    public const HTMLUNIT = 'htmlunit';
    public const IE = 'internet explorer';
    public const IPHONE = 'iphone';
    public const IPAD = 'iPad';
    /**
     * @deprecated PhantomJS is no longer developed and its support will be removed in next major version.
     * Use headless Chrome or Firefox instead.
     */
    public const PHANTOMJS = 'phantomjs';

    private function __construct()
    {
    }
}
