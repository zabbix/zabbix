<?php declare(strict_types = 0);
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
		'abs' =>				[['count' => 1]],
		'acos' =>				[['count' => 1]],
		'ascii' =>				[['count' => 1]],
		'asin' =>				[['count' => 1]],
		'atan' =>				[['count' => 1]],
		'atan2' =>				[['count' => 2]],
		'avg' => 				[['min' => 1]],
		'between' =>			[['count' => 3]],
		'bitand' =>				[['count' => 2]],
		'bitlength' =>			[['count' => 1]],
		'bitlshift' =>			[['count' => 2]],
		'bitnot' =>				[['count' => 1]],
		'bitor' =>				[['count' => 2]],
		'bitrshift' =>			[['count' => 2]],
		'bitxor' =>				[['count' => 2]],
		'bytelength' =>			[['count' => 1]],
		'cbrt' =>				[['count' => 1]],
		'ceil' =>				[['count' => 1]],
		'char' =>				[['count' => 1]],
		'concat' =>				[['min' => 2]],
		'cos' =>				[['count' => 1]],
		'cosh' =>				[['count' => 1]],
		'cot' =>				[['count' => 1]],
		'count' =>				[['min' => 1, 'max' => 3]],
		'date' =>				[['count' => 0]],
		'dayofmonth' =>			[['count' => 0]],
		'dayofweek' =>			[['count' => 0]],
		'degrees' =>			[['count' => 1]],
		'e' =>					[['count' => 0]],
		'exp' =>				[['count' => 1]],
		'expm1' =>				[['count' => 1]],
		'floor' =>				[['count' => 1]],
		'histogram_quantile' =>	[['min' => 5, 'step' => 2], ['count' => 2]],
		'in' =>					[['min' => 2]],
		'insert' =>				[['count' => 4]],
		'jsonpath' =>			[['min' => 2, 'max' => 3]],
		'kurtosis' =>			[['min' => 1, 'max' => 2]],
		'left' =>				[['count' => 2]],
		'length' =>				[['count' => 1]],
		'log' =>				[['count' => 1]],
		'log10' =>				[['count' => 1]],
		'ltrim' =>				[['min' => 1, 'max' => 2]],
		'mad' =>				[['min' => 1, 'max' => 2]],
		'max' =>				[['min' => 1]],
		'mid' =>				[['count' => 3]],
		'min' =>				[['min' => 1]],
		'mod' =>				[['count' => 2]],
		'now' =>				[['count' => 0]],
		'pi' =>					[['count' => 0]],
		'power' =>				[['count' => 2]],
		'radians' =>			[['count' => 1]],
		'rand' =>				[['count' => 0]],
		'repeat' =>				[['count' => 2]],
		'replace' =>			[['count' => 3]],
		'right' =>				[['count' => 2]],
		'round' =>				[['count' => 2]],
		'rtrim' =>				[['min' => 1, 'max' => 2]],
		'signum' =>				[['count' => 1]],
		'sin' =>				[['count' => 1]],
		'sinh' =>				[['count' => 1]],
		'skewness' =>			[['min' => 1, 'max' => 2]],
		'sqrt' =>				[['count' => 1]],
		'stddevpop' =>			[['min' => 1, 'max' => 2]],
		'stddevsamp' =>			[['min' => 1, 'max' => 2]],
		'sum' =>				[['min' => 1]],
		'sumofsquares' =>		[['min' => 1, 'max' => 2]],
		'tan' =>				[['count' => 1]],
		'time' =>				[['count' => 0]],
		'trim' =>				[['min' => 1, 'max' => 2]],
		'truncate' =>			[['count' => 2]],
		'varpop' =>				[['min' => 1, 'max' => 2]],
		'varsamp' =>			[['min' => 1, 'max' => 2]],
		'xmlxpath' =>			[['min' => 2, 'max' => 3]]
	];

	/**
	 * Additional requirements for math function usage in expressions.
	 *
	 * @var array
	 */
	private const EXPRESSION_RULES = [
		'count' => [
			[
				'if' => [
					'parameters' => ['count' => 1]
				],
				'rules' => [
					[
						'type' => 'require_history_child',
						'in' => ['avg_foreach', 'count_foreach', 'exists_foreach', 'last_foreach',
							'max_foreach', 'min_foreach', 'sum_foreach'
						],
						'position' => 0
					]
				]
			],
			[
				'if' => [
					'parameters' => ['min' => 2, 'max' => 3]
				],
				'rules' => [
					[
						'type' => 'require_history_child',
						'in' => ['avg_foreach', 'count_foreach', 'exists_foreach', 'last_foreach',
							'max_foreach', 'min_foreach', 'sum_foreach'
						],
						'position' => 0
					],
					[
						'type' => 'regexp',
						'pattern' => '/^(eq|ne|gt|ge|lt|le|like|bitand|regexp|iregexp)$/',
						'position' => 1
					]
				]
			]
		],
		'histogram_quantile' => [[
			'if' => [
				'parameters' => ['count' => 2]
			],
			'rules' => [[
				'type' => 'require_history_child',
				'in' => ['bucket_rate_foreach'],
				'position' => 1
			]]
		]]
	];

	/**
	 * A subset of aggregating math functions for use in calculated item formulas.
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
	 * Get additional requirements for math function usage in expressions.
	 *
	 * @return array
	 */
	public function getExpressionRules(): array {
		if ($this->options['calculated']) {
			return self::EXPRESSION_RULES;
		}

		return array_diff_key(self::EXPRESSION_RULES, array_flip(self::CALCULATED_ONLY));
	}
}
