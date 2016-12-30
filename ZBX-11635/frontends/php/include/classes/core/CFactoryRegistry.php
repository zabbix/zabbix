<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
class CFactoryRegistry {

	/**
	 * An array of created object instances.
	 *
	 * @var
	 */
	protected $objects;

	/**
	 * An instance of the factory.
	 *
	 * @var CFactoryRegistry
	 */
	protected static $instance;

	/**
	 * Returns an instance of the factory object.
	 *
	 * @param string $class
	 *
	 * @return CFactoryRegistry
	 */
	public static function getInstance($class = __CLASS__) {
		if (!self::$instance) {
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	 * Creates and returns an object from the given class.
	 *
	 * @param $class
	 *
	 * @return mixed
	 */
	protected function getObject($class) {
		if (!isset($this->objects[$class])) {
			$this->objects[$class] = new $class();
		}

		return $this->objects[$class];
	}

}
