<?php

namespace Facebook\WebDriver\Local;

use Facebook\WebDriver\Exception\Internal\LogicException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

/**
 * @codeCoverageIgnore
 * @todo Break inheritance from RemoteWebDriver in next major version. (Composition over inheritance!)
 */
abstract class LocalWebDriver extends RemoteWebDriver
{
    // @todo Remove in next major version (should not be inherited)
    public static function create(
        $selenium_server_url = 'http://localhost:4444/wd/hub',
        $desired_capabilities = null,
        $connection_timeout_in_ms = null,
        $request_timeout_in_ms = null,
        $http_proxy = null,
        $http_proxy_port = null,
        ?DesiredCapabilities $required_capabilities = null
    ) {
        throw LogicException::forError('Use start() method to start local WebDriver.');
    }

    // @todo Remove in next major version (should not be inherited)
    public static function createBySessionID(
        $session_id,
        $selenium_server_url = 'http://localhost:4444/wd/hub',
        $connection_timeout_in_ms = null,
        $request_timeout_in_ms = null
    ) {
        throw LogicException::forError('Use start() method to start local WebDriver.');
    }
}
