<?php
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
	 *
	 * @return bool
	 */
	private static function validateData($rule, &$data, $path, &$error) {
		switch ($rule['type']) {
			case API_CALC_FORMULA:
				return self::validateCalcFormula($rule, $data, $path, $error);

			case API_COLOR:
				return self::validateColor($rule, $data, $path, $error);

			case API_COLORS:
				return self::validateColors($rule, $data, $path, $error);

			case API_COND_FORMULA:
				return self::validateCondFormula($rule, $data, $path, $error);

			case API_COND_FORMULAID:
				return self::validateCondFormulaId($rule, $data, $path, $error);

			case API_STRING_UTF8:
				return self::validateStringUtf8($rule, $data, $path, $error);

			case API_STRINGS_UTF8:
				return self::validateStringsUtf8($rule, $data, $path, $error);

			case API_INT32:
				return self::validateInt32($rule, $data, $path, $error);

			case API_INTS32:
				return self::validateInts32($rule, $data, $path, $error);

			case API_INT32_RANGES:
				return self::validateInt32Ranges($rule, $data, $path, $error);

			case API_UINT64:
				return self::validateUInt64($rule, $data, $path, $error);

			case API_UINTS64:
				return self::validateUInts64($rule, $data, $path, $error);

			case API_FILTER:
				return self::validateFilter($rule, $data, $path, $error);

			case API_FILTER_VALUES:
				return self::validateFilterValues($rule, $data, $path, $error);

			case API_FILTER_VALUE:
				return self::validateFilterValue($rule, $data, $path, $error);

			case API_FLOAT:
				return self::validateFloat($rule, $data, $path, $error);

			case API_FLOATS:
				return self::validateFloats($rule, $data, $path, $error);

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

			case API_PSK:
				return self::validatePSK($rule, $data, $path, $error);

			case API_SORTORDER:
				return self::validateSortOrder($rule, $data, $path, $error);

			case API_IDS:
				return self::validateIds($rule, $data, $path, $error);

			case API_OBJECTS:
				return self::validateObjects($rule, $data, $path, $error);

			case API_HG_NAME:
				return self::validateHostGroupName($rule, $data, $path, $error);

			case API_H_NAME:
				return self::validateHostName($rule, $data, $path, $error);

			case API_NUMERIC:
				return self::validateNumeric($rule, $data, $path, $error);

			case API_SCRIPT_MENU_PATH:
				return self::validateScriptMenuPath($rule, $data, $path, $error);

			case API_USER_MACRO:
				return self::validateUserMacro($rule, $data, $path, $error);

			case API_USER_MACROS:
				return self::validateUserMacros($rule, $data, $path, $error);

			case API_LLD_MACRO:
				return self::validateLLDMacro($rule, $data, $path, $error);

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

			case API_IP:
				return self::validateIp($rule, $data, $path, $error);

			case API_IP_RANGES:
				return self::validateIpRanges($rule, $data, $path, $error);

			case API_DNS:
				return self::validateDns($rule, $data, $path, $error);

			case API_PORT:
				return self::validatePort($rule, $data, $path, $error);

			case API_TRIGGER_EXPRESSION:
				return self::validateTriggerExpression($rule, $data, $path, $error);

			case API_EVENT_NAME:
				return self::validateEventName($rule, $data, $path, $error);

			case API_JSONRPC_PARAMS:
				return self::validateJsonRpcParams($rule, $data, $path, $error);

			case API_JSONRPC_ID:
				return self::validateJsonRpcId($rule, $data, $path, $error);

			case API_DATE:
				return self::validateDate($rule, $data, $path, $error);

			case API_NUMERIC_RANGES:
				return self::validateNumericRanges($rule, $data, $path, $error);

			case API_UUID:
				return self::validateUuid($rule, $data, $path, $error);

			case API_CUIDS:
				return self::validateCuids($rule, $data, $path, $error);

			case API_CUID:
				return self::validateCuid($rule, $data, $path, $error);

			case API_VAULT_SECRET:
				return self::validateVaultSecret($rule, $data, $path, $error);

			case API_IMAGE:
				return self::validateImage($rule, $data, $path, $error);

			case API_EXEC_PARAMS:
				return self::validateExecParams($rule, $data, $path, $error);

			case API_LAT_LNG_ZOOM:
				return self::validateLatLngZoom($rule, $data, $path, $error);

			case API_TIMESTAMP:
				return self::validateTimestamp($rule, $data, $path, $error);

			case API_TG_NAME:
				return self::validateTemplateGroupName($rule, $data, $path, $error);
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
			case API_CALC_FORMULA:
			case API_COLOR:
			case API_COLORS:
			case API_COND_FORMULA:
			case API_COND_FORMULAID:
			case API_STRING_UTF8:
			case API_INT32:
			case API_INT32_RANGES:
			case API_UINT64:
			case API_UINTS64:
			case API_FILTER:
			case API_FILTER_VALUES:
			case API_FILTER_VALUE:
			case API_FLOAT:
			case API_FLOATS:
			case API_ID:
			case API_BOOLEAN:
			case API_FLAG:
			case API_OUTPUT:
			case API_PSK:
			case API_SORTORDER:
			case API_HG_NAME:
			case API_H_NAME:
			case API_NUMERIC:
			case API_SCRIPT_MENU_PATH:
			case API_USER_MACRO:
			case API_LLD_MACRO:
			case API_TIME_PERIOD:
			case API_TIME_UNIT:
			case API_REGEX:
			case API_HTTP_POST:
			case API_VARIABLE_NAME:
			case API_URL:
			case API_IP:
			case API_IP_RANGES:
			case API_DNS:
			case API_PORT:
			case API_TRIGGER_EXPRESSION:
			case API_EVENT_NAME:
			case API_JSONRPC_PARAMS:
			case API_JSONRPC_ID:
			case API_DATE:
			case API_NUMERIC_RANGES:
			case API_UUID:
			case API_CUID:
			case API_VAULT_SECRET:
			case API_IMAGE:
			case API_EXEC_PARAMS:
			case API_UNEXPECTED:
			case API_LAT_LNG_ZOOM:
			case API_TIMESTAMP:
			case API_TG_NAME:
				return true;

			case API_OBJECT:
				return self::validateObjectUniqueness($rule, $data, $path, $error);

			case API_IDS:
			case API_STRINGS_UTF8:
			case API_INTS32:
			case API_CUIDS:
			case API_USER_MACROS:
				return self::validateStringsUniqueness($rule, $data, $path, $error);

			case API_OBJECTS:
				return self::validateObjectsUniqueness($rule, $data, $path, $error);
		}

		// For use by developers. Do not translate.
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
	 * Calculated item formula validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_LLD_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateCalcFormula($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO),
			'calculated' => true,
			'host_macro' => true,
			'empty_host' => true
		]);

		if ($expression_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $expression_parser->getError());
			return false;
		}

		$expression_validator = new CExpressionValidator([
			'usermacros' => true,
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO),
			'calculated' => true
		]);

		if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $expression_validator->getError());
			return false;
		}

		return true;
	}

	/**
	 * Color validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateColor($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (preg_match('/^[0-9a-f]{6}$/i', $data) !== 1) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_('a hexadecimal color code (6 symbols) is expected')
			);
			return false;
		}

		return true;
	}


	/**
	 * Array of colors validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateColors($rule, &$data, $path, &$error) {
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
		$rule['type'] = API_COLOR;

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateData($rule, $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Calculated condition formula validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateCondFormula(array $rule, &$data, string $path, string &$error): bool {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$condition_formula_parser = new CConditionFormula();

		if (!$condition_formula_parser->parse($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $condition_formula_parser->error);
			return false;
		}

		return true;
	}

	/**
	 * Calculated condition formula ID validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateCondFormulaId(array $rule, &$data, string $path, string &$error): bool {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if (preg_match('/^[A-Z]+$/', $data) !== 1) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('uppercase identifier expected'));
			return false;
		}

		return true;
	}

	/**
	 * Returns unescaped array of "in" rules.
	 *
	 * @static
	 *
	 * @param string $in  A comma-delimited character string. For example, 'xml,json' or '\,,.,/'.
	 *
	 * @return array  An array of "in" rules. For example, ['xml', 'json'] or [',', '.', '/'].
	 */
	private static function unescapeInRule(string $in): array {
		$result = [];
		$pos = 0;

		do {
			preg_match('/^([^,\\\\]|\\\\[,\\\\])*/', substr($in, $pos), $matches);
			$result[] = strtr($matches[0], ['\\,' => ',', '\\\\' => '\\']);
			$pos += strlen($matches[0]);

			if (!isset($in[$pos])) {
				break;
			}

			$pos++;
		}
		while (true);

		return $result;
	}

	/**
	 * String validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL
	 * @param string $rule['in']      (optional) A comma-delimited character string, for example: 'xml,json'.
	 *                                           Comma and backslash char can be escaped.
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

		if (array_key_exists('in', $rule)) {
			$in = self::unescapeInRule($rule['in']);

			if (!in_array($data, $in, true)) {
				if (($i = array_search('', $in)) !== false) {
					unset($in[$i]);
				}

				if ($i === false) {
					$error = _n('value must be %1$s', 'value must be one of %1$s', '"'.implode('", "', $in).'"',
						count($in)
					);
				}
				elseif ($in) {
					$error = _n('value must be empty or %1$s', 'value must be empty or one of %1$s',
						'"'.implode('", "', $in).'"', count($in)
					);
				}
				else {
					$error = _s('value must be empty');
				}

				$error = _s('Invalid parameter "%1$s": %2$s.', $path, $error);
				return false;
			}
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

		if ((!is_int($data) && !is_string($data)) || !preg_match('/^'.ZBX_PREG_INT.'$/', strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an integer is expected'));
			return false;
		}

		if ($data < ZBX_MIN_INT32 || $data > ZBX_MAX_INT32) {
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

	/**
	 * Unsigned integers validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_NULL
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUInt64($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_scalar($data) || is_bool($data) || is_double($data) || !ctype_digit(strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an unsigned integer is expected'));
			return false;
		}

		if (bccomp($data, ZBX_MAX_UINT64) > 0) {
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
	 * Array of unsigned integers validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUInts64($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (($flags & API_NORMALIZE) && self::validateUInt64([], $data, '', $e)) {
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
		$rules = ['type' => API_UINT64];

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
	 * Filter validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_NULL
	 * @param array  $rule['fields']
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFilter($rule, &$data, $path, &$error) {
		$rule['fields'] = array_flip($rule['fields']);

		foreach ($rule['fields'] as &$field_rule) {
			$field_rule = ['type' => API_FILTER_VALUES, 'flags' => API_ALLOW_NULL | API_NORMALIZE];
		}
		unset($field_rule);

		return self::validateData(['type' => API_OBJECT] + $rule, $data, $path, $error);
	}

	/**
	 * Filter values validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_ALLOW_NULL, API_NORMALIZE
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFilterValues($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (($flags & API_NORMALIZE) && self::validateFilterValue([], $data, '', $e)) {
			$data = [$data];
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		$data = array_values($data);
		$rules = ['type' => API_FILTER_VALUE];

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
	 * Filter value validator.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFilterValue($rule, &$data, $path, &$error) {
		if (!is_string($data) && !is_double($data) && !is_int($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_('a character string, integer or floating point value is expected')
			);
			return false;
		}

		if (is_string($data) &&  mb_check_encoding($data, 'UTF-8') !== true) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid byte sequence in UTF-8'));
			return false;
		}

		return true;
	}

	/**
	 * Floating point number validator.
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
	private static function validateFloat($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (is_int($data) || is_float($data)) {
			$value = (float) $data;
		}
		elseif (is_string($data)) {
			$number_parser = new CNumberParser();

			if ($number_parser->parse($data) == CParser::PARSE_SUCCESS) {
				$value = (float) $number_parser->getMatch();
			}
			else {
				$value = NAN;
			}
		}
		else {
			$value = NAN;
		}

		if (is_nan($value)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a floating point value is expected'));

			return false;
		}

		if (!self::checkFloatIn($rule, $value, $path, $error)) {
			return false;
		}

		$data = $value;

		return true;
	}

	/**
	 * Array of floating point numbers validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFloats($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (($flags & API_NORMALIZE) && self::validateFloat([], $data, '', $e)) {
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
		$rules = ['type' => API_FLOAT];

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateData($rules, $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	private static function isInRange($data, $in) {
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

		$valid = self::isInRange($data, $rule['in']);

		if (!$valid) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _n('value must be %1$s', 'value must be one of %1$s',
				strtr($rule['in'], [',' => ', ', ':' => '-']), (strpbrk($rule['in'], ',:') === false) ? 1 : 2
			));
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
	private static function checkFloatIn($rule, $data, $path, &$error) {
		if (!array_key_exists('in', $rule)) {
			return true;
		}

		$valid = self::isInRange($data, $rule['in']);

		if (!$valid) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_s('value must be within the range of %1$s', str_replace([',', ':'], [', ', '-'], $rule['in']))
			);
		}

		return $valid;
	}

	/**
	 * Array of integers validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE
	 * @param string $rule['in']      (optional) a comma-delimited character string, for example: '0,60:900'
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
	 * Validate integer ranges.
	 * Example:
	 *   0-100,200,300-400
	 *
	 * @static
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY
	 * @param int    $rule['length']  (optional)
	 * @param string $rule['in']      (optional) A comma-delimited character string, for example: '0,60:900'
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateInt32Ranges(array $rule, &$data, string $path, string &$error): bool {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags, $data, $path, $error) === false) {
			return false;
		}

		if ($data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$parser = new CRangesParser(['with_minus' => true]);

		if ($parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid range expression'));
			return false;
		}

		foreach ($parser->getRanges() as $ranges) {
			foreach ($ranges as $range) {
				if (!self::checkInt32In($rule, $range, $path, $error)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Identifier validator.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateId($rule, &$data, $path, &$error) {
		if (!is_scalar($data) || is_bool($data) || is_double($data) || !ctype_digit(strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is expected'));
			return false;
		}

		if (bccomp($data, ZBX_DB_MAX_ID) > 0) {
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
	 * @param int    $rule['flags']  (optional) API_ALLOW_NULL
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateBoolean($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

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
	 * @param array  $rule
	 * @param int    $rule['flags']                                   (optional) API_ALLOW_NULL
	 * @param array  $rule['fields']
	 * @param int    $rule['fields'][<field_name>]['flags']           (optional) API_REQUIRED, API_DEPRECATED,
	 *                                                                           API_ALLOW_UNEXPECTED
	 * @param string $rule['fields'][<field_name>]['replacement']     (optional) Parameter name which replaces the
	 *                                                                           deprecated one. Can be used with
	 *                                                                           API_DEPRECATED flag.
	 * @param mixed  $rule['fields'][<field_name>]['default']         (optional)
	 * @param string $rule['fields'][<field_name>]['default_source']  (optional)
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
		if (!($flags & API_ALLOW_UNEXPECTED)) {
			foreach ($data as $field_name => $value) {
				if (!$rule['fields']) {
					$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('should be empty'));
					return false;
				}

				if (!array_key_exists($field_name, $rule['fields'])) {
					$error = _s('Invalid parameter "%1$s": %2$s.', $path,
						_s('unexpected parameter "%1$s"', $field_name)
					);
					return false;
				}
			}
		}

		// validation of the values type
		foreach ($rule['fields'] as $field_name => $field_rule) {
			while ($field_rule['type'] == API_MULTIPLE) {
				$matched_multiple_rule = null;

				foreach ($field_rule['rules'] as $multiple_rule) {
					if (array_key_exists('else', $multiple_rule)
							|| (is_array($multiple_rule['if'])
								&& self::isInRange($data[$multiple_rule['if']['field']], $multiple_rule['if']['in']))
							|| ($multiple_rule['if'] instanceof Closure
								&& call_user_func($multiple_rule['if'], $data))) {
						$field_rule += ['flags' => 0x00];
						$multiple_rule += ['flags' => 0x00];
						$multiple_rule['flags'] = ($field_rule['flags'] & API_REQUIRED) | $multiple_rule['flags'];
						$matched_multiple_rule = $multiple_rule +
							array_intersect_key($field_rule, array_flip(['default', 'default_source']));
						break;
					}
				}

				if ($matched_multiple_rule === null) {
					// For use by developers. Do not translate.
					$error = 'Incorrect API_MULTIPLE validation rules.';
					return false;
				}

				$field_rule = $matched_multiple_rule;
			}

			if ($field_rule['type'] === API_UNEXPECTED
					&& !self::validateUnexpected($field_name, $field_rule, $data, $path, $error)) {
				return false;
			}

			$flags = array_key_exists('flags', $field_rule) ? $field_rule['flags'] : 0x00;

			if (array_key_exists($field_name, $data) && ($flags & API_DEPRECATED)) {
				$subpath = ($path === '/' ? $path : $path.'/').$field_name;

				if (array_key_exists('replacement', $field_rule)) {
					if (array_key_exists($field_rule['replacement'], $data)) {
						$error = _s('Deprecated parameter "%1$s" cannot be used with "%2$s".', $subpath,
							($path === '/' ? $path : $path.'/').$field_rule['replacement']
						);
						return false;
					}

					$data[$field_rule['replacement']] = $data[$field_name];
					unset($data[$field_name]);
				}

				trigger_error(_s('Parameter "%1$s" is deprecated.', $subpath), E_USER_DEPRECATED);
			}

			if (array_key_exists('default', $field_rule) && !array_key_exists($field_name, $data)) {
				$data[$field_name] = $field_rule['default'];
			}

			if (array_key_exists('default_source', $field_rule) && !array_key_exists($field_name, $data)) {
				$data[$field_name] = $data[$field_rule['default_source']];
			}

			if (array_key_exists('compare', $field_rule)) {
				$field_rule['compare']['path'] = ($path === '/' ? $path : $path.'/').$field_rule['compare']['field'];
				$field_rule['compare']['value'] = $data[$field_rule['compare']['field']];
			}

			if (array_key_exists($field_name, $data)) {
				$subpath = ($path === '/' ? $path : $path.'/').$field_name;
				if (!self::validateData($field_rule, $data[$field_name], $subpath, $error)) {
					return false;
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
	 * API output validator.
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

		if (is_string($data)) {
			$in = ($flags & API_ALLOW_COUNT) ? implode(',', [API_OUTPUT_EXTEND, API_OUTPUT_COUNT]) : API_OUTPUT_EXTEND;

			return self::validateData(['type' => API_STRING_UTF8, 'in' => $in], $data, $path, $error);
		}

		$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array or a character string is expected'));

		return false;
	}

	/**
	 * PSK key validator.
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
	private static function validatePSK($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		$mb_len = mb_strlen($data);

		if ($mb_len != 0 && $mb_len < PSK_MIN_LEN) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _s('minimum length is %1$s characters', PSK_MIN_LEN));
			return false;
		}

		if (preg_match('/^([0-9a-f]{2})*$/i', $data) !== 1) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_('an even number of hexadecimal characters is expected')
			);
			return false;
		}

		if (array_key_exists('length', $rule) && $mb_len > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		return true;
	}

	/**
	 * API sort order validator.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateSortOrder($rule, &$data, $path, &$error) {
		$in = ZBX_SORT_UP.','.ZBX_SORT_DOWN;

		if (self::validateStringUtf8(['in' => $in], $data, $path, $e)) {
			return true;
		}

		if (is_string($data)) {
			$error = $e;
			return false;
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array or a character string is expected'));
			return false;
		}

		$data = array_values($data);
		$rules = [
			'type' => API_STRING_UTF8,
			'in' => $in
		];

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
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_NULL, API_NORMALIZE, API_PRESERVE_KEYS,
	 *                                           API_ALLOW_UNEXPECTED
	 * @param array  $rule['fields']
	 * @param int    $rule['length']  (optional)
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

		if (array_key_exists('length', $rule) && count($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if (($flags & API_NORMALIZE) && $data) {
			reset($data);

			if (!is_int(key($data))) {
				$data = [$data];
			}
		}

		if (!($flags & API_PRESERVE_KEYS)) {
			$data = array_values($data);
		}

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateObject(['flags' => ($flags & API_ALLOW_UNEXPECTED), 'fields' => $rule['fields']], $value,
					$subpath, $error)) {
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
	 * @param int    $rule['flags']   (optional) API_REQUIRED_LLD_MACRO
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

		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;
		$host_group_name_parser = new CHostGroupNameParser(['lldmacros' => ($flags & API_REQUIRED_LLD_MACRO)]);

		if ($host_group_name_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid host group name'));

			return false;
		}

		if (($flags & API_REQUIRED_LLD_MACRO) && !$host_group_name_parser->getMacros()) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_('must contain at least one low-level discovery macro')
			);

			return false;
		}

		return true;
	}

	/**
	 * Host name validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_REQUIRED_LLD_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateHostName($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));

			return false;
		}

		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;
		$host_name_parser = new CHostNameParser(['lldmacros' => ($flags & API_REQUIRED_LLD_MACRO)]);

		// For example, host prototype name MUST contain macros.
		if ($host_name_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid host name'));

			return false;
		}

		if (($flags & API_REQUIRED_LLD_MACRO) && !$host_name_parser->getMacros()) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_('must contain at least one low-level discovery macro')
			);

			return false;
		}

		return true;
	}

	/**
	 * Validator for numeric data with optional suffix.
	 * Supported time suffixes: s, m, h, d, w
	 * Supported metric suffixes: K, M, G, T
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
	private static function validateNumeric($rule, &$data, $path, &$error) {
		global $DB;

		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (is_int($data)) {
			$data = (string) $data;
		}

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));

			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		if ($number_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is expected'));

			return false;
		}

		$value = $number_parser->calcValue();

		if ($DB['DOUBLE_IEEE754']) {
			if (abs($value) > ZBX_FLOAT_MAX) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));

				return false;
			}
		}
		else {
			if (abs($value) >= 1E+16) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));

				return false;
			}
			elseif ($value != round($value, 4)) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number has too many fractional digits'));

				return false;
			}
		}

		// Remove leading zeros.
		$data = preg_replace('/^(-)?(0+)?(\d.*)$/', '${1}${3}', $data);

		// Add leading zero.
		$data = preg_replace('/^(-)?(\..*)$/', '${1}0${2}', $data);

		return true;
	}

	/**
	 * Global script menu_path validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional)
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateScriptMenuPath($rule, &$data, $path, &$error) {
		// Having only a root folder is the same as being empty. Temporary modify data to check if it is actually empty.
		$tmp_data = $data;

		if ($tmp_data === '/') {
			$tmp_data = '';
		}

		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags, $tmp_data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		// If empty is allowed there is only root folder, return early.
		if ($data === '/') {
			return true;
		}

		$folders = splitPath($data);
		$folders = array_map('trim', $folders);
		$count = count($folders);

		// folder1/{empty}/name or folder1/folder2/{empty}
		foreach ($folders as $num => $folder) {
			// Allow the trailing slash.
			if ($folder === '' && $num != ($count - 1) && $num != 0) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('directory cannot be empty'));

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

		$user_macro_parser = new CUserMacroParser();

		if ($user_macro_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $user_macro_parser->getError());
			return false;
		}

		return true;
	}

	/**
	 * Array of strings validator.
	 *
	 * @param array   $rule
	 * @param int     $rule['flags']   (optional) API_NORMALIZE
	 * @param integer $rule['length']  (optional)
	 * @param mixed   $data
	 * @param string  $path
	 * @param string  $error
	 *
	 * @return bool
	 */
	private static function validateUserMacros(array $rule, &$data, string $path, ?string &$error): bool {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_NORMALIZE) && self::validateUserMacro([], $data, '', $e)) {
			$data = [$data];
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		$data = array_values($data);
		$rules = ['type' => API_USER_MACRO];

		if (array_key_exists('length', $rule)) {
			$rules['length'] = $rule['length'];
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
	 * LLD macro validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateLLDMacro($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if ((new CLLDMacroParser())->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a low-level discovery macro is expected'));
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
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_GLOBAL_REGEX
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

		if (($flags & API_ALLOW_GLOBAL_REGEX) && $data !== '' && $data[0] === '@') {
			return true;
		}

		if (@preg_match('/'.str_replace('/', '\/', $data).'/', '') === false) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid regular expression'));
			return false;
		}

		return true;
	}

	/**
	 * Time unit validator like "10", "20s", "30m", "4h", "{$TIME}" etc.
	 *
	 * @param array  $rule
	 * @param int    $rule['length'] (optional)
	 * @param int    $rule['flags']  (optional) API_NOT_EMPTY, API_ALLOW_USER_MACRO, API_ALLOW_LLD_MACRO,
	 *                                          API_TIME_UNIT_WITH_YEAR
	 * @param int    $rule['in']     (optional)
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

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		$simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => ($flags & API_ALLOW_USER_MACRO),
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO),
			'negative' => true,
			'with_year' => ($flags & API_TIME_UNIT_WITH_YEAR)
		]);

		if ($simple_interval_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a time unit is expected'));
			return false;
		}

		if (($flags & (API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO)) && $data[0] === '{') {
			return true;
		}

		$seconds = timeUnitToSeconds($data, ($flags & API_TIME_UNIT_WITH_YEAR));

		if ($seconds < ZBX_MIN_INT32 || $seconds > ZBX_MAX_INT32) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is too large'));
			return false;
		}

		return self::checkInt32In($rule, $seconds, $path, $error);
	}

	/**
	 * Array of ids, int32 or strings uniqueness validator.
	 *
	 * @param bool       $rule
	 * @param integer    $rule['type']
	 * @param bool       $rule['uniq']    (optional)
	 * @param array|null $data
	 * @param string     $path
	 * @param string     $error
	 *
	 * @return bool
	 */
	private static function validateStringsUniqueness($rule, ?array $data, $path, &$error) {
		// $data can be NULL when API_ALLOW_NULL is set
		if ($data === null) {
			return true;
		}

		if (!array_key_exists('uniq', $rule) || $rule['uniq'] === false) {
			return true;
		}

		$uniq = [];

		foreach ($data as $index => $value) {
			$uniq_value = ($rule['type'] == API_USER_MACROS)
				? self::trimMacro($value)
				: $value;

			if (array_key_exists($uniq_value, $uniq)) {
				$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
				$error = _s('Invalid parameter "%1$s": %2$s.', $subpath,
					_s('value %1$s already exists', '('.$value.')')
				);
				return false;
			}
			$uniq[$uniq_value] = true;
		}

		return true;
	}

	/**
	 * Returns macro without spaces and curly braces.
	 *
	 * "{$MACRO}" => "MACRO"
	 * "{$MACRO:}" => "MACRO:context:"
	 * "{$MACRO: /var}" => "MACRO:context:/var"
	 * "{$MACRO: /"var"}" => "MACRO:context:/var"
	 * "{$MACRO:regex: ^[a-z]+}" => "MACRO:regex:^[a-z]+"
	 *
	 * @param string $macro
	 *
	 * @return string
	 */
	public static function trimMacro(string $macro): string {
		$user_macro_parser = new CUserMacroParser();

		$user_macro_parser->parse($macro);

		$macro = $user_macro_parser->getMacro();
		$context = $user_macro_parser->getContext();
		$regex = $user_macro_parser->getRegex();

		if ($context !== null) {
			$macro .= ':context:'.$context;
		}
		elseif ($regex !== null) {
			$macro .= ':regex:'.$regex;
		}

		return $macro;
	}

	/**
	 * Array of objects uniqueness validator.
	 *
	 * @param array      $rule
	 * @param array      $rule['uniq']    (optional) subsets of unique fields ([['hostid', 'name'], [...]])
	 * @param array      $rule['fields']
	 * @param array|null $data
	 * @param string     $path
	 * @param string     $error
	 *
	 * @return bool
	 */
	private static function validateObjectsUniqueness($rule, ?array $data, $path, &$error) {
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

						$value = ($rule['fields'][$field_name]['type'] == API_USER_MACRO)
							? self::trimMacro($object[$field_name])
							: $object[$field_name];

						if ($level < count($field_names)) {
							if (!array_key_exists($value, $_uniq)) {
								$_uniq[$value] = [];
							}

							$_uniq = &$_uniq[$value];
						}
						else {
							if (array_key_exists($value, $_uniq)) {
								$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
								$error = _s('Invalid parameter "%1$s": %2$s.', $subpath, _s('value %1$s already exists',
									'('.implode(', ', $field_names).')=('.implode(', ', $values).')'
								));
								return false;
							}

							$_uniq[$value] = true;
						}

						$level++;
					}
				}
			}
		}

		foreach ($data as $index => $object) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);

			if (!self::validateObjectUniqueness(['fields' => $rule['fields']], $object, $subpath, $error)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Object uniqueness validator.
	 *
	 * @param            $rule
	 * @param array|null $data
	 * @param            $path
	 * @param            $error
	 *
	 * @return bool
	 */
	private static function validateObjectUniqueness($rule, ?array $data, $path, &$error): bool {
		if ($data === null) {
			return true;
		}

		foreach ($rule['fields'] as $field_name => $field_rule) {
			if (array_key_exists($field_name, $data)) {
				while ($field_rule['type'] == API_MULTIPLE) {
					$matched_multiple_rule = null;

					foreach ($field_rule['rules'] as $multiple_rule) {
						if (array_key_exists('else', $multiple_rule)
								|| (is_array($multiple_rule['if']) && self::isInRange(
									$data[$multiple_rule['if']['field']], $multiple_rule['if']['in']))
								|| ($multiple_rule['if'] instanceof Closure
									&& call_user_func($multiple_rule['if'], $data))) {
							$matched_multiple_rule = $multiple_rule;
							break;
						}
					}

					if ($matched_multiple_rule === null) {
						// For use by developers. Do not translate.
						$error = 'Incorrect API_MULTIPLE validation rules.';
						return false;
					}

					$field_rule = $matched_multiple_rule;
				}

				$subpath = ($path === '/' ? $path : $path.'/').$field_name;

				if (!self::validateDataUniqueness($field_rule, $data[$field_name], $subpath, $error)) {
					return false;
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
	 * @param int    $rule['flags']   (optional) API_ALLOW_USER_MACRO, API_ALLOW_EVENT_TAGS_MACRO, API_NOT_EMPTY
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUrl($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$options = [
			'allow_user_macro' => (bool) ($flags & API_ALLOW_USER_MACRO),
			'allow_event_tags_macro' => (bool) ($flags & API_ALLOW_EVENT_TAGS_MACRO)
		];

		if ($data !== '' && CHtmlUrlValidator::validate($data, $options) === false) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('unacceptable URL'));
			return false;
		}

		return true;
	}

	/**
	 * IP address validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_USER_MACRO, API_ALLOW_LLD_MACRO,
	 *                                API_ALLOW_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateIp($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$ip_parser = new CIPParser([
			'v6' => ZBX_HAVE_IPV6,
			'usermacros' => ($flags & API_ALLOW_USER_MACRO),
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO),
			'macros' => ($flags & API_ALLOW_MACRO)
		]);

		if ($ip_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an IP address is expected'));
			return false;
		}

		return true;
	}

	/**
	 * Validate IP ranges. Multiple IPs separated by comma character.
	 * Example:
	 *   127.0.0.1,192.168.1.1-254,192.168.2.1-100,192.168.3.0/24
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_DNS, API_ALLOW_RANGE
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateIpRanges($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags, $data, $path, $error) === false) {
			return false;
		}

		if ($data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$ip_range_parser = new CIPRangeParser([
			'v6' => ZBX_HAVE_IPV6,
			'dns' => ($flags & API_ALLOW_DNS),
			'ranges' => ($flags & API_ALLOW_RANGE)
		]);

		if (!$ip_range_parser->parse($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $ip_range_parser->getError());
			return false;
		}

		return true;
	}

	/**
	 * DNS name validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_USER_MACRO, API_ALLOW_LLD_MACRO,
	 *                                API_ALLOW_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateDns($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$dns_parser = new CDnsParser([
			'usermacros' => ($flags & API_ALLOW_USER_MACRO),
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO),
			'macros' => ($flags & API_ALLOW_MACRO)
		]);

		if ($dns_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a DNS name is expected'));
			return false;
		}

		return true;
	}

	/**
	 * Port number validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY, API_ALLOW_USER_MACRO, API_ALLOW_LLD_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validatePort($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (!is_int($data) && !is_string($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a number is expected'));
			return false;
		}

		$data = (string) $data;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if ($flags & API_ALLOW_USER_MACRO) {
			$user_macro_parser = new CUserMacroParser();

			if ($user_macro_parser->parse($data) == CParser::PARSE_SUCCESS) {
				return true;
			}
		}

		if ($flags & API_ALLOW_LLD_MACRO) {
			$lld_macro_parser = new CLLDMacroParser();

			if ($lld_macro_parser->parse($data) == CParser::PARSE_SUCCESS) {
				return true;
			}
		}

		if (!self::validateInt32(['in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER], $data, $path, $error)) {
			return false;
		}

		$data = (string) $data;

		return true;
	}

	/**
	 * Trigger expression validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param int    $rule['flags']   (optional) API_ALLOW_LLD_MACRO, API_NOT_EMPTY
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateTriggerExpression($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO)
		]);

		if ($expression_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $expression_parser->getError());
			return false;
		}

		$expression_validator = new CExpressionValidator([
			'usermacros' => true,
			'lldmacros' => ($flags & API_ALLOW_LLD_MACRO)
		]);

		if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $expression_validator->getError());
			return false;
		}

		return true;
	}

	/**
	 * Event name validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateEventName($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(0, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$eventname_validator = new CEventNameValidator();

		if (!$eventname_validator->validate($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $eventname_validator->getError());
			return false;
		}

		return true;
	}

	/**
	 * JSON RPC parameters validator. Parameters MUST contain an array or object value.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateJsonRpcParams($rule, &$data, $path, &$error) {
		if (is_array($data)) {
			return true;
		}

		$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array or object is expected'));

		return false;
	}

	/**
	 * JSON RPC identifier validator. This identifier MUST contain a String, Number, or NULL value.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateJsonRpcId($rule, &$data, $path, &$error) {
		if (is_string($data) || is_int($data) || is_float($data) || $data === null) {
			return true;
		}

		$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a string, number or null value is expected'));

		return false;
	}

	/**
	 * Date validator in YYYY-MM-DD format.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']  (optional) API_NOT_EMPTY
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateDate(array $rule, &$data, string $path, string &$error): bool {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		$date = DateTime::createFromFormat(ZBX_DATE, $data);

		if (!$date || $date->format(ZBX_DATE) !== $data) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a date in YYYY-MM-DD format is expected'));
			return false;
		}

		if (!validateDateInterval($date->format('Y'), $date->format('m'), $date->format('d'))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_s('value must be between "%1$s" and "%2$s"', '1970-01-01', '2038-01-18')
			);
			return false;
		}

		return true;
	}

	/**
	 * Validate numeric ranges. Multiple ranges separated by comma character.
	 * Example:
	 *   10-20,-20--10,-5-0,0.5-0.7,-20--10,-20.20--20.10
	 *   30,-10,0.7,-0.5
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
	private static function validateNumericRanges($rule, &$data, $path, &$error) {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$parser = new CRangesParser(['with_minus' => true, 'with_float' => true, 'with_suffix' => true]);

		if ($parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid range expression'));

			return false;
		}

		return true;
	}

	/**
	 * UUIDv4 validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']  (optional) API_NOT_EMPTY
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUuid(array $rule, &$data, string $path, string &$error): bool {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (mb_strlen($data) != 32) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _s('must be %1$s characters long', 32));
			return false;
		}

		if (!ctype_xdigit($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('UUIDv4 is expected'));
			return false;
		}

		$binary = hex2bin($data);
		if ((ord($binary[6]) & 0xf0) != 0x40 || (ord($binary[8]) & 0xc0) != 0x80) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('UUIDv4 is expected'));
			return false;
		}

		$data = strtolower($data);

		return true;
	}

	/**
	 * Array of CUIDS validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']  (optional) API_ALLOW_NULL, API_NORMALIZE
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateCuids(array $rule, &$data, string $path, ?string &$error): bool {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (($flags & API_NORMALIZE) && self::validateCuid([], $data, '', $e)) {
			$data = [$data];
		}
		unset($e);

		if (!is_array($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an array is expected'));
			return false;
		}

		$data = array_values($data);

		foreach ($data as $index => &$value) {
			$subpath = ($path === '/' ? $path : $path.'/').($index + 1);
			if (!self::validateCuid([], $value, $subpath, $error)) {
				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * CUID validator.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateCuid(array $rule, &$data, string $path, ?string &$error): bool {
		if (self::checkStringUtf8(0, $data, $path, $error) === false) {
			return false;
		}

		if (!CCuid::checkLength($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_s('must be %1$s characters long', CCuid::LENGTH)
			);
			return false;
		}

		if (!CCuid::isCuid($data)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('CUID is expected'));
			return false;
		}

		return true;
	}

	/**
	 * User vault secret.
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateVaultSecret($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		$options = [];
		$providers = [ZBX_VAULT_TYPE_HASHICORP, ZBX_VAULT_TYPE_CYBERARK];

		if (array_key_exists('provider', $rule)) {
			if (!in_array($rule['provider'], $providers)) {
				$error = _s('value must be one of %1$s', implode(', ', $providers));
				return false;
			}
			else {
				$options['provider'] = $rule['provider'];
			}
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$vault_secret_parser = new CVaultSecretParser($options);

		if ($vault_secret_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, $vault_secret_parser->getError());
			return false;
		}

		return true;
	}

	/**
	 * Validate image.
	 *
	 * @param array  $rule
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateImage($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(0, $data, $path, $error) === false) {
			return false;
		}

		$data = base64_decode($data);

		if (bccomp(strlen($data), ZBX_MAX_IMAGE_SIZE) == 1) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_s('image size must be less than %1$s', convertUnits(['value' => ZBX_MAX_IMAGE_SIZE, 'units' => 'B']))
			);
			return false;
		}

		if (@imageCreateFromString($data) === false) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('file format is unsupported'));
			return false;
		}

		return true;
	}

	/**
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_NOT_EMPTY
	 * @param int    $rule['length']  (optional)
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateExecParams(array $rule, &$data, string $path, string &$error): bool {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (self::checkStringUtf8($flags & API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (($flags & API_NOT_EMPTY) == 0 && $data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		if ($data !== '' && mb_substr($data, -1) !== "\n") {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('the last new line feed is missing'));
			return false;
		}

		return true;
	}

	/**
	 * Check if input value matches one of following formats:
	 *  - <latitude>,<longitude>,<zoom>
	 *  - <latitude>,<longitude>
	 *
	 * @param array  $rule
	 * @param int    $rule['length']  (optional)
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateLatLngZoom(array $rule, &$data, string $path, string &$error): bool {
		if ($data === '') {
			return true;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));
			return false;
		}

		$geoloc_parser = new CGeomapCoordinatesParser();

		if ($geoloc_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path,
				_('geographical coordinates (values of comma separated latitude and longitude) are expected')
			);
			return false;
		}

		if (array_key_exists('zoom', $geoloc_parser->result) && $geoloc_parser->result['zoom'] > ZBX_GEOMAP_MAX_ZOOM) {
			$error = _s('Invalid zoomparameter "%1$s": %2$s.', $path,
				_s('zoom level must be between "%1$s" and "%2$s"', 0, ZBX_GEOMAP_MAX_ZOOM)
			);
			return false;
		}

		return true;
	}

	/**
	 * Timestamp validator.
	 *
	 * @param array  $rule
	 * @param int    $rule[flags]    (optional) API_ALLOW_NULL
	 * @param string $rule[in]       (optional) A comma-delimited character string, for example: '0,60:900'.
	 * @param array  $rule[compare]  (optional) Data of the object field to compare against.
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateTimestamp(array $rule, &$data, string $path, string &$error): bool {
		$flags = array_key_exists('flags', $rule) ? $rule['flags'] : 0x00;

		if (($flags & API_ALLOW_NULL) && $data === null) {
			return true;
		}

		if (!is_scalar($data) || is_bool($data) || is_double($data) || !ctype_digit(strval($data))) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('an unsigned integer is expected'));

			return false;
		}

		if (bccomp($data, ZBX_MAX_DATE) > 0) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('a timestamp is too large'));

			return false;
		}

		if (!self::checkTimestampIn($rule, $data, $path, $error)) {
			return false;
		}

		if (!self::checkCompare($rule, $data, $path, $error)) {
			return false;
		}

		if (is_string($data)) {
			$data = (int) $data;
		}

		return true;
	}

	/**
	 * @param array  $rule
	 * @param string $rule['in']  (optional)
	 * @param int    $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkTimestampIn(array $rule, $data, string $path, string &$error): bool {
		if (!array_key_exists('in', $rule)) {
			return true;
		}

		$valid = self::isInRange($data, $rule['in']);

		if (!$valid) {
			$format = array_key_exists('format', $rule) ? $rule['format'] : ZBX_FULL_DATE_TIME;
			$in = explode(',', $rule['in']);
			$formatted_in = '';

			if (array_key_exists('timezone', $rule)) {
				$default_timezone = date_default_timezone_get();
				date_default_timezone_set('UTC');
			}

			foreach ($in as $i => $el) {
				if (strpos($el, ':')) {
					[$from, $to] = explode(':', $el);

					$formatted_in .= date($format, $from).'-'.date($format, $to);
				}
				else {
					$formatted_in .= date($format, $el);
				}

				if (array_key_exists($i + 1, $in)) {
					$formatted_in .= ', ';
				}
			}

			if (array_key_exists('timezone', $rule)) {
				date_default_timezone_set($default_timezone);
			}

			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _n('value must be %1$s', 'value must be one of %1$s',
				$formatted_in, (strpbrk($rule['in'], ',:') === false) ? 1 : 2
			));
		}

		return $valid;
	}

	/**
	 * Template group name validator.
	 *
	 * @param array  $rule
	 * @param int    $rule['flags']   (optional) API_REQUIRED_LLD_MACRO
	 * @param int    $rule['length']  (optional)
	 * @param mixed  $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateTemplateGroupName($rule, &$data, $path, &$error) {
		if (self::checkStringUtf8(API_NOT_EMPTY, $data, $path, $error) === false) {
			return false;
		}

		if (array_key_exists('length', $rule) && mb_strlen($data) > $rule['length']) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('value is too long'));

			return false;
		}

		$template_group_name_parser = new CHostGroupNameParser();

		if ($template_group_name_parser->parse($data) != CParser::PARSE_SUCCESS) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _('invalid template group name'));

			return false;
		}

		return true;
	}

	/**
	 * @param array  $rule
	 * @param array  $rule[compare]            (optional)
	 * @param string $rule[compare][operator]
	 * @param string $rule[compare][path]
	 * @param mixed  $rule[compare][value]
	 * @param int    $data
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkCompare(array $rule, $data, string $path, string &$error): bool {
		if (!array_key_exists('compare', $rule)) {
			return true;
		}

		switch ($rule['compare']['operator']) {
			case '>':
				if ($data <= $rule['compare']['value']) {
					$error = _s('Invalid parameter "%1$s": %2$s.', $path,
						_s('cannot be less than or equal to the value of parameter "%1$s"', $rule['compare']['path'])
					);

					return false;
				}
				break;

			default:
				$error = 'Incorrect validation rules.';

				return false;
		}

		return true;
	}

	/**
	 * Unexpected validator.
	 *
	 * @param string $field_name
	 * @param array  $field_rule
	 * @param string $field_rule[error_type]  (optional) API_ERR_INHERITED, API_ERR_DISCOVERED
	 * @param array  $object
	 * @param string $path
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateUnexpected(string $field_name, array $field_rule, array $object, string $path,
			string &$error): bool {
		if (!array_key_exists($field_name, $object)) {
			return true;
		}

		if (!array_key_exists('error_type', $field_rule)) {
			$error = _s('Invalid parameter "%1$s": %2$s.', $path, _s('unexpected parameter "%1$s"', $field_name));

			return false;
		}

		switch ($field_rule['error_type']) {
			case API_ERR_INHERITED:
				$error = _s('Invalid parameter "%1$s": %2$s.', $path,
					_s('cannot update readonly parameter "%1$s" of inherited object', $field_name)
				);
				break;

			case API_ERR_DISCOVERED:
				$error = _s('Invalid parameter "%1$s": %2$s.', $path,
					_s('cannot update readonly parameter "%1$s" of discovered object', $field_name)
				);
				break;

			default:
				$error = 'Incorrect validation rules.';
		}

		return false;
	}
}
