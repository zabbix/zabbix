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
 * Class for validating trigger expressions and calculated item formulas.
 */
class CExpressionValidator extends CValidator {

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false  Enable user macros usage in function parameters.
	 *   'lldmacros' => false   Enable low-level discovery macros usage in function parameters.
	 *   'calculated' => false  Validate expression as part of calculated item formula.
	 *   'partial' => false     Validate partial expression (relaxed requirements).
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
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
	 * Known math functions along with additional requirements for usage in expressions.
	 *
	 * @var array
	 */
	private $math_function_expression_rules = [];

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
	 * Known history functions along with additional requirements for usage in expressions.
	 *
	 * @var array
	 */
	private $hist_function_expression_rules = [];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->math_function_data = new CMathFunctionData(['calculated' => $this->options['calculated']]);
		$this->math_function_parameters = $this->math_function_data->getParameters();
		$this->math_function_expression_rules = $this->math_function_data->getExpressionRules();

		$this->hist_function_data = new CHistFunctionData(['calculated' => $this->options['calculated']]);
		$this->hist_function_parameters = $this->hist_function_data->getParameters();
		$this->hist_function_expression_rules = $this->hist_function_data->getExpressionRules();
	}

	/**
	 * Validate expression.
	 *
	 * @param array $tokens  A hierarchy of tokens of parsed expression.
	 *
	 * @return bool
	 */
	public function validate($tokens) {
		if (!$this->validateRecursively($tokens, null, null)) {
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
	 * @param array      $tokens        A hierarchy of tokens.
	 * @param array|null $parent_token  Parent token containing the hierarchy of tokens.
	 * @param int|null   $position      The parameter number in the math function.
	 *
	 * @return bool
	 */
	private function validateRecursively(array $tokens, ?array $parent_token, ?int $position): bool {
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

					if (!$this->validateMathFunctionExpressionRules($token)) {
						$this->setError(_s('incorrect usage of function "%1$s"', $token['data']['function']));

						return false;
					}

					foreach ($token['data']['parameters'] as $position => $parameter) {
						if (!$this->validateRecursively($parameter['data']['tokens'], $token, $position)) {
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
						'usermacros' => $this->options['usermacros'],
						'lldmacros' => $this->options['lldmacros'],
						'calculated' => $this->options['calculated']
					];

					if ($this->options['calculated']) {
						$options['aggregating'] = $this->hist_function_data->isAggregating($token['data']['function']);
					}

					$hist_function_validator = new CHistFunctionValidator($options);

					if (!$hist_function_validator->validate($token)) {
						$this->setError($hist_function_validator->getError());

						return false;
					}

					if (!$this->validateHistFunctionExpressionRules($token, $parent_token, $position)) {
						$this->setError(_s('incorrect usage of function "%1$s"', $token['data']['function']));

						return false;
					}

					break;
			}
		}

		return true;
	}

	/**
	 * @param array $token
	 *
	 * @return bool
	 */
	private function validateMathFunctionExpressionRules(array $token): bool {
		if (!array_key_exists($token['data']['function'], $this->math_function_expression_rules)) {
			return true;
		}

		foreach ($this->math_function_expression_rules[$token['data']['function']] as $rule_set) {
			if (array_key_exists('if', $rule_set)) {
				if (array_key_exists('parameters', $rule_set['if'])) {
					if (array_key_exists('count', $rule_set['if']['parameters'])
							&& count($token['data']['parameters']) != $rule_set['if']['parameters']['count']) {
						continue;
					}

					if (array_key_exists('min', $rule_set['if']['parameters'])
							&& count($token['data']['parameters']) < $rule_set['if']['parameters']['min']) {
						continue;
					}

					if (array_key_exists('max', $rule_set['if']['parameters'])
							&& count($token['data']['parameters']) > $rule_set['if']['parameters']['max']) {
						continue;
					}
				}
			}

			foreach ($rule_set['rules'] as $rule) {
				switch ($rule['type']) {
					case 'require_history_child':
						$tokens = $token['data']['parameters'][$rule['position']]['data']['tokens'];

						if (count($tokens) != 1
								|| $tokens[0]['type'] != CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION) {
							return false;
						}

						if (array_key_exists('in', $rule) && !in_array($tokens[0]['data']['function'], $rule['in'])) {
							return false;
						}

						break;

					case 'regexp':
						$tokens = $token['data']['parameters'][$rule['position']]['data']['tokens'];

						if (preg_match($rule['pattern'], CHistFunctionParser::unquoteParam($tokens[0]['match'])) != 1) {
							return false;
						}

						break;

					default:
						return false;
				}
			}
		}

		return true;
	}

	/**
	 * @param array      $token
	 * @param array|null $parent_token
	 * @param int|null   $position
	 *
	 * @return bool
	 */
	private function validateHistFunctionExpressionRules(array $token, ?array $parent_token, ?int $position): bool {
		if (!array_key_exists($token['data']['function'], $this->hist_function_expression_rules)) {
			return true;
		}

		$is_valid = true;

		foreach ($this->hist_function_expression_rules[$token['data']['function']] as $rule) {
			switch ($rule['type']) {
				case 'require_math_parent':
					if ($parent_token === null
							|| $parent_token['type'] != CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION) {
						$is_valid = false;
						break;
					}

					if (array_key_exists('in', $rule) && !in_array($parent_token['data']['function'], $rule['in'])) {
						$is_valid = false;
						break;
					}

					if (array_key_exists('parameters', $rule)) {
						if (array_key_exists('count', $rule['parameters'])
								&& count($parent_token['data']['parameters']) != $rule['parameters']['count']) {
							$is_valid = false;
							break;
						}

						if (array_key_exists('min', $rule['parameters'])
								&& count($parent_token['data']['parameters']) < $rule['parameters']['min']) {
							$is_valid = false;
							break;
						}

						if (array_key_exists('max', $rule['parameters'])
								&& count($parent_token['data']['parameters']) > $rule['parameters']['max']) {
							$is_valid = false;
							break;
						}
					}

					if (array_key_exists('position', $rule) && $position != $rule['position']) {
						$is_valid = false;
						break;
					}

					return true;

				default:
					$is_valid = false;
					break;
			}
		}

		return $is_valid;
	}

	/**
	 * Check if there are history function tokens within the hierarchy of given tokens.
	 *
	 * @param array $tokens
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
					return self::hasHistoryFunctions($token['data']['tokens']);
			}
		}

		return false;
	}
}
