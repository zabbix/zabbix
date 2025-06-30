<?php

namespace Facebook\WebDriver\Remote;

/**
 * WebDriverCapabilityType contains all constants defined in the WebDriver Wire Protocol.
 *
 * @codeCoverageIgnore
 */
class WebDriverCapabilityType
{
    public const BROWSER_NAME = 'browserName';
    public const VERSION = 'version';
    public const PLATFORM = 'platform';
    public const JAVASCRIPT_ENABLED = 'javascriptEnabled';
    public const TAKES_SCREENSHOT = 'takesScreenshot';
    public const HANDLES_ALERTS = 'handlesAlerts';
    public const DATABASE_ENABLED = 'databaseEnabled';
    public const LOCATION_CONTEXT_ENABLED = 'locationContextEnabled';
    public const APPLICATION_CACHE_ENABLED = 'applicationCacheEnabled';
    public const BROWSER_CONNECTION_ENABLED = 'browserConnectionEnabled';
    public const CSS_SELECTORS_ENABLED = 'cssSelectorsEnabled';
    public const WEB_STORAGE_ENABLED = 'webStorageEnabled';
    public const ROTATABLE = 'rotatable';
    public const ACCEPT_SSL_CERTS = 'acceptSslCerts';
    public const NATIVE_EVENTS = 'nativeEvents';
    public const PROXY = 'proxy';

    private function __construct()
    {
    }
}
