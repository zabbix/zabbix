<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Exception\StaleElementReferenceException;

/**
 * Base class for web page elements.
 */
abstract class CBaseElement extends RemoteWebElement {

	/**
	 * Option that allows to disable auto reload of staled elements.
	 *
	 * @var boolead
	 */
	protected $reload_staled = true;

	/**
	 * Method called when element is stalled.
	 * This method should be overridden to provide logic of reloading stalled elements.
	 */
	public abstract function reload();

	/**
	 * Execute element action in a stale safe context.
	 *
	 * @param string $method    method to be executed
	 * @param array  $params    method execution params
	 *
	 * @return mixed
	 */
	private function executeStaleSafe($method, $params = []) {
		try {
			return call_user_func_array(['parent', $method], $params);
		}
		catch (StaleElementReferenceException $exception) {
			if (!$this->reload_staled) {
				throw $exception;
			}

			$this->reload();
			return call_user_func_array(['parent', $method], $params);
		}
	}

	/**
	 * Check if element is in stalled state.
	 *
	 * @return boolean
	 */
	public function isStalled() {
		try {
			parent::isEnabled();
		}
		catch (StaleElementReferenceException $exception) {
			return true;
		}
		catch (Exception $exception) {
			// Code is not missing here.
		}

		return false;
	}

	/**
	 * Alias for sendKeys.
	 * @see RemoteWebElement::sendKeys
	 *
	 * @param string $text
	 *
	 * @return $this
	 */
	public function type($text) {
		return $this->sendKeys($text);
	}

	/**
	 * Alias for isDisplayed.
	 * @see isDisplayed
	 *
	 * @param boolean $displayed if element should be visible
	 *
	 * @return boolean
	 */
	public function isVisible($displayed = true) {
		return $this->isDisplayed($displayed);
	}

	/**
	 * @inheritdoc
	 */
	public function clear() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function click() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function getAttribute($attribute_name) {
		return $this->executeStaleSafe(__FUNCTION__, [$attribute_name]);
	}

	/**
	 * @inheritdoc
	 */
	public function getCSSValue($css_property_name) {
		return $this->executeStaleSafe(__FUNCTION__, [$css_property_name]);
	}

	/**
	 * @inheritdoc
	 */
	public function getLocation() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function getLocationOnScreenOnceScrolledIntoView() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function getCoordinates() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function getSize() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function getTagName() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function getText() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function sendKeys($value) {
		return $this->executeStaleSafe(__FUNCTION__, [$value]);
	}

	/**
	 * @inheritdoc
	 */
	public function submit() {
		return $this->executeStaleSafe(__FUNCTION__);
	}

	/**
	 * @inheritdoc
	 */
	public function findElement(WebDriverBy $by) {
		return $this->executeStaleSafe(__FUNCTION__, [$by]);
	}

	/**
	 * @inheritdoc
	 */
	public function findElements(WebDriverBy $by) {
		return $this->executeStaleSafe(__FUNCTION__, [$by]);
	}

	/**
	 * @inheritdoc
	 */
	public function isDisplayed($displayed = true) {
		try {
			return (parent::isDisplayed() === $displayed);
		}
		catch (StaleElementReferenceException $exception) {
			return ($displayed === false);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		return (parent::isEnabled() === $enabled);
	}

	/**
	 * @inheritdoc
	 */
	public function isSelected($selected = true) {
		return (parent::isSelected() === $selected);
	}

	/**
	 * Check if element is valid (all non-null elements are considered valid).
	 *
	 * @return boolean
	 */
	public function isValid() {
		return true;
	}
}
