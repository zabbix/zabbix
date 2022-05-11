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
