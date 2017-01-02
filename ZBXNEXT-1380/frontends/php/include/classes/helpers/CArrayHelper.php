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
		$result = [];
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
		$result = [];
		foreach ($keys as $key) {
			if (array_key_exists($key, $array)) {
				$result[$key] = $array[$key];
			}
		}

		return $result;
	}

	/**
	 * Renames array elements keys according to given map.
	 *
	 * @param array $array
	 * @param array $field_map
	 *
	 * @return array
	 */
	public static function renameKeys(array $array, array $field_map) {
		foreach ($field_map as $old_key => $new_key) {
			if (array_key_exists($old_key, $array)) {
				$array[$new_key] = $array[$old_key];
				unset($array[$old_key]);
			}
		}

		return $array;
	}

	/**
	 * Renames keys according to given map for given array of the objects.
	 *
	 * @param array $array
	 * @param array $field_map
	 *
	 * @return array
	 */
	public static function renameObjectsKeys(array $array, array $field_map) {
		foreach ($array as &$object) {
			foreach ($field_map as $old_key => $new_key) {
				$object[$new_key] = $object[$old_key];
				unset($object[$old_key]);
			}
		}
		unset($object);

		return $array;
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
				$fields[$fid] = ['field' => $field, 'order' => ZBX_SORT_UP];
			}
		}
		self::$fields = $fields;
		uasort($array, ['self', 'compare']);
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

	/**
	 * Unset values that are contained in $a2 from $a1. Skip arrays and keys given in $skipKeys.
	 *
	 * @param array $a1         array to modify
	 * @param array $a2         array to compare with
	 * @param array $skipKeys   fields to ignore
	 *
	 * @return array
	 */
	public static function unsetEqualValues(array $a1, array $a2, array $skipKeys = []) {
		// ignore given fields
		foreach ($skipKeys as $key) {
			unset($a2[$key]);
		}

		foreach ($a1 as $key => $value) {
			// check if the values under $key are equal, skip arrays
			if (isset($a2[$key]) && !is_array($value) && (string) $a1[$key] === (string) $a2[$key]) {
				unset($a1[$key]);
			}
		}

		return $a1;
	}

	/**
	 * Checks if array $arrays contains arrays with duplicate values under the $uniqueField key. If a duplicate exists,
	 * returns the first duplicate, otherwise returns null.
	 *
	 * Example 1:
	 * $data = array(
	 *     array('name' => 'CPU load'),
	 * 	   array('name' => 'CPU load'),
	 * 	   array('name' => 'Free memory')
	 * );
	 * var_dump(CArrayHelper::findDuplicate($data, 'name')); // returns array with index 1
	 *
	 * Example 2:
	 * $data = array(
	 *     array('host' => 'Zabbix server', 'name' => 'CPU load'),
	 * 	   array('host' => 'Zabbix server', 'name' => 'Free memory'),
	 * 	   array('host' => 'Linux server', 'name' => 'CPU load'),
	 * 	   array('host' => 'Zabbix server', 'name' => 'CPU load')
	 * );
	 * var_dump(CArrayHelper::findDuplicate($data, 'name', 'host')); // returns array with index 3
	 *
	 * @param array $arrays         an array of arrays
	 * @param string $uniqueField   key to be used as unique criteria
	 * @param string $uniqueField2	second key to be used as unique criteria
	 *
	 * @return null|array           the first duplicate found or null if there are no duplicates
	 */
	public static function findDuplicate(array $arrays, $uniqueField, $uniqueField2 = null) {
		$uniqueValues = [];

		foreach ($arrays as $array) {
			$value = $array[$uniqueField];

			if ($uniqueField2 !== null) {
				$uniqueByValue = $array[$uniqueField2];

				if (isset($uniqueValues[$uniqueByValue]) && isset($uniqueValues[$uniqueByValue][$value])) {
					return $array;
				}
				$uniqueValues[$uniqueByValue][$value] = $value;
			}
			else {
				if (isset($uniqueValues[$value])) {
					return $array;
				}
				$uniqueValues[$value] = $value;
			}
		}
	}
}
