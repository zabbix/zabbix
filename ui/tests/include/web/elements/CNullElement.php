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

require_once 'vendor/autoload.php';

/**
 * NullObject implementation for non-existing web page element.
 */
class CNullElement {

	/**
	 * Element can be used as castable object to cast element to specific type.
	 */
	use CastableTrait;

	/**
	 * Element locator.
	 *
	 * @var string
	 */
	protected $locator;

	/**
	 * Element selector.
	 *
	 * @var WebDriverBy
	 */
	protected $by;

	/**
	 * Get object selector (if any) as text.
	 *
	 * @return string
	 */
	public function getSelectorAsText() {
		return $this->locator;
	}

	/**
	 * Initialize element.
	 *
	 * @param type $options
	 */
	public function __construct($options = []) {
		foreach ($options as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}

		if ($this->locator === null && array_key_exists('by', $options)) {
			$this->locator = '"'.$this->by->getValue().'" ('.$this->by->getMechanism().')';
		}
	}

	/**
	 * Simplified selector for elements that can be located directly on page.
	 * @throws Exception
	 */
	public static function find() {
		throw new Exception('Element cannot be located without selector.');
	}

	/**
	 * Null element is never casted to any element type.
	 *
	 * @param string $class      class to be casted to
	 * @param array  $options    additional options passed to object
	 *
	 * @return $this
	 */
	public function cast($class, $options = []) {
		return $this;
	}

	/**
	 * Get condition that will always return false.
	 *
	 * @return callable
	 */
	protected static function getFailCondition() {
		return function () {
			return false;
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getClickableCondition() {
		return self::getFailCondition();
	}

	/**
	 * @inheritdoc
	 */
	public function getPresentCondition() {
		return self::getFailCondition();
	}

	/**
	 * @inheritdoc
	 */
	public function getVisibleCondition() {
		return self::getFailCondition();
	}

	/**
	 * @inheritdoc
	 */
	public function getSelectedCondition() {
		return self::getFailCondition();
	}

	/**
	 * @inheritdoc
	 */
	public function getTextPresentCondition($text) {
		return self::getFailCondition();
	}

	/**
	 * @inheritdoc
	 */
	public function getAttributesPresentCondition($attributes) {
		return self::getFailCondition();
	}

	/**
	 * @inheritdoc
	 */
	public function getReadyCondition() {
		return self::getFailCondition();
	}

	/**
	 * Wait until element changes it's state from stalled to normal.
	 *
	 * @throws Exception
	 */
	public function waitUntilReloaded() {
		throw new Exception('Cannot wait for null element reload.');
	}

	/**
	 * Wait until element is selected.
	 *
	 * @throws Exception
	 */
	public function waitUntilSelected() {
		throw new Exception('Cannot wait for null element to be selected.');
	}

	/**
	 * Null element cannot be used to detect element type.
	 *
	 * @param type $options
	 */
	public function detect($options = []) {
		return $this;
	}

	/**
	 * Check element value.
	 *
	 * @param mixed $expected    expected value of the element
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	public function checkValue($expected, $raise_exception = true) {
		if ($raise_exception) {
			throw new Exception('Cannot check value of null element.');
		}

		return false;
	}

	/**
	 * Call methods specific for custom element types.
	 *
	 * @param string $name         method name
	 * @param array  $arguments    method arguments
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function __call($name, $arguments) {
		// Null object returns false for all isXXX methods (isReady, isVisible, isEnabled...).
		if (substr($name, 0, 2) === 'is') {
			// Some methods (like isEnabled) allow passing boolean argument to get reversed result.
			$expected = (count($arguments) === 1 && is_bool($arguments[0])) ? $arguments[0] : true;

			return $expected === false;
		}

		$selector = $this->getSelectorAsText();
		if ($selector !== null) {
			$selector = ' located by '.$selector;
		}

		throw new Exception('Failed to find element'.$selector.' and execute "'.$name.'".');
	}
}
