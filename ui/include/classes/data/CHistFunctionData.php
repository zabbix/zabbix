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


final class CHistFunctionData {

	public const PERIOD_MODE_DEFAULT = 0;
	public const PERIOD_MODE_SEC = 1;
	public const PERIOD_MODE_NUM = 2;
	public const PERIOD_MODE_TREND = 3;

	private const PARAMETERS = [
		'avg' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'avg_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
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
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
		],
		'change' => [
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
		'last' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM]], 'required' => false]
		],
		'last_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]], 'required' => false]
		],
		'logeventid' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM]], 'required' => false],
			['required' => false]
		],
		'logseverity' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM]], 'required' => false]
		],
		'logsource' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_NUM]], 'required' => false],
			['required' => false]
		],
		'max' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'max_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
		],
		'min' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'min_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
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
		'sum' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_DEFAULT]]]
		],
		'sum_foreach' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_SEC]]]
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
		'trendsum' => [
			['rules' => [['type' => 'query']]],
			['rules' => [['type' => 'period', 'mode' => self::PERIOD_MODE_TREND]]]
		]
	];

	private const ITEM_VALUE_TYPES_INT = [ITEM_VALUE_TYPE_UINT64];
	private const ITEM_VALUE_TYPES_NUM = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
	private const ITEM_VALUE_TYPES_STR = [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG];
	private const ITEM_VALUE_TYPES_LOG = [ITEM_VALUE_TYPE_LOG];
	private const ITEM_VALUE_TYPES_ALL = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR,
		ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG
	];

	private const VALUE_TYPES = [
		'avg' => self::ITEM_VALUE_TYPES_NUM,
		'avg_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'count' => self::ITEM_VALUE_TYPES_ALL,
		'count_foreach' => self::ITEM_VALUE_TYPES_ALL,
		'change' => self::ITEM_VALUE_TYPES_ALL,
		'find' => self::ITEM_VALUE_TYPES_ALL,
		'forecast' => self::ITEM_VALUE_TYPES_NUM,
		'fuzzytime' => self::ITEM_VALUE_TYPES_NUM,
		'last' => self::ITEM_VALUE_TYPES_ALL,
		'last_foreach' => self::ITEM_VALUE_TYPES_ALL,
		'logeventid' => self::ITEM_VALUE_TYPES_LOG,
		'logseverity' => self::ITEM_VALUE_TYPES_LOG,
		'logsource' => self::ITEM_VALUE_TYPES_LOG,
		'max' => self::ITEM_VALUE_TYPES_NUM,
		'max_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'min' => self::ITEM_VALUE_TYPES_NUM,
		'min_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'nodata' => self::ITEM_VALUE_TYPES_ALL,
		'percentile' => self::ITEM_VALUE_TYPES_NUM,
		'sum' => self::ITEM_VALUE_TYPES_NUM,
		'sum_foreach' => self::ITEM_VALUE_TYPES_NUM,
		'timeleft' => self::ITEM_VALUE_TYPES_NUM,
		'trendavg' => self::ITEM_VALUE_TYPES_NUM,
		'trendcount' => self::ITEM_VALUE_TYPES_NUM,
		'trendmax' => self::ITEM_VALUE_TYPES_NUM,
		'trendmin' => self::ITEM_VALUE_TYPES_NUM,
		'trendsum' => self::ITEM_VALUE_TYPES_NUM
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
	 * @param bool  $options['calculated']
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	public static function isCalculated(string $function): bool {
		switch ($function) {
			case 'avg_foreach':
			case 'count_foreach':
			case 'last_foreach':
			case 'max_foreach':
			case 'min_foreach':
			case 'sum_foreach':
				return true;

			default:
				return false;
		}
	}

	public function getParameters(): array {
		if ($this->options['calculated']) {
			return self::PARAMETERS;
		}

		$result = [];

		foreach (self::PARAMETERS as $function => $parameters) {
			if (self::isCalculated($function)) {
				continue;
			}

			$result[$function] = $parameters;
		}

		return $result;
	}

	public function getValueTypes(): array {
		if ($this->options['calculated']) {
			return self::VALUE_TYPES;
		}

		$result = [];

		foreach (self::VALUE_TYPES as $function => $value_types) {
			if (self::isCalculated($function)) {
				continue;
			}

			$result[$function] = $value_types;
		}

		return $result;
	}
}
