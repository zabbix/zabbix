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

	private $math_function_validator;

	private $hist_function_validator;

	private $hist_function_data;

	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->hist_function_data = new CHistFunctionData([
			'calculated' => $this->options['calculated']
		]);
		$this->math_function_validator = new CMathFunctionValidator();
		$this->hist_function_validator = new CHistFunctionValidator([
			'parameters' => $this->hist_function_data->getParameters()
		]);
	}

	/**
	 * Validate expression.
	 *
	 * @param array $tokens  A hierarchy of tokens of parsed expression.
	 *
	 * @return bool
	 */
	public function validate($tokens) {
		return $this->validateRecursively($tokens, null);
	}

	private function validateRecursively(array $tokens, ?array $parent_token) {
		foreach ($tokens as $token) {
			switch ($token['type']) {
				case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
					if (!$this->math_function_validator->validate($token)) {
						$this->setError($this->math_function_validator->getError());

						return false;
					}

					foreach ($token['data']['parameters'] as $parameter) {
						if (!$this->validateRecursively($parameter['data']['tokens'], $token)) {
							return false;
						}
					}

					break;

				case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
					if (!$this->hist_function_validator->validate($token)) {
						$this->setError($this->hist_function_validator->getError());

						return false;
					}

					if (CHistFunctionData::isCalculated($token['data']['function'])) {
						if ($parent_token === null
								|| $parent_token['type'] != CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION
								|| !in_array($parent_token['data']['function'], self::AGGREGATE_MATH_FUNCTIONS)
								|| count($parent_token['data']['parameters']) != 1) {
							$this->setError(_s('Incorrect usage of function "%1$s".', $token['data']['function']));

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
}
