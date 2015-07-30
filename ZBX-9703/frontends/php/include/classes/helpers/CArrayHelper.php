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


class CArrayHelper {

	/**
	 * @var array
	 */
	protected static $fields;

	private function __construct() {}

	/**
	 * Get from array only values with given keys.
	 * If requested key is not in given array exception is thrown.
	 *
	 * @static
	 * @throws InvalidArgumentException
	 *
	 * @param array $array
	 * @param array $keys
	 *
	 * @return array
	 */
	public static function getByKeysStrict(array $array, array $keys) {
		$result = array();
		foreach ($keys as $key) {
			if (!isset($array[$key])) {
				throw new InvalidArgumentException(sprintf('Array does not have element with key "%1$s".', $key));
			}
			$result[$key] = $array[$key];
		}

		return $result;
	}

	/**
	 * Get values with the $keys from $array.
	 * If the requested key is not in the given array it is skipped.
	 *
	 * @static
	 *
	 * @param array $array
	 * @param array $keys
	 *
	 * @return array
	 */
	public static function getByKeys(array $array, array $keys) {
		$result = array();
		foreach ($keys as $key) {
			if (isset($array[$key])) {
				$result[$key] = $array[$key];
			}
		}

		return $result;
	}

	/**
	 * Converts the field with the given key to either an empty array, if the field is empty, or to an array with numeric keys.
	 * If the field with the given key does not exist, does nothing.
	 *
	 * @param array  $array
	 * @param string $fieldKey
	 */
	public static function convertFieldToArray(array &$array, $fieldKey) {
		if (array_key_exists($fieldKey, $array)) {
			if (is_array($array[$fieldKey])) {
				$array[$fieldKey] = array_values($array[$fieldKey]);
			}
			else {
				$array[$fieldKey] = array();
			}
		}
	}

	/**
	 * Sort array by multiple fields.
	 *
	 * @static
	 *
	 * @param array $array  array to sort passed by reference
	 * @param array $fields fields to sort, can be either string with field name or array with 'field' and 'order' keys
	 */
	public static function sort(array &$array, array $fields) {
		foreach ($fields as $fid => $field) {
			if (!is_array($field)) {
				$fields[$fid] = array('field' => $field, 'order' => ZBX_SORT_UP);
			}
		}
		self::$fields = $fields;
		uasort($array, array('self', 'compare'));
	}

	/**
	 * Method to be used as callback for uasort function in sort method.
	 *
	 * @TODO: with PHP 5.3+ this should be changed to closure
	 * @static
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	protected static function compare($a, $b) {
		foreach (self::$fields as $field) {
			// if field is not set or is null, treat it as smallest string
			// strnatcasecmp() has unexpected behaviour with null values
			if (!isset($a[$field['field']]) && !isset($b[$field['field']])) {
				$cmp = 0;
			}
			elseif (!isset($a[$field['field']])) {
				$cmp = -1;
			}
			elseif (!isset($b[$field['field']])) {
				$cmp = 1;
			}
			else {
				$cmp = strnatcasecmp($a[$field['field']], $b[$field['field']]);
			}

			if ($cmp != 0) {
				return $cmp * ($field['order'] == ZBX_SORT_UP?1:-1);
			}
		}
		return 0;
	}
}
