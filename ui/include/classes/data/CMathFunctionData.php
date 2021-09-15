<?php declare(strict_types = 1);
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
 * Class containing information on math functions.
 */
final class CMathFunctionData {

	/**
	 * Known math functions along with number or range of required parameters.
	 *
	 * @var array
	 */
	private const PARAMETERS = [
		'abs' => [
			['count' => 1]
		],
		'acos' => [
			['count' => 1]
		],
		'ascii' => [
			['count' => 1]
		],
		'asin' => [
			['count' => 1]
		],
		'atan' => [
			['count' => 1]
		],
		'atan2' => [
			['count' => 2]
		],
		'avg' => [
			['min' => 1]
		],
		'between' => [
			['count' => 3]
		],
		'bitand' => [
			['count' => 2]
		],
		'bitlength' => [
			['count' => 1]
		],
		'bitlshift' => [
			['count' => 2]
		],
		'bitnot' => [
			['count' => 1]
		],
		'bitor' => [
			['count' => 2]
		],
		'bitrshift' => [
			['count' => 2]
		],
		'bitxor' => [
			['count' => 2]
		],
		'bytelength' => [
			['count' => 1]
		],
		'cbrt' => [
			['count' => 1]
		],
		'ceil' => [
			['count' => 1]
		],
		'char' => [
			['count' => 1]
		],
		'concat' => [
			['count' => 2]
		],
		'cos' => [
			['count' => 1]
		],
		'cosh' => [
			['count' => 1]
		],
		'cot' => [
			['count' => 1]
		],
		'count' => [
			['count' => 1]
		],
		'date' => [
			['count' => 0]
		],
		'dayofmonth' => [
			['count' => 0]
		],
		'dayofweek' => [
			['count' => 0]
		],
		'degrees' => [
			['count' => 1]
		],
		'e' => [
			['count' => 0]
		],
		'exp' => [
			['count' => 1]
		],
		'expm1' => [
			['count' => 1]
		],
		'floor' => [
			['count' => 1]
		],
		'histogram_quantile' => [
			['min' => 5, 'step' => 2],
			['count' => 2]
		],
		'in' => [
			['min' => 2]
		],
		'insert' => [
			['count' => 4]
		],
		'left' => [
			['count' => 2]
		],
		'length' => [
			['count' => 1]
		],
		'log' => [
			['count' => 1]
		],
		'log10' => [
			['count' => 1]
		],
		'ltrim' => [
			['min' => 1, 'max' => 2]
		],
		'max' => [
			['min' => 1]
		],
		'mid' => [
			['count' => 3]
		],
		'min' => [
			['min' => 1]
		],
		'mod' => [
			['count' => 2]
		],
		'now' => [
			['count' => 0]
		],
		'pi' => [
			['count' => 0]
		],
		'power' => [
			['count' => 2]
		],
		'radians' => [
			['count' => 1]
		],
		'rand' => [
			['count' => 0]
		],
		'repeat' => [
			['count' => 2]
		],
		'replace' => [
			['count' => 3]
		],
		'right' => [
			['count' => 2]
		],
		'round' => [
			['count' => 2]
		],
		'rtrim' => [
			['min' => 1, 'max' => 2]
		],
		'signum' => [
			['count' => 1]
		],
		'sin' => [
			['count' => 1]
		],
		'sinh' => [
			['count' => 1]
		],
		'sqrt' => [
			['count' => 1]
		],
		'sum' => [
			['min' => 1]
		],
		'tan' => [
			['count' => 1]
		],
		'time' => [
			['count' => 0]
		],
		'trim' => [
			['min' => 1, 'max' => 2]
		],
		'truncate' => [
			['count' => 2]
		]
	];

	/**
	 * A subset of math functions for use in calculated item formulas only.
	 *
	 * @var array
	 */
	private const CALCULATED_ONLY = [
		'count',
		'histogram_quantile'
	];

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'calculated' => false  Provide math functions data for use in calculated item formulas.
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
	 * Check if function is known math function.
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public function isKnownFunction(string $function): bool {
		if (!array_key_exists($function, self::PARAMETERS)) {
			return false;
		}

		if (!$this->options['calculated'] && in_array($function, self::CALCULATED_ONLY, true)) {
			return false;
		}

		return true;
	}

	/**
	 * Get known math functions along with number or range of required parameters.
	 *
	 * @return array
	 */
	public function getParameters(): array {
		if ($this->options['calculated']) {
			return self::PARAMETERS;
		}

		return array_diff_key(self::PARAMETERS, array_flip(self::CALCULATED_ONLY));
	}

	/**
	 * Check if function is aggregating it's parameters.
	 *
	 * @static
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public static function isAggregating(string $function): bool {
		switch ($function) {
			case 'avg':
			case 'count':
			case 'max':
			case 'min':
			case 'sum':
				return true;

			default:
				return false;
		}
	}

	/**
	 * Check if function is only aggregating the result of aggregating history functions.
	 *
	 * @static
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public static function isAggregatingHistOnly(string $function): bool {
		switch ($function) {
			case 'count':
				return true;

			default:
				return false;
		}
	}

	/**
	 * Check if function is supports aggregation of history bucket aggregation functions. It uses 2nd parameter as
	 * aggregating history functions.
	 *
	 * @See CHistFunctionData::isAggregatableBucket().
	 *
	 * @static
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public static function isAggregatingBucket(string $function): bool {
		switch ($function) {
			case 'histogram_quantile':
				return true;

			default:
				return false;
		}
	}
}
