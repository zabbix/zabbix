<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	 * Array of defined objects.
	 *
	 * @var array
	 */
	protected $objects = array();

	/**
	 * An array of created object instances.
	 *
	 * @var array
	 */
	protected $instances = array();

	/**
	 * Creates and returns an instance of the given object.
	 *
	 * @param $object
	 *
	 * @return object
	 */
	public function getObject($object) {
		if (!isset($this->instances[$object])) {
			$class = $this->objects[$object];
			$this->instances[$object] = new $class();
		}

		return $this->instances[$object];
	}

}
