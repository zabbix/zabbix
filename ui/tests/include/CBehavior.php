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

/**
 * Base class for all the test behaviors.
 */
class CBehavior {

	/**
	 * Behavior owner test.
	 * @var mixed
	 */
	protected $test;

	/**
	 * Constructor.
	 *
	 * @param array $params    behavior attributes to be set
	 */
	public function __construct($params = []) {
		foreach ($params as $param => $value) {
			$this->$param = $value;
		}
	}

	/**
	 * Check if behavior has specific method.
	 *
	 * @param string $name    name of the method
	 *
	 * @return boolean
	 */
	public function hasMethod($name) {
		return method_exists($this, $name);
	}

	/**
	 * Check if behavior has specific attribute.
	 *
	 * @param string $name    name of the attribute
	 *
	 * @return boolean
	 */
	public function hasAttribute($name) {
		return property_exists($this, $name);
	}

	/**
	 * Set test owning the behavior.
	 *
	 * @param mixed $test    owner test
	 */
	public function setTest($test) {
		$this->test = $test;
	}
}
