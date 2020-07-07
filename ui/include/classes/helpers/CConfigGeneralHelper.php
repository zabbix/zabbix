<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class containing methods for accessing once loaded parameters of API object designed to work with 'config' table.
 */
abstract class CConfigGeneralHelper {

	/**
	 * API object parameters array.
	 *
	 * @var array
	 */
	protected static $params = [];

	/**
	 * Load once all parameters of API object.
	 */
	abstract protected static function loadParams(): void;

	/**
	 * Get value by parameter name of API object (load parameters if need).
	 *
	 * @param string  $name  API object parameter name.
	 *
	 * @return string|null Parameter value. If parameter not exists, return null.
	 */
	public static function get(string $name): ?string {
		self::loadParams();

		return array_key_exists($name, self::$params) ? self::$params[$name] : null;
	}

	/**
	 * Get values of all parameters of API object (load parameters if need).
	 *
	 * @return array String array with all values of API object parameters in format <parameter name> => <value>.
	 */
	public static function getAll(): array {
		self::loadParams();

		return self::$params;
	}

	/**
	 * Set value by parameter name of API object into $params (load parameters if need).
	 *
	 * @param string $name   API object parameter name.
	 * @param string $value  API object parameter value.
	 */
	public static function set(string $key, string $value): void {
		self::loadParams();

		if (array_key_exists($key, self::$params)) {
			self::$params[$key] = $value;
		}
	}
}
