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
 * Class for validating trigger expressions and calculated item formulas.
 */
class CExpressionValidator extends CValidator {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'calculated' => false  Validate expression as part of calculated item formula.
	 *   'partial' => false     Validate partial expression (relaxed requirements).
	 *
	 * @var array
	 */
	private $options = [
		'calculated' => false,
		'partial' => false
	];

	/**
	 * Provider of information on math functions.
	 *
	 * @var CMathFunctionData
	 */
	private $math_function_data;

	/**
	 * Known math functions along with number or range of required parameters.
	 *
	 * @var array
	 */
	private $math_function_parameters = [];

	/**
	 * Provider of information on history functions.
	 *
	 * @var CHistFunctionData
	 */
	private $hist_function_data;

	/**
	 * Known history functions along with definition of parameters.
	 *
	 * @var array
	 */
	private $hist_function_parameters = [];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->math_function_data = new CMathFunctionData();
		$this->math_function_parameters = $this->math_function_data->getParameters();

		$this->hist_function_data = new CHistFunctionData(['calculated' => $this->options['calculated']]);
		$this->hist_function_parameters = $this->hist_function_data->getParameters();
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
			if (!$this->options['partial'] && !self::hasHistoryFunctions($tokens)) {
				$this->setError(_('trigger expression must contain at least one /host/key reference'));

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate expression (recursive helper).
	 *
	 * @param array $tokens             A hierarchy of tokens.
	 * @param array|null $parent_token  Parent token containing the hierarchy of tokens.
	 *
	 * @return bool
	 */
	private function validateRecursively(array $tokens, ?array $parent_token): bool {
		foreach ($tokens as $token) {
			switch ($token['type']) {
				case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
					if (!$this->math_function_data->isKnownFunction($token['data']['function'])
							&& $this->hist_function_data->isKnownFunction($token['data']['function'])) {
						$this->setError(_s('incorrect usage of function "%1$s"', $token['data']['function']));

						return false;
					}

					$math_function_validator = new CMathFunctionValidator([
						'parameters' => $this->math_function_parameters
					]);

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
					if (!$this->hist_function_data->isKnownFunction($token['data']['function'])
							&& $this->math_function_data->isKnownFunction($token['data']['function'])) {
						$this->setError(_s('incorrect usage of function "%1$s"', $token['data']['function']));

						return false;
					}

					$options = [
						'parameters' => $this->hist_function_parameters,
						'calculated' => $this->options['calculated']
					];

					if ($this->options['calculated']) {
						$options['aggregating'] = CHistFunctionData::isAggregating($token['data']['function']);
					}

					$hist_function_validator = new CHistFunctionValidator($options);

					if (!$hist_function_validator->validate($token)) {
						$this->setError($hist_function_validator->getError());

						return false;
					}

					if ($options['calculated'] && $options['aggregating']) {
						if ($parent_token === null
								|| $parent_token['type'] != CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION
								|| !$this->math_function_data->isAggregating($parent_token['data']['function'])
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

	/**
	 * Check if there are history function tokens within the hierarchy of given tokens.
	 *
	 * @param array $tokens
	 *
	 * @static
	 *
	 * @return bool
	 */
	private static function hasHistoryFunctions(array $tokens): bool {
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
