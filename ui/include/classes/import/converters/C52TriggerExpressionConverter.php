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
 * A converter to convert trigger expression syntax from 5.2 to 5.4.
 */
class C52TriggerExpressionConverter extends CConverter {

	/**
	 * Functions which are not related to item.
	 *
	 * @var array
	 */
	protected $standalone_functions;

	/**
	 * State of each host reference being present in some non-standalone function.
	 *
	 * @var array
	 */
	protected $hanged_refs = [];

	/**
	 * Host for simplified functions.
	 *
	 * @var string|null
	 */
	protected $host;

	/**
	 * Item for simplified functions.
	 *
	 * @var string|null
	 */
	protected $item;

	/**
	 * Either to add parentheses around subexpression.
	 *
	 * @var bool
	 */
	protected $wrap_subexpressions;

	/**
	 * Old trigger expression syntax parser.
	 *
	 * @var C10TriggerExpression
	 */
	protected $parser;

	public function __construct() {
		$this->parser = new C10TriggerExpression(['allow_func_only' => true]);
		$this->standalone_functions = getStandaloneFunctions();
	}

	/**
	 * Converts trigger expression to new syntax.
	 *
	 * @param array  $trigger_data
	 * @param string $trigger_data['expression']
	 * @param string $trigger_data['host']                 (optional)
	 * @param string $trigger_data['item']                 (optional)
	 *
	 * @return string
	 */
	public function convert($trigger_data) {
		$this->item = (array_key_exists('item', $trigger_data) && $trigger_data['item']) ? $trigger_data['item'] : '';
		$this->host = (array_key_exists('host', $trigger_data) && $this->item) ? $trigger_data['host'] : '';

		if (($this->parser->parse($trigger_data['expression'])) !== false) {
			$functions = $this->parser->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO);
			$this->hanged_refs = $this->checkHangedFunctionsPerHost($functions);
			$parts = $this->getExpressionParts(0, $this->parser->result->length - 1);
			$this->wrap_subexpressions = ($parts['type'] === 'operator');
			$extra_expressions = [];
			$this->convertExpressionParts($trigger_data['expression'], [$parts], $extra_expressions);

			$extra_expressions = array_filter($extra_expressions);
			if ($extra_expressions) {
				$extra_expressions = array_keys(array_flip($extra_expressions));

				$trigger_data['expression'] = '('.$trigger_data['expression'].')';

				$extra_expressions = array_reverse($extra_expressions);
				$trigger_data['expression'] .= ' or '.implode(' or ', $extra_expressions);
			}
		}

		return $trigger_data['expression'];
	}

	/**
	 * Convert expressions parts.
	 *
	 * @param string $expression          Expression string.
	 * @param array $expression_elements  Expression tree.
	 * @param array $extra_expr           Array to accumulate strings to add at the end of converted expression.
	 */
	protected function convertExpressionParts(string &$expression, array $expression_elements, array &$extra_expr
			): void {
		for ($i = count($expression_elements) - 1; $i >= 0; $i--) {
			$part = $expression_elements[$i];

			if ($part['type'] === 'operator') {
				$this->convertExpressionParts($expression, $part['elements'], $extra_expr);
			}
			elseif ($part['type'] === 'expression') {
				$this->convertSingleExpressionPart($expression, $part, $extra_expr);
			}
		}
	}

	/**
	 * Convert expression part.
	 *
	 * @param string $expression         Expression string.
	 * @param array $expression_element  Expression part to convert.
	 * @param array $extra_expr          Array to accumulate strings to add at the end of converted expression.
	 */
	protected function convertSingleExpressionPart(string &$expression, array $expression_element, array &$extra_expr) {
		$expression_data = new C10TriggerExpression(['allow_func_only' => true]);

		if (($expression_data->parse($expression_element['expression'])) !== false) {
			$fn_list = $expression_data->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO);

			for ($i = count($fn_list) - 1; $i >= 0; $i--) {
				$fn = $fn_list[$i]['data'];
				[$new_expression, $_extra_expr] = $this->convertFunction($fn, $this->host, $this->item);

				$extra_expr[] = $_extra_expr;

				$expression_element['expression'] = substr_replace($expression_element['expression'], $new_expression,
					$fn_list[$i]['pos'], $fn_list[$i]['length']
				);
			}

			if ($this->wrap_subexpressions && count($fn_list) > 1) {
				$expression_element['expression'] = '('.$expression_element['expression'].')';
			}

			$expression = substr_replace($expression, $expression_element['expression'],
				$expression_element['pos'], $expression_element['length']
			);
		}
	}

	/**
	 * Convert function to new syntax.
	 *
	 * @param array $fn          Function to convert.
	 * @param string $host_name  Host name.
	 * @param string $item_key   Item key.
	 *
	 * @return array
	 */
	protected function convertFunction(array $fn, string $host_name, string $item_key): array {
		if ($fn['item'] === '' && $fn['host'] === '') {
			$query = sprintf('/%s/%s', $host_name, $item_key);
			$has_hanged_functions = $this->hanged_refs[''];
		}
		else {
			$query = sprintf('/%s/%s', $fn['host'], $fn['item']);
			$has_hanged_functions = array_key_exists($fn['host'], $this->hanged_refs)
				? $this->hanged_refs[$fn['host']]
				: false;
		}

		$extra_expr = '';

		$parameters = [
			'unquotable' => array_filter($fn['functionParamsRaw']['parameters'], function ($param) {
				return ($param['type'] == C10FunctionParser::PARAM_UNQUOTED && $param['raw'] === '');
			}),
			'indicated' => array_filter($fn['functionParamsRaw']['parameters'], function ($param) {
				return ($param['type'] == C10FunctionParser::PARAM_QUOTED || $param['raw'] !== '');
			})
		];

		switch ($fn['functionName']) {
			case 'abschange':
				$new_expression = sprintf('abs(change(%1$s))', $query);
				break;

			case 'band':
				$params = self::convertParameters($fn['functionParams'], $parameters, $fn['functionName']);
				$timeshift = self::paramsToString([$params[0]]);
				$mask = self::paramsToString([$params[1]]);
				$new_expression = sprintf('bitand(last(%1$s%2$s)%3$s)', $query, $timeshift, $mask);
				break;

			case 'change':
				$new_expression = sprintf('change(%1$s)', $query);
				break;

			case 'delta':
				$params = self::convertParameters($fn['functionParams'], $parameters, $fn['functionName']);
				$params = self::paramsToString($params);
				$new_expression = sprintf('(max(%1$s%2$s)-min(%1$s%2$s))', $query, $params);
				break;

			case 'diff':
				$new_expression = sprintf('(last(%1$s,#1)<>last(%1$s,#2))', $query);
				break;

			case 'prev':
				$new_expression = sprintf('last(%1$s,#2)', $query);
				break;

			case 'trenddelta':
				$params = self::convertParameters($fn['functionParams'], $parameters, $fn['functionName']);
				$params = self::paramsToString($params);
				$new_expression = sprintf('(trendmax(%1$s%2$s)-trendmin(%1$s%2$s))', $query, $params);
				break;

			case 'iregexp':
			case 'regexp':
			case 'str':
				$params = self::convertParameters($fn['functionParams'], $parameters, $fn['functionName']);
				$params = self::paramsToString($params);
				$new_expression = sprintf('find(%1$s%2$s)', $query, $params);
				break;

			case 'strlen':
				$params = self::convertParameters($fn['functionParams'], $parameters, $fn['functionName']);
				$params = self::paramsToString($params);
				$new_expression = sprintf('length(last(%1$s%2$s))', $query, $params);
				break;

			case 'date':
			case 'dayofmonth':
			case 'dayofweek':
			case 'time':
			case 'now':
				$new_expression = $fn['functionName'].'()';
				if (!$has_hanged_functions) {
					$extra_expr = sprintf('(last(%1$s)<>last(%1$s))', $query);
				}
				break;

			case 'logseverity':
				$new_expression = sprintf('logseverity(%1$s)', $query);
				break;

			default:
				$new_expression = sprintf('%s(%s%s)', $fn['functionName'], $query,
					self::paramsToString(self::convertParameters($fn['functionParams'], $parameters,
						$fn['functionName']
					))
				);
				break;
		}

		return [$new_expression, $extra_expr];
	}

	/**
	 * Convert function parameters to new syntax.
	 *
	 * @param array $parameters                List of parameters according previous syntax.
	 * @param array $param_dets
	 * @param array $param_dets['unquotable']  List of numeric indexes for parameters that don't need to be quoted.
	 * @param array $param_dets['indicated']   List of numeric indexes for parameters that are especially indicated and
	 *                                         must be kept.
	 * @param string $fn_name                  Function name.
	 *
	 * @return array
	 */
	private static function convertParameters(array $parameters, array $param_dets, string $fn_name): array {
		switch ($fn_name) {
			// (sec|#num,<time_shift>)
			case 'delta':
			case 'avg':
			case 'max':
			case 'min':
			case 'sum':
			// (sec|#num,<time_shift>,percentage)
			case 'percentile':
				$parameters += ['', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[1] = self::convertTimeshift($parameters[1]);
				$parameters[0] = ((string) $parameters[0] === '0') ? '#1' : $parameters[0];
				if ($parameters[1] !== '') {
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1], $param_dets['unquotable'][1], $param_dets['indicated'][1]);
				break;

			// (sec|#num,<time_shift>,threshold,<fit>)
			case 'timeleft':
				$parameters += ['', '', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[1] = self::convertTimeshift($parameters[1]);
				$parameters[0] = ((string) $parameters[0] === '0') ? '#1' : $parameters[0];
				if ($parameters[1] !== '') {
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1], $param_dets['unquotable'][1], $param_dets['indicated'][1]);

				if ($parameters[3] === '') {
					// Don't quote unspecified <fit>.
					$param_dets['unquotable'][3] = true;
				}
				break;

			// (<#num>,<time_shift>)
			case 'strlen':
			case 'last':
				$parameters += ['', ''];
				if (!self::isMacro($parameters[0])
						&& (substr($parameters[0], 0, 1) !== '#'
							|| !ctype_digit(substr($parameters[0], 1))
							|| (int) substr($parameters[0], 1) === 0)) {
					$parameters[0] = '';
				}

				$parameters[1] = self::convertTimeshift($parameters[1]);
				if ($parameters[1] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1], $param_dets['unquotable'][1], $param_dets['indicated'][1]);
				break;

			// (sec|#num,<time_shift>,time,<fit>,<mode>)
			case 'forecast':
				$parameters += ['', '', '', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[1] = self::convertTimeshift($parameters[1]);
				$parameters[0] = ((string) $parameters[0] === '0') ? '#1' : $parameters[0];
				if ($parameters[1] !== '') {
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1], $param_dets['unquotable'][1], $param_dets['indicated'][1]);
				$parameters[2] = self::convertParamSec($parameters[2]);

				if ($parameters[3] === '') {
					// Don't quote unspecified <fit>.
					$param_dets['unquotable'][3] = true;
				}
				if ($parameters[4] === '') {
					// Don't quote unspecified <mode>.
					$param_dets['unquotable'][4] = true;
				}
				break;

			// (<sec|#num>,mask,<time_shift>)
			case 'band':
				$parameters += ['', '', ''];
				if (!self::isMacro($parameters[0])
						&& (substr($parameters[0], 0, 1) !== '#'
							|| !ctype_digit(substr($parameters[0], 1))
							|| (int) substr($parameters[0], 1) === 0)) {
					$parameters[0] = '';
				}

				$parameters[2] = self::convertTimeshift($parameters[2]);
				if ($parameters[2] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[2];
				}
				unset($parameters[2], $param_dets['unquotable'][2], $param_dets['indicated'][2]);
				break;

			// (sec|#num,<pattern>,<operator>,<time_shift>)
			case 'count':
				$parameters += ['', '', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[3] = self::convertTimeshift($parameters[3]);
				$parameters[0] = ((string) $parameters[0] === '0') ? '#1' : $parameters[0];
				if ($parameters[3] !== '') {
					$parameters[0] .= ':'.$parameters[3];
				}
				if ($parameters[2] === 'band') {
					$parameters[2] = 'bitand';
				}
				elseif ($parameters[2] === '') {
					// Don't quote unspecified <operator>.
					$param_dets['unquotable'][2] = true;
				}

				$parameters[3] = $parameters[1];
				unset($param_dets['unquotable'][3], $param_dets['indicated'][3], $parameters[1]);
				if (array_key_exists(1, $param_dets['unquotable'])) {
					$param_dets['unquotable'][3] = true;
					unset($param_dets['unquotable'][1]);
				}
				if (array_key_exists(1, $param_dets['indicated'])) {
					$param_dets['indicated'][3] = true;
					unset($param_dets['indicated'][1]);
				}
				break;

			// (sec,<mode>)
			case 'nodata':
				$parameters += ['', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				if ($parameters[1] === '') {
					// Don't quote unspecified <mode>.
					$param_dets['unquotable'][1] = true;
				}
				break;

			// (sec)
			case 'fuzzytime':
				$parameters += [''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				break;

			// (<pattern>,<sec|#num>)
			case 'iregexp':
			case 'regexp':
			case 'str':
				$parameters += ['', ''];
				$parameters = [
					self::convertParamSec($parameters[1]),
					($fn_name === 'str') ? 'like' : $fn_name,
					$parameters[0]
				];
				unset($param_dets['unquotable'][1]);
				if (array_key_exists(0, $param_dets['indicated'])) {
					$param_dets['indicated'][2] = true;
					unset($param_dets['indicated'][0]);
				}

				break;

			// (period,period_shift)
			case 'trendavg':
			case 'trendcount':
			case 'trenddelta':
			case 'trendmax':
			case 'trendmin':
			case 'trendsum':
				$parameters += ['', ''];
				$parameters[0] = self::convertParamPeriod($parameters[0]);
				if ($parameters[1] !== '') {
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1], $param_dets['unquotable'][1], $param_dets['indicated'][1]);
				break;

			case 'logeventid':
			case 'logsource':
				array_unshift($parameters, '');
				if (array_key_exists(0, $param_dets['indicated'])) {
					$param_dets['indicated'][1] = true;
					unset($param_dets['indicated'][0]);
				}
				break;
		}

		// Keys in $parameters array to skip from quoting.
		$param_dets['unquotable'] = array_keys($param_dets['unquotable']);
		$param_dets['indicated'] = array_keys($param_dets['indicated']);
		$functions_with_period_parameter = ['delta', 'avg', 'max', 'min', 'sum', 'last', 'strlen', 'percentile',
			'timeleft', 'forecast', 'band', 'count', 'fuzzytime', 'nodata', 'iregexp', 'regexp', 'str', 'trendavg',
			'trendcount', 'trenddelta', 'trendmax', 'trendmin', 'trendsum', 'logeventid', 'logsource'
		];
		if (in_array($fn_name, $functions_with_period_parameter)) {
			$param_dets['unquotable'][] = 0;
		}

		if (in_array($fn_name, ['forecast', 'timeleft', 'percentile'])) {
			// Time parameter don't need to be quoted for forecast() function.
			$param_dets['unquotable'][] = 2;
		}
		elseif ($fn_name === 'band') {
			// Mask parameter don't need to be quoted for bitand() function.
			$param_dets['unquotable'][] = 1;
		}

		array_walk($parameters, function (&$param, $i) use ($param_dets) {
			if (in_array($i, $param_dets['unquotable'])) {
				return;
			}

			$param = CHistFunctionParser::quoteParam($param);
		});

		// Remove empty parameters from the end of the parameters array.
		foreach (array_reverse(array_keys($parameters)) as $i) {
			if (in_array($i, $param_dets['indicated'])) {
				break;
			}

			if ($parameters[$i] !== '""' && $parameters[$i] !== '') {
				break;
			}

			unset($parameters[$i]);
		}

		return array_values($parameters);
	}

	/**
	 * Convert seconds.
	 *
	 * @param string $param  Parameter to convert.
	 *
	 * @return string
	 */
	private static function convertParamSec(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>['.ZBX_TIME_SUFFIXES.']{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	/**
	 * Convert period.
	 *
	 * @param string $param  Parameter to convert.
	 *
	 * @return string
	 */
	private static function convertParamPeriod(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>[hdwMy]{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	/**
	 * Convert time shift.
	 *
	 * @param string $param  Parameter to convert.
	 *
	 * @return string
	 */
	private static function convertTimeshift(string $param): string {
		$param = (preg_match('/^(?<num>\d+)(?<suffix>['.ZBX_TIME_SUFFIXES.']{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;

		return ($param !== '') ? 'now-'.$param : '';
	}

	/**
	 * Concatenate parameters into comma separated string.
	 *
	 * @param array $parameters  Parameter to concatenate.
	 *
	 * @return string
	 */
	private static function paramsToString(array $parameters): string {
		$parameters = rtrim(implode(',', $parameters), ',');

		return ($parameters === '') ? '' : ','.$parameters;
	}

	/**
	 * Check if each particular host reference would be linked through at least one functions according the new trigger
	 * expression syntax.
	 *
	 * @param array $tokens
	 *
	 * @return array
	 */
	protected function checkHangedFunctionsPerHost(array $tokens): array {
		$hanged_refs = [];

		foreach ($tokens as $token) {
			$fn = $token['data'];

			if (!array_key_exists($fn['host'], $hanged_refs)) {
				$hanged_refs[$fn['host']] = false;
			}
			if (!in_array($fn['functionName'], $this->standalone_functions)) {
				$hanged_refs[$fn['host']] = true;
			}
		}

		return $hanged_refs;
	}

	/**
	 * Split expression into sub-expressions.
	 *
	 * @param int $start
	 * @param int $end
	 *
	 * @return array
	 */
	protected function getExpressionParts(int $start, int $end): array {
		$blank_symbols = [' ', "\r", "\n", "\t"];

		$result = [];
		foreach (['or', 'and'] as $operator) {
			$operator_found = false;
			$left_parentheses = -1;
			$right_parentheses = -1;
			$expressions = [];
			$open_symbol_pos = $start;
			$operator_pos = 0;
			$operator_token = '';

			for ($i = $start, $level = 0; $i <= $end; $i++) {
				switch ($this->parser->expression[$i]) {
					case ' ':
					case "\r":
					case "\n":
					case "\t":
						if ($open_symbol_pos == $i) {
							$open_symbol_pos++;
						}
						break;

					case '(':
						if ($level == 0) {
							$left_parentheses = $i;
						}
						$level++;
						break;

					case ')':
						$level--;
						if ($level == 0) {
							$right_parentheses = $i;
						}
						break;

					case '{':
					case '"':
						// Skip any previously found tokens starting with brace or double quote.
						foreach ($this->parser->result->getTokens() as $expression_token) {
							$expr_endpos = $expression_token['pos'] + $expression_token['length'];

							if ($expression_token['pos'] >= $i && $i < $expr_endpos) {
								$i = $expr_endpos;
								break;
							}
						}
						break;

					default:
						// Try to parse an operator.
						if ($operator[$operator_pos] === $this->parser->expression[$i]) {
							$operator_pos++;
							$operator_token .= $this->parser->expression[$i];

							// Operator found.
							if ($operator_token === $operator) {
								/*
								 * Once reached the end of a complete expression, parse the expression on the left side
								 * of the operator.
								 */
								if ($level == 0) {
									// Find the last symbol of the expression before the operator.
									$close_symbol_pos = $i - strlen($operator);

									// Trim blank symbols after the expression.
									while (in_array($this->parser->expression[$close_symbol_pos], $blank_symbols)) {
										$close_symbol_pos--;
									}

									$expressions[] = $this->getExpressionParts($open_symbol_pos, $close_symbol_pos);
									$open_symbol_pos = $i + 1;
									$operator_found = true;
								}
								$operator_pos = 0;
								$operator_token = '';
							}
						}
				}
			}

			// Trim blank symbols in the end of the trigger expression.
			$close_symbol_pos = $end;
			while (in_array($this->parser->expression[$close_symbol_pos], $blank_symbols)) {
				$close_symbol_pos--;
			}

			/*
			 * We've found a whole expression and parsed the expression on the left side of the operator, parse the
			 * expression on the right.
			 */
			if ($operator_found) {
				$expressions[] = $this->getExpressionParts($open_symbol_pos, $close_symbol_pos);

				// Trim blank symbols in the beginning of the trigger expression.
				$open_symbol_pos = $start;
				while (in_array($this->parser->expression[$open_symbol_pos], $blank_symbols)) {
					$open_symbol_pos++;
				}

				// Trim blank symbols in the end of the trigger expression.
				$close_symbol_pos = $end;
				while (in_array($this->parser->expression[$close_symbol_pos], $blank_symbols)) {
					$close_symbol_pos--;
				}

				$expr = substr($this->parser->expression, $open_symbol_pos, $close_symbol_pos - $open_symbol_pos + 1);
				$result = [
					'pos' => $open_symbol_pos,
					'length' => strlen($expr),
					'expression' => $expr,
					'type' => 'operator',
					'elements' => $expressions
				];
				break;
			}
			/*
			 * If we've tried both operators and didn't find anything, it means there's only one expression return the
			 * result.
			 */
			elseif ($operator === 'and') {
				// Trim extra parentheses.
				if ($open_symbol_pos == $left_parentheses && $close_symbol_pos == $right_parentheses) {
					$open_symbol_pos++;
					$close_symbol_pos--;

					$result = $this->getExpressionParts($open_symbol_pos, $close_symbol_pos);
				}
				// No extra parentheses remain, return the result.
				else {
					$expr = substr($this->parser->expression, $open_symbol_pos, $close_symbol_pos - $open_symbol_pos + 1
					);
					$result = [
						'pos' => $open_symbol_pos,
						'length' => strlen($expr),
						'expression' => $expr,
						'type' => 'expression'
					];
				}
			}
		}

		return $result;
	}

	/**
	 * Check if given string is valid user or lld macro.
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private static function isMacro(string $param): bool {
		foreach ([new CUserMacroParser(), new CLLDMacroParser(), new CLLDMacroFunctionParser()] as $parser) {
			if ($parser->parse($param) == CParser::PARSE_SUCCESS) {
				return true;
			}
		}

		return false;
	}
}
