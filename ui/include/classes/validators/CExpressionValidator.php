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
 * Class for validating trigger expressions and calculated item formulas.
 */
class CExpressionValidator extends CValidator {

	/**
	 * Math functions accepting calculated history functions for aggregation in calculated item formulas.
	 *
	 * @var array
	 */
	private const AGGREGATE_MATH_FUNCTIONS = ['avg', 'max', 'min', 'sum'];

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'calculated' => false  Validate expression as part of calculated item formula.
	 *
	 * @var array
	 */
	private $options = [
		'calculated' => false
	];

	private $hist_function_parameters = [];

	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$hist_function_data = new CHistFunctionData(['calculated' => $this->options['calculated']]);

		$this->hist_function_parameters = $hist_function_data->getParameters();
	}

	/**
	 * Validate expression.
	 *
	 * @param array $tokens  A hierarchy of tokens of parsed expression.
	 *
	 * @return bool
	 */
	public function validate($tokens) {
		if (!$this->validateRecursively($tokens, null)) {
			return false;
		}

		if (!$this->options['calculated']) {
			if (!self::hasHistoryFunctions($tokens)) {
				$this->setError(_('trigger expression must contain at least one /host/key reference'));

				return false;
			}
		}

		return true;
	}

	private function validateRecursively(array $tokens, ?array $parent_token) {
		foreach ($tokens as $token) {
			switch ($token['type']) {
				case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
					$math_function_validator = new CMathFunctionValidator();

					if (!$math_function_validator->validate($token)) {
						$this->setError($math_function_validator->getError());

						return false;
					}

					foreach ($token['data']['parameters'] as $parameter) {
						if (!$this->validateRecursively($parameter['data']['tokens'], $token)) {
							return false;
						}
					}

					break;

				case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
					$options = [
						'parameters' => $this->hist_function_parameters,
						'calculated' => $this->options['calculated']
					];

					if ($this->options['calculated']) {
						$options['aggregated'] = CHistFunctionData::isAggregated($token['data']['function']);
					}

					$hist_function_validator = new CHistFunctionValidator($options);

					if (!$hist_function_validator->validate($token)) {
						$this->setError($hist_function_validator->getError());

						return false;
					}

					if ($options['calculated'] && $options['aggregated']) {
						if ($parent_token === null
								|| $parent_token['type'] != CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION
								|| !in_array($parent_token['data']['function'], self::AGGREGATE_MATH_FUNCTIONS)
								|| count($parent_token['data']['parameters']) != 1) {
							$this->setError(_s('incorrect usage of function "%1$s"', $token['data']['function']));

							return false;
						}
					}

					break;

				case CExpressionParserResult::TOKEN_TYPE_EXPRESSION:
					if (!$this->validateRecursively($parameter['data']['tokens'], $parent_token)) {
						return false;
					}

					break;
			}
		}

		return true;
	}

	private static function hasHistoryFunctions(array $tokens) {
		foreach ($tokens as $token) {
			switch ($token['type']) {
				case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
					foreach ($token['data']['parameters'] as $parameter) {
						if (self::hasHistoryFunctions($parameter['data']['tokens'])) {
							return true;
						}
					}

					break;

				case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
					return true;

				case CExpressionParserResult::TOKEN_TYPE_EXPRESSION:
					return self::hasHistoryFunctions($parameter['data']['tokens']);
			}
		}

		return false;
	}
}
