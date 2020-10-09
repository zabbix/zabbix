<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	 * Structure: array(
	 *   '<function>' => array(
	 *     'args' => array(
	 *       array('type' => '<parameter_type>'[, 'mandat' => bool]),
	 *       ...
	 *     ),
	 *     'value_types' => array(<value_type>, <value_type>, ...)
	 *   )
	 * )
	 *
	 * <parameter_type> can be 'fit', 'mode', 'num_suffix', 'num_unsigned', 'operation', 'percent', 'sec_neg',
	 *                         'sec_num', 'sec_num_zero', 'sec_zero'
	 * <value_type> can be one of ITEM_VALUE_TYPE_*
	 *
	 * @var array
	 */
	private $allowed;

	/**
	 * If set to true, LLD macros can be uses inside functions and are properly validated using LLD macro parser.
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

		$valueTypesAll = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		];
		$valueTypesNum = [
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		];
		$valueTypesChar = [
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		];
		$valueTypesLog = [
			ITEM_VALUE_TYPE_LOG => true
		];
		$valueTypesInt = [
			ITEM_VALUE_TYPE_UINT64 => true
		];

		$argsIgnored = [['type' => 'str']];

		$this->allowed = [
			'abschange' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'avg' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'band' => [
				'args' => [
					['type' => 'sec_num_zero', 'mandat' => true, 'can_be_empty' => true],
					['type' => 'num_unsigned', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesInt
			],
			'change' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'count' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'str'],
					['type' => 'operation'],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesAll
			],
			'date' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'dayofmonth' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'dayofweek' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'delta' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'diff' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'forecast' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true],
					['type' => 'sec_neg', 'mandat' => true],
					['type' => 'fit', 'can_be_empty' => true],
					['type' => 'mode', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'fuzzytime' => [
				'args' => [
					['type' => 'sec_zero', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			],
			'iregexp' => [
				'args' => [
					['type' => 'str', 'mandat' => true],
					['type' => 'sec_num', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesChar
			],
			'last' => [
				'args' => [
					['type' => 'sec_num_zero', 'mandat' => true, 'can_be_empty' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesAll
			],
			'logeventid' => [
				'args' => [
					['type' => 'str', 'mandat' => true]
				],
				'value_types' => $valueTypesLog
			],
			'logseverity' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesLog
			],
			'logsource' => [
				'args' => [
					['type' => 'str', 'mandat' => true]
				],
				'value_types' => $valueTypesLog
			],
			'max' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'min' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'nodata'=> [
				'args' => [
					['type' => 'sec', 'mandat' => true],
					['type' => 'nodata_mode', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesAll
			],
			'now' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'percentile' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true],
					['type' => 'percent', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			],
			'prev' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'regexp' => [
				'args' => [
					['type' => 'str', 'mandat' => true],
					['type' => 'sec_num', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesChar
			],
			'str' => [
				'args' => [
					['type' => 'str', 'mandat' => true],
					['type' => 'sec_num', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesChar
			],
			'strlen' => [
				'args' => [
					['type' => 'sec_num_zero', 'mandat' => true, 'can_be_empty' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesChar
			],
			'sum' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'time' => [
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			],
			'timeleft' => [
				'args' => [
					['type' => 'sec_num', 'mandat' => true],
					['type' => 'sec_zero', 'can_be_empty' => true],
					['type' => 'num_suffix', 'mandat' => true],
					['type' => 'fit', 'can_be_empty' => true]
				],
				'value_types' => $valueTypesNum
			],
			'trendavg' => [
				'args' => [
					['type' => 'period', 'mandat' => true],
					['type' => 'period_shift', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			],
			'trendcount' => [
				'args' => [
					['type' => 'period', 'mandat' => true],
					['type' => 'period_shift', 'mandat' => true]
				],
				'value_types' => $valueTypesAll
			],
			'trenddelta' => [
				'args' => [
					['type' => 'period', 'mandat' => true],
					['type' => 'period_shift', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			],
			'trendmax' => [
				'args' => [
					['type' => 'period', 'mandat' => true],
					['type' => 'period_shift', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			],
			'trendmin' => [
				'args' => [
					['type' => 'period', 'mandat' => true],
					['type' => 'period_shift', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			],
			'trendsum' => [
				'args' => [
					['type' => 'period', 'mandat' => true],
					['type' => 'period_shift', 'mandat' => true]
				],
				'value_types' => $valueTypesNum
			]
		];
	}

	/**
	 * Validate trigger function like last(0), time(), etc.
	 * Examples:
	 *	array(
	 *		'function' => last("#15"),
	 *		'functionName' => 'last',
	 *		'functionParamList' => array(0 => '#15'),
	 *		'valueType' => 3
	 *	)
	 *
	 * @param string $value['function']
	 * @param string $value['functionName']
	 * @param array  $value['functionParamList']
	 * @param int    $value['valueType']
	 *
	 * @return bool
	 */
	public function validate($value) {
		$this->setError('');

		if (!isset($this->allowed[$value['functionName']])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['function']).' '.
				_('Unknown function.'));
			return false;
		}

		if (!isset($this->allowed[$value['functionName']]['value_types'][$value['valueType']])) {
			$this->setError(_s('Incorrect item value type "%1$s" provided for trigger function "%2$s".',
				itemValueTypeString($value['valueType']), $value['function']));
			return false;
		}

		if (count($this->allowed[$value['functionName']]['args']) < count($value['functionParamList'])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['function']).' '.
				_('Invalid number of parameters.'));
			return false;
		}

		$paramLabels = [
			_('Invalid first parameter.'),
			_('Invalid second parameter.'),
			_('Invalid third parameter.'),
			_('Invalid fourth parameter.'),
			_('Invalid fifth parameter.')
		];

		$user_macro_parser = new CUserMacroParser();
		if ($this->lldmacros) {
			$lld_macro_parser = new CLLDMacroParser();
			$lld_macro_function_parser = new CLLDMacroFunctionParser();
		}

		$func_args = [];

		foreach ($this->allowed[$value['functionName']]['args'] as $aNum => $arg) {
			// mandatory check
			if (isset($arg['mandat']) && $arg['mandat'] && !isset($value['functionParamList'][$aNum])) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['function']).' '.
					_('Mandatory parameter is missing.'));
				return false;
			}

			if (!isset($value['functionParamList'][$aNum])) {
				continue;
			}

			if (isset($arg['can_be_empty']) && $value['functionParamList'][$aNum] == '') {
				continue;
			}

			// user macro
			if ($user_macro_parser->parse($value['functionParamList'][$aNum]) == CParser::PARSE_SUCCESS) {
				continue;
			}

			if ($this->lldmacros
					&& ($lld_macro_function_parser->parse($value['functionParamList'][$aNum]) == CParser::PARSE_SUCCESS
						|| $lld_macro_parser->parse($value['functionParamList'][$aNum]) == CParser::PARSE_SUCCESS)) {
				continue;
			}

			if (!$this->validateParameter($value['functionParamList'][$aNum], $arg['type'])) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.',
					$value['function']).' '.$paramLabels[$aNum]);
				return false;
			}

			$func_args[$arg['type']] = $value['functionParamList'][$aNum];
		}

		if (array_key_exists('period', $func_args) && array_key_exists('period_shift', $func_args)
				&& !$this->validateTrendPeriods($func_args['period'], $func_args['period_shift'])) {
			return false;
		}

		return true;
	}

	/**
	 * Validate trend* function used period and period_shift arguments.
	 *
	 * @param string $period_value        Value of period, first argument for trend* function.
	 * @param string $period_shift_value  Value of period shift, second argument for trend* function.
	 *
	 * @return bool
	 */
	private function validateTrendPeriods($period_value, $period_shift_value): bool {
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
	 * Validate trigger function parameter.
	 *
	 * @param string $param
	 * @param string $type  type of $param ('fit', 'mode', 'num_suffix', 'num_unsigned', 'operation', 'percent',
	 *                                      'period', 'period_shift', 'sec_neg', 'sec_num', 'sec_num_zero', 'sec_zero')
	 *
	 * @return bool
	 */
	private function validateParameter($param, $type) {
		switch ($type) {
			case 'sec':
				return $this->validateSec($param);

			case 'sec_zero':
				return $this->validateSecZero($param);

			case 'sec_neg':
				return $this->validateSecNeg($param);

			case 'sec_num':
				return $this->validateSecNum($param);

			case 'sec_num_zero':
				return $this->validateSecNumZero($param);

			case 'num_unsigned':
				return CNewValidator::is_uint64($param);

			case 'num_suffix':
				return $this->validateNumSuffix($param);

			case 'nodata_mode':
				return ($param === 'strict');

			case 'fit':
				return $this->validateFit($param);

			case 'mode':
				return $this->validateMode($param);

			case 'percent':
				return $this->validatePercent($param);

			case 'operation':
				return $this->validateOperation($param);

			case 'period':
				return $this->validatePeriod($param);

			case 'period_shift':
				return $this->validatePeriodShift($param);
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
	private function validateSecValue($param) {
		return preg_match('/^\d+['.ZBX_TIME_SUFFIXES.']{0,1}$/', $param);
	}

	/**
	 * Validate trigger function parameter which can contain only seconds.
	 * Examples: 1, 5w
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSec($param) {
		return ($this->validateSecValue($param) && $param > 0);
	}

	/**
	 * Validate trigger function parameter which can contain only seconds or zero.
	 * Examples: 0, 1, 5w
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecZero($param) {
		return $this->validateSecValue($param);
	}

	/**
	 * Validate trigger function parameter which can contain negative seconds.
	 * Examples: 0, 1, 5w, -3h
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecNeg($param) {
		return preg_match('/^[-]?\d+['.ZBX_TIME_SUFFIXES.']{0,1}$/', $param);
	}

	/**
	 * Validate trigger function parameter which can contain seconds greater zero or count.
	 * Examples: 1, 5w, #1
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecNum($param) {
		if (preg_match('/^#\d+$/', $param)) {
			return (substr($param, 1) > 0);
		}

		return ($this->validateSecValue($param) && $param > 0);
	}

	/**
	 * Validate trigger function parameter which can contain seconds or count.
	 * Examples: 0, 1, 5w, #1
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecNumZero($param) {
		if (preg_match('/^#\d+$/', $param)) {
			return (substr($param, 1) > 0);
		}

		return $this->validateSecValue($param);
	}

	/**
	 * Validate trigger function parameter which can contain suffixed decimal number.
	 * Examples: 0, 1, 5w, -3h, 10.2G
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateNumSuffix($param) {
		return ((new CNumberParser(['with_suffix' => true]))->parse($param) == CParser::PARSE_SUCCESS);
	}

	/**
	 * Validate trigger function parameter which can contain fit function (linear, polynomialN with 1 <= N <= 6,
	 * exponential, logarithmic, power) or an empty value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateFit($param) {
		return preg_match('/^(linear|polynomial[1-6]|exponential|logarithmic|power|)$/', $param);
	}

	/**
	 * Validate trigger function parameter which can contain forecast mode (value, max, min, delta, avg) or
	 * an empty value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateMode($param) {
		return preg_match('/^(value|max|min|delta|avg|)$/', $param);
	}

	/**
	 * Validate trigger function parameter which can contain a percentage.
	 * Examples: 0, 1, 1.2, 1.2345, 1., .1, 100
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validatePercent($param) {
		return (preg_match('/^\d*(\.\d{0,4})?$/', $param) && $param !== '.' && $param <= 100);
	}

	/**
	 * Validate trigger function parameter which can contain operation (band, eq, ge, gt, le, like, lt, ne,
	 * regexp, iregexp) or an empty value.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateOperation($param) {
		return preg_match('/^(eq|ne|gt|ge|lt|le|like|band|regexp|iregexp|)$/', $param);
	}

	/**
	 * Validate trigger function parameter which can contain time unit not less than 1 hour and multiple of an hour.
	 * Examples: 3600, 7200s, 2h, 1d
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validatePeriod(string $param): bool {
		$simple_interval_parser = new CSimpleIntervalParser(['with_year' => true]);
		$value = (string) $param;

		if ($simple_interval_parser->parse($value) == CParser::PARSE_SUCCESS) {
			$value = timeUnitToSeconds($value, true);

			if ($value >= SEC_PER_HOUR && $value % SEC_PER_HOUR === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate trigger function parameter which can contain time range value with precision and multiple of an hour.
	 * Examples: now/h, now/w, now/M, now/y
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validatePeriodShift(string $param): bool {
		$relative_time_parser = new CRelativeTimeParser();

		if ($relative_time_parser->parse((string) $param) === CParser::PARSE_SUCCESS) {
			$tokens = $relative_time_parser->getTokens();

			foreach ($tokens as $token) {
				if ($token['type'] === CRelativeTimeParser::ZBX_TOKEN_PRECISION && $token['suffix'] === 'm') {
					return false;
				}
			}

			foreach ($tokens as $token) {
				if ($token['type'] === CRelativeTimeParser::ZBX_TOKEN_PRECISION
						&& $relative_time_parser->getDateTime(true)->getTimestamp() % SEC_PER_HOUR === 0) {
					return true;
				}
			}
		}

		return false;
	}
}
