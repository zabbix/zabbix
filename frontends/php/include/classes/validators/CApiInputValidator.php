<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
		$error = '';

		return self::validateData($rule, $data, $path, $error)
			&& self::validateDataUniqueness($rule, $data, $path, $error);
	}

	/**
	 * Base uniqueness validation function.
	 *
	 * @param array  $rule  validation rule
	 * @param mixed  $data  import data
	 * @param string $path  data path (for error reporting)
	 * @param string $error
	 *
	 * @return bool
	 */
	public static function validateUniqueness(array $rule, $data, $path, &$error) {
		$error = '';

		return self::validateDataUniqueness($rule, $data, $path, $error);
	}

	/**
	 * Base data validation function.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 * @param array  $parent_data
	 *
	 * @return bool
	 */
	private static function validateData($rule, &$data, $path, &$error, array $parent_data = null) {
		switch ($rule['type']) {
			case API_MULTIPLE:
				if ($parent_data !== null) {
					return self::validateMultiple($rule, $data, $path, $error, $parent_data);
				}
				break;

			case API_STRING_UTF8:
				return self::validateStringUtf8($rule, $data, $path, $error);

			case API_STRINGS_UTF8:
				return self::validateStringsUtf8($rule, $data, $path, $error);

			case API_INT32:
				return self::validateInt32($rule, $data, $path, $error);

			case API_INTS32:
				return self::validateInts32($rule, $data, $path, $error);

			case API_ID:
				return self::validateId($rule, $data, $path, $error);

			case API_BOOLEAN:
				return self::validateBoolean($rule, $data, $path, $error);

			case API_FLAG:
				return self::validateFlag($rule, $data, $path, $error);

			case API_OBJECT:
				return self::validateObject($rule, $data, $path, $error);

			case API_OUTPUT:
				return self::validateOutput($rule, $data, $path, $error);

			case API_IDS:
				return self::validateIds($rule, $data, $path, $error);

			case API_OBJECTS:
				return self::validateObjects($rule, $data, $path, $error);

			case API_HG_NAME:
				return self::validateHostGroupName($rule, $data, $path, $error);

			case API_SCRIPT_NAME:
				return self::validateScriptName($rule, $data, $path, $error);

			case API_USER_MACRO:
				return self::validateUserMacro($rule, $data, $path, $error);

			case API_TIME_PERIOD:
				return self::validateTimePeriod($rule, $data, $path, $error);

			case API_REGEX:
				return self::validateRegex($rule, $data, $path, $error);

			case API_HTTP_POST:
				return self::validateHttpPosts($rule, $data, $path, $error);

			case API_VARIABLE_NAME:
				return self::validateVariableName($rule, $data, $path, $error);

			case API_TIME_UNIT:
				return self::validateTimeUnit($rule, $data, $path, $error);

			case API_URL:
				return self::validateUrl($rule, $data, $path, $error);
		}

		// This message can be untranslated because warn about incorrect validation rules at a development stage.
		$error = 'Incorrect validation rules.';

		return false;
	}

	/**
	 * Base data uniqueness validation function.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateDataUniqueness($rule, &$data, $path, &$error) {
		switch ($rule['type']) {
			case API_MULTIPLE:
			case API_STRING_UTF8:
			case API_INT32:
			case API_ID:
			case API_BOOLEAN:
			case API_FLAG:
			case API_OBJECT:
			case API_OUTPUT:
			case API_HG_NAME:
			case API_SCRIPT_NAME:
			case API_USER_MACRO:
			case API_TIME_PERIOD:
			case API_TIME_UNIT:
			case API_REGEX:
			case API_HTTP_POST:
			case API_VARIABLE_NAME:
			case API_URL:
				return true;

			case API_IDS:
			case API_STRINGS_UTF8:
			case API_INTS32:
				return self::validateStringsUniqueness($rule, $data, $path, $error);

			case API_OBJECTS:
				return self::validateObjectsUniqueness($rule, $data, $path, $error);
		}

		// This message can be untranslated because warn about incorrect validation rules at a development stage.
		$error = 'Incorrect validation rules.';

		return false;
	}

	/**
	 * Generic string validator.
	 *
	 * @param int    $flags  API_NOT_EMPTY
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkStringUtf8($flags, &$data, $path, &$error) {
		if (!is_string($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a character string is expected'));
			return false;
		}

		if (mb_check_encoding($data, 'UTF-8') !== true) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid byte sequence in UTF-8'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) && $data === '') {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		return true;
	}

	/**
	 * Myltiple data types validator.
	 *
	 * @param array  $rule
	 * @param array  $rule['rules']
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 * @param array  $parent_data
	 *
	 * @return bool
	 */
	private static function validateMultiple($rule, &$data, $path, &$error, array $parent_data) {
		foreach ($rule['rules'] as $field_rule) {
			if (self::Int32In($parent_data[$field_rule['if']['field']], $field_rule['if']['in'])) {
				unset($field_rule['if']);

				return self::validateData($field_rule, $data, $path, $error);
			}
		}

		// This message can be untranslated because warn about incorrect validation rules at a development stage.
		$error = 'Incorrect validation rules.';

		return false;
	}

	/**
	 * String validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL
	 * @param string $rule['in']      (optional) a comma-delimited character string, for example: 'xml,json'
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateStringUtf8($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (self::checkStringUtf8($flags, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('in', $rule) && !in_array($data, explode(',', $rule['in']), true)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_s('value must be one of %1$s', str_replace(',', ', ', $rule['in']))
			);
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		return true;
	}

	/**
	 * Array of strings validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param string $rule['in']      (optional) a comma-delimited character string, for example: 'xml,json'
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateStringsUtf8($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (($flags & API_NORMALIZE) && self::validateStringUtf8([], $data, '', $e)) {
			$data = [$data];
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) && !$data) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		$data = array_values($data);
		$rules = ['type' => API_STRING_UTF8];

		if (array_key_exists('in', $rule)) {
			$rules['in'] = $rule['in'];
		}

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateData($rules, $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Integers validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_NULL
	 * @param string $rule['in']      (optional) a comma-delimited character string, for example: '0,60:900'
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateInt32($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if ((!is_int($data) && !is_string($data)) || 1 != preg_match('/^\-?[0-9]+$/', strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is expected'));
			return false;
		}

		if (bccomp($data, ZBX_MIN_INT32) < 0 || bccomp($data, ZBX_MAX_INT32) > 0) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));
			return false;
		}

		if (!self::checkInt32In($rule, $data, $path, $error)) {
			return false;
		}

		if (is_string($data)) {
			$data = (int) $data;
		}

		return true;
	}

	private static function Int32In($data, $in) {
		$valid = false;

		foreach (explode(',', $in) as $in) {
			if (strpos($in, ':') !== false) {
				list($from, $to) = explode(':', $in);
			}
			else {
				$from = $in;
				$to = $in;
			}

			if ($from <= $data && $data <= $to) {
				$valid = true;
				break;
			}
		}

		return $valid;
	}

	/**
	 * @param array  $rule
	 * @param int    $rule['in']  (optional)
	 * @param int    $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkInt32In($rule, $data, $path, &$error) {
		if (!array_key_exists('in', $rule)) {
			return true;
		}

		$valid = self::Int32In($data, $rule['in']);

		if (!$valid) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_s('value must be one of %1$s', str_replace([',', ':'], [', ', '-'], $rule['in']))
			);
		}

		return $valid;
	}

	/**
	 * Array of integers validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param string $rule['in']      (optional) a comma-delimited character string, for example: 'xml,json'
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateInts32($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (($flags & API_NORMALIZE) && self::validateInt32([], $data, '', $e)) {
			$data = [$data];
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) && !$data) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		$data = array_values($data);
		$rules = ['type' => API_INT32];

		if (array_key_exists('in', $rule)) {
			$rules['in'] = $rule['in'];
		}

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateData($rules, $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

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
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_scalar($data) || is_bool($data) || is_double($data) || !ctype_digit(strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is expected'));
			return false;
		}

		if (bccomp($data, '9223372036854775807') > 0) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));
			return false;
		}

		$data = (string) $data;

		if ($data[0] === '0') {
			$data = ltrim($data, '0');

			if ($data === '') {
				$data = '0';
			}
		}

		return true;
	}

	/**
	 * Boolean validator.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateBoolean($rule, &$data, $path, &$error) {
		if (!is_bool($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a boolean is expected'));
			return false;
		}

		return true;
	}

	/**
	 * Flag validator.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFlag($rule, &$data, $path, &$error) {
		if (is_bool($data)) {
			return true;
		}

		/**
		 * @deprecated  As of version 3.4, use boolean flags only.
		 */
		trigger_error(_('Non-boolean flags are deprecated.'), E_USER_NOTICE);

		$data = !is_null($data);

		return true;
	}

	/**
	 * Object validator.
	 *
	 * @param array  $rul
	 * @param int    $rule['flags']                           (optional) API_ALLOW_NULL
	 * @param array  $rule['fields']
	 * @param int    $rule['fields'][<field_name>]['flags']   (optional) API_REQUIRED, API_DEPRECATED
	 * @param mixed  $rule['fields'][<field_name>]['default'] (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateObject($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

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
			$flags = array_key_exists('flags', $field_rule) ? $field_rule['flags'] : 0x00;

			if (array_key_exists('default', $field_rule) && !array_key_exists($field_name, $data)) {
				$data[$field_name] = $field_rule['default'];
			}

			if (array_key_exists($field_name, $data)) {
				$subpath = ($path === '/' ? $path : $path.'/').$field_name;
				if (!self::validateData($field_rule, $data[$field_name], $subpath, $error, $data)) {
					return false;
				}
				if ($flags & API_DEPRECATED) {
					trigger_error(_s('Parameter "%1$s" is deprecated.', $subpath), E_USER_NOTICE);
				}
			}
			elseif ($flags & API_REQUIRED) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path,
					_s('the parameter "%1$s" is missing', $field_name)
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * APPI output validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_COUNT, API_ALLOW_NULL
	 * @param string $rule['in']      (optional) comma-delimited field names, for example: 'hostid,name'
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateOutput($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (is_array($data)) {
			$rules = ['type' => API_STRINGS_UTF8, 'uniq' => true];

			if (array_key_exists('in', $rule)) {
				$rules['in'] = $rule['in'];
			}

			return self::validateData($rules, $data, $path, $error)
				&& self::validateDataUniqueness($rules, $data, $path, $error);
		}
		elseif (is_string($data)) {
			$in = ($flags & API_ALLOW_COUNT) ? implode(',', [API_OUTPUT_EXTEND, API_OUTPUT_COUNT]) : API_OUTPUT_EXTEND;

			return self::validateData(['type' => API_STRING_UTF8, 'in' => $in], $data, $path, $error);
		}

		$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array or a character string is expected'));

		return false;
	}

	/**
	 * Array of ids validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
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

		if (($flags & API_NORMALIZE) && self::validateId([], $data, '', $e)) {
			$data = [$data];
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) && !$data) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('cannot be empty'));
			return false;
		}

		$data = array_values($data);

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateId([], $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Array of objects validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param array  $rule['fields']
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

		if (($flags & API_NORMALIZE) && $data) {
			reset($data);

			if (!is_int(key($data))) {
				$data = [$data];
			}
		}

		$data = array_values($data);

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateObject(['fields' => $rule['fields']], $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Host group name validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateHostGroupName($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$host_group_name_validator = new CHostGroupNameValidator();

		if (!$host_group_name_validator->validate($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $host_group_name_validator->getError());
			return false;
		}

		return true;
	}

	/**
	 * Global script name validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateScriptName($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$folders = splitPath($data);
		$folders = array_map('trim', $folders);

		// folder1/{empty}/name or folder1/folder2/{empty}
		foreach ($folders as $folder) {
			if ($folder === '') {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('directory or script name cannot be empty'));
				return false;
			}
		}

		return true;
	}

	/**
	 * User macro validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUserMacro($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if ((new CUserMacroParser())->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a user macro is expected'));
			return false;
		}

		return true;
	}

	/**
	 * Time period validator like "1-7,00:00-24:00".
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_USER_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateTimePeriod($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$time_period_parser = new CTimePeriodsParser(['usermacros' => ($flags & API_ALLOW_USER_MACRO)]);

		if ($time_period_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a time period is expected'));
			return false;
		}

		return true;
	}

	/**
	 * Regular expression validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateRegex($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if ($data !== '' && $data[0] === '@') {
			return true;
		}

		if (false === @preg_match('/'.$data.'/', '')) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid regular expression'));
			return false;
		}

		return true;
	}

	/**
	 * Time unit validator like "10", "20s", "30m", "4h", "{$TIME}" etc.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_USER_MACRO, API_ALLOW_LLD_MACRO
	 * @param int    $rule['in']      (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateTimeUnit($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		/*
		 * It's possible to enter seconds as integers, but by default now we look for strings. For example: "30m".
		 * Other rules like emptiness and invalid characters are validated by parsers.
		 */
		if (is_int($data)) {
			$data = (string) $data;
		}

		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		$simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => ($flags & API_ALLOW_USER_MACRO),
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO)
		]);

		if ($simple_interval_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a time unit is expected'));
			return false;
		}

		if (($flags & (API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO)) && $data[0] === '{') {
			return true;
		}

		$seconds = timeUnitToSeconds($data);

		if (bccomp($seconds, ZBX_MAX_INT32) > 0) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));
			return false;
		}

		return self::checkInt32In($rule, $seconds, $path, $error);
	}

	/**
	 * Array of ids, int32 or strings uniqueness validator.
	 *
	 * @param array  $rule
	 * @param bool   $rule['uniq']    (optional)
	 * @param array  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateStringsUniqueness($rule, array $data = null, $path, &$error) {
		// $data can be NULL when API_ALLOW_NULL is set
		if ($data === null) {
			return true;
		}

		if (!array_key_exists('uniq', $rule) || $rule['uniq'] === false) {
			return true;
		}

		$uniq = [];

		foreach ($data as $index => $value) {
			if (array_key_exists($value, $uniq)) {
				$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
				$error = _s('Invalid parameter "%1$s": %2$s.', $subpath,
					_s('value %1$s already exists', '('.$value.')')
				);
				return false;
			}
			$uniq[$value] = true;
		}

		return true;
	}

	/**
	 * Array of objects uniqueness validator.
	 *
	 * @param array  $rule
	 * @param array  $rule['uniq']    (optional) subsets of unique fields ([['hostid', 'name'], [...]])
	 * @param array  $rule['fields']  (optional)
	 * @param array  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateObjectsUniqueness($rule, array $data = null, $path, &$error) {
		// $data can be NULL when API_ALLOW_NULL is set
		if ($data === null) {
			return true;
		}

		if (array_key_exists('uniq', $rule)) {
			foreach ($rule['uniq'] as $field_names) {
				$uniq = [];

				foreach ($data as $index => $object) {
					$_uniq = &$uniq;
					$values = [];
					$level = 1;

					foreach ($field_names as $field_name) {
						if (!array_key_exists($field_name, $object)) {
							break;
						}

						$values[] = $object[$field_name];

						if ($level < count($field_names)) {
							if (!array_key_exists($object[$field_name], $_uniq)) {
								$_uniq[$object[$field_name]] = [];
							}

							$_uniq = &$_uniq[$object[$field_name]];
						}
						else {
							if (array_key_exists($object[$field_name], $_uniq)) {
								$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
								$error = _s('Invalid parameter "%1$s": %2$s.', $subpath, _s('value %1$s already exists',
									'('.implode(', ', $field_names).')=('.implode(', ', $values).')'
								));
								return false;
							}

							$_uniq[$object[$field_name]] = true;
						}

						$level++;
					}
				}
			}
		}

		if (array_key_exists('fields', $rule)) {
			foreach ($data as $index => $object) {
				foreach ($rule['fields'] as $field_name => $field_rule) {
					if (array_key_exists($field_name, $object)) {
						$subpath = ($path === '/' ? $path : $path.'/').($index + 1).'/'.$field_name;
						if (!self::validateDataUniqueness($field_rule, $object[$field_name], $subpath, $error)) {
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * HTTP POST validator. Posts can be set to string (raw post) or to http pairs (form fields)
	 *
	 * @param array  $rule
	 * @param int    $rule['length']        (optional)
	 * @param int    $rule['name-length']   (optional)
	 * @param int    $rule['value-length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateHttpPosts($rule, &$data, $path, &$error) {
		if (is_array($data)) {
			$rules = ['type' => API_OBJECTS, 'fields' => [
				'name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'value' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]];

			if (array_key_exists('name-length', $rule)) {
				$rules['fields']['name']['length'] = $rule['name-length'];
			}

			if (array_key_exists('value-length', $rule)) {
				$rules['fields']['value']['length'] = $rule['value-length'];
			}
		}
		else {
			$rules = ['type' => API_STRING_UTF8];

			if (array_key_exists('length', $rule)) {
				$rules['length'] = $rule['length'];
			}
		}

		return self::validateData($rules, $data, $path, $error);
	}

	/**
	 * HTTP variable validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateVariableName($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if (preg_match('/^{[^{}]+}$/', $data) !== 1) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('is not enclosed in {} or is malformed'));
			return false;
		}

		return true;
	}

	/**
	 * URL validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param int    $rule['flags']   (optional) API_ALLOW_USER_MACRO
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUrl($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8(0x00, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if ($data !== '' && CHtmlUrlValidator::validate($data, ($flags & API_ALLOW_USER_MACRO)) === false) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('unacceptible URL'));
			return false;
		}

		return true;
	}
}
