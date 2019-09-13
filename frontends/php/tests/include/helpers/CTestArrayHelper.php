<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Helper for array related operations.
 */
class CTestArrayHelper {

	/**
	 * Get value from array by key.
	 *
	 * @param mixed $array      array
	 * @param mixed $key        key to look for
	 * @param mixed $default    default value to be returned if array key doesn't exist (or if non-array is passed)
	 *
	 * @return mixed
	 */
	public static function get($array, $key, $default = null) {
		if (!is_array($array)) {
			return $default;
		}

		if (array_key_exists($key, $array)) {
			return $array[$key];
		}

		if (($pos = strrpos($key, '.')) !== false) {
			return static::get(static::get($array, substr($key, 0, $pos)), substr($key, $pos + 1), $default);
		}

		return $default;
	}

	/**
	 * Check if array is associative.
	 *
	 * @param array $array
	 *
	 * @return boolean
	 */
	public static function isAssociative($array) {
		return $array && array_keys($array) !== range(0, count($array) - 1);
	}
}
