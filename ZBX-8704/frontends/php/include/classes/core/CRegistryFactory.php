<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * A class for creating a storing instances of global objects.
 */
class CRegistryFactory {

	/**
	 * Array of defined objects. Each object can be defined either using the name of its class, or a closure that
	 * returns an instance of the object.
	 *
	 * @var array
	 */
	protected $objects = [];

	/**
	 * An array of created object instances.
	 *
	 * @var array
	 */
	protected $instances = [];

	/**
	 * @param array $objects	array of defined objects
	 */
	public function __construct(array $objects = []) {
		$this->objects = $objects;
	}

	/**
	 * Creates and returns an instance of the given object.
	 *
	 * @param $object
	 *
	 * @return object
	 */
	public function getObject($object) {
		if (!isset($this->instances[$object])) {
			$definition = $this->objects[$object];
			$this->instances[$object] = ($definition instanceof Closure) ? $definition() : new $definition();
		}

		return $this->instances[$object];
	}

	/**
	 * Returns true if the given object is defined in the factory.
	 *
	 * @param string $object
	 *
	 * @return bool
	 */
	public function hasObject($object) {
		return isset($this->objects[$object]);
	}

}
