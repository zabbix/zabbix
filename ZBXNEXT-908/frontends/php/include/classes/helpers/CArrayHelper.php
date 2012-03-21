<?php

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
			if (!(isset($a[$field['field']]) && isset($b[$field['field']]))) {
				return 0;
			}

			if ($a[$field['field']] != $b[$field['field']]) {
				if ($field['order'] == ZBX_SORT_UP) {
					return strnatcasecmp($a[$field['field']], $b[$field['field']]);
				}
				else {
					return strnatcasecmp($b[$field['field']], $a[$field['field']]);
				}
			}
		}
		return 0;
	}
}
