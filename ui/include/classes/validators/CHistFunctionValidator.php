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
	 *   'parameters' => []  Definition of parameters of known history functions.
	 *
	 * @var array
	 */
	private $options = [
		'parameters' => []
	];

	/**
	 * @param array $options
	 * @param bool  $options['calculated']
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
			$param_match_unquoted = ($param['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED)
				? CHistFunctionParser::unquoteParam($param['match'])
				: $param['match'];

			if (self::isMacro($param_match_unquoted)) {
				continue;
			}

			$is_valid =	in_array($param['type'],
				array_key_exists('type_any', $param_spec) ? $param_spec['type_any'] : [$param_spec['type']]
			);

			if ($is_valid) {
				switch ($param['type']) {
					case CHistFunctionParser::PARAM_TYPE_QUERY:
						$is_valid = self::validateQuery($param['data']['host'], $param['data']['item']);
						break;

					case CHistFunctionParser::PARAM_TYPE_PERIOD:
						$mode = array_key_exists('mode', $param_spec)
							? $param_spec['mode']
							: CHistFunctionData::PERIOD_MODE_DEFAULT;

						$is_valid = (!$required || $param['data']['sec_num'] !== '')
							&& self::validatePeriod($param['data']['sec_num'], $param['data']['time_shift'], $mode);

						break;

					case CHistFunctionParser::PARAM_TYPE_QUOTED:
					case CHistFunctionParser::PARAM_TYPE_UNQUOTED:
						if (array_key_exists('rules', $param_spec)) {
							$is_valid = self::validateRules($param_match_unquoted, $param_spec['rules']);
						}
						break;
				}
			}

			if (!$is_valid) {
				$this->setError(_s('Incorrect usage of function "%1$s".', $token['data']['function']).' '.
					$invalid_param_labels[$index]
				);

				return false;
			}
		}

		return true;
	}

	private static function isMacro(string $value): bool {
		return (substr($value, 0, 1) === '{');
	}

	private static function validateQuery(string $host, string $item): bool {
		return ($host !== CQueryParser::HOST_ITEMKEY_WILDCARD || $item !== CQueryParser::HOST_ITEMKEY_WILDCARD);
	}

	private static function validatePeriod(string $sec_num, string $time_shift, int $mode): bool {
		switch ($mode) {
			case CHistFunctionData::PERIOD_MODE_DEFAULT:
				if ($sec_num === '' || self::isMacro($sec_num)) {
					return true;
				}

				if (self::validateSimpleInterval($sec_num) && self::validateRange($sec_num, ['min' => 1])) {
					return true;
				}

				if (preg_match('/^#(?<num>\d+)$/', $sec_num, $matches) == 1 && $matches['num'] > 0) {
					return true;
				}

				return false;

			case CHistFunctionData::PERIOD_MODE_SEC_POSITIVE:
				if ($time_shift !== '') {
					return false;
				}

				if ($sec_num === '') {
					return true;
				}

				if (self::validateSimpleInterval($sec_num) && self::validateRange($sec_num, ['min' => 1])) {
					return true;
				}

				return false;

			case CHistFunctionData::PERIOD_MODE_TREND:
				if ($sec_num === '' || $time_shift === '') {
					return false;
				}

				if (!self::isMacro($sec_num)) {
					if (!self::validateSimpleInterval($sec_num, ['with_year' => true])) {
						return false;
					}

					$sec = timeUnitToSeconds($sec_num, true);

					if ($sec == 0 || $sec % SEC_PER_HOUR != 0) {
						return false;
					}
				}

				return true;
		}

		return false;
	}

	private static function validateRules(string $match_unquoted, array $rules): bool {
		foreach ($rules as $rule) {
			switch ($rule['type']) {
				case 'range':
					$options = array_intersect_key($rule, array_flip(['min', 'max']));

					if (!self::validateRange($match_unquoted, $options)) {
						return false;
					}

					break;

				case 'regexp':
					if (preg_match($rule['pattern'], $match_unquoted) != 1) {
						return false;
					}

					break;

				case 'simple_interval':
					$options = array_key_exists('options', $rule) ? $rule['options'] : [];

					if (!self::validateSimpleInterval($match_unquoted, $options)) {
						return false;
					}

					break;
			}
		}

		return true;
	}

	private static function validateRange(string $match_unquoted, array $options): bool {
		$number_parser = new CNumberParser(['with_minus' => true, 'with_suffix' => true]);

		if ($number_parser->parse($match_unquoted) != CParser::PARSE_SUCCESS) {
			return false;
		}

		$value = $number_parser->calcValue();

		$is_valid = (!array_key_exists('min', $options) || $value >= $options['min'])
			&& (!array_key_exists('max', $options) || $value <= $options['max']);

		return $is_valid;
	}

	private static function validateSimpleInterval(string $match_unquoted, array $options = []): bool {
		$simple_interval_parser = new CSimpleIntervalParser($options);

		return ($simple_interval_parser->parse($match_unquoted) == CParser::PARSE_SUCCESS);
	}
}
