<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once 'vendor/autoload.php';

require_once __DIR__.'/CElementQuery.php';

/**
 * Element collection filter based on a specified condition and parameters.
 */
class CElementFilter {

	/**
	 * Possible filter conditions.
	 */
	const PRESENT = 'present';
	const TEXT_PRESENT = 'text present';
	const ATTRIBUTES_PRESENT = 'attributes present';
	const CLASSES_PRESENT = 'classes present';
	const VISIBLE = 'visible';
	const CLICKABLE = 'clickable';
	const READY = 'ready';
	const NOT_READY = 'not ready';
	const NOT_PRESENT = 'not present';
	const TEXT_NOT_PRESENT = 'text not present';
	const ATTRIBUTES_NOT_PRESENT = 'attributes not present';
	const CLASSES_NOT_PRESENT = 'classes not present';
	const NOT_VISIBLE = 'not visible';
	const NOT_CLICKABLE = 'not clickable';
	const SELECTED = 'selected';
	const NOT_SELECTED = 'not selected';
	const KEY = 'key';
	const ENABLED = 'enabled';
	const NOT_ENABLED = 'not enabled';
	const DISABLED = 'disabled';
	const NOT_DISABLED = 'not disabled';
	const COUNT = 'count';

	private $type;
	private $params = [];

	/**
	 * Constructor.
	 *
	 * @param string $type		condition to be filtered by
	 * @param array $params		condition parameters to be set
	 */
	public function __construct($type, $params = []) {
		$this->type = $type;
		$this->params = $params;
	}

	/**
	 * Get the type of filter condition.
	 *
	 * @return $type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get the parameters of filter condition.
	 *
	 * @return $params
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * Set the type of filter condition.
	 *
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Set the parameters of filter condition.
	 *
	 * @param array $params
	 */
	public function setParams($params) {
		$this->params = $params;
	}

	/**
	 * Get element condition callable name.
	 *
	 * @param string $condition    condition name
	 *
	 * @return array
	 */
	public static function getConditionCallable($condition) {
		$conditions = [
			static::READY => 'getReadyCondition',
			static::NOT_READY => 'getNotReadyCondition',
			static::PRESENT => 'getPresentCondition',
			static::NOT_PRESENT => 'getNotPresentCondition',
			static::TEXT_PRESENT => 'getTextPresentCondition',
			static::TEXT_NOT_PRESENT => 'getTextNotPresentCondition',
			static::ATTRIBUTES_PRESENT => 'getAttributesPresentCondition',
			static::ATTRIBUTES_NOT_PRESENT => 'getAttributesNotPresentCondition',
			static::CLASSES_PRESENT => 'getClassesPresentCondition',
			static::CLASSES_NOT_PRESENT => 'getClassesNotPresentCondition',
			static::VISIBLE => 'getVisibleCondition',
			static::NOT_VISIBLE => 'getNotVisibleCondition',
			static::CLICKABLE => 'getClickableCondition',
			static::NOT_CLICKABLE => 'getNotClickableCondition',
			static::SELECTED => 'getSelectedCondition',
			static::NOT_SELECTED => 'getNotSelectedCondition',
			static::ENABLED => 'getEnabledCondition',
			static::NOT_ENABLED => 'getNotEnabledCondition',
			static::DISABLED => 'getNotEnabledCondition',
			static::NOT_DISABLED => 'getEnabledCondition',
			static::COUNT => 'getCountCondition'
		];

		if (!array_key_exists($condition, $conditions)) {
			throw new Exception('Cannot get element condition callable by name "'.$condition.'"!');
		}

		return $conditions[$condition];
	}

	/**
	 * Determine whether this element matches the filter or not.
	 *
	 * @param CElement $element		element to be checked
	 * @param mixed	   $key			element key in the collection array
	 *
	 * @return boolean
	 */
	public function match($element, $key = null) {
		if ($this->type === self::KEY) {
			return in_array($key, $this->params);
		}

		$method = self::getConditionCallable($this->type);

		// A reflection-based implementation of callbacks will be required once filters have more than one attribute.
		$callable = call_user_func_array([$element, $method], [$this->params]);
		try {
			if (call_user_func($callable) === true) {
				return true;
			}
		} catch (Exception $e) {
			// Code is not missing here.
		}

		return false;
	}
}
