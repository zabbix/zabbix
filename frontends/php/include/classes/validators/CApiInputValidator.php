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
 * API validator
 */
class CApiInputValidator {

	/**
	 * Base validation function.
	 *
	 * @param array  $rule  validation rule
	 * @param mixed  $data  import data
	 * @param string $path  data path (for error reporting)
	 * @param string $error
	 *
	 * @return bool
	 */
	public static function validate(array $rule, &$data, $path, &$error) {
		switch ($rule['type']) {
			case API_STRING_UTF8:
				return self::validateStringUtf8($rule, $data, $path, $error);

			case API_ID:
				return self::validateId($rule, $data, $path, $error);

			case API_OBJECT:
				return self::validateObject($rule, $data, $path, $error);

			case API_IDS:
				return self::validateIds($rule, $data, $path, $error);

			case API_OBJECTS:
				return self::validateObjects($rule, $data, $path, $error);
		}

		return false;
	}

	/**
	 * String validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateStringUtf8($rule, &$data, $path, &$error) {
		if (array_key_exists('flags', $rule) && ($rule['flags'] & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_string($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a character string is expected'));
			return false;
		}

		if (mb_check_encoding($data, 'UTF-8') !== true) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid byte sequence in UTF-8'));
			return false;
		}

		if (array_key_exists('flags', $rule) && ($rule['flags'] & API_NOT_EMPTY) && $data === '') {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		return true;
	}

	/**
	 * Identifier validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_NULL
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateId($rule, &$data, $path, &$error) {
		if (array_key_exists('flags', $rule) && ($rule['flags'] & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_scalar($data) || !ctype_digit(strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is expected'));
			return false;
		}

		if (bccomp($data, '9223372036854775807') > 0) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));
			return false;
		}

		if (is_string($data) && $data[0] === '0' && strlen($data) > 1) {
			$data = ltrim($data, '0');
		}

		return true;
	}

	/**
	 * Object validator.
	 *
	 * @param array  $rule
	 * @param array  $rule['fields']
	 * @param int    $rule['fields'][<field_name>]['flags']   (optional) API_REQUIRED
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateObject($rule, &$data, $path, &$error) {
		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		// unexpected parameter validation
		foreach ($data as $field_name => $value) {
			if (!array_key_exists($field_name, $rule['fields'])) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _s('unexpected parameter "%1$s"', $field_name));
				return false;
			}
		}

		// validation of the values type
		foreach ($rule['fields'] as $field_name => $field_rule) {
			if (array_key_exists($field_name, $data)) {
				$subpath = ($path === '/' ? $path : $path.'/').$field_name;
				if (!self::validate($field_rule, $data[$field_name], $subpath, $error)) {
					return false;
				}
			}
			elseif (array_key_exists('flags', $field_rule) && ($field_rule['flags'] & API_REQUIRED)) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path,
					_s('the parameter "%1$s" is missing', $field_name)
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Array of ids validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_UNIQ
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateIds($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) && !$data) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		$index = 0;
		$uniq = [];

		foreach ($data as $key => &$value) {
			if (!ctype_digit(strval($key)) || strval($key) !== strval($index)) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _s('unexpected parameter "%1$s"', $key));
				return false;
			}

			$index++;
			$subpath = ($path === '/' ? $path : $path.'/').$index;
			if (!self::validateId([], $value, $subpath, $error)) {
				return false;
			}

			if ($flags & API_UNIQ) {
				if (array_key_exists($value, $uniq)) {
					$error = _s('Invalid parameter "%1$s": %2$s.', $subpath, _('value is not unique'));
					return false;
				}
				$uniq[$value] = true;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Array of objects validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_UNIQ
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateObjects($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) && !$data) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		$index = 0;

		foreach ($data as $key => &$value) {
			if (!ctype_digit(strval($key)) || strval($key) !== strval($index)) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _s('unexpected parameter "%1$s"', $key));
				return false;
			}

			$index++;
			$subpath = ($path === '/' ? $path : $path.'/').$index;
			if (!self::validateObject(['fields' => $rule['fields']], $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		// Uniqueness check.

		$uniq_fields = [];
		foreach ($rule['fields'] as $field_name => $field_rule) {
			if (array_key_exists('flags', $field_rule) && ($field_rule['flags'] & API_UNIQ)) {
				$uniq_fields[] = $field_name;
			}
		}

		foreach ($uniq_fields as $field_name) {
			$uniq = [];
			$index = 0;

			foreach ($data as $object) {
				$index++;

				if (array_key_exists($field_name, $object)) {
					if (array_key_exists($object[$field_name], $uniq)) {
						$subpath = ($path === '/' ? $path : $path.'/').$index.'/'.$field_name;
						$error = _s('Invalid parameter "%1$s": %2$s.', $subpath, _('value is not unique'));
						return false;
					}
					$uniq[$object[$field_name]] = true;
				}
			}
		}

		return true;
	}
}
