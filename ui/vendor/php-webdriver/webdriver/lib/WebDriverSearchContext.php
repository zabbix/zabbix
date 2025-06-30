<?php

namespace Facebook\WebDriver;

use Facebook\WebDriver\Exception\NoSuchElementException;

/**
 * The interface for WebDriver and WebDriverElement which is able to search for WebDriverElement inside.
 */
interface WebDriverSearchContext
{
    /**
     * Find the first WebDriverElement within this element using the given mechanism.
     *
     * @throws NoSuchElementException If no element is found
     * @return WebDriverElement
     * @see WebDriverBy
     */
    public function findElement(WebDriverBy $locator);

    /**
     * Find all WebDriverElements within this element using the given mechanism.
     *
     * @return WebDriverElement[] A list of all WebDriverElements, or an empty array if nothing matches
     * @see WebDriverBy
     */
    public function findElements(WebDriverBy $locator);
}
