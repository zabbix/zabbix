<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerPopupTriggerExpr extends CController {

	private $metrics = [];
	private $param1SecCount = [];
	private $param1Period = [];
	private $param1Sec = [];
	private $param1Str = [];
	private $param2SecCount = [];
	private $param2SecMode = [];
	private $param2SecCountMode = [];
	private $param3SecVal = [];
	private $param_find = [];
	private $param3SecPercent = [];
	private $paramForecast = [];
	private $paramTimeleft = [];
	private $allowedTypesAny = [];
	private $allowedTypesNumeric = [];
	private $allowedTypesStr = [];
	private $allowedTypesLog = [];
	private $allowedTypesInt = [];
	private $functions = [];
	private $operators = ['=', '<>', '>', '<', '>=', '<='];
	private $period_optional = [];
	private $period_seasons = [];

	protected function init() {
		$this->disableCsrfValidation();

		$this->metrics = [
			PARAM_TYPE_TIME => _('Time'),
			PARAM_TYPE_COUNTS => _('Count')
		];

		/*
		 * C - caption
		 * T - type
		 * M - metrics
		 * A - asterisk
		 */
		$this->param1SecCount = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			]
		];

		$this->period_optional = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => false
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			]
		];

		$this->param1Period = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'period_shift' => [
				'C' => _('Period shift'),
				'T' => T_ZBX_INT,
				'A' => true
			]
		];

		$this->period_seasons = [
			'last' => [
				'C' => _('Period').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'period_shift' => [
				'C' => _('Period shift'),
				'T' => T_ZBX_INT,
				'A' => true
			],
			'season_unit' => [
				'C' => _('Season'),
				'T' => T_ZBX_STR,
				'A' => true,
				'options' => [
					'h' => _('Hour'),
					'd' => _('Day'),
					'w' => _('Week'),
					'M' => _("Month"),
					'y' => _('Year')
				]
			],
			'num_seasons' => [
				'C' => _('Number of seasons'),
				'T' => T_ZBX_INT,
				'A' => true
			]
		];

		$this->param1Sec = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			]
		];

		$this->param1Str = [
			'pattern' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param2SecCount = [
			'pattern' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => false
			]
		];

		$this->param2SecMode = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'mode' => [
				'C' => 'Mode',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param2SecCountMode = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'mode' => [
				'C' => 'Mode',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param3SecVal = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'o' => [
				'C' => 'O',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'v' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param_find = [
			'o' => [
				'C' => 'O',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'v' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param3SecPercent = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'p' => [
				'C' => _('Percentage').' (P)',
				'T' => T_ZBX_DBL,
				'A' => true
			]
		];

		$this->paramForecast = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'time' => [
				'C' => _('Time').' (t)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'fit' => [
				'C' => _('Fit'),
				'T' => T_ZBX_STR,
				'A' => false
			],
			'mode' => [
				'C' => _('Mode'),
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->paramTimeleft = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			't' => [
				'C' => _('Threshold'),
				'T' => T_ZBX_DBL,
				'A' => true
			],
			'fit' => [
				'C' => _('Fit'),
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->allowedTypesAny = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_STR => 1,
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_UINT64 => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		$this->allowedTypesNumeric = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		$this->allowedTypesStr = [
			ITEM_VALUE_TYPE_STR => 1,
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		$this->allowedTypesLog = [
			ITEM_VALUE_TYPE_LOG => 1
		];

		$this->allowedTypesInt = [
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		$this->functions = [
			'abs' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('abs() - Absolute value'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'acos' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('acos() - The arccosine of a value as an angle, expressed in radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'ascii' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('ascii() - Returns the ASCII code of the leftmost character of the value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'asin' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('asin() - The arcsine of a value as an angle, expressed in radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'atan' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('atan() - The arctangent of a value as an angle, expressed in radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'atan2' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('atan2() - The arctangent of the ordinate (value) and abscissa coordinates specified as an angle, expressed in radians'),
				'params' => $this->param1SecCount + [
					'abscissa' => [
						'C' => _('Abscissa'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'avg' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE, ZBX_FUNCTION_TYPE_MATH],
				'description' => _('avg() - Average value of a period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'between' => [
				'types' => [ZBX_FUNCTION_TYPE_OPERATOR],
				'description' => _('between() - Checks if a value belongs to the given range (1 - in range, 0 - otherwise)'),
				'params' => $this->param1SecCount + [
					'min' => [
						'C' => _('Min'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'max' => [
						'C' => _('Max'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>']
			],
			'bitand' => [
				'types' => [ZBX_FUNCTION_TYPE_BITWISE],
				'description' => _('bitand() - Bitwise AND'),
				'params' => $this->param1SecCount + [
					'mask' => [
						'C' => _('Mask'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'bitlength' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('bitlength() - Returns the length in bits'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'bitlshift' => [
				'types' => [ZBX_FUNCTION_TYPE_BITWISE],
				'description' => _('bitlshift() - Bitwise shift left'),
				'params' => $this->param1SecCount + [
					'bits' => [
						'C' => _('Bits to shift'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'bitnot' => [
				'types' => [ZBX_FUNCTION_TYPE_BITWISE],
				'description' => _('bitnot() - Bitwise NOT'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'bitor' => [
				'types' => [ZBX_FUNCTION_TYPE_BITWISE],
				'description' => _('bitor() - Bitwise OR'),
				'params' => $this->param1SecCount + [
					'mask' => [
						'C' => _('Mask'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'bitrshift' => [
				'types' => [ZBX_FUNCTION_TYPE_BITWISE],
				'description' => _('bitrshift() - Bitwise shift right'),
				'params' => $this->param1SecCount + [
					'bits' => [
						'C' => _('Bits to shift'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'bitxor' => [
				'types' => [ZBX_FUNCTION_TYPE_BITWISE],
				'description' => _('bitxor() - Bitwise exclusive OR'),
				'params' => $this->param1SecCount + [
					'mask' => [
						'C' => _('Mask'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'bytelength' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('bytelength() - Returns the length in bytes'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'cbrt' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('cbrt() - Cube root'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'ceil' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('ceil() - Rounds up to the nearest greater integer'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'change' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('change() - Difference between last and previous value'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'changecount' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('changecount() - Number of changes between adjacent values, Mode (all - all changes, inc - only increases, dec - only decreases)'),
				'params' => $this->param2SecCountMode,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'char' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('char() - Returns the character which represents the given ASCII code'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesInt,
				'operators' => $this->operators
			],
			'concat' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('concat() - Returns a string that is the result of concatenating value to string'),
				'params' => $this->param1SecCount + [
					'string' => [
						'C' => _('String'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'cos' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('cos() - The cosine of a value, where the value is an angle expressed in radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'cosh' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('cosh() - The hyperbolic cosine of a value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'cot' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('cot() - The cotangent of a value, where the value is an angle expressed in radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'count' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('count() - Number of successfully retrieved values V (which fulfill operator O) for period T'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'countunique' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('countunique() - The number of unique values'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'date' => [
				'types' => [ZBX_FUNCTION_TYPE_DATE_TIME],
				'description' => _('date() - Current date'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'dayofmonth' => [
				'types' => [ZBX_FUNCTION_TYPE_DATE_TIME],
				'description' => _('dayofmonth() - Day of month'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'dayofweek' => [
				'types' => [ZBX_FUNCTION_TYPE_DATE_TIME],
				'description' => _('dayofweek() - Day of week'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'degrees' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('degrees() - Converts a value from radians to degrees'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'e' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _("e() - Returns Euler's number"),
				'allowed_types' => $this->allowedTypesAny
			],
			'exp' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _("exp() - Euler's number at a power of a value"),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'expm1' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _("expm1() - Euler's number at a power of a value minus 1"),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'find' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('find() - Check occurrence of pattern V (which fulfill operator O) for period T (1 - match, 0 - no match)'),
				'params' => $this->period_optional + $this->param_find,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>']
			],
			'first' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('first() - The oldest value in the specified time interval'),
				'params' => $this->param1Sec + $this->period_optional,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'floor' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('floor() - Rounds down to the nearest smaller integer'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'forecast' => [
				'types' => [ZBX_FUNCTION_TYPE_PREDICTION],
				'description' => _('forecast() - Forecast for next t seconds based on period T'),
				'params' => $this->paramForecast,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'fuzzytime' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('fuzzytime() - Difference between item value (as timestamp) and Zabbix server timestamp is less than or equal to T seconds (1 - true, 0 - false)'),
				'params' => $this->param1Sec,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>']
			],
			'in' => [
				'types' => [ZBX_FUNCTION_TYPE_OPERATOR],
				'description' => _('in() - Checks if a value equals to one of the listed values (1 - equals, 0 - otherwise)'),
				'params' => $this->param1SecCount + [
					'values' => [
						'C' => _('Values'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>']
			],
			'insert' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('insert() - Inserts specified characters or spaces into a character string, beginning at a specified position in the string'),
				'params' => $this->param1SecCount + [
					'start' => [
						'C' => _('Start'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'length' => [
						'C' => _('Length'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'replace' => [
						'C' => _('Replacement'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'jsonpath' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('jsonpath() - Returns JSONPath result'),
				'params' => [
					'last' => [
						'C' => _('Last of').' (T)',
						'T' => T_ZBX_INT,
						'M' => [PARAM_TYPE_COUNTS => _('Count')],
						'A' => false
					],
					'shift' => [
						'C' => _('Time shift'),
						'T' => T_ZBX_INT,
						'A' => false
					],
					'path' => [
						'C' => _('JSONPath'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'replace' => [
						'C' => _('Default'),
						'T' => T_ZBX_STR,
						'A' => false
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'kurtosis' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('kurtosis() - Measures the "tailedness" of the probability distribution'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'last' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('last() - Last (most recent) T value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'left' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('left() - Returns the leftmost count characters'),
				'params' => $this->param1SecCount + [
					'count' => [
						'C' => _('Count'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'length' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('length() - Length of last (most recent) T value in characters'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'log' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('log() - Natural logarithm'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'log10' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('log10() - Decimal logarithm'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'logeventid' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('logeventid() - Event ID of last log entry matching regular expression V for period T (1 - match, 0 - no match)'),
				'params' => $this->period_optional + $this->param1Str,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>']
			],
			'logseverity' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('logseverity() - Log severity of the last log entry for period T'),
				'params' => $this->period_optional,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => $this->operators
			],
			'logsource' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('logsource() - Log source of the last log entry matching parameter V for period T (1 - match, 0 - no match)'),
				'params' => $this->period_optional + $this->param1Str,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>']
			],
			'ltrim' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('ltrim() - Remove specified characters from the beginning of a string'),
				'params' => $this->param1SecCount + [
					'chars' => [
						'C' => _('Chars'),
						'T' => T_ZBX_STR,
						'A' => false
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'mad' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('mad() - Median absolute deviation'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'max' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE, ZBX_FUNCTION_TYPE_MATH],
				'description' => _('max() - Maximum value for period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'mid' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('mid() - Returns a substring beginning at the character position specified by start for N characters'),
				'params' => $this->param1SecCount + [
					'start' => [
						'C' => _('Start'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'length' => [
						'C' => _('Length'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'min' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE, ZBX_FUNCTION_TYPE_MATH],
				'description' => _('min() - Minimum value for period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'mod' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('mod() - Division remainder'),
				'params' => $this->param1SecCount + [
					'denominator' => [
						'C' => _('Division denominator'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'monodec' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('monodec() - Check for continuous item value decrease (1 - data is monotonic, 0 - otherwise), Mode (strict - require strict monotonicity)'),
				'params' => $this->param2SecCountMode,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>']
			],
			'monoinc' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('monoinc() - Check for continuous item value increase (1 - data is monotonic, 0 - otherwise), Mode (strict - require strict monotonicity)'),
				'params' => $this->param2SecCountMode,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>']
			],
			'nodata' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('nodata() - No data received during period of time T (1 - true, 0 - false), Mode (strict - ignore proxy time delay in sending data)'),
				'params' => $this->param2SecMode,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>']
			],
			'now' => [
				'types' => [ZBX_FUNCTION_TYPE_DATE_TIME],
				'description' => _('now() - Number of seconds since the Epoch'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'percentile' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('percentile() - Percentile P of a period T'),
				'params' => $this->param3SecPercent,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'pi' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('pi() - Returns the Pi constant'),
				'allowed_types' => $this->allowedTypesAny
			],
			'power' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('power() - The power of a base value to a power value'),
				'params' => $this->param1SecCount + [
					'power' => [
						'C' => _('Power value'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'radians' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('radians() - Converts a value from degrees to radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'rand' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('rand() - A random integer value'),
				'allowed_types' => $this->allowedTypesAny
			],
			'rate' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('rate() - Returns per-second average rate for monotonically increasing counters'),
				'params' => $this->param1Sec + $this->period_optional,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'repeat' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('repeat() - Returns a string composed of value repeated count times'),
				'params' => $this->param1SecCount + [
					'count' => [
						'C' => _('Count'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'replace' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('replace() - Search value for occurrences of pattern, and replace with replacement'),
				'params' => $this->param1SecCount + [
					'pattern' => [
						'C' => _('Pattern'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'replace' => [
						'C' => _('Replacement'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'right' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('right() - Returns the rightmost count characters'),
				'params' => $this->param1SecCount + [
					'count' => [
						'C' => _('Count'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'round' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('round() - Rounds a value to decimal places'),
				'params' => $this->param1SecCount + [
					'decimals' => [
						'C' => _('Decimal places'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'rtrim' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('rtrim() - Removes specified characters from the end of a string'),
				'params' => $this->param1SecCount + [
					'chars' => [
						'C' => _('Chars'),
						'T' => T_ZBX_STR,
						'A' => false
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'signum' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('signum() - Returns -1 if a value is negative, 0 if a value is zero, 1 if a value is positive'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'sin' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('sin() - The sine of a value, where the value is an angle expressed in radians'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'sinh' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('sinh() - The hyperbolic sine of a value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'skewness' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('skewness() - Measures the asymmetry of the probability distribution'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'sqrt' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('sqrt() - Square root of a value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'stddevpop' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('stddevpop() - Population standard deviation'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'stddevsamp' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('stddevsamp() - Sample standard deviation'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'sum' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE, ZBX_FUNCTION_TYPE_MATH],
				'description' => _('sum() - Sum of values of a period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'sumofsquares' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('sumofsquares() - The sum of squares'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'tan' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('tan() - The tangent of a value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'time' => [
				'types' => [ZBX_FUNCTION_TYPE_DATE_TIME],
				'description' => _('time() - Current time'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'timeleft' => [
				'types' => [ZBX_FUNCTION_TYPE_PREDICTION],
				'description' => _('timeleft() - Time to reach threshold estimated based on period T'),
				'params' => $this->paramTimeleft,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'trendavg' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('trendavg() - Average value of a period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'baselinedev' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('baselinedev() - Returns the number of deviations between data periods in seasons and the last data period'),
				'params' => $this->period_seasons,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'baselinewma' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('baselinewma() - Calculates baseline by averaging data periods in seasons'),
				'params' => $this->period_seasons,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'trendcount' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('trendcount() - Number of successfully retrieved values for period T'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => $this->operators
			],
			'trendmax' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('trendmax() - Maximum value for period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'trendmin' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('trendmin() - Minimum value for period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'trendstl' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('trendstl() - Anomaly detection for period T'),
				'params' => [
					'last' => [
						'C' => _('Evaluation period').' (T)',
						'T' => T_ZBX_INT,
						'A' => true
					],
					'period_shift' => [
						'C' => _('Period shift'),
						'T' => T_ZBX_INT,
						'A' => true
					],
					'detect_period' => [
						'C' => _('Detection period'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'season' => [
						'C' => _('Season'),
						'T' => T_ZBX_INT,
						'A' => true
					],
					'deviations' => [
						'C' => _('Deviations'),
						'T' => T_ZBX_DBL,
						'A' => false
					],
					'algorithm' => [
						'C' => _('Algorithm'),
						'T' => T_ZBX_STR,
						'A' => false
					],
					'season_window' => [
						'C' => _('Season deviation window'),
						'T' => T_ZBX_INT,
						'A' => false
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'trendsum' => [
				'types' => [ZBX_FUNCTION_TYPE_HISTORY],
				'description' => _('trendsum() - Sum of values of a period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'trim' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('trim() - Remove specified characters from the beginning and the end of a string'),
				'params' => $this->param1SecCount + [
					'chars' => [
						'C' => _('Chars'),
						'T' => T_ZBX_STR,
						'A' => false
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			],
			'truncate' => [
				'types' => [ZBX_FUNCTION_TYPE_MATH],
				'description' => _('truncate() - Truncates a value to decimal places'),
				'params' => $this->param1SecCount + [
					'decimals' => [
						'C' => _('Decimal places'),
						'T' => T_ZBX_STR,
						'A' => true
					]
				],
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'varpop' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('varpop() - Population variance'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'varsamp' => [
				'types' => [ZBX_FUNCTION_TYPE_AGGREGATE],
				'description' => _('varsamp() - Sample variance'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => $this->operators
			],
			'xmlxpath' => [
				'types' => [ZBX_FUNCTION_TYPE_STRING],
				'description' => _('xmlxpath() - Returns XML XPath result'),
				'params' => [
					'last' => [
						'C' => _('Last of').' (T)',
						'T' => T_ZBX_INT,
						'M' => [PARAM_TYPE_COUNTS => _('Count')],
						'A' => false
					],
					'shift' => [
						'C' => _('Time shift'),
						'T' => T_ZBX_INT,
						'A' => false
					],
					'path' => [
						'C' => _('XPath'),
						'T' => T_ZBX_STR,
						'A' => true
					],
					'replace' => [
						'C' => _('Default'),
						'T' => T_ZBX_STR,
						'A' => false
					]
				],
				'allowed_types' => $this->allowedTypesStr,
				'operators' => $this->operators
			]
		];

		CArrayHelper::sort($this->functions, ['description']);
	}

	protected function checkInput() {
		$fields = [
			'dstfrm' =>				'string|fatal',
			'dstfld1' =>			'string|not_empty',
			'context' =>			'required|string|in host,template',
			'expression' =>			'string',
			'itemid' =>				'db items.itemid',
			'parent_discoveryid' =>	'db items.itemid',
			'function' =>			'in '.implode(',', array_keys($this->functions)),
			'operator' =>			'in '.implode(',', $this->operators),
			'params' =>				'',
			'paramtype' =>			'in '.implode(',', [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]),
			'value' =>				'string|not_empty',
			'hostid' =>				'db hosts.hostid',
			'groupid' =>			'db hosts_groups.hostgroupid',
			'add' =>				'in 1'
		];

		$ret = $this->validateInput($fields) || !$this->hasInput('add');

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot insert trigger expression'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
		$expression_validator = new CExpressionValidator([
			'usermacros' => true,
			'lldmacros' => true,
			'partial' => true
		]);

		$itemid = $this->getInput('itemid', 0);
		$function = $this->getInput('function', 'last');
		$operator = $this->getInput('operator', '=');
		$param_type = $this->getInput('paramtype', PARAM_TYPE_TIME);
		$dstfld1 = $this->getInput('dstfld1');
		$expression = $this->getInput('expression', '');
		$params = $this->getInput('params', []);
		$value = $this->getInput('value', 0);

		$item = false;

		// Opening the popup when editing an expression in the trigger constructor.
		if (($dstfld1 === 'expr_temp' || $dstfld1 === 'recovery_expr_temp') && $expression !== '') {
			if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
				$math_function_token = null;
				$hist_function_token = null;
				$function_token_index = null;
				$tokens = $expression_parser->getResult()->getTokens();

				foreach ($tokens as $index => $token) {
					switch ($token['type']) {
						case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
							$math_function_token = $token;
							$function_token_index = $index;

							foreach ($token['data']['parameters'] as $parameter) {
								foreach ($parameter['data']['tokens'] as $parameter_token) {
									if ($parameter_token['type'] == CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION) {
										$hist_function_token = $parameter_token;
										break 2;
									}
								}
							}
							break 2;

						case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
							$hist_function_token = $token;
							$function_token_index = $index;
							break 2;
					}
				}

				if ($function_token_index !== null) {
					/*
					 * Try to find an operator and a value.
					 * The value and operator can be extracted only if they immediately follow the function.
					 */
					$index = $function_token_index + 1;

					if (array_key_exists($index, $tokens)
							&& $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_OPERATOR
							&& in_array($tokens[$index]['match'], $this->operators)) {
						$operator = $tokens[$index]['match'];
						$index++;

						if (array_key_exists($index, $tokens)) {
							if ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_NUMBER
									|| $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_MACRO
									|| $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_USER_MACRO
									|| $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_LLD_MACRO) {
								$value = $tokens[$index]['match'];
							}
							elseif ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_STRING) {
								$value = CExpressionParser::unquoteString($tokens[$index]['match']);
							}
							elseif ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_OPERATOR
									&& array_key_exists($index + 1, $tokens)
									&& $tokens[$index + 1]['type'] == CExpressionParserResult::TOKEN_TYPE_NUMBER) {
								$value = '-'.$tokens[$index + 1]['match'];
							}
						}
					}

					// Get function parameters.
					$parameters = null;

					if ($math_function_token) {
						$function = $math_function_token['data']['function'];

						if ($hist_function_token && $hist_function_token['data']['function'] === 'last') {
							$parameters = $hist_function_token['data']['parameters'];
						}
					}
					else {
						$function = $hist_function_token['data']['function'];
						$parameters = $hist_function_token['data']['parameters'];
					}

					if ($parameters !== null) {
						$host = $hist_function_token['data']['parameters'][0]['data']['host'];
						$key = $hist_function_token['data']['parameters'][0]['data']['item'];

						$items = API::Item()->get([
							'output' => ['itemid', 'name', 'key_', 'value_type'],
							'selectHosts' => ['name'],
							'webitems' => true,
							'filter' => [
								'host' => $host,
								'key_' => $key
							]
						]);

						if (!$items) {
							$items = API::ItemPrototype()->get([
								'output' => ['itemid', 'name', 'key_', 'value_type'],
								'selectHosts' => ['name'],
								'filter' => [
									'host' => $host,
									'key_' => $key
								]
							]);
						}

						if (($item = reset($items)) === false) {
							error(_('Unknown host item, no such item in selected host'));
						}
					}

					$params = [];

					if ($parameters !== null && array_key_exists(1, $parameters)) {
						if ($function === "nodata" || $function === "fuzzytime") {
							$params[] = ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED)
								? CHistFunctionParser::unquoteParam($parameters[1]['match'])
								: $parameters[1]['match'];
						}
						else {
							if ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_PERIOD) {
								$sec_num = $parameters[1]['data']['sec_num'];
								if ($sec_num !== '' && $sec_num[0] === '#') {
									$params[] = substr($sec_num, 1);
									$param_type = PARAM_TYPE_COUNTS;
								}
								else {
									$params[] = $sec_num;
									$param_type = PARAM_TYPE_TIME;
								}
								$params[] = $parameters[1]['data']['time_shift'];
							}
							else {
								$params[] = '';
								$params[] = '';
							}
						}

						for ($i = 2; $i < count($parameters); $i++) {
							$parameter = $parameters[$i];
							$params[] = $parameter['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED
								? CHistFunctionParser::unquoteParam($parameter['match'])
								: $parameter['match'];
						}
					}
				}
			}
		}
		// Opening an empty form or switching a function.
		else {
			$item = API::Item()->get([
				'output' => ['itemid', 'name', 'key_', 'value_type'],
				'selectHosts' => ['host', 'name'],
				'itemids' => $itemid,
				'webitems' => true,
				'filter' => ['flags' => null]
			]);

			$item = reset($item);
		}

		if ($item) {
			if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
				throw new Exception(_s('Binary item "%1$s" cannot be used in trigger', $item['key_']));
			}

			$itemid = $item['itemid'];
			$item_value_type = $item['value_type'];
			$item_key = $item['key_'];
			$item_host_data = reset($item['hosts']);
			$description = $item_host_data['name'].NAME_DELIMITER.$item['name'];
		}
		else {
			$item_key = '';
			$description = '';
			$item_value_type = null;
		}

		if ($param_type === null && array_key_exists($function, $this->functions)
				&& array_key_exists('params', $this->functions[$function])
				&& array_key_exists('M', $this->functions[$function]['params'])) {
			$param_type = is_array($this->functions[$function]['params']['M'])
				? reset($this->functions[$function]['params']['M'])
				: $this->functions[$function]['params']['M'];
		}
		elseif ($param_type === null) {
			$param_type = PARAM_TYPE_TIME;
		}

		$data = [
			'parent_discoveryid' => $this->getInput('parent_discoveryid', ''),
			'dstfrm' => $this->getInput('dstfrm'),
			'dstfld1' => $dstfld1,
			'context' => $this->getInput('context'),
			'itemid' => $itemid,
			'value' => $value,
			'params' => $params,
			'paramtype' => $param_type,
			'item_description' => $description,
			'item_required' => !in_array($function, array_merge(getStandaloneFunctions(), getFunctionsConstants())),
			'functions' => $this->functions,
			'function' => $function,
			'function_type' => array_key_exists($function, $this->functions)
				? reset($this->functions[$function]['types'])
				: null,
			'operator' => $operator,
			'item_key' => $item_key,
			'itemValueType' => $item_value_type,
			'selectedFunction' => null,
			'groupid' => $this->getInput('groupid', 0),
			'hostid' => $this->getInput('hostid', 0)
		];

		// Check if submitted function is usable with selected item.
		foreach ($data['functions'] as $id => $f) {
			if (($data['itemValueType'] === null || array_key_exists($item_value_type, $f['allowed_types']))
					&& $id === $function) {
				$data['selectedFunction'] = $id;
				break;
			}
		}

		if ($data['selectedFunction'] === null) {
			$data['selectedFunction'] = 'last';
			$data['function'] = 'last';
			$data['function_type'] = ZBX_FUNCTION_TYPE_HISTORY;
		}

		// Remove functions that not correspond to chosen item.
		foreach ($data['functions'] as $id => $f) {
			if ($data['itemValueType'] !== null && !array_key_exists($data['itemValueType'], $f['allowed_types'])) {
				unset($data['functions'][$id]);

				// Take first available function from list.
				if ($id === $data['function']) {
					$data['function'] = key($data['functions']);
					$data['function_type'] = reset($data['functions'][$data['function']]['types']);
					$data['operator'] = reset($data['functions'][$data['function']]['operators']);
				}
			}
		}

		// Create and validate trigger expression before inserting it into textarea field.
		if ($this->getInput('add', false)) {
			try {
				if (in_array($function, getFunctionsConstants())) {
					$data['expression'] = sprintf('%s()', $function);
				}
				elseif (in_array($function, getStandaloneFunctions())) {
					$data['expression'] = sprintf('%s()%s%s', $function, $operator,
						CExpressionParser::quoteString($data['value'])
					);
				}
				elseif ($data['item_description']) {
					// Quote function string parameters.
					$quote_params = [
						'algorithm',
						'chars',
						'fit',
						'mode',
						'o',
						'pattern',
						'path',
						'replace',
						'season_unit',
						'string',
						'v'
					];
					$quote_params = array_intersect_key($data['params'], array_fill_keys($quote_params, ''));
					$quote_params = array_filter($quote_params, 'strlen');

					foreach ($quote_params as $param_key => $param) {
						$data['params'][$param_key] = CExpressionParser::quoteString($param);
					}

					// Combine sec|#num and <time_shift|period_shift> parameters into one.
					if (array_key_exists('last', $data['params'])) {
						if ($data['paramtype'] == PARAM_TYPE_COUNTS && zbx_is_int($data['params']['last'])) {
							$data['params']['last'] = '#'.$data['params']['last'];
						}
					}
					else {
						$data['params']['last'] = '';
					}

					if (array_key_exists('shift', $data['params']) && $data['params']['shift'] !== '') {
						$data['params']['last'] .= ':'.$data['params']['shift'];
					}
					elseif (array_key_exists('period_shift', $data['params'])
							&& $data['params']['period_shift'] !== '') {
						$data['params']['last'] .= ':'.$data['params']['period_shift'];
					}
					unset($data['params']['shift'], $data['params']['period_shift']);

					// Functions where item is wrapped in last() like func(last(/host/item)).
					$last_functions = [
						'abs', 'acos', 'ascii', 'asin', 'atan', 'atan2', 'between', 'bitand', 'bitlength', 'bitlshift',
						'bitnot', 'bitor', 'bitrshift', 'bitxor', 'bytelength', 'cbrt', 'ceil', 'char', 'concat', 'cos',
						'cosh', 'cot', 'degrees', 'exp', 'expm1', 'floor', 'in', 'insert', 'jsonpath', 'left', 'length',
						'log', 'log10', 'ltrim', 'mid', 'mod', 'power', 'radians', 'repeat', 'replace', 'right', 'round',
						'signum', 'sin', 'sinh', 'sqrt', 'tan', 'trim', 'truncate', 'xmlxpath'
					];

					if (in_array($function, $last_functions)) {
						$last_params = $data['params']['last'];
						unset($data['params']['last']);
						$fn_params = rtrim(implode(',', $data['params']), ',');

						$data['expression'] = sprintf('%s(last(/%s/%s%s)%s)%s%s',
							$function,
							$item_host_data['host'],
							$data['item_key'],
							($last_params === '') ? '' : ','.$last_params,
							($fn_params === '') ? '' : ','.$fn_params,
							$operator,
							CExpressionParser::quoteString($data['value'])
						);
					}
					else {
						$fn_params = rtrim(implode(',', $data['params']), ',');

						$data['expression'] = sprintf('%s(/%s/%s%s)%s%s',
							$function,
							$item_host_data['host'],
							$data['item_key'],
							($fn_params === '') ? '' : ','.$fn_params,
							$operator,
							CExpressionParser::quoteString($data['value'])
						);
					}
				}
				else {
					error(_('Item not selected'));
				}

				if (array_key_exists('expression', $data)) {
					// Parse and validate trigger expression.
					if ($expression_parser->parse($data['expression']) == CParser::PARSE_SUCCESS) {
						if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
							error(_s('Invalid condition: %1$s.', $expression_validator->getError()));
						}
					}
					else {
						error($expression_parser->getError());
					}
				}
			}
			catch (Exception $e) {
				error($e->getMessage());
			}

			if ($messages = get_and_clear_messages()) {
				$output = [
					'error' => [
						'title' => _('Cannot insert trigger expression'),
						'messages' => array_column($messages, 'message')
					]
				];
			}
			else {
				$output = [
					'expression' => $data['expression'],
					'dstfld1' => $data['dstfld1'],
					'dstfrm' => $data['dstfrm']
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$this->setResponse(new CControllerResponseData(
				$data + [
					'title' => _('Condition'),
					'messages' => hasErrorMessages() ? getMessages() : null,
					'user' => [
						'debug_mode' => $this->getDebugMode()
					]
				]
			));
		}
	}
}
