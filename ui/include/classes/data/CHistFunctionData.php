<?php declare(strict_types = 0);
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
 * Class containing information on history functions.
 */
final class CHistFunctionData {

	public const PERIOD_MODE_DEFAULT = 0;
	public const PERIOD_MODE_SEC = 1;
	public const PERIOD_MODE_SEC_ONLY = 2;
	public const PERIOD_MODE_NUM_ONLY = 3;
	public const PERIOD_MODE_TREND = 4;

	/**
	 * Known history functions along with definition of parameters.
	 *
	 * @var array
	 */
	private const PARAMETERS = [
		'avg' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'avg_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]]
		],
		'bucket_percentile' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^((\d+(\.\d{0,4})?)|(\.\d{1,4}))$/'],
				['type' => 'number', 'min' => 0, 'max' => 100]
			]]
		],
		'bucket_rate_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^\d+$/'],
				['type' => 'number', 'min' => 1]
			], 'required' => false]
		],
		'change' => [
			['rules' => [['type' => 'query']]]
		],
		'changecount' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(inc|dec|all)$/']], 'required' => false]
		],
		'count' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(eq|ne|gt|ge|lt|le|like|bitand|regexp|iregexp)$/']],
				'required' => false
			],
			['required' => false]
		],
		'count_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]]
		],
		'countunique' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(eq|ne|gt|ge|lt|le|like|bitand|regexp|iregexp)$/']],
				'required' => false
			],
			['required' => false]
		],
		'exists_foreach' => [
			['rules' => [['type' => 'query']]]
		],
		'find' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]], 'required' => false],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(eq|ne|gt|ge|lt|le|like|bitand|regexp|iregexp)$/']],
				'required' => false
			],
			['required' => false]
		],
		'first' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
		],
		'forecast' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'time']]],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^(linear|polynomial[1-6]|exponential|logarithmic|power)$/']
			], 'required' => false],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(value|max|min|delta|avg)$/']], 'required' => false]
		],
		'fuzzytime' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'time', 'min' => 1]]]
		],
		'item_count' => [
			['rules' => [['type' => 'query']]]
		],
		'kurtosis' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'last' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM_ONLY]], 'required' => false]
		],
		'last_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]], 'required' => false]
		],
		'logeventid' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM_ONLY]], 'required' => false],
			['required' => false]
		],
		'logseverity' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM_ONLY]], 'required' => false]
		],
		'logsource' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM_ONLY]], 'required' => false],
			['required' => false]
		],
		'mad' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'max' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'max_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]]
		],
		'min' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'min_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]]
		],
		'monodec' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(weak|strict)$/']], 'required' => false]
		],
		'monoinc' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(weak|strict)$/']], 'required' => false]
		],
		'nodata' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'time', 'min' => 1]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^(strict)$/']], 'required' => false]
		],
		'percentile' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^((\d+(\.\d{0,4})?)|(\.\d{1,4}))$/'],
				['type' => 'number', 'min' => 0, 'max' => 100]
			]]
		],
		'rate' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
		],
		'skewness' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'stddevpop' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'stddevsamp' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'sum' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'sum_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC_ONLY]]]
		],
		'sumofsquares' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'timeleft' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]],
			['rules' => [['type' => 'number', 'with_suffix' => true]]],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^(linear|polynomial[1-6]|exponential|logarithmic|power)$/']
			], 'required' => false]
		],
		'trendavg' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]]
		],
		'baselinedev' => [
			['rules' => [['type' => 'query']]],
			['rules' => [[
				'type' => 'period',
				'mode' => self::PERIOD_MODE_TREND,
				'min' => SEC_PER_HOUR,
				'aligned_shift' => true
			]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^[hdwMy]$/']]],
			['rules' => [['type' => 'number', 'with_suffix' => false, 'min' => 1, 'with_float' => false]]]
		],
		'baselinewma' => [
			['rules' => [['type' => 'query']]],
			['rules' => [[
				'type' => 'period',
				'mode' => self::PERIOD_MODE_TREND,
				'min' => SEC_PER_HOUR,
				'aligned_shift' => true
			]]],
			['rules' => [['type' => 'regexp', 'pattern' => '/^[hdwMy]$/']]],
			['rules' => [['type' => 'number', 'with_suffix' => false, 'min' => 1, 'with_float' => false]]]
		],
		'trendcount' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]]
		],
		'trendmax' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]]
		],
		'trendmin' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]]
		],
		'trendstl' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]],
			['rules' => [['type' => 'time', 'min' => SEC_PER_HOUR]]],
			['rules' => [['type' => 'time', 'min' => SEC_PER_HOUR * 2]]],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^((\d+(\.\d{0,4})?)|(\.\d{1,4}))$/'],
				['type' => 'number', 'min' => 1, 'max' => ZBX_MAX_INT32]
			], 'required' => false],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^(mad|stddevpop|stddevsamp)$/']
			], 'required' => false],
			['rules' => [
				['type' => 'regexp', 'pattern' => '/^\d+$/'],
				['type' => 'number', 'min' => 7, 'max' => ZBX_MAX_INT32]
			], 'required' => false]
		],
		'trendsum' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]]
		],
		'varpop' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'varsamp' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		]
	];

	/**
	 * Additional requirements for history function usage in expressions.
	 *
	 * @var array
	 */
	private const EXPRESSION_RULES = [
		'avg_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]],
		'bucket_rate_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['histogram_quantile'],
			'parameters' => ['count' => 2],
			'position' => 1
		]],
		'count_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]],
		'exists_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]],
		'last_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]],
		'max_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]],
		'min_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]],
		'sum_foreach' => [[
			'type' => 'require_math_parent',
			'in' => ['avg', 'count', 'max', 'min', 'sum'],
			'parameters' => ['count' => 1],
			'position' => 0
		]]
	];

	/**
	 * A subset of aggregating history functions for use in calculated item formulas.
	 *
	 * @var array
	 */
	private const AGGREGATING = [
		'avg_foreach',
		'bucket_percentile',
		'bucket_rate_foreach',
		'count_foreach',
		'exists_foreach',
		'item_count',
		'last_foreach',
		'max_foreach',
		'min_foreach',
		'sum_foreach'
	];

	private const ITEM_VALUE_TYPES_NUM = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
	private const ITEM_VALUE_TYPES_LOG = [ITEM_VALUE_TYPE_LOG];
	private const ITEM_VALUE_TYPES_ALL = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR,
		ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG
	];

	/**
	 * Known history functions along with supported item value types.
	 *
	 * @var array
	 */
	private const VALUE_TYPES = [
		'avg' => self::ITEM_VALUE_TYPES_NUM,
		'avg_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'bucket_percentile' => self::ITEM_VALUE_TYPES_NUM,
		'bucket_rate_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'change' => self::ITEM_VALUE_TYPES_ALL,
		'changecount' => self::ITEM_VALUE_TYPES_ALL,
		'count' => self::ITEM_VALUE_TYPES_ALL,
		'count_foreach' => self::ITEM_VALUE_TYPES_ALL,
		'countunique' => self::ITEM_VALUE_TYPES_ALL,
		'exists_foreach' => self::ITEM_VALUE_TYPES_ALL,
		'find' => self::ITEM_VALUE_TYPES_ALL,
		'first' => self::ITEM_VALUE_TYPES_ALL,
		'forecast' => self::ITEM_VALUE_TYPES_NUM,
		'fuzzytime' => self::ITEM_VALUE_TYPES_NUM,
		'item_count' => self::ITEM_VALUE_TYPES_ALL,
		'kurtosis' => self::ITEM_VALUE_TYPES_NUM,
		'last' => self::ITEM_VALUE_TYPES_ALL,
		'last_foreach' => self::ITEM_VALUE_TYPES_ALL,
		'logeventid' => self::ITEM_VALUE_TYPES_LOG,
		'logseverity' => self::ITEM_VALUE_TYPES_LOG,
		'logsource' => self::ITEM_VALUE_TYPES_LOG,
		'mad' => self::ITEM_VALUE_TYPES_NUM,
		'max' => self::ITEM_VALUE_TYPES_NUM,
		'max_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'min' => self::ITEM_VALUE_TYPES_NUM,
		'min_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'monodec' =>  self::ITEM_VALUE_TYPES_NUM,
		'monoinc' =>  self::ITEM_VALUE_TYPES_NUM,
		'nodata' => self::ITEM_VALUE_TYPES_ALL,
		'percentile' => self::ITEM_VALUE_TYPES_NUM,
		'rate' => self::ITEM_VALUE_TYPES_NUM,
		'skewness' => self::ITEM_VALUE_TYPES_NUM,
		'stddevpop' => self::ITEM_VALUE_TYPES_NUM,
		'stddevsamp' => self::ITEM_VALUE_TYPES_NUM,
		'sum' => self::ITEM_VALUE_TYPES_NUM,
		'sum_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'sumofsquares' => self::ITEM_VALUE_TYPES_NUM,
		'timeleft' => self::ITEM_VALUE_TYPES_NUM,
		'trendavg' => self::ITEM_VALUE_TYPES_NUM,
		'baselinedev' => self::ITEM_VALUE_TYPES_NUM,
		'baselinewma' => self::ITEM_VALUE_TYPES_NUM,
		'trendcount' => self::ITEM_VALUE_TYPES_NUM,
		'trendmax' => self::ITEM_VALUE_TYPES_NUM,
		'trendmin' => self::ITEM_VALUE_TYPES_NUM,
		'trendstl' => self::ITEM_VALUE_TYPES_NUM,
		'trendsum' => self::ITEM_VALUE_TYPES_NUM,
		'varpop' => self::ITEM_VALUE_TYPES_NUM,
		'varsamp' => self::ITEM_VALUE_TYPES_NUM
	];

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'calculated' => false  Provide history functions data for use in calculated item formulas.
	 *
	 * @var array
	 */
	private $options = [
		'calculated' => false
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Check if function is known history function.
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public function isKnownFunction(string $function): bool {
		if (!array_key_exists($function, self::PARAMETERS)) {
			return false;
		}

		if (!$this->options['calculated'] && in_array($function, self::AGGREGATING, true)) {
			return false;
		}

		return true;
	}

	/**
	 * Get known history functions along with definition of parameters.
	 *
	 * @return array
	 */
	public function getParameters(): array {
		if ($this->options['calculated']) {
			return self::PARAMETERS;
		}

		return array_diff_key(self::PARAMETERS, array_flip(self::AGGREGATING));
	}

	/**
	 * Get additional requirements for history function usage in expressions.
	 *
	 * @return array
	 */
	public function getExpressionRules(): array {
		if ($this->options['calculated']) {
			return self::EXPRESSION_RULES;
		}

		return array_diff_key(self::EXPRESSION_RULES, array_flip(self::AGGREGATING));
	}

	/**
	 * Check if function is aggregating wildcarded host/item queries and is exclusive to calculated item formulas.
	 *
	 * @static
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public function isAggregating(string $function): bool {
		return in_array($function, self::AGGREGATING);
	}

	/**
	 * Get known history functions along with supported item value types.
	 *
	 * @return array
	 */
	public function getValueTypes(): array {
		if ($this->options['calculated']) {
			return self::VALUE_TYPES;
		}

		return array_diff_key(self::VALUE_TYPES, array_flip(self::AGGREGATING));
	}
}
