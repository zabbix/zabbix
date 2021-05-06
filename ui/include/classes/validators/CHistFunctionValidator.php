<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class for validating history functions.
 */
class CHistFunctionValidator extends CValidator {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'parameters' => []     Definition of parameters of known history functions.
	 *   'calculated' => false  Validate history function as part of calculated item formula.
	 *
	 * @var array
	 */
	private $options = [
		'parameters' => [],
		'calculated' => false
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Validate history function.
	 *
	 * @param array $token  A token of CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION type.
	 *
	 * @return bool
	 */
	public function validate($token) {
		$invalid_param_labels = [
			_('Invalid first parameter.'),
			_('Invalid second parameter.'),
			_('Invalid third parameter.'),
			_('Invalid fourth parameter.'),
			_('Invalid fifth parameter.')
		];

		if (!array_key_exists($token['data']['function'], $this->options['parameters'])) {
			$this->setError(_s('Unknown function "%1$s".', $token['data']['function']));

			return false;
		}

		$params = $token['data']['parameters'];
		$params_spec = $this->options['parameters'][$token['data']['function']];

		if (count($params) > count($params_spec)) {
			$this->setError(_s('Incorrect usage of function "%1$s".', $token['data']['function']).' '.
				_('Invalid number of parameters.')
			);

			return false;
		}

		foreach ($params_spec as $index => $param_spec) {
			$required = !array_key_exists('required', $param_spec) || $param_spec['required'];

			if ($index >= count($params) || $params[$index]['match'] === '') {
				if ($required) {
					$this->setError(_s('Incorrect usage of function "%1$s".', $token['data']['function']).' '.
						_('Mandatory parameter is missing.')
					);

					return false;
				}

				continue;
			}

			$param = $params[$index];

			switch ($param['type']) {
				case CHistFunctionParser::PARAM_TYPE_PERIOD:
					if (self::hasMacros($param['data']['sec_num']) && $param['data']['time_shift'] === '') {
						continue 2;
					}
					break;

				case CHistFunctionParser::PARAM_TYPE_QUOTED:
					if (self::hasMacros(CHistFunctionParser::unquoteParam($param['match']))) {
						continue 2;
					}
					break;

				case CHistFunctionParser::PARAM_TYPE_UNQUOTED:
					if (self::hasMacros($param['match'])) {
						continue 2;
					}
					break;
			}

			if (array_key_exists('rules', $param_spec)) {
				if (!self::validateRules($param, $param_spec['rules'], $required, $this->options)) {
					$this->setError(_s('Incorrect usage of function "%1$s".', $token['data']['function']).' '.
						$invalid_param_labels[$index]
					);

					return false;
				}
			}
		}

		return true;
	}

	private static function hasMacros(string $value): bool {
		return (strpos($value, '{') !== false);
	}

	private static function validateRules(array $param, array $rules, bool $required, array $options): bool {
		$param_match_unquoted = ($param['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED)
			? CHistFunctionParser::unquoteParam($param['match'])
			: $param['match'];

		foreach ($rules as $rule) {
			switch ($rule['type']) {
				case 'query':
					if ($param['type'] != CHistFunctionParser::PARAM_TYPE_QUERY) {
						return false;
					}

					if (!self::validateQuery($param['data']['host'], $param['data']['item'], $options)) {
						return false;
					}

					break;

				case 'period':
					if ($param['type'] != CHistFunctionParser::PARAM_TYPE_PERIOD) {
						return false;
					}

					if ($required && $param['data']['sec_num'] === '') {
						return false;
					}

					if (!self::validatePeriod($param['data']['sec_num'], $param['data']['time_shift'], $rule['mode'])) {
						return false;
					}

					break;

				case 'number':
					$with_suffix = array_key_exists('with_suffix', $rule) && $rule['with_suffix'];

					$parser = new CNumberParser(['with_minus' => true, 'with_suffix' => $with_suffix]);

					if ($parser->parse($param_match_unquoted) != CParser::PARSE_SUCCESS) {
						return false;
					}

					$value = $parser->calcValue();

					if ((array_key_exists('min', $rule) && $value < $rule['min'])
							|| array_key_exists('max', $rule) && $value > $rule['max']) {
						return false;
					}

					break;

				case 'regexp':
					if (preg_match($rule['pattern'], $param_match_unquoted) != 1) {
						return false;
					}

					break;

				case 'time':
					$with_year = array_key_exists('with_year', $rule) && $rule['with_year'];
					$min = array_key_exists('min', $rule) ? $rule['min'] : ZBX_MIN_INT32;
					$max = array_key_exists('max', $rule) ? $rule['max'] : ZBX_MAX_INT32;

					$sec = timeUnitToSeconds($param_match_unquoted, $with_year);

					if ($sec === null || $sec < $min || $sec > $max) {
						return false;
					}

					break;

				default:
					return false;
			}
		}

		return true;
	}

	private static function validateQuery(string $host, string $item, array $options): bool {
		if (!$options['calculated']) {
			return true;
		}

		return ($host !== CQueryParser::HOST_ITEMKEY_WILDCARD || $item !== CQueryParser::HOST_ITEMKEY_WILDCARD);
	}

	private static function validatePeriod(string $sec_num, string $time_shift, int $mode): bool {
		switch ($mode) {
			case CHistFunctionData::PERIOD_MODE_DEFAULT:
				if ($sec_num === '' || self::hasMacros($sec_num)) {
					return true;
				}

				$sec = timeUnitToSeconds($sec_num);

				if ($sec !== null) {
					return ($sec > 0 && $sec <= ZBX_MAX_INT32);
				}

				if (preg_match('/^#(?<num>\d+)$/', $sec_num, $matches) == 1) {
					return ($matches['num'] >= 1 && $matches['num'] <= ZBX_MAX_INT32);
				}

				return false;

			case CHistFunctionData::PERIOD_MODE_SEC:
				if ($time_shift !== '') {
					return false;
				}

				if ($sec_num === '') {
					return true;
				}

				$sec = timeUnitToSeconds($sec_num);

				if ($sec !== null) {
					return ($sec > 0 && $sec <= ZBX_MAX_INT32);
				}

				return false;

			case CHistFunctionData::PERIOD_MODE_NUM:
				if ($time_shift !== '') {
					return false;
				}

				if ($sec_num === '') {
					return true;
				}

				if (preg_match('/^#(?<num>\d+)$/', $sec_num, $matches) == 1) {
					return ($matches['num'] >= 1 && $matches['num'] <= ZBX_MAX_INT32);
				}

				return false;

			case CHistFunctionData::PERIOD_MODE_TREND:
				if ($sec_num === '' || $time_shift === '') {
					return false;
				}

				if (self::hasMacros($sec_num)) {
					return true;
				}

				$sec = timeUnitToSeconds($sec_num, true);

				if ($sec !== null) {
					return ($sec > 0 && $sec <= ZBX_MAX_INT32 && $sec % SEC_PER_HOUR == 0);
				}

				return false;

			default:
				return false;
		}

		return false;
	}
}
