<?php

namespace Facebook\WebDriver\Firefox;

/**
 * Constants of common Firefox profile preferences (about:config values).
 * @see http://kb.mozillazine.org/Firefox_:_FAQs_:_About:config_Entries
 *
 * @codeCoverageIgnore
 */
class FirefoxPreferences
{
    /** @var string Port WebDriver uses to communicate with Firefox instance */
    public const WEBDRIVER_FIREFOX_PORT = 'webdriver_firefox_port';
    /** @var string Should the reader view (FF 38+) be enabled? */
    public const READER_PARSE_ON_LOAD_ENABLED = 'reader.parse-on-load.enabled';
    /** @var string Browser homepage */
    public const BROWSER_STARTUP_HOMEPAGE = 'browser.startup.homepage';
    /** @var string Should the Devtools JSON view be enabled? */
    public const DEVTOOLS_JSONVIEW = 'devtools.jsonview.enabled';

    private function __construct()
    {
    }
}
