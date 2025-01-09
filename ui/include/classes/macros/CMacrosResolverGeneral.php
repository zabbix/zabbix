<?php
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


class CMacrosResolverGeneral {

	/**
	 * Interface priorities.
	 *
	 * @var array
	 */
	protected const interfacePriorities = [
		INTERFACE_TYPE_AGENT => 4,
		INTERFACE_TYPE_SNMP => 3,
		INTERFACE_TYPE_JMX => 2,
		INTERFACE_TYPE_IPMI => 1
	];

	protected const aggr_triggers_macros = ['{TRIGGER.EVENTS.ACK}', '{TRIGGER.EVENTS.PROBLEM.ACK}',
		'{TRIGGER.EVENTS.PROBLEM.UNACK}', '{TRIGGER.EVENTS.UNACK}', '{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}',
		'{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}', '{TRIGGERS.UNACK}', '{TRIGGERS.PROBLEM.UNACK}', '{TRIGGERS.ACK}',
		'{TRIGGERS.PROBLEM.ACK}'];

	/**
	 * Get reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param string $expression
	 * @param array  $references
	 *
	 * @return array
	 */
	protected static function resolveTriggerReferences($expression, $references) {
		$values = [];
		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => true,
			'collapsed_expression' => true
		]);

		if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
			foreach ($expression_parser->getResult()->getTokens() as $token) {
				switch ($token['type']) {
					case CExpressionParserResult::TOKEN_TYPE_NUMBER:
					case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
						$values[] = $token['match'];
						break;

					case CExpressionParserResult::TOKEN_TYPE_STRING:
						$values[] = CExpressionParser::unquoteString($token['match']);
						break;
				}
			}
		}

		foreach ($references as $macro => $value) {
			$i = (int) $macro[1] - 1;
			$references[$macro] = array_key_exists($i, $values) ? $values[$i] : '';
		}

		return $references;
	}

	/**
	 * Checking existence of the macros.
	 *
	 * @param array  $texts
	 * @param array  $type
	 *
	 * @return bool
	 */
	protected function hasMacros(array $texts, array $types) {
		foreach ($texts as $text) {
			if (self::getMacroPositions($text, $types)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Transform types, used in extractMacros() function to types which can be used in getMacroPositions().
	 *
	 * @param array  $types
	 *
	 * @return array
	 */
	protected static function transformToPositionTypes(array $types) {
		foreach (['macros', 'macros_n', 'macros_an'] as $type) {
			if (array_key_exists($type, $types)) {
				$patterns = [];
				foreach ($types[$type] as $key => $_patterns) {
					$patterns = array_merge($patterns, $_patterns);
				}
				$types[$type] = $patterns;
			}
		}

		return $types;
	}

	/**
	 * Extract positions of the macros from a string.
	 *
	 * @param string $text
	 * @param array  $types
	 * @param bool   $types['usermacros']
	 * @param array  $types['macros'][<macro_patterns>]
	 * @param array  $types['macros_n'][<macro_patterns>]
	 * @param array  $types['macros_an'][<macro_patterns>]
	 * @param bool   $types['references']
	 * @param bool   $types['lldmacros']
	 * @param bool   $types['functionids']
	 *
	 * @return array
	 */
	public static function getMacroPositions($text, array $types) {
		$macros = [];
		$macro_parsers = [];

		if (array_key_exists('usermacros', $types)) {
			array_push($macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}

		if (array_key_exists('macros', $types)) {
			$options = ['macros' => $types['macros']];
			array_push($macro_parsers, new CMacroParser($options), new CMacroFunctionParser($options));
		}

		if (array_key_exists('macros_n', $types)) {
			$options = ['macros' => $types['macros_n'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC];
			array_push($macro_parsers, new CMacroParser($options), new CMacroFunctionParser($options));
		}

		if (array_key_exists('macros_an', $types)) {
			$options = ['macros' => $types['macros_an'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC];
			array_push($macro_parsers, new CMacroParser($options), new CMacroFunctionParser($options));
		}

		if (array_key_exists('references', $types)) {
			$macro_parsers[] = new CReferenceParser;
		}

		if (array_key_exists('lldmacros', $types)) {
			array_push($macro_parsers, new CLLDMacroParser, new CLLDMacroFunctionParser);
		}

		if (array_key_exists('functionids', $types)) {
			$macro_parsers[] = new CFunctionIdParser();
		}

		for ($pos = 0; isset($text[$pos]); $pos++) {
			foreach ($macro_parsers as $macro_parser) {
				if ($macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros[$pos] = $macro_parser->getMatch();
					$pos += $macro_parser->getLength() - 1;
					break;
				}
			}
		}

		return $macros;
	}

	/**
	 * Returns true if parsed expression is calculable.
	 *
	 * @param array $tokens
	 *
	 * @return bool
	 */
	private static function isCalculableExpression(array $tokens): bool {
		if (count($tokens) != 1 || $tokens[0]['type'] != CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION) {
			return false;
		}

		$expression_validator = new CExpressionValidator();

		if (!$expression_validator->validate($tokens)) {
			return false;
		}

		if (!in_array($tokens[0]['data']['function'], ['last', 'min', 'max', 'avg'])) {
			return false;
		}

		$parameters = $tokens[0]['data']['parameters'];

		// Time shift is not supported.
		if (array_key_exists(1, $parameters) && ($parameters[1]['type'] != CHistFunctionParser::PARAM_TYPE_PERIOD
				|| $parameters[1]['data']['sec_num'][0] === '#' || $parameters[1]['data']['time_shift'] !== '')) {
			return false;
		}

		return true;
	}

	/**
	 * Extract macros from a string.
	 *
	 * @param array  $texts
	 * @param array  $types
	 * @param bool   $types['usermacros']                         Extract user macros.
	 *                                                              For example, "{$MACRO}", "{{$MACRO}.func(param)}".
	 * @param array  $types['macros'][][<macro_patterns>]         Extract macros with optional macro function.
	 *                                                              For example, "{HOST.HOST}",
	 *                                                                "{{ITEM.VALUE}.func(param)}".
	 * @param array  $types['macros_n'][][<macro_patterns>]       Extract macros with optional numeric index and macro
	 *                                                              function.
	 *                                                              For example, "{HOST.HOST<1-9>}",
	 *                                                                "{{ITEM.VALUE<1-9>}.func(param)}".
	 * @param array  $types['macros_an'][][<macro_patterns>]      Extract macros with optional alphanumeric index.
	 *                                                              For example, "{EVENT.TAGS.Service}",
	 *                                                                {{EVENT.TAGS.Service}.func(param)}"
	 * @param bool   $types['references']                         Extract dollar-sign references. For example, "$5".
	 * @param bool   $types['lldmacros']                          Extract low-level discovery macros.
	 *                                                              For example, "{#LLD}", {{#LLD}.func(param)}.
	 * @param bool   $types['functionids']                        Extract numeric macros. For example, "{12345}".
	 * @param bool   $types['expr_macros']                        Extract expression macros.
	 *                                                              For example, "{?func(/host/key, param)}",
	 *                                                                "{{?func(/host/key, param)}.func(param)}"
	 * @param bool   $types['expr_macros_host']                   Extract expression macros with the ability to
	 *                                                              specify a {HOST.HOST} macro or an empty host name
	 *                                                              instead of a hostname.
	 *                                                              For example,
	 *                                                                "{?func(/host/key, param)}",
	 *                                                                "{?func(/{HOST.HOST}/key, param)}",
	 *                                                                "{?func(//key, param)}",
	 *                                                                "{{?func(/host/key, param)}.func(param)}".
	 * @param bool   $types['expr_macros_host_n']                 Extract expression macros with the ability to
	 *                                                              specify a {HOST.HOST<1-9>} macro or an empty host
	 *                                                              name instead of a hostname.
	 *                                                              For example,
	 *                                                                "{?func(/host/key, param)}",
	 *                                                                "{?func(/{HOST.HOST}/key, param)}",
	 *                                                                "{?func(/{HOST.HOST5}/key, param)}",
	 *                                                                "{?func(//key, param)}",
	 *                                                                "{{?func(/host/key, param)}.func(param)}".
	 *
	 * @return array
	 */
	public static function extractMacros(array $texts, array $types) {
		$macros = [];
		$extract_usermacros = array_key_exists('usermacros', $types);
		$extract_macros = array_key_exists('macros', $types);
		$extract_macros_n = array_key_exists('macros_n', $types);
		$extract_macros_an = array_key_exists('macros_an', $types);
		$extract_references = array_key_exists('references', $types);
		$extract_lldmacros = array_key_exists('lldmacros', $types);
		$extract_functionids = array_key_exists('functionids', $types);
		$extract_expr_macros = array_key_exists('expr_macros', $types);
		$extract_expr_macros_host = array_key_exists('expr_macros_host', $types);
		$extract_expr_macros_host_n = array_key_exists('expr_macros_host_n', $types);

		$macro_parsers_by_type = [];
		$macro_function_parsers_by_type = [];

		if ($extract_usermacros) {
			$macros['usermacros'] = [];

			$user_macro_parser = new CUserMacroParser();
			$user_macro_function_parser = new CUserMacroFunctionParser();
		}

		if ($extract_macros) {
			$macros['macros'] = [];

			foreach ($types['macros'] as $key => $macro_patterns) {
				$options = ['macros' => $macro_patterns];
				$macro_parsers_by_type['macros'][$key] = new CMacroParser($options);
				$macro_function_parsers_by_type['macros'][$key] = new CMacroFunctionParser($options);
				$macros['macros'][$key] = [];
			}
		}

		if ($extract_macros_n) {
			$macros['macros_n'] = [];

			foreach ($types['macros_n'] as $key => $macro_patterns) {
				$options = ['macros' => $macro_patterns, 'ref_type' => CMacroParser::REFERENCE_NUMERIC];
				$macro_parsers_by_type['macros_n'][$key] = new CMacroParser($options);
				$macro_function_parsers_by_type['macros_n'][$key] = new CMacroFunctionParser($options);
				$macros['macros_n'][$key] = [];
			}
		}

		if ($extract_macros_an) {
			$macros['macros_an'] = [];

			foreach ($types['macros_an'] as $key => $macro_patterns) {
				$options = ['macros' => $macro_patterns, 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC];
				$macro_parsers_by_type['macros_an'][$key] = new CMacroParser($options);
				$macro_function_parsers_by_type['macros_an'][$key] = new CMacroFunctionParser($options);
				$macros['macros_an'][$key] = [];
			}
		}

		if ($extract_references) {
			$macros['references'] = [];

			$reference_parser = new CReferenceParser();
		}

		if ($extract_lldmacros) {
			$macros['lldmacros'] = [];

			$lld_macro_parser = new CLLDMacroParser();
			$lld_macro_function_parser = new CLLDMacroFunctionParser();
		}

		if ($extract_functionids) {
			$macros['functionids'] = [];

			$functionid_parser = new CFunctionIdParser();
		}

		if ($extract_expr_macros) {
			$macros['expr_macros'] = [];

			$expr_macro_parser = new CExpressionMacroParser();
			$expr_macro_function_parser = new CExpressionMacroFunctionParser();
		}

		if ($extract_expr_macros_host) {
			$macros['expr_macros_host'] = [];
			$options = ['host_macro' => true, 'empty_host' => true];

			$expr_macro_parser_host = new CExpressionMacroParser($options);
			$expr_macro_function_parser_host = new CExpressionMacroFunctionParser($options);
		}

		if ($extract_expr_macros_host_n) {
			$macros['expr_macros_host_n'] = [];
			$options = ['host_macro_n' => true, 'empty_host' => true];

			$expr_macro_parser_host_n = new CExpressionMacroParser($options);
			$expr_macro_function_parser_host_n = new CExpressionMacroFunctionParser($options);
		}

		foreach ($texts as $text) {
			for ($pos = 0; isset($text[$pos]); $pos++) {
				if ($extract_usermacros) {
					if ($user_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
						$macros['usermacros'][$user_macro_parser->getMatch()] = [
							'macro' => $user_macro_parser->getMacro(),
							'context' => $user_macro_parser->getContext()
						];
						$pos += $user_macro_parser->getLength() - 1;
						continue;
					}

					if ($user_macro_function_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
						$user_macro_parser = $user_macro_function_parser->getUserMacroParser();
						$function_parser = $user_macro_function_parser->getFunctionParser();

						$macros['usermacros'][$user_macro_function_parser->getMatch()] = [
							'macro' => $user_macro_parser->getMacro(),
							'context' => $user_macro_parser->getContext(),
							'macrofunc' => [
								'function' => $function_parser->getFunction(),
								'parameters' => $function_parser->getParams()
							]
						];
						$pos += $user_macro_function_parser->getLength() - 1;
						continue;
					}
				}

				foreach ($macro_parsers_by_type as $type => $macro_parsers) {
					foreach ($macro_parsers as $key => $macro_parser) {
						if ($macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
							$macros[$type][$key][$macro_parser->getMatch()] = [
								'macro' => $macro_parser->getMacro(),
								'f_num' => $macro_parser->getReference()
							];
							$pos += $macro_parser->getLength() - 1;
							continue 2;
						}
					}
				}

				foreach ($macro_function_parsers_by_type as $type => $macro_function_parsers) {
					foreach ($macro_function_parsers as $key => $macro_function_parser) {
						if ($macro_function_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
							$macro_parser = $macro_function_parser->getMacroParser();
							$function_parser = $macro_function_parser->getFunctionParser();

							$macros[$type][$key][$macro_function_parser->getMatch()] = [
								'macro' => $macro_parser->getMacro(),
								'f_num' => $macro_parser->getReference(),
								'macrofunc' => [
									'function' => $function_parser->getFunction(),
									'parameters' => $function_parser->getParams()
								]
							];
							$pos += $macro_function_parser->getLength() - 1;
							continue 2;
						}
					}
				}

				if ($extract_references && $reference_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['references'][$reference_parser->getMatch()] = null;
					$pos += $reference_parser->getLength() - 1;
					continue;
				}

				if ($extract_lldmacros) {
					if ($lld_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
						$macros['lldmacros'][$lld_macro_parser->getMatch()] = null;
						$pos += $lld_macro_parser->getLength() - 1;
						continue;
					}
					elseif ($lld_macro_function_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
						$macros['lldmacros'][$lld_macro_function_parser->getMatch()] = null;
						$pos += $lld_macro_function_parser->getLength() - 1;
						continue;
					}
				}

				if ($extract_functionids && $functionid_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$macros['functionids'][$functionid_parser->getMatch()] = null;
					$pos += $functionid_parser->getLength() - 1;
					continue;
				}

				if ($extract_expr_macros && $expr_macro_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$tokens = $expr_macro_parser
						->getExpressionParser()
						->getResult()
						->getTokens();

					if (self::isCalculableExpression($tokens)) {
						$macros['expr_macros'][$expr_macro_parser->getMatch()] = [
							'function' => $tokens[0]['data']['function'],
							'host' => $tokens[0]['data']['parameters'][0]['data']['host'],
							'key' => $tokens[0]['data']['parameters'][0]['data']['item'],
							'sec_num' => array_key_exists(1, $tokens[0]['data']['parameters'])
								? $tokens[0]['data']['parameters'][1]['data']['sec_num']
								: ''
						];
						$pos += $expr_macro_parser->getLength() - 1;
						continue;
					}
				}

				if ($extract_expr_macros && $expr_macro_function_parser->parse($text, $pos) != CParser::PARSE_FAIL) {
					$tokens = $expr_macro_function_parser
						->getExpressionMacroParser()
						->getExpressionParser()
						->getResult()
						->getTokens();

					if (self::isCalculableExpression($tokens)) {
						$function_parser = $expr_macro_function_parser->getFunctionParser();

						$macros['expr_macros'][$expr_macro_function_parser->getMatch()] = [
							'function' => $tokens[0]['data']['function'],
							'host' => $tokens[0]['data']['parameters'][0]['data']['host'],
							'key' => $tokens[0]['data']['parameters'][0]['data']['item'],
							'sec_num' => array_key_exists(1, $tokens[0]['data']['parameters'])
								? $tokens[0]['data']['parameters'][1]['data']['sec_num']
								: '',
							'macrofunc' => [
								'function' => $function_parser->getFunction(),
								'parameters' => $function_parser->getParams()
							]
						];
						$pos += $expr_macro_function_parser->getLength() - 1;
						continue;
					}
				}

				if ($extract_expr_macros_host && $expr_macro_parser_host->parse($text, $pos) != CParser::PARSE_FAIL) {
					$tokens = $expr_macro_parser_host
						->getExpressionParser()
						->getResult()
						->getTokens();

					if (self::isCalculableExpression($tokens)) {
						$macros['expr_macros_host'][$expr_macro_parser_host->getMatch()] = [
							'function' => $tokens[0]['data']['function'],
							'host' => $tokens[0]['data']['parameters'][0]['data']['host'],
							'key' => $tokens[0]['data']['parameters'][0]['data']['item'],
							'sec_num' => array_key_exists(1, $tokens[0]['data']['parameters'])
								? $tokens[0]['data']['parameters'][1]['data']['sec_num']
								: ''
						];
						$pos += $expr_macro_parser_host->getLength() - 1;
						continue;
					}
				}

				if ($extract_expr_macros_host
						&& $expr_macro_function_parser_host->parse($text, $pos) != CParser::PARSE_FAIL) {
					$tokens = $expr_macro_function_parser_host
						->getExpressionMacroParser()
						->getExpressionParser()
						->getResult()
						->getTokens();

					if (self::isCalculableExpression($tokens)) {
						$function_parser = $expr_macro_function_parser_host->getFunctionParser();

						$macros['expr_macros_host'][$expr_macro_function_parser_host->getMatch()] = [
							'function' => $tokens[0]['data']['function'],
							'host' => $tokens[0]['data']['parameters'][0]['data']['host'],
							'key' => $tokens[0]['data']['parameters'][0]['data']['item'],
							'sec_num' => array_key_exists(1, $tokens[0]['data']['parameters'])
								? $tokens[0]['data']['parameters'][1]['data']['sec_num']
								: '',
							'macrofunc' => [
								'function' => $function_parser->getFunction(),
								'parameters' => $function_parser->getParams()
							]

						];
						$pos += $expr_macro_function_parser_host->getLength() - 1;
						continue;
					}
				}

				if ($extract_expr_macros_host_n
						&& $expr_macro_parser_host_n->parse($text, $pos) != CParser::PARSE_FAIL) {
					$tokens = $expr_macro_parser_host_n
						->getExpressionParser()
						->getResult()
						->getTokens();

					if (self::isCalculableExpression($tokens)) {
						$macros['expr_macros_host_n'][$expr_macro_parser_host_n->getMatch()] = [
							'function' => $tokens[0]['data']['function'],
							'host' => $tokens[0]['data']['parameters'][0]['data']['host'],
							'key' => $tokens[0]['data']['parameters'][0]['data']['item'],
							'sec_num' => array_key_exists(1, $tokens[0]['data']['parameters'])
								? $tokens[0]['data']['parameters'][1]['data']['sec_num']
								: ''
						];
						$pos += $expr_macro_parser_host_n->getLength() - 1;
						continue;
					}
				}

				if ($extract_expr_macros_host_n
						&& $expr_macro_function_parser_host_n->parse($text, $pos) != CParser::PARSE_FAIL) {
					$tokens = $expr_macro_function_parser_host_n
						->getExpressionMacroParser()
						->getExpressionParser()
						->getResult()
						->getTokens();

					if (self::isCalculableExpression($tokens)) {
						$function_parser = $expr_macro_function_parser_host_n->getFunctionParser();

						$macros['expr_macros_host_n'][$expr_macro_function_parser_host_n->getMatch()] = [
							'function' => $tokens[0]['data']['function'],
							'host' => $tokens[0]['data']['parameters'][0]['data']['host'],
							'key' => $tokens[0]['data']['parameters'][0]['data']['item'],
							'sec_num' => array_key_exists(1, $tokens[0]['data']['parameters'])
								? $tokens[0]['data']['parameters'][1]['data']['sec_num']
								: '',
							'macrofunc' => [
								'function' => $function_parser->getFunction(),
								'parameters' => $function_parser->getParams()
							]
						];
						$pos += $expr_macro_function_parser_host_n->getLength() - 1;
						continue;
					}
				}
			}
		}

		return $macros;
	}

	/**
	 * Returns the list of the item key parameters.
	 *
	 * @param array $params_raw
	 *
	 * @return array
	 */
	public static function getItemKeyParameters($params_raw) {
		$item_key_parameters = [];

		foreach ($params_raw as $param_raw) {
			switch ($param_raw['type']) {
				case CItemKey::PARAM_ARRAY:
					$item_key_parameters = array_merge($item_key_parameters,
						self::getItemKeyParameters($param_raw['parameters'])
					);
					break;

				case CItemKey::PARAM_UNQUOTED:
					$item_key_parameters[] = $param_raw['raw'];
					break;

				case CItemKey::PARAM_QUOTED:
					$item_key_parameters[] = CItemKey::unquoteParam($param_raw['raw']);
					break;
			}
		}

		return $item_key_parameters;
	}

	/**
	 * Extract macros from an item key.
	 *
	 * @param string $key		an item key
	 * @param array  $types		the types of macros (see extractMacros() for more details)
	 *
	 * @return array			see extractMacros() for more details
	 */
	protected static function extractItemKeyMacros($key, array $types) {
		$item_key_parser = new CItemKey();

		$item_key_parameters = [];
		if ($item_key_parser->parse($key) == CParser::PARSE_SUCCESS) {
			$item_key_parameters = self::getItemKeyParameters($item_key_parser->getParamsRaw());
		}

		return self::extractMacros($item_key_parameters, $types);
	}

	/**
	 * Extract macros from a trigger function.
	 *
	 * @param string $function	a history function, for example 'last(/host/key, {$OFFSET})'
	 * @param array  $types		the types of macros (see extractMacros() for more details)
	 *
	 * @return array			see extractMacros() for more details
	 */
	protected static function extractFunctionMacros($function, array $types) {
		$hist_function_parser = new CHistFunctionParser(['usermacros' => true, 'lldmacros' => true]);
		$function_parameters = [];

		if ($hist_function_parser->parse($function) == CParser::PARSE_SUCCESS) {
			foreach ($hist_function_parser->getParameters() as $parameter) {
				switch ($parameter['type']) {
					case CHistFunctionParser::PARAM_TYPE_PERIOD:
					case CHistFunctionParser::PARAM_TYPE_UNQUOTED:
						$function_parameters[] = $parameter['match'];
						break;

					case CHistFunctionParser::PARAM_TYPE_QUOTED:
						$function_parameters[] = CHistFunctionParser::unquoteParam($parameter['match']);
						break;
				}
			}
		}

		return self::extractMacros($function_parameters, $types);
	}

	/**
	 * Resolves macros in the item key parameters.
	 *
	 * @param string $key_chain		an item key chain
	 * @param array  $params_raw
	 * @param array  $values		the list of macros (['{<MACRO>}' => '<value>', ...])
	 *
	 * @return string
	 */
	private static function resolveItemKeyParamsMacros($key_chain, array $params_raw, array $values) {
		foreach (array_reverse($params_raw) as $param_raw) {
			$param = $param_raw['raw'];
			$forced = false;

			switch ($param_raw['type']) {
				case CItemKey::PARAM_ARRAY:
					$param = self::resolveItemKeyParamsMacros($param, $param_raw['parameters'], $values);
					break;

				case CItemKey::PARAM_QUOTED:
					$param = CItemKey::unquoteParam($param);
					$forced = true;
					// break; is not missing here

				case CItemKey::PARAM_UNQUOTED:
					$param = quoteItemKeyParam(strtr($param, $values), $forced);
					break;
			}

			$key_chain = substr_replace($key_chain, $param, $param_raw['pos'], strlen($param_raw['raw']));
		}

		return $key_chain;
	}

	/**
	 * Resolves macros in the item key.
	 *
	 * @param string $key     An item key.
	 * @param array  $values  The list of macros (['{<MACRO>}' => '<value>', ...]).
	 *
	 * @return string
	 */
	public static function resolveItemKeyMacros($key, array $values) {
		$item_key_parser = new CItemKey();

		if ($item_key_parser->parse($key) == CParser::PARSE_SUCCESS) {
			$key = self::resolveItemKeyParamsMacros($key, $item_key_parser->getParamsRaw(), $values);
		}

		return $key;
	}

	/**
	 * Resolves macros in the trigger function parameters.
	 *
	 * @param string $function	a trigger function
	 * @param array  $macros	the list of macros (['{<MACRO>}' => '<value>', ...])
	 *
	 * @return string
	 */
	protected static function resolveFunctionMacros($function, array $macros) {
		$hist_function_parser = new CHistFunctionParser(['usermacros' => true, 'lldmacros' => true]);

		if ($hist_function_parser->parse($function) == CParser::PARSE_SUCCESS) {
			foreach (array_reverse($hist_function_parser->getParameters(), true) as $i => $parameter) {
				switch ($parameter['type']) {
					case CHistFunctionParser::PARAM_TYPE_PERIOD:
					case CHistFunctionParser::PARAM_TYPE_UNQUOTED:
					case CHistFunctionParser::PARAM_TYPE_QUOTED:
						$param = strtr($hist_function_parser->getParam($i), $macros);

						if ($parameter['type'] != CHistFunctionParser::PARAM_TYPE_PERIOD) {
							$force = $parameter['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED;
							$param = CHistFunctionParser::quoteParam($param, $force,
								['usermacros' => true, 'lldmacros' => true]
							);
						}

						$function = substr_replace($function, $param, $parameter['pos'], $parameter['length']);

						break;
				}
			}
		}

		return $function;
	}

	/**
	 * Find function ids in trigger expression.
	 *
	 * @param string $expression
	 *
	 * @return array	where key is function id position in expression and value is function id
	 */
	protected static function findFunctions($expression) {
		$functionids = [];

		$expression_parser = new CExpressionParser(['usermacros' => true, 'collapsed_expression' => true]);

		if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
			$tokens = $expression_parser
				->getResult()
				->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO]);

			foreach ($tokens as $f_num => $token) {
				$functionids[$f_num + 1] = substr($token['match'], 1, -1); // strip curly braces
			}
		}

		if (array_key_exists(1, $functionids)) {
			$functionids[0] = $functionids[1];
		}

		return $functionids;
	}

	/**
	 * Get interface macros.
	 *
	 * @param array $macros
	 * @param array $macros[<functionid>]
	 * @param array $macros[<functionid>][<macro>]  an array of the tokens
	 * @param array $macro_values
	 *
	 * @return array
	 */
	protected static function getIpMacros(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$result = DBselect(
			'SELECT f.triggerid,f.functionid,n.ip,n.dns,n.type,n.useip,n.port'.
			' FROM functions f'.
				' JOIN items i ON f.itemid=i.itemid'.
				' JOIN interface n ON i.hostid=n.hostid'.
			' WHERE '.dbConditionInt('f.functionid', array_keys($macros)).
				' AND n.main=1'
		);

		// Macro should be resolved to interface with highest priority ($priorities).
		$interfaces = [];

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['functionid'], $interfaces)
					&& self::interfacePriorities[$interfaces[$row['functionid']]['type']]
						> self::interfacePriorities[$row['type']]) {
				continue;
			}

			$interfaces[$row['functionid']] = $row;
		}

		foreach ($interfaces as $interface) {
			foreach ($macros[$interface['functionid']] as $macro => $tokens) {
				switch ($macro) {
					case 'IPADDRESS':
					case 'HOST.IP':
						$value = $interface['ip'];
						break;
					case 'HOST.DNS':
						$value = $interface['dns'];
						break;
					case 'HOST.CONN':
						$value = $interface['useip'] ? $interface['ip'] : $interface['dns'];
						break;
					case 'HOST.PORT':
						$value = $interface['port'];
						break;
				}

				foreach ($tokens as $token) {
					$macro_values[$interface['triggerid']][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Resolves items value maps, valuemap property will be added to every item.
	 *
	 * @param array $items
	 * @param int   $items[]['itemid']
	 * @param int   $items[]['valuemapid']
	 *
	 * @return array
	 */
	protected static function getItemsValueMaps(array $items): array {
		foreach ($items as &$item) {
			$item['valuemap'] = [];
		}
		unset($item);

		$valuemapids = array_flip(array_column($items, 'valuemapid'));
		unset($valuemapids[0]);

		if (!$valuemapids) {
			return $items;
		}

		$options = [
			'output' => ['valuemapid', 'type', 'value', 'newvalue'],
			'filter' => ['valuemapid' => array_keys($valuemapids)],
			'sortfield' => ['sortorder']
		];
		$db_mappings = DBselect(DB::makeSql('valuemap_mapping', $options));

		$db_valuemaps = [];

		while ($db_mapping  = DBfetch($db_mappings)) {
			$db_valuemaps[$db_mapping['valuemapid']]['mappings'][] = [
				'type' => $db_mapping['type'],
				'value' => $db_mapping['value'],
				'newvalue' => $db_mapping['newvalue']
			];
		}

		foreach ($items as &$item) {
			if (array_key_exists($item['valuemapid'], $db_valuemaps)) {
				$item['valuemap'] = $db_valuemaps[$item['valuemapid']];
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Get item macros by itemid.
	 *
	 * @param array  $macros
	 * @param array  $macros[<itemid>]
	 * @param array  $macros[<itemid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<itemid>]
	 * @param string $macro_values[<itemid>][<token>]
	 *
	 * @return array
	 */
	protected static function getItemMacrosByItemId(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$db_items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'name_resolved', 'key_', 'value_type', 'state', 'description'],
			'itemids' => array_keys($macros),
			'webitems' => true,
			'preservekeys' => true
		]);

		$db_items = CMacrosResolverHelper::resolveItemKeys($db_items);
		$db_items = CMacrosResolverHelper::resolveItemDescriptions($db_items);

		foreach ($db_items as &$db_item) {
			$db_item['state'] = itemState($db_item['state']);
		}
		unset($db_item);

		$item_macros = ['ITEM.DESCRIPTION' => 'description_expanded', 'ITEM.DESCRIPTION.ORIG' => 'description',
			'ITEM.ID' => 'itemid', 'ITEM.KEY' => 'key_expanded', 'ITEM.KEY.ORIG' => 'key_',
			'ITEM.NAME' => 'name_resolved', 'ITEM.NAME.ORIG' => 'name', 'ITEM.STATE' => 'state',
			'ITEM.VALUETYPE' => 'value_type'
		];

		foreach ($db_items as $itemid => $db_item) {
			foreach ($macros[$itemid] as $macro => $tokens) {
				$value = $db_item[$item_macros[$macro]];

				foreach ($tokens as $token) {
					$macro_values[$itemid][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}
		return $macro_values;
	}

	/**
	 * Get item value macros by itemid.
	 *
	 * @param array  $macros
	 * @param array  $macros[<itemid>]
	 * @param array  $macros[<itemid>][<macro>]
	 * @param array  $macros[<itemid>][<macro>][]
	 * @param string $macros[<itemid>][<macro>][]['token']
	 * @param array  $macros[<itemid>][<macro>][]['macrofunc']
	 * @param array  $macro_values
	 *
	 * @return array
	 */
	protected static function getItemValueMacrosByItemId(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$db_items = API::Item()->get([
			'output' => ['itemid', 'value_type', 'units', 'valuemapid'],
			'itemids' => array_keys($macros),
			'webitems' => true,
			'preservekeys' => true
		]);
		$db_items = self::getItemsValueMaps($db_items);

		$history = Manager::History()->getLastValues($db_items, 1, timeUnitToSeconds(
			CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD)
		));

		foreach ($history as $itemid => $item_history) {
			foreach ($macros[$itemid] as $macro => $tokens) {
				if ($macro === 'ITEM.VALUE' || $macro === 'ITEM.LASTVALUE') {
					$value = $item_history[0]['value'];

					foreach ($tokens as $token) {
						$macro_values[$itemid][$token['token']] = array_key_exists('macrofunc', $token)
							? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
							: formatHistoryValue($value, $db_items[$itemid]);
					}
				}
				elseif ($db_items[$itemid]['value_type'] == ITEM_VALUE_TYPE_LOG) {
					switch ($macro) {
						case 'ITEM.LOG.DATE':
							$value = date('Y.m.d', $history[$itemid][0]['timestamp']);
							break;

						case 'ITEM.LOG.TIME':
							$value = date('H:i:s', $history[$itemid][0]['timestamp']);
							break;

						case 'ITEM.LOG.TIMESTAMP':
							$value = $history[$itemid][0]['timestamp'];
							break;

						case 'ITEM.LOG.AGE':
							$value = zbx_date2age($history[$itemid][0]['timestamp']);
							break;

						case 'ITEM.LOG.SOURCE':
							$value = $history[$itemid][0]['source'];
							break;

						case 'ITEM.LOG.SEVERITY':
							$value = get_item_logtype_description($history[$itemid][0]['severity']);
							break;

						case 'ITEM.LOG.NSEVERITY':
							$value = $history[$itemid][0]['severity'];
							break;

						case 'ITEM.LOG.EVENTID':
							$value = $history[$itemid][0]['logeventid'];
							break;
					}

					foreach ($tokens as $token) {
						$macro_values[$itemid][$token['token']] = array_key_exists('macrofunc', $token)
							? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
							: $value;
					}
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get inventory macros by itemid.
	 *
	 * @param array  $macros
	 * @param array  $macros[<itemid>]
	 * @param array  $macros[<itemid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<itemid>]
	 * @param string $macro_values[<itemid>][<token>]
	 *
	 * @return array
	 */
	protected static function getInventoryMacrosByItemId(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$db_items = API::Item()->get([
			'output' => ['hostid'],
			'itemids' => array_keys($macros),
			'preservekeys' => true
		]);

		if (!$db_items) {
			return $macro_values;
		}

		$inventory_macros = self::getSupportedHostInventoryMacrosMap();

		$db_hosts = API::Host()->get([
			'output' => ['inventory_mode'],
			'selectInventory' => array_values($inventory_macros),
			'hostids' => array_unique(array_column($db_items, 'hostid')),
			'preservekeys' => true
		]);

		foreach ($db_items as $itemid => $db_item) {
			if (!array_key_exists($db_item['hostid'], $db_hosts)
					|| $db_hosts[$db_item['hostid']]['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				continue;
			}

			foreach ($macros[$itemid] as $macro => $tokens) {
				$value = $db_hosts[$db_item['hostid']]['inventory'][$inventory_macros['{'.$macro.'}']];

				foreach ($tokens as $token) {
					$macro_values[$itemid][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get item macros.
	 *
	 * @param array $macros
	 * @param array $macros[<functionid>]
	 * @param array $macros[<functionid>][<macro>]  An array of the tokens.
	 * @param array $macro_values
	 * @param array $triggers
	 * @param array $options
	 * @param bool  $options['events']              Resolve {ITEM.VALUE} macro using 'clock' and 'ns' fields.
	 * @param bool  $options['html']
	 *
	 * @return array
	 */
	protected static function getItemMacros(array $macros, array $macro_values, array $triggers = [], array $options = []) {
		if (!$macros) {
			return $macro_values;
		}

		$options += [
			'events' => false,
			'html' => false
		];

		$functions = DBfetchArray(DBselect(
			'SELECT f.triggerid,f.functionid,i.itemid,i.name,i.value_type,i.units,i.valuemapid'.
			' FROM functions f'.
				' JOIN items i ON f.itemid=i.itemid'.
				' JOIN hosts h ON i.hostid=h.hostid'.
			' WHERE '.dbConditionInt('f.functionid', array_keys($macros))
		));

		$functions = self::getItemsValueMaps($functions);

		// False passed to DBfetch to get data without null converted to 0, which is done by default.
		foreach ($functions as $function) {
			foreach ($macros[$function['functionid']] as $m => $tokens) {
				$clock = null;
				$value = null;

				switch ($m) {
					case 'ITEM.VALUE':
						if ($options['events']) {
							$trigger = $triggers[$function['triggerid']];
							$history = Manager::History()->getValueAt($function, $trigger['clock'], $trigger['ns']);

							if (is_array($history)) {
								if (array_key_exists('clock', $history)) {
									$clock = $history['clock'];
								}

								if (array_key_exists('value', $history)
										&& $function['value_type'] != ITEM_VALUE_TYPE_BINARY) {
									$value = $history['value'];
								}
							}
							break;
						}
						// break; is not missing here

					case 'ITEM.LASTVALUE':
						$history = Manager::History()->getLastValues([$function], 1, timeUnitToSeconds(
							CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD)
						));

						if (array_key_exists($function['itemid'], $history)) {
							$clock = $history[$function['itemid']][0]['clock'];

							if ($function['value_type'] != ITEM_VALUE_TYPE_BINARY) {
								$value = $history[$function['itemid']][0]['value'];
							}
						}
						break;
				}

				foreach ($tokens as $token) {
					if ($value !== null) {
						$macro_value = array_key_exists('macrofunc', $token)
							? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
							: formatHistoryValue($value, $function);
					}
					else {
						$macro_value = UNRESOLVED_MACRO_STRING;
					}

					if ($options['html']) {
						$macro_value = str_replace(["\r\n", "\n"], [" "], $macro_value);
						$hint_table = (new CTable())
							->addClass(ZBX_STYLE_LIST_TABLE)
							->addRow([
								new CCol($function['name']),
								new CCol(
									($clock !== null)
										? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock)
										: UNRESOLVED_MACRO_STRING
								),
								new CCol($macro_value),
								new CCol(
									($function['value_type'] == ITEM_VALUE_TYPE_FLOAT
											|| $function['value_type'] == ITEM_VALUE_TYPE_UINT64)
										? new CLink(_('Graph'), (new CUrl('history.php'))
											->setArgument('action', HISTORY_GRAPH)
											->setArgument('itemids[]', $function['itemid'])
											->getUrl()
										)
										: new CLink(_('History'), (new CUrl('history.php'))
											->setArgument('action', HISTORY_VALUES)
											->setArgument('itemids[]', $function['itemid'])
											->getUrl()
										)
								)
							]);
						$macro_value = new CSpan([
							(new CSpan())
								->addClass('main-hint')
								->setHint($hint_table),
							(new CLinkAction($macro_value))
								->addClass('hint-item')
								->setAttribute('data-hintbox', '1')
						]);
					}

					$macro_values[$function['triggerid']][$token['token']] = $macro_value;
				}
			}
		}

		return $macro_values;
	}

	protected static function getItemLogMacros(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$functions = DBfetchArray(DBselect(
			'SELECT f.triggerid,f.functionid,i.itemid,i.value_type'.
			' FROM functions f'.
				' JOIN items i ON f.itemid=i.itemid'.
				' JOIN hosts h ON i.hostid=h.hostid'.
			' WHERE '.dbConditionInt('f.functionid', array_keys($macros)).
			' AND i.value_type='.ITEM_VALUE_TYPE_LOG
		));

		if (!$functions) {
			return $macro_values;
		}

		foreach ($functions as $function) {
			foreach ($macros[$function['functionid']] as $m => $tokens) {
				$value = UNRESOLVED_MACRO_STRING;

				$history = Manager::History()->getLastValues([$function], 1, timeUnitToSeconds(
					CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD)
				));

				if (!array_key_exists($function['itemid'], $history)) {
					continue;
				}

				switch ($m) {
					case 'ITEM.LOG.DATE':
						$value = date('Y.m.d', $history[$function['itemid']][0]['timestamp']);
						break;

					case 'ITEM.LOG.TIME':
						$value = date('H:i:s', $history[$function['itemid']][0]['timestamp']);
						break;

					case 'ITEM.LOG.TIMESTAMP':
						$value = $history[$function['itemid']][0]['timestamp'];
						break;

					case 'ITEM.LOG.AGE':
						$value = zbx_date2age($history[$function['itemid']][0]['timestamp']);
						break;

					case 'ITEM.LOG.SOURCE':
						$value = $history[$function['itemid']][0]['source'];
						break;

					case 'ITEM.LOG.SEVERITY':
						$value = get_item_logtype_description($history[$function['itemid']][0]['severity']);
						break;

					case 'ITEM.LOG.NSEVERITY':
						$value = $history[$function['itemid']][0]['severity'];
						break;

					case 'ITEM.LOG.EVENTID':
						$value = $history[$function['itemid']][0]['logeventid'];
						break;
				}

				foreach ($tokens as $token) {
					$macro_values[$function['triggerid']][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get macros with values.
	 *
	 * @param array $usermacros
	 * @param array $usermacros[<triggerid>]['macros']  The list of user macros to resolve,
	 *                                                    ['<usermacro1>' => null, ...].
	 *
	 * @return array
	 */
	protected static function getTriggerUserMacros(array $usermacros, array $macro_values) {
		if (!$usermacros) {
			return $macro_values;
		}

		$db_triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'triggerids' => array_keys($usermacros),
			'preservekeys' => true
		]);

		foreach ($usermacros as $triggerid => &$usermacros_data) {
			if (array_key_exists($triggerid, $db_triggers)) {
				$usermacros_data['hostids'] = array_unique(array_column($db_triggers[$triggerid]['hosts'], 'hostid'));
			}
		}
		unset($usermacros_data);

		return self::getUserMacros($usermacros, $macro_values);
	}

	/**
	 * Get host macros.
	 *
	 * @param array $macros
	 * @param array $macros[<functionid>]
	 * @param array $macros[<functionid>][<macro>]  an array of the tokens
	 * @param array $macro_values
	 *
	 * @return array
	 */
	protected static function getHostMacros(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$result = DBselect(
			'SELECT f.triggerid,f.functionid,h.hostid,h.host,h.name'.
			' FROM functions f'.
				' JOIN items i ON f.itemid=i.itemid'.
				' JOIN hosts h ON i.hostid=h.hostid'.
			' WHERE '.dbConditionInt('f.functionid', array_keys($macros))
		);

		$host_macros = ['HOST.ID' => 'hostid', 'HOSTNAME' => 'host', 'HOST.HOST' => 'host', 'HOST.NAME' => 'name'];

		while ($row = DBfetch($result)) {
			foreach ($macros[$row['functionid']] as $macro => $tokens) {
				$value = $row[$host_macros[$macro]];

				foreach ($tokens as $token) {
					$macro_values[$row['triggerid']][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get expression macros like "{?avg(/host/key, 1d)}" or {{?min(/host/key, 1h)}.fmtnum(2)}.
	 *
	 * @param array  $macros
	 * @param array  $macros[<macro>]
	 * @param string $macros[<macro>]['function']
	 * @param string $macros[<macro>]['host']
	 * @param string $macros[<macro>]['key']
	 * @param string $macros[<macro>]['sec_num']
	 * @param array  $macros[<macro>]['macrofunc']                (optional)
	 * @param string $macros[<macro>]['macrofunc']['function']
	 * @param array  $macros[<macro>]['macrofunc']['parameters']
	 * @param array  $macro_values
	 *
	 * @return array
	 */
	protected static function getExpressionMacros(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$function_data = [];

		foreach ($macros as $macro => $data) {
			$macro_data = ['macro' => $macro];
			if (array_key_exists('macrofunc', $data)) {
				$macro_data['macrofunc'] = $data['macrofunc'];
			}

			if ($data['function'] === 'last') {
				$function_data['last'][$data['host']][$data['key']][] = $macro_data;
			}
			else {
				$function_data['other'][$data['host']][$data['key']][$data['function']][$data['sec_num']][] =
					$macro_data;
			}
		}

		foreach ($function_data as $ftype => $hosts) {
			foreach ($hosts as $host => $keys) {
				if ($ftype === 'last') {
					$db_items = API::Item()->get([
						'output' => ['key_', 'value_type', 'units', 'lastvalue', 'lastclock'],
						'selectValueMap' => ['mappings'],
						'webitems' => true,
						'filter' => [
							'host' => $host,
							'key_' => array_keys($keys)
						]
					]);

					foreach ($db_items as $db_item) {
						foreach ($keys[$db_item['key_']] as $macro_data) {
							if ($db_item['lastclock'] && $db_item['value_type'] != ITEM_VALUE_TYPE_BINARY) {
								$macro_values[$macro_data['macro']] = array_key_exists('macrofunc', $macro_data)
									? CMacroFunction::calcMacrofunc($db_item['lastvalue'], $macro_data['macrofunc'])
									: formatHistoryValue($db_item['lastvalue'], $db_item);
							}
							else {
								$macro_values[$macro_data['macro']] = UNRESOLVED_MACRO_STRING;
							}
						}
					}
				}
				else {
					$db_items = API::Item()->get([
						'output' => ['itemid', 'key_', 'value_type', 'units'],
						'webitems' => true,
						'filter' => [
							'host' => $host,
							'key_' => array_keys($keys)
						]
					]);

					foreach ($db_items as $db_item) {
						foreach ($keys[$db_item['key_']] as $function => $sec_nums) {
							foreach ($sec_nums as $sec_num => $_macros) {
								$value = getItemFunctionalValue($db_item, $function, $sec_num);

								foreach ($_macros as $macro_data) {
									if ($value !== null) {
										$macro_values[$macro_data['macro']] = array_key_exists('macrofunc', $macro_data)
											? CMacroFunction::calcMacrofunc($value, $macro_data['macrofunc'])
											: convertUnits(['value' => $value, 'units' => $db_item['units']]);
									}
									else {
										$macro_values[$macro_data['macro']] = UNRESOLVED_MACRO_STRING;
									}
								}
							}
						}
					}
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get map macros.
	 *
	 * @param array  $macros
	 * @param array  $macros[<sysmapid>]
	 * @param array  $macros[<sysmapid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param string $macro_values[<key>][<token>]
	 *
	 * @return array
	 */
	protected static function getMapMacros(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$sysmap_macros = ['MAP.ID' => 'sysmapid', 'MAP.NAME' => 'name'];

		$db_maps = API::Map()->get([
			'output' => ['sysmapid', 'name'],
			'sysmapids' => array_keys($macros),
			'preservekeys' => true
		]);

		foreach ($db_maps as $sysmapid => $db_map) {
			foreach ($macros[$sysmapid] as $macro => $tokens) {
				$value = $db_map[$sysmap_macros[$macro]];

				foreach ($tokens as $token) {
					$macro_values[$token['key']][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/*
	 * Resolve aggregated macros like {TRIGGER.EVENTS.*}, {TRIGGER(S).PROBLEM.*} and {TRIGGERS.(UN)ACK}.
	 *
	 * @param array $selement
	 * @param string $macro
	 *
	 * @return int
	 */
	private static function getTriggersMacroValue(array $selement, string $macro) {
		switch ($macro) {
			case 'TRIGGER.EVENTS.ACK':
				return get_events_unacknowledged($selement, null, null, true);

			case 'TRIGGER.EVENTS.PROBLEM.ACK':
				return get_events_unacknowledged($selement, null, TRIGGER_VALUE_TRUE, true);

			case 'TRIGGER.EVENTS.PROBLEM.UNACK':
				return get_events_unacknowledged($selement, null, TRIGGER_VALUE_TRUE);

			case 'TRIGGER.EVENTS.UNACK':
				return get_events_unacknowledged($selement);

			case 'TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK':
				return get_events_unacknowledged($selement, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE, true);

			case 'TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK':
				return get_events_unacknowledged($selement, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE);

			case 'TRIGGERS.UNACK':
				return get_triggers_unacknowledged($selement);

			case 'TRIGGERS.PROBLEM.UNACK':
				return get_triggers_unacknowledged($selement, true);

			case 'TRIGGERS.ACK':
				return get_triggers_unacknowledged($selement, null, true);

			case 'TRIGGERS.PROBLEM.ACK':
				return get_triggers_unacknowledged($selement, true, true);
		}
	}

	/**
	 * Get aggregated trigger macros.
	 *
	 * @param array  $macros
	 * @param array  $macros[<key>]
	 * @param array  $macros[<key>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param string $macro_values[<key>][<token>]
	 * @param array  $selements
	 * @param array  $selements[<key>]
	 * @param int    $selements[<key>]['elementtype']
	 * @param array  $selements[<key>]['elements']
	 *
	 * @return array
	 */
	protected static function getAggrTriggerMacros(array $macros, array $macro_values, array $selements): array {
		foreach ($macros as $key => $macro_tokens) {
			foreach ($macro_tokens as $macro => $tokens) {
				$value = self::getTriggersMacroValue($selements[$key], $macro);

				foreach ($tokens as $token) {
					$macro_values[$key][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get host macros.
	 *
	 * @param array  $macros
	 * @param array  $macros[<hostid>]
	 * @param array  $macros[<hostid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<hostid>]
	 * @param string $macro_values[<hostid>][<token>]
	 *
	 * @return array
	 */
	protected static function getHostMacrosByHostId(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$db_hosts = API::Host()->get([
			'output' => ['host', 'name', 'description'],
			'hostids' => array_keys($macros),
			'preservekeys' => true
		]);

		$host_macros = ['HOST.ID' => 'hostid', 'HOSTNAME' => 'host', 'HOST.HOST' => 'host', 'HOST.NAME' => 'name',
			'HOST.DESCRIPTION' => 'description'
		];

		foreach ($db_hosts as $hostid => $db_host) {
			foreach ($macros[$hostid] as $macro => $tokens) {
				$value = $db_host[$host_macros[$macro]];

				foreach ($tokens as $token) {
					$key = array_key_exists('key', $token) ? $token['key'] : $hostid;
					$macro_values[$key][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get host macros by itemid.
	 *
	 * @param array  $macros
	 * @param array  $macros[<itemid>]
	 * @param array  $macros[<itemid>][<macro>]  an array of the tokens
	 * @param array  $macro_values
	 * @param array  $macro_values[<itemid>]
	 * @param string $macro_values[<itemid>][<token>]
	 *
	 * @return array
	 */
	protected static function getHostMacrosByItemId(array $macros, array $macro_values) {
		if (!$macros) {
			return $macro_values;
		}

		$db_items = API::Item()->get([
			'output' => [],
			'selectHosts' => ['hostid', 'host', 'name', 'description'],
			'itemids' => array_keys($macros),
			'webitems' => true,
			'preservekeys' => true
		]);

		$host_macros = ['HOST.ID' => 'hostid', 'HOSTNAME' => 'host', 'HOST.HOST' => 'host', 'HOST.NAME' => 'name',
			'HOST.DESCRIPTION' => 'description'];

		foreach ($db_items as $itemid => $db_item) {
			foreach ($macros[$itemid] as $macro => $tokens) {
				$value = $db_item['hosts'][0][$host_macros[$macro]];

				foreach ($tokens as $token) {
					$macro_values[$itemid][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get interface macros by itemid.
	 *
	 * @param array  $macros
	 * @param array  $macros[<itemid>]
	 * @param array  $macros[<itemid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<itemid>]
	 * @param string $macro_values[<itemid>][<token>]
	 *
	 * @return array
	 */
	protected static function getInterfaceMacrosByItemId(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$db_items = API::Item()->get([
			'output' => ['hostid', 'interfaceid'],
			'itemids' => array_keys($macros),
			'webitems' => true,
			'preservekeys' => true
		]);

		$interfaceids = [];
		$hostids = [];

		foreach ($db_items as $itemid => $db_item) {
			if ($db_item['interfaceid'] != 0) {
				// Collecting interface IDs for items with specific interface.
				$interfaceids[$db_item['interfaceid']][] = $itemid;
			}
			else {
				/*
				 * Collecting host IDs for items without interface. Macros for such items will resolve to either the
				 * Zabbix agent, SNMP, JMX or IPMI interface of the host in this order of priority or to 'UNKNOWN' if
				 * the host does not have any interface.
				 */
				$hostids[$db_item['hostid']][] = $itemid;
			}
		}

		$db_interfaces = [];

		if ($hostids) {
			$host_interfaces = [];

			$db_interfaces = API::HostInterface()->get([
				'output' => ['hostid', 'type', 'main', 'useip', 'ip', 'dns', 'port'],
				'hostids' => array_keys($hostids),
				'filter' => ['main' => INTERFACE_PRIMARY],
				'preservekeys' => true
			]);

			usort($db_interfaces, function ($a, $b) {
				return self::interfacePriorities[$b['type']] <=> self::interfacePriorities[$a['type']];
			});

			/*
			 * Collecting host interfaces:
			 *  - with highest priority for each host
			 *  - with interface IDs contained in the $interfaceids array
			 */
			foreach ($db_interfaces as $interfaceid => $db_interface) {
				if (array_key_exists($db_interface['hostid'], $hostids)) {
					$host_interfaces[$db_interface['hostid']] = $interfaceid;
					unset($hostids[$db_interface['hostid']]);
				}
				elseif (array_key_exists($interfaceid, $interfaceids)) {
					unset($interfaceids[$interfaceid]);
				}
				else {
					unset($db_interfaces[$interfaceid]);
				}
			}
		}

		if ($interfaceids) {
			$db_interfaces += API::HostInterface()->get([
				'output' => ['hostid', 'type', 'main', 'useip', 'ip', 'dns', 'port'],
				'interfaceids' => array_keys($interfaceids),
				'preservekeys' => true
			]);
		}

		$db_interfaces = CMacrosResolverHelper::resolveHostInterfaces($db_interfaces);

		foreach ($db_interfaces as &$db_interface) {
			$db_interface['conn'] = $db_interface['useip'] == INTERFACE_USE_IP
				? $db_interface['ip']
				: $db_interface['dns'];
		}
		unset($host_interface);

		$interface_macros = ['IPADDRESS' => 'ip', 'HOST.IP' => 'ip', 'HOST.DNS' => 'dns', 'HOST.CONN' => 'conn',
			'HOST.PORT' => 'port'
		];

		foreach ($db_items as $itemid => $db_item) {
			if ($db_item['interfaceid'] != 0) {
				$interfaceid = $db_item['interfaceid'];
			}
			elseif (array_key_exists($db_item['hostid'], $host_interfaces)) {
				$interfaceid = $host_interfaces[$db_item['hostid']];
			}
			else {
				continue;
			}

			if (!array_key_exists($interfaceid, $db_interfaces)) {
				continue;
			}

			foreach ($macros[$itemid] as $macro => $tokens) {
				$value = $db_interfaces[$interfaceid][$interface_macros[$macro]];

				foreach ($tokens as $token) {
					$macro_values[$itemid][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Resolve interface macros to the main agent interface.
	 *
	 * @param array  $macros
	 * @param array  $macros[<hostid>]
	 * @param array  $macros[<hostid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<hostid>]
	 * @param string $macro_values[<hostid>][<token>]
	 *
	 * @return array
	 */
	protected static function getMainAgentInterfaceMacrosByHostId(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$db_interfaces = array_column(API::HostInterface()->get([
			'output' => ['hostid', 'useip', 'ip', 'dns'],
			'hostids' => array_keys($macros),
			'filter' => [
				'type' => INTERFACE_TYPE_AGENT,
				'main' => INTERFACE_PRIMARY
			]
		]), null, 'hostid');

		$data = [];

		foreach ($db_interfaces as $hostid => $db_interface) {
			$data[$hostid] = ['ip' => $db_interface['ip'], 'dns' => $db_interface['dns']];
		}

		$data = CMacrosResolver::resolve([
			'config' => 'hostInterfaceIpDnsAgentPrimary',
			'data' => $data
		]);

		foreach ($db_interfaces as $hostid => &$db_interface) {
			$db_interface['ip'] = $data[$hostid]['ip'];
			$db_interface['dns'] = $data[$hostid]['dns'];
			$db_interface['conn'] = ($db_interface['useip'] == INTERFACE_USE_IP)
				? $db_interface['ip']
				: $db_interface['dns'];
		}
		unset($db_interface);

		$interface_macros = ['IPADDRESS' => 'ip', 'HOST.IP' => 'ip', 'HOST.DNS' => 'dns', 'HOST.CONN' => 'conn'];

		foreach ($db_interfaces as $hostid => $db_interface) {
			foreach ($macros[$hostid] as $macro => $tokens) {
				$value = $db_interface[$interface_macros[$macro]];

				foreach ($tokens as $token) {
					$macro_values[$hostid][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Resolve interface macros by interface priority.
	 *
	 * @param array  $macros
	 * @param array  $macros[<hostid>]
	 * @param array  $macros[<hostid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<hostid>]
	 * @param string $macro_values[<hostid>][<token>]
	 *
	 * @return array
	 */
	protected static function getInterfaceMacrosByHostId(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$db_interfaces = API::HostInterface()->get([
			'output' => ['hostid', 'type', 'main', 'useip', 'ip', 'dns', 'port'],
			'hostids' => array_keys($macros),
			'filter' => [
				'main' => INTERFACE_PRIMARY
			]
		]);

		usort($db_interfaces, function ($a, $b) {
			return self::interfacePriorities[$b['type']] <=> self::interfacePriorities[$a['type']];
		});

		$db_interfaces = CMacrosResolverHelper::resolveHostInterfaces($db_interfaces);

		$host_interfaces = [];

		foreach ($db_interfaces as $db_interface) {
			if (!array_key_exists($db_interface['hostid'], $host_interfaces)) {
				$host_interfaces[$db_interface['hostid']] = $db_interface;
			}
		}

		foreach ($host_interfaces as &$host_interface) {
			$host_interface['conn'] = ($host_interface['useip'] == INTERFACE_USE_IP)
				? $host_interface['ip']
				: $host_interface['dns'];
		}
		unset($host_interface);

		$interface_macros = ['IPADDRESS' => 'ip', 'HOST.IP' => 'ip', 'HOST.DNS' => 'dns', 'HOST.CONN' => 'conn',
			'HOST.PORT' => 'port'
		];

		foreach ($host_interfaces as $hostid => $host_interface) {
			foreach ($macros[$hostid] as $macro => $tokens) {
				$value = $host_interface[$interface_macros[$macro]];

				foreach ($tokens as $token) {
					$key = array_key_exists('key', $token) ? $token['key'] : $hostid;
					$macro_values[$key][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Function returns array holding of inventory macros as a keys and corresponding database fields as value.
	 *
	 * @return array
	 */
	protected static function getSupportedHostInventoryMacrosMap(): array {
		return [
			'{INVENTORY.ALIAS}' => 'alias',
			'{INVENTORY.ASSET.TAG}' => 'asset_tag',
			'{INVENTORY.CHASSIS}' => 'chassis',
			'{INVENTORY.CONTACT}' => 'contact',
			'{PROFILE.CONTACT}' => 'contact', // deprecated
			'{INVENTORY.CONTRACT.NUMBER}' => 'contract_number',
			'{INVENTORY.DEPLOYMENT.STATUS}' => 'deployment_status',
			'{INVENTORY.HARDWARE}' => 'hardware',
			'{PROFILE.HARDWARE}' => 'hardware', // deprecated
			'{INVENTORY.HARDWARE.FULL}' => 'hardware_full',
			'{INVENTORY.HOST.NETMASK}' => 'host_netmask',
			'{INVENTORY.HOST.NETWORKS}' => 'host_networks',
			'{INVENTORY.HOST.ROUTER}' => 'host_router',
			'{INVENTORY.HW.ARCH}' => 'hw_arch',
			'{INVENTORY.HW.DATE.DECOMM}' => 'date_hw_decomm',
			'{INVENTORY.HW.DATE.EXPIRY}' => 'date_hw_expiry',
			'{INVENTORY.HW.DATE.INSTALL}' => 'date_hw_install',
			'{INVENTORY.HW.DATE.PURCHASE}' => 'date_hw_purchase',
			'{INVENTORY.INSTALLER.NAME}' => 'installer_name',
			'{INVENTORY.LOCATION}' => 'location',
			'{PROFILE.LOCATION}' => 'location', // deprecated
			'{INVENTORY.LOCATION.LAT}' => 'location_lat',
			'{INVENTORY.LOCATION.LON}' => 'location_lon',
			'{INVENTORY.MACADDRESS.A}' => 'macaddress_a',
			'{PROFILE.MACADDRESS}' => 'macaddress_a', // deprecated
			'{INVENTORY.MACADDRESS.B}' => 'macaddress_b',
			'{INVENTORY.MODEL}' => 'model',
			'{INVENTORY.NAME}' => 'name',
			'{PROFILE.NAME}' => 'name', // deprecated
			'{INVENTORY.NOTES}' => 'notes',
			'{PROFILE.NOTES}' => 'notes', // deprecated
			'{INVENTORY.OOB.IP}' => 'oob_ip',
			'{INVENTORY.OOB.NETMASK}' => 'oob_netmask',
			'{INVENTORY.OOB.ROUTER}' => 'oob_router',
			'{INVENTORY.OS}' => 'os',
			'{PROFILE.OS}' => 'os', // deprecated
			'{INVENTORY.OS.FULL}' => 'os_full',
			'{INVENTORY.OS.SHORT}' => 'os_short',
			'{INVENTORY.POC.PRIMARY.CELL}' => 'poc_1_cell',
			'{INVENTORY.POC.PRIMARY.EMAIL}' => 'poc_1_email',
			'{INVENTORY.POC.PRIMARY.NAME}' => 'poc_1_name',
			'{INVENTORY.POC.PRIMARY.NOTES}' => 'poc_1_notes',
			'{INVENTORY.POC.PRIMARY.PHONE.A}' => 'poc_1_phone_a',
			'{INVENTORY.POC.PRIMARY.PHONE.B}' => 'poc_1_phone_b',
			'{INVENTORY.POC.PRIMARY.SCREEN}' => 'poc_1_screen',
			'{INVENTORY.POC.SECONDARY.CELL}' => 'poc_2_cell',
			'{INVENTORY.POC.SECONDARY.EMAIL}' => 'poc_2_email',
			'{INVENTORY.POC.SECONDARY.NAME}' => 'poc_2_name',
			'{INVENTORY.POC.SECONDARY.NOTES}' => 'poc_2_notes',
			'{INVENTORY.POC.SECONDARY.PHONE.A}' => 'poc_2_phone_a',
			'{INVENTORY.POC.SECONDARY.PHONE.B}' => 'poc_2_phone_b',
			'{INVENTORY.POC.SECONDARY.SCREEN}' => 'poc_2_screen',
			'{INVENTORY.SERIALNO.A}' => 'serialno_a',
			'{PROFILE.SERIALNO}' => 'serialno_a', // deprecated
			'{INVENTORY.SERIALNO.B}' => 'serialno_b',
			'{INVENTORY.SITE.ADDRESS.A}' => 'site_address_a',
			'{INVENTORY.SITE.ADDRESS.B}' => 'site_address_b',
			'{INVENTORY.SITE.ADDRESS.C}' => 'site_address_c',
			'{INVENTORY.SITE.CITY}' => 'site_city',
			'{INVENTORY.SITE.COUNTRY}' => 'site_country',
			'{INVENTORY.SITE.NOTES}' => 'site_notes',
			'{INVENTORY.SITE.RACK}' => 'site_rack',
			'{INVENTORY.SITE.STATE}' => 'site_state',
			'{INVENTORY.SITE.ZIP}' => 'site_zip',
			'{INVENTORY.SOFTWARE}' => 'software',
			'{PROFILE.SOFTWARE}' => 'software', // deprecated
			'{INVENTORY.SOFTWARE.APP.A}' => 'software_app_a',
			'{INVENTORY.SOFTWARE.APP.B}' => 'software_app_b',
			'{INVENTORY.SOFTWARE.APP.C}' => 'software_app_c',
			'{INVENTORY.SOFTWARE.APP.D}' => 'software_app_d',
			'{INVENTORY.SOFTWARE.APP.E}' => 'software_app_e',
			'{INVENTORY.SOFTWARE.FULL}' => 'software_full',
			'{INVENTORY.TAG}' => 'tag',
			'{PROFILE.TAG}' => 'tag', // deprecated
			'{INVENTORY.TYPE}' => 'type',
			'{PROFILE.DEVICETYPE}' => 'type', // deprecated
			'{INVENTORY.TYPE.FULL}' => 'type_full',
			'{INVENTORY.URL.A}' => 'url_a',
			'{INVENTORY.URL.B}' => 'url_b',
			'{INVENTORY.URL.C}' => 'url_c',
			'{INVENTORY.VENDOR}' => 'vendor'
		];
	}

	/**
	 * Get inventory macros.
	 *
	 * @param array  $macros
	 * @param array  $macros[<hostid>]
	 * @param array  $macros[<hostid>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param string $macro_values[<key>][<token>]
	 *
	 * @return array
	 */
	protected static function getInventoryMacrosByHostId(array $macros, array $macro_values): array {
		if (!$macros) {
			return $macro_values;
		}

		$inventory_macros = self::getSupportedHostInventoryMacrosMap();

		$db_hosts = API::Host()->get([
			'output' => ['inventory_mode'],
			'selectInventory' => array_values($inventory_macros),
			'hostids' => array_keys($macros),
			'preservekeys' => true
		]);

		foreach ($db_hosts as $hostid => $db_host) {
			if ($db_host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				continue;
			}

			foreach ($macros[$hostid] as $macro => $tokens) {
				$value = $db_host['inventory'][$inventory_macros['{'.$macro.'}']];

				foreach ($tokens as $token) {
					$key = array_key_exists('key', $token) ? $token['key'] : $hostid;
					$macro_values[$key][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get a list of hosts for each selected trigger, in the order in which they located in the expression.
	 * Returns an array of host IDs by trigger ID.
	 *
	 * @param array $triggerids
	 * @param bool  $get_host_name  Returns host names instead of host IDs.
	 *
	 * @return array
	 */
	protected static function getExpressionHosts(array $triggerids, bool $get_host_name = false): array {
		if (!$triggerids) {
			return [];
		}

		$db_triggers = API::Trigger()->get([
			'output' => ['expression'],
			'selectFunctions' => ['functionid', 'itemid'],
			'selectItems' => ['itemid', 'hostid'],
			'selectHosts' => $get_host_name ? ['hostid', 'host'] : null,
			'triggerids' => $triggerids,
			'preservekeys' => true
		]);

		$trigger_hosts_by_f_num = [];
		$expression_parser = new CExpressionParser(['usermacros' => true, 'collapsed_expression' => true]);

		foreach ($db_triggers as $triggerid => $db_trigger) {
			if ($expression_parser->parse($db_trigger['expression']) != CParser::PARSE_SUCCESS) {
				continue;
			}

			$db_trigger['functions'] = array_column($db_trigger['functions'], 'itemid', 'functionid');
			$db_trigger['items'] = array_column($db_trigger['items'], 'hostid', 'itemid');
			if ($get_host_name) {
				$db_trigger['hosts'] = array_column($db_trigger['hosts'], 'host', 'hostid');
			}
			$tokens = $expression_parser
				->getResult()
				->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO]);

			foreach ($tokens as $f_num => $token) {
				$functionid = substr($token['match'], 1, -1); // strip curly braces
				$itemid = $db_trigger['functions'][$functionid];
				$hostid = $db_trigger['items'][$itemid];
				$value = $get_host_name ? $db_trigger['hosts'][$hostid] : $hostid;

				// Add host reference for macro without numeric index.
				if ($f_num == 0) {
					$trigger_hosts_by_f_num[$triggerid][0] = $value;
				}
				$trigger_hosts_by_f_num[$triggerid][$f_num + 1] = $value;
			}
		}

		return $trigger_hosts_by_f_num;
	}

	/**
	 * Get host macros with references.
	 *
	 * @param array  $macros
	 * @param array  $macros[<triggerid>]
	 * @param array  $macros[<triggerid>][<macro>]
	 * @param array  $macros[<triggerid>][<macro>][<f_num>]
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]['token']
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]['macrofunc']  (optional)
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]'key']
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param string $macro_values[<key>][<token>]
	 * @param array  $trigger_hosts_by_f_num
	 * @param array  $trigger_hosts_by_f_num[<triggerid>]            An array of host IDs.
	 *
	 * @return array
	 */
	protected static function getHostNMacros(array $macros, array $macro_values,
			array $trigger_hosts_by_f_num): array {
		if (!$macros) {
			return $macro_values;
		}

		$hostids = [];

		foreach (array_intersect_key($trigger_hosts_by_f_num, $macros) as $triggerid => $_hostids) {
			$hostids += array_flip($_hostids);
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'name', 'description'],
			'hostids' => array_keys($hostids),
			'preservekeys' => true
		]);

		$host_macros = ['HOST.ID' => 'hostid', 'HOSTNAME' => 'host', 'HOST.HOST' => 'host', 'HOST.NAME' => 'name',
			'HOST.DESCRIPTION' => 'description'
		];

		foreach ($macros as $triggerid => $macro_data) {
			if (!array_key_exists($triggerid, $trigger_hosts_by_f_num)) {
				continue;
			}

			foreach ($macro_data as $macro => $f_num_data) {
				foreach ($f_num_data as $f_num => $tokens) {
					if (!array_key_exists($f_num, $trigger_hosts_by_f_num[$triggerid])) {
						continue;
					}

					$hostid = $trigger_hosts_by_f_num[$triggerid][$f_num];

					if (array_key_exists($hostid, $db_hosts)) {
						$value = $db_hosts[$hostid][$host_macros[$macro]];

						foreach ($tokens as $token) {
							$macro_values[$token['key']][$token['token']] = array_key_exists('macrofunc', $token)
								? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
								: $value;
						}
					}
				}
				unset($value);
			}
		}

		return $macro_values;
	}

	/**
	 * Get interface macros with references.
	 *
	 * @param array  $macros
	 * @param array  $macros[<triggerid>]
	 * @param array  $macros[<triggerid>][<macro>]
	 * @param array  $macros[<triggerid>][<macro>][<f_num>]
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]['token']
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]['macrofunc']  (optional)
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]'key']
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param array  $macro_values[<key>][<macro>]
	 * @param array  $trigger_hosts_by_f_num
	 * @param array  $trigger_hosts_by_f_num[<triggerid>]            An array of host IDs.
	 *
	 * @return array
	 */
	protected static function getInterfaceNMacros(array $macros, array $macro_values,
			array $trigger_hosts_by_f_num): array {
		if (!$macros) {
			return $macro_values;
		}

		$hostids = [];

		foreach (array_intersect_key($trigger_hosts_by_f_num, $macros) as $triggerid => $_hostids) {
			$hostids += array_flip($_hostids);
		}

		$db_interfaces = API::HostInterface()->get([
			'output' => ['hostid', 'type', 'useip', 'ip', 'dns', 'port'],
			'hostids' => array_keys($hostids),
			'filter' => ['main' => INTERFACE_PRIMARY]
		]);

		usort($db_interfaces, function ($a, $b) {
			return self::interfacePriorities[$b['type']] <=> self::interfacePriorities[$a['type']];
		});

		$host_interfaces = [];

		foreach ($db_interfaces as $db_interface) {
			if (!array_key_exists($db_interface['hostid'], $host_interfaces)) {
				$host_interfaces[$db_interface['hostid']] = $db_interface;
			}
		}

		foreach ($host_interfaces as &$host_interface) {
			$host_interface['conn'] = ($host_interface['useip'] == INTERFACE_USE_IP)
				? $host_interface['ip']
				: $host_interface['dns'];
		}
		unset($host_interface);

		$interface_macros = ['IPADDRESS' => 'ip', 'HOST.IP' => 'ip', 'HOST.DNS' => 'dns', 'HOST.CONN' => 'conn',
			'HOST.PORT' => 'port'
		];

		foreach ($macros as $triggerid => $macro_data) {
			if (!array_key_exists($triggerid, $trigger_hosts_by_f_num)) {
				continue;
			}

			foreach ($macro_data as $macro => $f_num_data) {
				foreach ($f_num_data as $f_num => $tokens) {
					if (!array_key_exists($f_num, $trigger_hosts_by_f_num[$triggerid])) {
						continue;
					}

					$hostid = $trigger_hosts_by_f_num[$triggerid][$f_num];

					if (array_key_exists($hostid, $host_interfaces)) {
						$value = $host_interfaces[$hostid][$interface_macros[$macro]];

						foreach ($tokens as $token) {
							$macro_values[$token['key']][$token['token']] = array_key_exists('macrofunc', $token)
								? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
								: $value;
						}
					}
				}
				unset($value);
			}
		}

		return $macro_values;
	}

	/**
	 * Get inventory macros with references.
	 *
	 * @param array  $macros
	 * @param array  $macros[<triggerid>]
	 * @param array  $macros[<triggerid>][<macro>]
	 * @param array  $macros[<triggerid>][<macro>][<f_num>]
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]['token']
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]['macrofunc']  (optional)
	 * @param string $macros[<triggerid>][<macro>][<f_num>][]'key']
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param array  $macro_values[<key>][<macro>]
	 * @param array  $trigger_hosts_by_f_num
	 * @param array  $trigger_hosts_by_f_num[<triggerid>]            An array of host IDs.
	 *
	 * @return array
	 */
	protected static function getInventoryNMacros(array $macros, array $macro_values,
			array $trigger_hosts_by_f_num): array {
		if (!$macros) {
			return $macro_values;
		}

		$hostids = [];

		foreach (array_intersect_key($trigger_hosts_by_f_num, $macros) as $triggerid => $_hostids) {
			$hostids += array_flip($_hostids);
		}

		$inventory_macros = self::getSupportedHostInventoryMacrosMap();

		$db_hosts = API::Host()->get([
			'output' => ['inventory_mode'],
			'selectInventory' => array_values($inventory_macros),
			'hostids' => array_keys($hostids),
			'preservekeys' => true
		]);

		foreach ($macros as $triggerid => $macro_data) {
			if (!array_key_exists($triggerid, $trigger_hosts_by_f_num)) {
				continue;
			}

			foreach ($macro_data as $macro => $f_num_data) {
				foreach ($f_num_data as $f_num => $tokens) {
					if (!array_key_exists($f_num, $trigger_hosts_by_f_num[$triggerid])) {
						continue;
					}

					$hostid = $trigger_hosts_by_f_num[$triggerid][$f_num];

					if (array_key_exists($hostid, $db_hosts)
							&& $db_hosts[$hostid]['inventory_mode'] != HOST_INVENTORY_DISABLED) {
						$value = $db_hosts[$hostid]['inventory'][$inventory_macros['{'.$macro.'}']];

						foreach ($tokens as $token) {
							$macro_values[$token['key']][$token['token']] = array_key_exists('macrofunc', $token)
								? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
								: $value;
						}
					}
				}
				unset($value);
			}
		}

		return $macro_values;
	}

	/**
	 * Get expression macros with and without {HOST.HOST<1-9>} references.
	 *
	 * @param array  $expr_macros_host_n
	 * @param array  $expr_macros_host_n[<triggerid>]
	 * @param array  $expr_macros_host_n[<triggerid>][<key>]
	 * @param array  $expr_macros_host_n[<triggerid>][<key>][<macro>]
	 * @param string $expr_macros_host_n[<triggerid>][<key>][<macro>]['host']
	 * @param array  $expr_macros_host
	 * @param array  $expr_macros_host[<hostid>]
	 * @param array  $expr_macros_host[<hostid>][<key>]
	 * @param array  $expr_macros_host[<hostid>][<key>][<macro>]
	 * @param string $expr_macros_host[<hostid>][<key>][<macro>]['host']
	 * @param array  $expr_macros
	 * @param array  $expr_macros[<macro>]
	 * @param string $expr_macros[<macro>]['host']
	 * @param array  $expr_macros[<macro>]['links']
	 * @param array  $expr_macros[<macro>]['links'][<macro>]         An array of keys.
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param array  $macro_values[<key>][<macro>]
	 * @param array  $trigger_hosts_by_f_num
	 * @param array  $trigger_hosts_by_f_num[<triggerid>]            An array of host IDs.
	 *
	 * @return array
	 */
	protected static function getExpressionNMacros(array $expr_macros_host_n, array $expr_macros_host,
			array $expr_macros, array $macro_values): array {
		if (!$expr_macros_host_n && !$expr_macros_host && !$expr_macros) {
			return $macro_values;
		}

		$trigger_hosts_by_f_num = self::getExpressionHosts(array_keys($expr_macros_host_n), true);
		$macro_parser = new CMacroParser(['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC]);

		foreach ($expr_macros_host_n as $triggerid => $keys) {
			if (!array_key_exists($triggerid, $trigger_hosts_by_f_num)) {
				continue;
			}

			foreach ($keys as $key => $_macros) {
				foreach ($_macros as $_macro => $data) {
					if ($data['host'] === '') {
						$reference = 0;
						$pattern = '#//#';
					}
					else {
						$macro_parser->parse($data['host']);
						$reference = $macro_parser->getReference();
						$pattern = '#/\{HOST\.HOST[1-9]?\}/#';
					}

					if (!array_key_exists($reference, $trigger_hosts_by_f_num[$triggerid])) {
						continue;
					}

					$host = $trigger_hosts_by_f_num[$triggerid][$reference];

					// Replace {HOST.HOST<1-9>} macro with real host name.
					$macro = preg_replace($pattern, '/'.$host.'/', $_macro, 1);

					if (!array_key_exists($macro, $expr_macros)) {
						$expr_macros[$macro] = ['host' => $host] + $data;
					}
					$expr_macros[$macro]['links'][$_macro][] = $key;
				}
			}
		}

		$db_hosts = $expr_macros_host
			? API::Host()->get([
				'output' => ['host'],
				'hostids' => array_keys($expr_macros_host),
				'preservekeys' => true
			])
			: [];

		foreach ($expr_macros_host as $hostid => $keys) {
			if (!array_key_exists($hostid, $db_hosts)) {
				continue;
			}

			foreach ($keys as $key => $_macros) {
				foreach ($_macros as $_macro => $data) {
					// Replace {HOST.HOST} macro with real host name.
					$pattern = $data['host'] === '' ? '#//#' : '#/\{HOST\.HOST\}/#';
					$macro = preg_replace($pattern, '/'.$db_hosts[$hostid]['host'].'/', $_macro, 1);

					if (!array_key_exists($macro, $expr_macros)) {
						$expr_macros[$macro] = ['host' => $db_hosts[$hostid]['host']] + $data;
					}
					$expr_macros[$macro]['links'][$_macro][] = $key;
				}
			}
		}

		$expr_macro_values = self::getExpressionMacros($expr_macros, []);

		foreach ($expr_macros as $macro => $expr_macro) {
			if (!array_key_exists($macro, $expr_macro_values)) {
				continue;
			}

			foreach ($expr_macro['links'] as $_macro => $keys) {
				foreach ($keys as $key) {
					$macro_values[$key][$_macro] = $expr_macro_values[$macro];
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Get macros with values.
	 *
	 * @param array  $usermacros
	 * @param array  $usermacros[n]['hostids']                       The list of host ids; [<hostid1>, ...].
	 * @param array  $usermacros[n]['macros']                        The list of user macros to resolve.
	 * @param array  $usermacros[n]['macros'][<token>]
	 * @param string $usermacros[n]['macros'][<token>]['macro']
	 * @param string $usermacros[n]['macros'][<token>]['context']
	 * @param array  $usermacros[n]['macros'][<token>]['macrofunc']  (optional)
	 * @param array  $macro_values
	 * @param array  $macro_values[<key>]
	 * @param array  $macro_values[<key>][<macro>]
	 * @param bool   $unset_undefined     Unset undefined macros.
	 *
	 * @return array
	 */
	protected static function getUserMacros(array $usermacros, array $macro_values, bool $unset_undefined = false) {
		if (!$usermacros) {
			return $macro_values;
		}

		// User macros.
		$hostids = [];
		foreach ($usermacros as $usermacros_data) {
			$hostids += array_flip($usermacros_data['hostids']);
		}

		$user_macro_parser_with_regex = new CUserMacroParser(['allow_regex' => true]);
		$user_macro_parser = new CUserMacroParser();

		/*
		 * @var array $host_templates
		 * @var array $host_templates[<hostid>]		array of templates
		 */
		$host_templates = [];

		/*
		 * @var array  $host_macros
		 * @var array  $host_macros[<hostid>]
		 * @var array  $host_macros[<hostid>][<macro>]				macro base without curly braces
		 * @var string $host_macros[<hostid>][<macro>]['value']		base macro value (without context and regex);
		 * 															can be null
		 * @var array  $host_macros[<hostid>][<macro>]['contexts']	context values; ['<context1>' => '<value1>', ...]
		 * @var array  $host_macros[<hostid>][<macro>]['regex']		regex values; ['<regex1>' => '<value1>', ...]
		 */
		$host_macros = [];

		if ($hostids) {
			do {
				$hostids = array_keys($hostids);

				$db_host_macros = API::UserMacro()->get([
					'output' => ['macro', 'value', 'type', 'hostid'],
					'hostids' => $hostids
				]);

				foreach ($db_host_macros as $db_host_macro) {
					if ($user_macro_parser_with_regex->parse($db_host_macro['macro']) != CParser::PARSE_SUCCESS) {
						continue;
					}

					$hostid = $db_host_macro['hostid'];
					$macro = $user_macro_parser_with_regex->getMacro();
					$context = $user_macro_parser_with_regex->getContext();
					$regex = $user_macro_parser_with_regex->getRegex();
					$value = self::getMacroValue($db_host_macro);

					if (!array_key_exists($hostid, $host_macros) || !array_key_exists($macro, $host_macros[$hostid])) {
						$host_macros[$hostid][$macro] = ['value' => null, 'contexts' => [], 'regex' => []];
					}

					if ($context === null && $regex === null) {
						$host_macros[$hostid][$macro]['value'] = $value;
					}
					elseif ($regex !== null) {
						$host_macros[$hostid][$macro]['regex'][$regex] = $value;
					}
					else {
						$host_macros[$hostid][$macro]['contexts'][$context] = $value;
					}
				}

				foreach ($hostids as $hostid) {
					$host_templates[$hostid] = [];
				}

				$templateids = [];
				$db_host_templates = DBselect(
					'SELECT ht.hostid,ht.templateid'.
					' FROM hosts_templates ht'.
					' WHERE '.dbConditionInt('ht.hostid', $hostids)
				);
				while ($db_host_template = DBfetch($db_host_templates)) {
					$host_templates[$db_host_template['hostid']][] = $db_host_template['templateid'];
					$templateids[$db_host_template['templateid']] = true;
				}

				// only unprocessed templates will be populated
				$hostids = [];
				foreach (array_keys($templateids) as $templateid) {
					if (!array_key_exists($templateid, $host_templates)) {
						$hostids[$templateid] = true;
					}
				}
			} while ($hostids);
		}

		// Reordering only regex array.
		$host_macros = self::sortRegexHostMacros($host_macros);

		$all_macros_resolved = true;
		foreach ($usermacros as &$usermacros_data) {
			$hostids = array_unique($usermacros_data['hostids']);
			natsort($hostids);

			foreach ($usermacros_data['macros'] as $usermacro => &$data) {
				$data['value'] = self::getHostUserMacros($hostids, $data['macro'], $data['context'], $host_templates,
					$host_macros
				);

				if ($data['value']['value'] === null) {
					$all_macros_resolved = false;
				}
			}
			unset($data);
		}
		unset($usermacros_data);

		if (!$all_macros_resolved) {
			// Global macros.
			$db_global_macros = API::UserMacro()->get([
				'output' => ['macro', 'value', 'type'],
				'globalmacro' => true
			]);

			/*
			 * @var array  $global_macros
			 * @var array  $global_macros[<macro>]				macro base without curly braces
			 * @var string $global_macros[<macro>]['value']		base macro value (without context and regex);
			 * 													can be null
			 * @var array  $global_macros[<macro>]['contexts']	context values; ['<context1>' => '<value1>', ...]
			 * @var array  $global_macros[<macro>]['regex']		regex values; ['<regex1>' => '<value1>', ...]
			 */
			$global_macros = [];

			foreach ($db_global_macros as $db_global_macro) {
				if ($user_macro_parser_with_regex->parse($db_global_macro['macro']) == CParser::PARSE_SUCCESS) {
					$macro = $user_macro_parser_with_regex->getMacro();
					$context = $user_macro_parser_with_regex->getContext();
					$regex = $user_macro_parser_with_regex->getRegex();
					$value = self::getMacroValue($db_global_macro);

					if (!array_key_exists($macro, $global_macros)) {
						$global_macros[$macro] = ['value' => null, 'contexts' => [], 'regex' => []];
					}

					if ($context === null && $regex === null) {
						$global_macros[$macro]['value'] = $value;
					}
					elseif ($regex !== null) {
						$global_macros[$macro]['regex'][$regex] = $value;
					}
					else {
						$global_macros[$macro]['contexts'][$context] = $value;
					}
				}
			}

			// Reordering only regex array.
			$global_macros = self::sortRegexGlobalMacros($global_macros);

			foreach ($usermacros as &$usermacros_data) {
				foreach ($usermacros_data['macros'] as $usermacro => &$data) {
					if ($data['value']['value'] === null) {
						if (array_key_exists($data['macro'], $global_macros)) {
							if ($data['context'] !== null
									&& array_key_exists($data['context'], $global_macros[$data['macro']]['contexts'])) {
								$data['value']['value'] = $global_macros[$data['macro']]['contexts'][$data['context']];
							}
							elseif ($data['context'] !== null && count($global_macros[$data['macro']]['regex'])) {
								foreach ($global_macros[$data['macro']]['regex'] as $regex => $val) {
									if (preg_match('/'.self::handleSlashEscaping($regex).'/', $data['context'])) {
										$data['value']['value'] = $val;
										break;
									}
								}
							}

							if ($data['value']['value'] === null && $global_macros[$data['macro']]['value'] !== null) {
								if ($data['context'] === null) {
									$data['value']['value'] = $global_macros[$data['macro']]['value'];
								}
								elseif ($data['value']['value_default'] === null) {
									$data['value']['value_default'] = $global_macros[$data['macro']]['value'];
								}
							}
						}
					}
				}
				unset($data);
			}
			unset($usermacros_data);
		}

		foreach ($usermacros as $key => $usermacros_data) {
			foreach ($usermacros_data['macros'] as $usermacro => $data) {
				if ($data['value']['value'] !== null) {
					$usermacros[$key]['macros'][$usermacro] = array_key_exists('macrofunc', $data)
						? CMacroFunction::calcMacrofunc($data['value']['value'], $data['macrofunc'])
						: $data['value']['value'];
				}
				elseif ($data['value']['value_default'] !== null) {
					$usermacros[$key]['macros'][$usermacro] = array_key_exists('macrofunc', $data)
						? CMacroFunction::calcMacrofunc($data['value']['value_default'], $data['macrofunc'])
						: $data['value']['value_default'];
				}
				// Unresolved macro.
				elseif ($unset_undefined) {
					unset($usermacros[$key]['macros'][$usermacro]);
				}
				else {
					$usermacros[$key]['macros'][$usermacro] = $usermacro;
				}
			}
		}

		foreach ($usermacros as $key => $usermacros_data) {
			$macro_values[$key] = array_key_exists($key, $macro_values)
				? array_merge($macro_values[$key], $usermacros_data['macros'])
				: $usermacros_data['macros'];
		}

		return $macro_values;
	}

	/**
	 * Get user macro from the requested hosts.
	 *
	 * Use the base value returned by host macro as default value when expanding expand global macro. This will ensure
	 * the following user macro resolving priority:
	 *  1) host/template context macro
	 *  2) global context macro
	 *  3) host/template base (default) macro
	 *  4) global base (default) macro
	 *
	 * @param array  $hostids			The sorted list of hosts where macros will be looked for (hostid => hostid)
	 * @param string $macro				Macro base without curly braces, for example: SNMP_COMMUNITY
	 * @param string $context			Macro context to resolve
	 * @param array  $host_templates	The list of linked templates (see getUserMacros() for more details)
	 * @param array  $host_macros		The list of macros on hosts (see getUserMacros() for more details)
	 * @param string $value_default		Value
	 *
	 * @return array
	 */
	private static function getHostUserMacros(array $hostids, $macro, $context, array $host_templates, array $host_macros,
			$value_default = null) {
		foreach ($hostids as $hostid) {
			if (array_key_exists($hostid, $host_macros) && array_key_exists($macro, $host_macros[$hostid])) {
				// Searching context coincidence with macro contexts.
				if ($context !== null && array_key_exists($context, $host_macros[$hostid][$macro]['contexts'])) {
					return [
						'value' => $host_macros[$hostid][$macro]['contexts'][$context],
						'value_default' => $value_default
					];
				}
				// Searching context coincidence, if regex array not empty.
				elseif ($context !== null && count($host_macros[$hostid][$macro]['regex'])) {
					foreach ($host_macros[$hostid][$macro]['regex'] as $regex => $val) {
						if (preg_match('/'.self::handleSlashEscaping($regex).'/', $context) === 1) {
							return [
								'value' => $val,
								'value_default' => $value_default
							];
						}
					}
				}

				if ($host_macros[$hostid][$macro]['value'] !== null) {
					if ($context === null) {
						return ['value' => $host_macros[$hostid][$macro]['value'], 'value_default' => $value_default];
					}
					elseif ($value_default === null) {
						$value_default = $host_macros[$hostid][$macro]['value'];
					}
				}
			}
		}

		if (!$host_templates) {
			return ['value' => null, 'value_default' => $value_default];
		}

		$templateids = [];

		foreach ($hostids as $hostid) {
			if (array_key_exists($hostid, $host_templates)) {
				foreach ($host_templates[$hostid] as $templateid) {
					$templateids[$templateid] = true;
				}
			}
		}

		if ($templateids) {
			$templateids = array_keys($templateids);
			natsort($templateids);

			return self::getHostUserMacros($templateids, $macro, $context, $host_templates, $host_macros,
				$value_default
			);
		}

		return ['value' => null, 'value_default' => $value_default];
	}

	/**
	 * Get and resolve user data macros like name, surname, username. Input array contains a collection of prepared
	 * and unresolved macros. Get data from API service, because direct requests to API do no have CWebUser data.
	 *
	 * Example input:
	 *     array (
	 *         0 => array (
	 *             '{USER.FULLNAME}' => '*UNKNOWN*',
	 *         ),
	 *         1 => array (
	 *             '{USER.NAME}' => '*UNKNOWN*',
	 *             '{USER.SURNAME}' => '*UNKNOWN*',
	 *         )
	 *     )
	 *
	 * Output:
	 *     array (
	 *         0 => array (
	 *             '{USER.FULLNAME}' => 'Zabbix Administrator',
	 *         ),
	 *         1 => array (
	 *             '{USER.NAME}' => 'Zabbix',
	 *             '{USER.SURNAME}' => 'Administrator',
	 *         )
	 *     )
	 *
	 * @param array  $macros
	 * @param array  $macros[<n>]
	 * @param array  $macros[<n>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<n>]
	 * @param string $macro_values[<n>][<token>]
	 *
	 * @return array
	 */
	protected static function getUserDataMacros(array $macros, array $macro_values): array {
		foreach ($macros as $n => $macro_data) {
			foreach ($macro_data as $macro => $tokens) {
				switch ($macro) {
					case 'USER.ALIAS': // Deprecated in version 5.4.
					case 'USER.USERNAME':
						$value = CApiService::$userData['username'];
						break;

					case 'USER.FULLNAME':
						$fullname = [];

						foreach (['name', 'surname'] as $field) {
							if (CApiService::$userData[$field] !== '') {
								$fullname[] = CApiService::$userData[$field];
							}
						}

						$value = $fullname
							? implode(' ', array_merge($fullname, ['('.CApiService::$userData['username'].')']))
							: CApiService::$userData['username'];
						break;

					case 'USER.NAME':
						$value = CApiService::$userData['name'];
						break;

					case 'USER.SURNAME':
						$value = CApiService::$userData['surname'];
						break;
				}

				foreach ($tokens as $token) {
					$macro_values[$n][$token['token']] = array_key_exists('macrofunc', $token)
						? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
						: $value;
				}
			}
		}

		return $macro_values;
	}

	/**
	 * Escape slashes in the regular expression based on preceding backslashes.
	 *
	 * @param string $regex
	 *
	 * @return string
	 */
	private static function handleSlashEscaping(string $regex): string {
		$formatted_regex = '';
		$backslash_count = 0;

		for ($p = 0; isset($regex[$p]); $p++) {
			if ($regex[$p] === '\\') {
				$backslash_count++;
			}
			else {
				if ($regex[$p] === '/' && $backslash_count % 2 == 0) {
					$formatted_regex .= '\\';
				}
				$backslash_count = 0;
			}

			$formatted_regex .= $regex[$p];
		}

		return $formatted_regex;
	}

	/**
	 * Get macro value refer by type.
	 *
	 * @param array $macro
	 *
	 * @return string
	 */
	public static function getMacroValue(array $macro): string {
		return ($macro['type'] == ZBX_MACRO_TYPE_SECRET || $macro['type'] == ZBX_MACRO_TYPE_VAULT)
			? ZBX_SECRET_MASK
			: $macro['value'];
	}

	/**
	 * Sorting host macros.
	 *
	 * @param array $host_macros
	 *
	 * @return array
	 */
	private static function sortRegexHostMacros(array $host_macros): array {
		foreach ($host_macros as &$macros) {
			foreach ($macros as &$value) {
				$value['regex'] = self::sortRegex($value['regex']);
			}
			unset($value);
		}
		unset($macros);

		return $host_macros;
	}

	/**
	 * Sorting global macros.
	 *
	 * @param array $global_macros
	 *
	 * @return array
	 */
	private static function sortRegexGlobalMacros(array $global_macros): array {
		foreach ($global_macros as &$value) {
			$value['regex'] = self::sortRegex($value['regex']);
		}
		unset($value);

		return $global_macros;
	}

	/**
	 * Sort regex.
	 *
	 * @param array $macros
	 *
	 * @return array
	 */
	private static function sortRegex(array $macros): array {
		$keys = array_keys($macros);

		usort($keys, 'strcmp');

		$new_array = [];

		foreach($keys as $key) {
			$new_array[$key] = $macros[$key];
		}

		return $new_array;
	}

	/**
	 * Get manualinput macros.
	 *
	 * @param array  $macros
	 * @param array  $macros[<id>]
	 * @param array  $macros[<id>][<macro>]
	 * @param array  $macro_values
	 * @param array  $macro_values[<id>]
	 * @param string $macro_values[<id>][<token>]
	 * @param array  $manualinput_values
	 * @param string $manualinput_values[<id>]
	 *
	 * @return array
	 */
	protected static function getManualInputMacros(array $macros, array $macro_values,
			array $manualinput_values): array {
		foreach ($macros as $id => $macro_tokens) {
			if (array_key_exists($id, $manualinput_values)) {
				$value = $manualinput_values[$id];

				foreach ($macro_tokens as $macro => $tokens) {
					foreach ($tokens as $token) {
						$macro_values[$id][$token['token']] = array_key_exists('macrofunc', $token)
							? CMacroFunction::calcMacrofunc($value, $token['macrofunc'])
							: $value;
					}
				}
			}
		}

		return $macro_values;
	}
}
