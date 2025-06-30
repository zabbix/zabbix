<?php

namespace Facebook\WebDriver;

use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\Support\Events\EventFiringWebDriver;
use Facebook\WebDriver\Support\Events\EventFiringWebElement;

interface WebDriverEventListener
{
    /**
     * @param string $url
     */
    public function beforeNavigateTo($url, EventFiringWebDriver $driver);

    /**
     * @param string $url
     */
    public function afterNavigateTo($url, EventFiringWebDriver $driver);

    public function beforeNavigateBack(EventFiringWebDriver $driver);

    public function afterNavigateBack(EventFiringWebDriver $driver);

    public function beforeNavigateForward(EventFiringWebDriver $driver);

    public function afterNavigateForward(EventFiringWebDriver $driver);

    public function beforeFindBy(WebDriverBy $by, $element, EventFiringWebDriver $driver);

    public function afterFindBy(WebDriverBy $by, $element, EventFiringWebDriver $driver);

    /**
     * @param string $script
     */
    public function beforeScript($script, EventFiringWebDriver $driver);

    /**
     * @param string $script
     */
    public function afterScript($script, EventFiringWebDriver $driver);

    public function beforeClickOn(EventFiringWebElement $element);

    public function afterClickOn(EventFiringWebElement $element);

    public function beforeChangeValueOf(EventFiringWebElement $element);

    public function afterChangeValueOf(EventFiringWebElement $element);

    public function onException(WebDriverException $exception, ?EventFiringWebDriver $driver = null);
}
