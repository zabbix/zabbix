<?php declare(strict_types = 0);
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
 * Class containing methods for accessing once loaded parameters of API object designed to work with 'config' table.
 */
abstract class CConfigGeneralHelper {

	/**
	 * Load once all parameters of API object.
	 *
	 * @static
	 *
	 * @param string|null $param      API object parameter name.
	 * @param bool        $is_global  Set to "true" to allow parameters loading via getglobal API method.
	 */
	abstract protected static function loadParams(?string $param = null, bool $is_global = false): void;

	/**
	 * Get value by parameter name of API object (load parameters if needed).
	 *
	 * @static
	 *
	 * @param string  $name  API object parameter name.
	 *
	 * @return string|null Parameter value. If parameter not exists, return null.
	 */
	public static function get(string $name): ?string {
		static::loadParams($name, false);

		return array_key_exists($name, static::$params) ? static::$params[$name] : null;
	}

	/**
	 * Get values of all parameters of API object (load parameters if needed).
	 *
	 * @static
	 *
	 * @return array String array with all values of API object parameters in format <parameter name> => <value>.
	 */
	public static function getAll(): array {
		static::loadParams();

		return static::$params;
	}

	/**
	 * Set value by parameter name of API object into $params (load parameters if needed).
	 *
	 * @static
	 *
	 * @param string $name   API object parameter name.
	 * @param string $value  API object parameter value.
	 */
	public static function set(string $name, string $value): void {
		static::loadParams($name, false);

		if (array_key_exists($name, static::$params)) {
			static::$params[$name] = $value;
		}
	}
}
