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
	protected $hanged_refs;

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
	 * @param string $trigger_data['expression']           (optional)
	 * @param string $trigger_data['recovery_expression']  (optional)
	 * @param string $trigger_data['host']                 (optional)
	 * @param string $trigger_data['item']                 (optional)
	 *
	 * @return string
	 */
	public function convert($trigger_data) {
		$this->item = array_key_exists('item', $trigger_data) ? $trigger_data['item'] : null;
		$this->host = (array_key_exists('host', $trigger_data) && $this->item) ? $trigger_data['host'] : null;

		$extra_expressions = [];

		if (array_key_exists('recovery_expression', $trigger_data) && $trigger_data['recovery_expression'] !== ''
				&& ($this->parser->parse($trigger_data['recovery_expression'])) !== false) {
			$functions = $this->parser->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO);
			$this->hanged_refs = $this->checkHangedFunctionsPerHost($functions);
			$parts = $this->getExpressionParts(0, $this->parser->result->length-1);
			$this->wrap_subexpressions = ($parts['type'] === 'operator');
			$this->convertExpressionParts($trigger_data['recovery_expression'], [$parts], $extra_expressions);
		}

		if (array_key_exists('expression', $trigger_data) && $trigger_data['expression'] !== ''
				&& ($this->parser->parse($trigger_data['expression'])) !== false) {
			$functions = $this->parser->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO);
			$this->hanged_refs = $this->checkHangedFunctionsPerHost($functions);
			$parts = $this->getExpressionParts(0, $this->parser->result->length-1);
			$this->wrap_subexpressions = ($parts['type'] === 'operator');
			$this->convertExpressionParts($trigger_data['expression'], [$parts], $extra_expressions);

			$extra_expressions = array_filter($extra_expressions);
			if ($extra_expressions) {
				$extra_expressions = array_keys(array_flip($extra_expressions));

				$trigger_data['expression'] = '('.$trigger_data['expression'].')';

				$extra_expressions = array_reverse($extra_expressions);
				$trigger_data['expression'] .= ' or '.implode(' or ', $extra_expressions);
			}
		}

		return array_intersect_key($trigger_data, array_flip(['recovery_expression', 'expression']));
	}

	private function convertExpressionParts(string &$expression, array $expression_elements, array &$extra_expr) {
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

	private function convertSingleExpressionPart(string &$expression, array $expression_element, array &$extra_expr) {
		$expression_data = new C10TriggerExpression(['allow_func_only' => true]);

		if (($expression_data->parse($expression_element['expression'])) !== false) {
			$fn_list = $expression_data->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO);

			for ($i = count($fn_list) - 1; $i >= 0; $i--) {
				$fn = $fn_list[$i]['data'];
				[$new_expression, $_extra_expr] = $this->convertFunction($fn);

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

	private function convertFunction(array $fn): array {
		if ($fn['item'] === '' && $fn['host'] === '') {
			$query = sprintf('/%s/%s', $this->host, $this->item);
			$has_hanged_functions = $this->hanged_refs[''];
		}
		else {
			$query = sprintf('/%s/%s', $fn['host'], $fn['item']);
			$has_hanged_functions = $this->hanged_refs[$fn['host']];
		}

		$extra_expr = '';

		switch ($fn['functionName']) {
			case 'abschange':
				$new_expression = sprintf('abs(change(%1$s))', $query);
				break;

			case 'band':
				$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
				$timeshift = self::paramsToString([$params[0]]);
				$mask = self::paramsToString([$params[1]]);
				$new_expression = sprintf('bitand(last(%1$s%2$s)%3$s)', $query, $timeshift, $mask);
				break;

			case 'change':
				$new_expression = sprintf('change(%1$s)', $query);
				break;

			case 'delta':
				$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
				$params = self::paramsToString($params);
				$new_expression = sprintf('(max(%1$s%2$s)-min(%1$s%2$s))', $query, $params);
				break;

			case 'diff':
				$new_expression = sprintf('(last(%1$s,1)<>last(%1$s,2))', $query);
				break;

			case 'prev':
				$new_expression = sprintf('last(%1$s,2)', $query);
				break;

			case 'trenddelta':
				$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
				$params = self::paramsToString($params);
				$new_expression = sprintf('(trendmax(%1$s%2$s)-trendmin(%1$s%2$s))', $query, $params);
				break;

			case 'iregexp':
			case 'regexp':
				$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
				$new_expression = sprintf('find(%1$s,%2$s,"%3$s",%4$s)', $query, $params[0], $fn['functionName'],
					$params[1]
				);
				break;

			case 'str':
				$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
				$new_expression = sprintf('find(%1$s,%2$s,"like",%3$s)', $query, $params[0], $params[1]);
				break;

			case 'strlen':
				$params = self::convertParameters($fn['functionParams'], $fn['functionName']);
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
					self::paramsToString(self::convertParameters($fn['functionParams'], $fn['functionName']))
				);
				break;
		}

		return [$new_expression, $extra_expr];
	}

	private static function convertParameters(array $parameters, string $fn_name): array {
		switch ($fn_name) {
			// (sec|#num,<time_shift>)
			case 'delta':
			case 'avg':
			case 'max':
			case 'min':
			case 'sum':
			// (<sec|#num>,<time_shift>)
			case 'last':
			case 'strlen':
			// (sec|#num,<time_shift>,percentage)
			case 'percentile':
			// (sec|#num,<time_shift>,threshold,<fit>)
			case 'timeleft':
				$parameters += ['', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[1] = self::convertTimeshift($parameters[1]);
				if ($parameters[1] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1]);
				break;

			// (sec|#num,<time_shift>,time,<fit>,<mode>)
			case 'forecast':
				$parameters += ['', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[1] = self::convertTimeshift($parameters[1]);
				if ($parameters[1] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1]);
				$parameters[2] = self::convertParamSec($parameters[2]);
				break;

			// (<sec|#num>,mask,<time_shift>)
			case 'band':
				$parameters += ['', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[2] = self::convertTimeshift($parameters[2]);
				if ($parameters[2] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[2];
				}
				unset($parameters[2]);
				break;

			// (sec|#num,<pattern>,<operator>,<time_shift>)
			case 'count':
				$parameters += ['', '', '', ''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				$parameters[3] = self::convertTimeshift($parameters[3]);
				if ($parameters[3] !== '') {
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[3];
				}
				if ($parameters[2] === 'band') {
					$parameters[2] = 'bitand';
				}
				unset($parameters[3]);
				array_push($parameters, $parameters[1]);
				unset($parameters[1]);
				break;

			// (sec)
			case 'fuzzytime':
			// (sec,<mode>)
			case 'nodata':
				$parameters += [''];
				$parameters[0] = self::convertParamSec($parameters[0]);
				break;

			// (<pattern>,<sec|#num>)
			case 'iregexp':
			case 'regexp':
			case 'str':
				$parameters += ['', ''];
				$parameters[1] = self::convertParamSec($parameters[1]);
				array_unshift($parameters, $parameters[1]);
				unset($parameters[2]);
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
					$parameters[0] = ($parameters[0] === '') ? '#1' : $parameters[0];
					$parameters[0] .= ':'.$parameters[1];
				}
				unset($parameters[1]);
				break;
		}

		// Keys in $parameters array to skip from quoting.
		$functions_with_period_parameter = ['delta', 'avg', 'max', 'min', 'sum', 'last', 'strlen', 'percentile',
			'timeleft', 'forecast', 'band', 'count', 'fuzzytime', 'nodata', 'iregexp', 'regexp', 'str', 'trendavg',
			'trendcount', 'trenddelta', 'trendmax', 'trendmin', 'trendsum'
		];
		$unquotable_parameters = in_array($fn_name, $functions_with_period_parameter) ? [0] : [];

		// Time parameter don't need to be quoted for forecast() function.
		if ($fn_name === 'forecast') {
			$unquotable_parameters[] = 2;
		}

		array_walk($parameters, function (&$param, $i) use ($unquotable_parameters) {
			if (in_array($i, $unquotable_parameters)) {
				return;
			}

			if ($param === '' || ($param[0] === '"' && substr($param, -1) === '"')) {
				return;
			}

			$param = '"'.str_replace('"', '\\"', $param).'"';
		});

		return array_values($parameters);
	}

	private static function convertParamSec(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>['.ZBX_TIME_SUFFIXES.']{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	private static function convertParamPeriod(string $param): string {
		return (preg_match('/^(?<num>\d+)(?<suffix>[hdwMy]{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
	}

	private static function convertTimeshift(string $param): string {
		$param = (preg_match('/^(?<num>\d+)(?<suffix>['.ZBX_TIME_SUFFIXES.']{0,1})$/', $param, $m) && $m['num'] > 0)
			? $m['num'].($m['suffix'] !== '' ? $m['suffix'] : 's')
			: $param;
		return ($param !== '') ? 'now-'.$param : '';
	}

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
	private function getExpressionParts(int $start, int $end): array {
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
							if ($expression_token['pos'] == $i) {
								$i += $expression_token['length'] - 1;
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
								// We've reached the end of a complete expression, parse the expression on the left side
								// of the operator.
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

			// We've found a whole expression and parsed the expression on the left side of the operator, parse the
			// expression on the right.
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
			// If we've tried both operators and didn't find anything, it means there's only one expression return the
			// result.
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

}
