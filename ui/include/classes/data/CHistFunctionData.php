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
	public const PERIOD_MODE_SEC_POSITIVE = 1;
	public const PERIOD_MODE_SEC_POSITIVE_OR_ZERO = 2;
	public const PERIOD_MODE_TREND = 3;

	private const PARAMETERS = [
		'avg' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD]
		],
		'avg_foreach' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE]
		],
		'count' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^(eq|ne|gt|ge|lt|le|like|bitand|regexp|iregexp)$/']
			]],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false]
		],
		'count_foreach' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE]
		],
		'change' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY]
		],
		'find' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'required' => false],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^(iregexp|regexp|like)$/']
			]],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false]
		],
		'forecast' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD],
			['type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED, 'rules' => [
				['type' => 'simple_interval', 'options' => ['negative' => true]]
			]],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^(linear|polynomial[1-6]|exponential|logarithmic|power)$/']
			]],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^(value|max|min|delta|avg)$/']
			]]
		],
		'fuzzytime' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE_OR_ZERO]
		],
		'last' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'required' => false]
		],
		'last_foreach' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'required' => false,
				'mode' => self::PERIOD_MODE_SEC_POSITIVE
			]
		],
		'logeventid' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'required' => false],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false]
		],
		'logseverity' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'required' => false]
		],
		'logsource' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'required' => false],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false]
		],
		'max' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD]
		],
		'max_foreach' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE]
		],
		'min' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD]
		],
		'min_foreach' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE]
		],
		'nodata' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^(strict)$/']
			]]
		],
		'percentile' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD],
			['type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^((\d+(\.\d{0,4})?)|(\.\d{1,4}))$/'],
				['type' => 'range', 'min' => 0, 'max' => 100]
			]]
		],
		'sum' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD]
		],
		'sum_foreach' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_SEC_POSITIVE]
		],
		'timeleft' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD],
			['type' => CHistFunctionParser::PARAM_TYPE_UNQUOTED],
			['type' => CHistFunctionParser::PARAM_TYPE_QUOTED, 'required' => false, 'rules' => [
				['type' => 'regexp', 'pattern' => '/^(linear|polynomial[1-6]|exponential|logarithmic|power)$/']
			]]
		],
		'trendavg' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_TREND]
		],
		'trendcount' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_TREND]
		],
		'trendmax' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_TREND]
		],
		'trendmin' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_TREND]
		],
		'trendsum' => [
			['type' => CHistFunctionParser::PARAM_TYPE_QUERY],
			['type' => CHistFunctionParser::PARAM_TYPE_PERIOD, 'mode' => self::PERIOD_MODE_TREND]
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
		'find' => self::ITEM_VALUE_TYPES_STR,
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
		'trendcount' => self::ITEM_VALUE_TYPES_ALL,
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
