<?php

/**
 * Helper for array related operations.
 */
class CTestArrayHelper {
	/**
	 * TODO
	 * @param type $array
	 * @param type $key
	 * @param type $default
	 * @return type
	 */
	public static function get($array, $key, $default = null) {
		return (is_array($array) && array_key_exists($key, $array)) ? $array[$key] : $default;
	}

	public static function fillMultiple($root, $mapping, $data) {

	}
}
