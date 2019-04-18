<?php

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
		return (is_array($array) && array_key_exists($key, $array)) ? $array[$key] : $default;
	}
}
