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


class CFunctionValidator extends CValidator {

	/**
	 * The array containing valid functions and parameters to them.
	 *
	 * Structure: [
	 *   '<function>' => [
	 *     'args' => [
	 *       [
	 *		   'type' => '<parameter_type>',
	 *         'mandat' => 0x00
	 *       ],
	 *       ...
	 *     ],
	 *     'value_types' => [<value_type>, <value_type>, ...]
	 *   ]
	 * ]
	 *
	 * <parameter_type> can be 'query', 'fit', 'mode', 'num_suffix', 'num_unsigned', 'operation', 'percent', 'sec_neg',
	 *                         'sec_num', 'sec_num_zero', 'sec_zero'
	 * <value_type> can be one of ITEM_VALUE_TYPE_*
	 *
	 * @var array
	 */
	private $allowed;

	/**
	 * If set to true, LLD macros can be used inside functions and are properly validated using LLD macro parser.
	 *
	 * @var bool
	 */
	private $lldmacros = true;

	public function __construct(array $options = []) {
		/*
		 * CValidator is an abstract class, so no specific functionality should be bound to it. Thus putting
		 * an option "lldmacros" (or class variable $lldmacros) in it, is not preferred. Without it, class
		 * initialization would fail due to __set(). So instead we create a local variable in this extended class
		 * and remove the option "lldmacros" before calling the parent constructor.
		 */
		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = $options['lldmacros'];
			unset($options['lldmacros']);
		}
		parent::__construct($options);

		$value_types_all = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		];
		$value_types_num = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		];
		$value_types_char = [
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		];
		$value_types_log = [
			ITEM_VALUE_TYPE_LOG => true
		];
		$value_types_int = [
			ITEM_VALUE_TYPE_UINT64 => true
		];

		$args_ignored = [[
			'type' => 'str',
			'mandat' => 0x00
		]];

		/*
		 * Types of parameters:
		 * - query - /host/key reference;
		 * - scale - sec|#num:time_shift;
		 * - num_unsigned
		 * - sec_neg
		 * - str
		 * - fit   - can be either empty or one of valid parameters;
		 * - mode  - can be either empty or one of valid parameters;
		 * - nodata_mode
		 * - function
		 * - operation
		 * - pattern
		 * - period
		 * - percent
		 * - num_suffix
		 *
		 * Mandatory property (mandat):
		 * - 0x00 if parameter is optional;
		 * - 0x01 if parameter is mandatory;
		 * - 0x02 if second part in joint paremater is mandatory (0x03 if both first and second are mandatory).
		 */
		$this->allowed = [
			'avg' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01]
				],
				'value_types' => $value_types_num
			],
			'band' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x00],
					['type' => 'num_unsigned', 'mandat' => 0x01]
				],
				'value_types' => $value_types_int
			],
			'count' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01],
					['type' => 'str', 'mandat' => 0x00],
					['type' => 'operation', 'mandat' => 0x00]
				],
				'value_types' => $value_types_all
			],
			'date' => [
				'args' => $args_ignored,
				'value_types' => $value_types_all
			],
			'dayofmonth' => [
				'args' => $args_ignored,
				'value_types' => $value_types_all
			],
			'dayofweek' => [
				'args' => $args_ignored,
				'value_types' => $value_types_all
			],
			'find' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01],
					['type' => 'function', 'mandat' => 0x00],
					['type' => 'pattern', 'mandat' => 0x00]
				],
				'value_types' => $value_types_all
			],
			'forecast' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01],
					['type' => 'sec_neg', 'mandat' => 0x01],
					['type' => 'fit', 'mandat' => 0x00],
					['type' => 'mode', 'mandat' => 0x00]
				],
				'value_types' => $value_types_num
			],
			'fuzzytime' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'sec_zero', 'mandat' => 0x01]
				],
				'value_types' => $value_types_num
			],
			'last' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x00]
				],
				'value_types' => $value_types_all
			],
			'length' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01]
				],
				'value_types' => $value_types_all
			],
			'logeventid' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'str', 'mandat' => 0x00]
				],
				'value_types' => $value_types_log
			],
			'logseverity' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01]
				],
				'value_types' => $value_types_log
			],
			'logsource' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'str', 'mandat' => 0x00]
				],
				'value_types' => $value_types_log
			],
			'max' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01]
				],
				'value_types' => $value_types_num
			],
			'min' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01]
				],
				'value_types' => $value_types_num
			],
			'nodata'=> [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'sec_neg', 'mandat' => 0x01],
					['type' => 'nodata_mode', 'mandat' => 0x00]
				],
				'value_types' => $value_types_all
			],
			'now' => [
				'args' => $args_ignored,
				'value_types' => $value_types_all
			],
			'percentile' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01],
					['type' => 'percent', 'mandat' => 0x01]
				],
				'value_types' => $value_types_num
			],
			'sum' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01]
				],
				'value_types' => $value_types_num
			],
			'time' => [
				'args' => $args_ignored,
				'value_types' => $value_types_all
			],
			'timeleft' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'scale', 'mandat' => 0x01],
					['type' => 'num_suffix', 'mandat' => 0x01],
					['type' => 'fit', 'mandat' => 0x00]
				],
				'value_types' => $value_types_num
			],
			'trendavg' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'period', 'mandat' => 0x03]
				],
				'value_types' => $value_types_num
			],
			'trendcount' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'period', 'mandat' => 0x03]
				],
				'value_types' => $value_types_all
			],
			'trendmax' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'period', 'mandat' => 0x03]
				],
				'value_types' => $value_types_num
			],
			'trendmin' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'period', 'mandat' => 0x03]
				],
				'value_types' => $value_types_num
			],
			'trendsum' => [
				'args' => [
					['type' => 'query', 'mandat' => 0x01],
					['type' => 'period', 'mandat' => 0x03]
				],
				'value_types' => $value_types_num
			]
		];
	}

	/**
	 * Validate trigger function like last(0), time(), etc.
	 *
	 * @param CFunctionParserResult  $fn
	 *
	 * @return bool
	 */
	public function validate($fn) {
		$this->setError('');

		if (!array_key_exists($fn->function, $this->allowed)) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Unknown function.'));
			return false;
		}

		if (count($this->allowed[$fn->function]['args']) < count($fn->params_raw['parameters'])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
				_('Invalid number of parameters.'));
			return false;
		}

		$param_labels = [
			_('Invalid first parameter.'),
			_('Invalid second parameter.'),
			_('Invalid third parameter.'),
			_('Invalid fourth parameter.'),
			_('Invalid fifth parameter.')
		];

		foreach ($this->allowed[$fn->function]['args'] as $num => $arg) {
			// Mandatory check.
			if ($arg['mandat'] && !array_key_exists($num, $fn->params_raw['parameters'])) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
					_('Mandatory parameter is missing.'));
				return false;
			}
			elseif (!array_key_exists($num, $fn->params_raw['parameters'])) {
				continue;
			}

			$param = ($fn->params_raw['parameters'][$num] instanceof CParserResult)
					? $fn->params_raw['parameters'][$num]->match
					: $fn->params_raw['parameters'][$num]['raw'];

			if (($arg['mandat'] & 0x02) && strstr($param, ':') === false) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.
					_('Mandatory parameter is missing.'));
				return false;
			}

			if (!$this->validateParameter($param, $arg)) {
				$this->setError(
					_s('Incorrect trigger function "%1$s" provided in expression.', $fn->match).' '.$param_labels[$num]
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate value type.
	 *
	 * @param int                   $value_type
	 * @param CFunctionParserResult $fn
	 *
	 * @return bool
	 */
	public function validateValueType(int $value_type, CFunctionParserResult $fn): bool {
		if (!array_key_exists($value_type, $this->allowed[$fn->function]['value_types'])) {
			$this->setError(_s('Incorrect item value type "%1$s" provided for trigger function "%2$s".',
				itemValueTypeString($value_type), $fn->match));
			return false;
		}

		return true;
	}

	/**
	 * Validate trigger function parameter.
	 *
	 * @param string $param
	 * @param array  $arg
	 * @param string $arg['type']
	 * @param string $arg['mandat']
	 *
	 * @return bool
	 */
	private function validateParameter(string $param, array $arg): bool {
		switch ($arg['type']) {
			case 'query':
				return $this->validateQuery($param);

			case 'scale':
				return $this->validateScale($param, $arg['mandat']);

			case 'sec_zero':
				return $this->validateSecZero($param);

			case 'sec_neg':
				return $this->validateSecNeg($param);

			case 'num_suffix':
				return $this->validateNumSuffix($param);

			case 'nodata_mode':
				return ($param === 'strict' || $param === '');

			case 'fit':
				return ($param === '' || $this->validateFit($param));

			case 'function':
				return $this->validateStringFunction($param);

			case 'mode':
				return ($param === '' || $this->validateMode($param));

			case 'percent':
				return $this->validatePercent($param);

			case 'operation':
				return $this->validateOperation($param);

			case 'period':
				return $this->validatePeriod($param, $arg['mandat']);
		}

		return true;
	}

	/**
	 * Validate trigger function parameter which can contain host and item key.
	 * Examples: /host/key, /host/vfs.fs.size["/var/log",pfree]
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateQuery(string $param): bool {
		if ($this->isMacro($param)) {
			return true;
		}

		$parser = new CQueryParser();
		return ($parser->parse($param) === CParser::PARSE_SUCCESS);
	}

	/**
	 * Validate joint "sec|#num:time_shift" syntax.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateScale(string $param, int $mandat): bool {
		if ($this->isMacro($param)) {
			return true;
		}

		$param = explode(':', $param) + ['', ''];
		$sec_num = $param[0];
		$time_shift = $param[1];

		$is_sec_num_valid = ((!($mandat & 0x01) && $sec_num === '') || $this->validateSecNum($sec_num));
		$is_time_shift_valid = ((!($mandat & 0x02) && $time_shift === '') || $this->validatePeriodShift($time_shift));

		return ($is_sec_num_valid && $is_time_shift_valid);
	}

	/**
	 * Validate joint "period:period_shift" period syntax.
	 *
	 * Valid period can contain time unit not less than 1 hour and multiple of an hour.
	 * Valid period shift can contain time range value with precision and multiple of an hour.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validatePeriod(string $param, int $mandat): bool {
		if ($this->isMacro($param)) {
			return true;
		}

		$param = explode(':', $param) + ['', ''];
		$period = $param[0];
		$period_shift = $param[1];

		$simple_interval_parser = new CSimpleIntervalParser(['with_year' => true]);
		if ($mandat & 0x01) {
			if (!$this->isMacro($period)) {
				if ($simple_interval_parser->parse($period) != CParser::PARSE_SUCCESS) {
					return false;
				}

				$value = timeUnitToSeconds($period, true);
				if ($value < SEC_PER_HOUR || $value % SEC_PER_HOUR != 0) {
					return false;
				}
			}
		}

		if (($mandat & 0x02) && !$this->validateTrendPeriods($period, $period_shift)) {
			return $this->isMacro($period_shift);
		}

		return true;
	}

	/**
	 * Validate trigger function parameter seconds value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecValue(string $param): bool {
		return ($this->isMacro($param) || preg_match('/^\d+['.ZBX_TIME_SUFFIXES.']{0,1}$/', $param));
	}

	/**
	 * Validate trigger function parameter which can contain only seconds or zero.
	 * Examples: 0, 1, 5w
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecZero(string $param): bool {
		return ($this->isMacro($param) || $this->validateSecValue($param));
	}

	/**
	 * Validate trigger function parameter which can contain negative seconds.
	 * Examples: 0, 1, 5w, -3h
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecNeg(string $param): bool {
		return ($this->isMacro($param) || preg_match('/^[-]?\d+['.ZBX_TIME_SUFFIXES.']{0,1}$/', $param));
	}

	/**
	 * Validate trigger function parameter which can contain seconds greater than zero or count.
	 * Examples: 1, 5w, #1
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecNum(string $param): bool {
		if ($this->isMacro($param)) {
			return true;
		}

		return preg_match('/^#\d+$/', $param)
			? (substr($param, 1) > 0)
			: ($this->validateSecValue($param) && $param > 0);
	}

	/**
	 * Validate trigger function parameter which can contain suffixed decimal number.
	 * Examples: 0, 1, 5w, -3h, 10.2G
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateNumSuffix(string $param): bool {
		return ($this->isMacro($param)
			|| (new CNumberParser(['with_suffix' => true]))->parse($param) == CParser::PARSE_SUCCESS);
	}

	/**
	 * Validate trigger function parameter which can contain fit function (linear, polynomialN with 1 <= N <= 6,
	 * exponential, logarithmic, power) or an empty value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateFit(string $param): bool {
		return ($this->isMacro($param)
			|| preg_match('/^(linear|polynomial[1-6]|exponential|logarithmic|power)$/', $param));
	}

	/**
	 * Validate trigger function parameter which can contain forecast mode (value, max, min, delta, avg) or
	 * an empty value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateMode(string $param): bool {
		return ($this->isMacro($param) || preg_match('/^(value|max|min|delta|avg)$/', $param));
	}

	/**
	 * Validate 'find' operator.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateStringFunction(string $param): bool {
		return ($this->isMacro($param) || preg_match('/^(iregexp|regexp|like)$/', $param));
	}

	/**
	 * Validate trigger function parameter which can contain a percentage.
	 * Examples: 0, 1, 1.2, 1.2345, 1., .1, 100
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validatePercent(string $param): bool {
		return ($this->isMacro($param) || preg_match('/^\d*(\.\d{0,4})?$/', $param) && $param !== '.' && $param <= 100);
	}

	/**
	 * Validate trigger function parameter which can contain operation (band, eq, ge, gt, le, like, lt, ne,
	 * regexp, iregexp) or an empty value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateOperation(string $param): bool {
		return ($this->isMacro($param) || preg_match('/^(eq|ne|gt|ge|lt|le|like|band|regexp|iregexp|)$/', $param));
	}

	/**
	 * Validate trigger function parameter which must contain time range value with precision.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validatePeriodShift(string $param): bool {
		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse($param) == CParser::PARSE_SUCCESS) {
			$tokens = $relative_time_parser->getTokens();

			$offset_tokens = array_filter($tokens, function ($token) {
				return ($token['type'] ==  CRelativeTimeParser::ZBX_TOKEN_OFFSET);
			});

			if (($offset_token = reset($offset_tokens)) !== false
					&& $offset_token['sign'] === '-' && (int) $offset_token['value'] > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate trend* function used period and period_shift arguments.
	 *
	 * @param string $period_value        Value of period, first argument for trend* function.
	 * @param string $period_shift_value  Value of period shift, second argument for trend* function.
	 *
	 * @return bool
	 */
	private function validateTrendPeriods(string $period_value, string $period_shift_value): bool {
		$precisions = 'hdwMy';
		$period = strpos($precisions, substr($period_value, -1));

		if ($period !== false) {
			$relative_time_parser = new CRelativeTimeParser();
			$relative_time_parser->parse($period_shift_value);

			foreach ($relative_time_parser->getTokens() as $token) {
				if ($token['type'] !== CRelativeTimeParser::ZBX_TOKEN_PRECISION) {
					continue;
				}

				if (strpos($precisions, $token['suffix']) < $period) {
					$this->setError(_('Time units in period shift must be greater or equal to period time unit.'));
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check if parameter is valid macro.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function isMacro(string $param): bool {
		$user_macro_parser = new CUserMacroParser();
		if ($this->lldmacros) {
			$lld_macro_parser = new CLLDMacroParser();
			$lld_macro_function_parser = new CLLDMacroFunctionParser();
		}

		$is_valid_lld_macro = ($this->lldmacros
			&& ($lld_macro_function_parser->parse($param) == CParser::PARSE_SUCCESS
				|| $lld_macro_parser->parse($param) == CParser::PARSE_SUCCESS)
		);

		return ($user_macro_parser->parse($param) == CParser::PARSE_SUCCESS || $is_valid_lld_macro);
	}
}
