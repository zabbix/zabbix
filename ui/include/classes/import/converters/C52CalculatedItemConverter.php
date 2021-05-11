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
 * Class to convert calculated item keys.
 */
class C52CalculatedItemConverter extends C52TriggerExpressionConverter {

	/**
	 * Item key parser.
	 *
	 * @var CItemKey
	 */
	protected $item_key_parser;

	/**
	 * Function parser.
	 *
	 * @var C10FunctionParser
	 */
	protected $function_parser;

	public function __construct() {
		$this->parser = new C10TriggerExpression([
			'allow_func_only' => true,
			'calculated' => true
		]);
		$this->item_key_parser = new CItemKey();
		$this->function_parser = new C10FunctionParser();
		$this->standalone_functions = getStandaloneFunctions();
	}

	/**
	 * Convert calculated item formula to 5.4 syntax.
	 *
	 * @param string $formula  Calculated item formula to convert.
	 *
	 * @return string
	 */
	public function convert($item) {
		$expression = preg_replace("/[\\r\\n\\t]/", '', $item['params']);

		if ($this->parser->parse($expression) === false) {
			return $item;
		}

		$extra_expressions = [];
		$functions = $this->parser->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION);
		$this->hanged_refs = $this->checkHangedFunctionsPerHost($functions);
		$parts = $this->getExpressionParts(0, $this->parser->result->length-1);
		$this->wrap_subexpressions = ($parts['type'] === 'operator');
		$this->convertExpressionParts($expression, [$parts], $extra_expressions);
		$extra_expressions = array_filter($extra_expressions);

		if ($extra_expressions) {
			$extra_expressions = array_keys(array_flip($extra_expressions));
			$item['params'] = '('.$expression.')';
			$extra_expressions = array_reverse($extra_expressions);
			$item['params'] .= ' or '.implode(' or ', $extra_expressions);
		}
		else {
			$item['params'] = $expression;
		}

		return $item;
	}

	/**
	 * Convert expression part.
	 *
	 * @param string $expression         Expression string.
	 * @param array $expression_element  Expression part to convert.
	 * @param array $extra_expr          Unused parameter to match parent class function signature.
	 */
	protected function convertSingleExpressionPart(string &$expression, array $expression_element, array &$extra_expr) {
		if (($this->parser->parse($expression_element['expression'])) === false) {
			return;
		}

		$functions = $this->parser->result->getTokensByType(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION);

		for ($i = count($functions) - 1; $i >= 0; $i--) {
			$fn = $functions[$i]['data'] + ['host' => '', 'item' => '', 'function' => $functions[$i]['value']];
			$key_param = $fn['functionParams'][0];
			$host_delimiter_pos = strpos($key_param, ':');
			$key_param_pos = strpos($key_param, '[');
			$host_name = '';

			if ($host_delimiter_pos !== false && ($key_param_pos === false || $host_delimiter_pos < $key_param_pos)) {
				list($host_name, $key_param) = explode(':', $key_param, 2);
			}

			if ($this->item_key_parser->parse($key_param) === CParser::PARSE_SUCCESS) {
				array_shift($fn['functionParams']);
				array_shift($fn['functionParamsRaw']['parameters']);
				[$new_expression] = $this->convertFunction($fn, $host_name, $key_param);

				$expression_element['expression'] = substr_replace($expression_element['expression'], $new_expression,
					$functions[$i]['pos'], $functions[$i]['length']
				);
			}
		}

		if ($this->wrap_subexpressions && count($functions) > 1) {
			$expression_element['expression'] = '('.$expression_element['expression'].')';
		}

		$expression = substr_replace($expression, $expression_element['expression'],
			$expression_element['pos'], $expression_element['length']
		);
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
		$hanged_refs = ['' => false];

		foreach ($tokens as $token) {
			$host_name = '';
			$fn = $token['data'];
			$item_key = $fn['functionParams'][0];
			$host_delimiter_pos = strpos($item_key, ':');

			if ($host_delimiter_pos === false || $host_delimiter_pos > strpos($item_key, '[')) {
				continue;
			}

			$host_name = substr($item_key, 0, $host_delimiter_pos);

			if (!array_key_exists($host_name, $hanged_refs)) {
				$hanged_refs[$host_name] = false;
			}
			if (!in_array($fn['functionName'], $this->standalone_functions)) {
				$hanged_refs[$host_name] = true;
			}
		}

		return $hanged_refs;
	}
}
