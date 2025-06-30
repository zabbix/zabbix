<?php

namespace Facebook\WebDriver;

use Facebook\WebDriver\Interactions\Internal\WebDriverCoordinates;

/**
 * Interface representing basic mouse operations.
 */
interface WebDriverMouse
{
    /**
     * @return WebDriverMouse
     */
    public function click(WebDriverCoordinates $where);

    /**
     * @return WebDriverMouse
     */
    public function contextClick(WebDriverCoordinates $where);

    /**
     * @return WebDriverMouse
     */
    public function doubleClick(WebDriverCoordinates $where);

    /**
     * @return WebDriverMouse
     */
    public function mouseDown(WebDriverCoordinates $where);

    /**
     * @param int $x_offset
     * @param int $y_offset
     * @return WebDriverMouse
     */
    public function mouseMove(
        WebDriverCoordinates $where,
        $x_offset = null,
        $y_offset = null
    );

    /**
     * @return WebDriverMouse
     */
    public function mouseUp(WebDriverCoordinates $where);
}
