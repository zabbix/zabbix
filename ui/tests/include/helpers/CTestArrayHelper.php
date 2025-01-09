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

	/**
	 * Sort array by multiple fields. This function assigns new keys to the elements in $array.
	 *
	 * @param array $array   The input array.
	 * @param array $fields  Fields to sort, can be either string with field name or array with 'field' and 'order' keys.
	 */
	public static function usort(array &$array, array $fields) {
		foreach ($fields as $i => $field) {
			if (is_string($field)) {
				$fields[$i] = ['field' => $field, 'order' => ZBX_SORT_UP];
			}
		}

		usort($array, function($a, $b) use ($fields) {
			foreach ($fields as $field) {
				$cmp = strnatcasecmp($a[$field['field']], $b[$field['field']]);

				if ($cmp != 0) {
					return $cmp * ($field['order'] == ZBX_SORT_UP ? 1 : -1);
				}
			}

			return 0;
		});
	}

	/**
	 * Recursive function for trimming all values in multi-level array.
	 *
	 * @param array    $array    array to be trimmed
	 *
	 * @return array
	 */
	public static function trim(&$array) {
		foreach ($array as &$value) {
			if (!is_array($value)) {
				$value = trim($value);
			}
			else {
				self::trim($value);
			}
		}
		unset($value);

		return $array;
	}

	/**
	 * Check if array is multidimensional.
	 *
	 * @param array $array	multidimensional or not multidimensional array under attention
	 *
	 * @return boolean
	 */
	public static function isMultidimensional($array) {
		return (count($array) !== count($array, COUNT_RECURSIVE));
	}
}
