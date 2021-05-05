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


class CControllerPopupTriggerExpr extends CController {
	private $metrics = [];
	private $param1SecCount = [];
	private $param1Period = [];
	private $param1Sec = [];
	private $param1Str = [];
	private $param2SecCount = [];
	private $param2SecMode = [];
	private $param3SecVal = [];
	private $param_find = [];
	private $param3SecPercent = [];
	private $paramSecIntCount = [];
	private $paramForecast = [];
	private $paramTimeleft = [];
	private $allowedTypesAny = [];
	private $allowedTypesNumeric = [];
	private $allowedTypesStr = [];
	private $allowedTypesLog = [];
	private $allowedTypesInt = [];
	private $functions = [];
	private $operators = [];
	private $period_optional = [];

	protected function init() {
		$this->disableSIDvalidation();

		$this->metrics = [
			PARAM_TYPE_TIME => _('Time'),
			PARAM_TYPE_COUNTS => _('Count')
		];

		/*
		 * C - caption
		 * T - type
		 * M - metrics
		 * A - asterisk
		 */
		$this->param1SecCount = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			]
		];

		$this->period_optional = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => false
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			]
		];

		$this->param1Period = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'period_shift' => [
				'C' => _('Period shift'),
				'T' => T_ZBX_INT,
				'A' => true
			]
		];

		$this->param1Sec = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			]
		];

		$this->param1Str = [
			'pattern' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param2SecCount = [
			'pattern' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => false
			]
		];

		$this->param2SecMode = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'mode' => [
				'C' => 'Mode',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param3SecVal = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'o' => [
				'C' => 'O',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'v' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param_find = [
			'o' => [
				'C' => 'O',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'v' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->param3SecPercent = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'p' => [
				'C' => _('Percentage').' (P)',
				'T' => T_ZBX_DBL,
				'A' => true
			]
		];

		$this->paramSecIntCount = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'mask' => [
				'C' => _('Mask'),
				'T' => T_ZBX_STR,
				'A' => true
			]
		];

		$this->paramForecast = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			'time' => [
				'C' => _('Time').' (t)',
				'T' => T_ZBX_INT,
				'A' => true
			],
			'fit' => [
				'C' => _('Fit'),
				'T' => T_ZBX_STR,
				'A' => false
			],
			'mode' => [
				'C' => _('Mode'),
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->paramTimeleft = [
			'last' => [
				'C' => _('Last of').' (T)',
				'T' => T_ZBX_INT,
				'M' => $this->metrics,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
			],
			't' => [
				'C' => _('Threshold'),
				'T' => T_ZBX_DBL,
				'A' => true
			],
			'fit' => [
				'C' => _('Fit'),
				'T' => T_ZBX_STR,
				'A' => false
			]
		];

		$this->allowedTypesAny = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_STR => 1,
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_UINT64 => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		$this->allowedTypesNumeric = [
			ITEM_VALUE_TYPE_FLOAT => 1,
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		$this->allowedTypesStr = [
			ITEM_VALUE_TYPE_STR => 1,
			ITEM_VALUE_TYPE_LOG => 1,
			ITEM_VALUE_TYPE_TEXT => 1
		];

		$this->allowedTypesLog = [
			ITEM_VALUE_TYPE_LOG => 1
		];

		$this->allowedTypesInt = [
			ITEM_VALUE_TYPE_UINT64 => 1
		];

		$this->functions = [
			'abs' => [
				'description' => _('abs() - Absolute value'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'avg' => [
				'description' => _('avg() - Average value of a period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'change' => [
				'description' => _('change() - Difference between last and previous value'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'count' => [
				'description' => _('count() - Number of successfully retrieved values V (which fulfill operator O) for period T'),
				'params' => $this->param3SecVal,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'find' => [
				'description' => _('find() - Check occurrence of pattern V (which fulfill operator O) for period T (1 - match, 0 - no match)'),
				'params' => $this->period_optional + $this->param_find,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>']
			],
			'last' => [
				'description' => _('last() - Last (most recent) T value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'length' => [
				'description' => _('length() - Length of last (most recent) T value in characters'),
				'allowed_types' => $this->allowedTypesStr,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'max' => [
				'description' => _('max() - Maximum value for period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'min' => [
				'description' => _('min() - Minimum value for period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'percentile' => [
				'description' => _('percentile() - Percentile P of a period T'),
				'params' => $this->param3SecPercent,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'sum' => [
				'description' => _('sum() - Sum of values of a period T'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'date' => [
				'description' => _('date() - Current date'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'dayofweek' => [
				'description' => _('dayofweek() - Day of week'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'dayofmonth' => [
				'description' => _('dayofmonth() - Day of month'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'fuzzytime' => [
				'description' => _('fuzzytime() - Difference between item value (as timestamp) and Zabbix server timestamp is less than or equal to T seconds (1 - true, 0 - false)'),
				'params' => $this->param1Sec,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>']
			],
			'logeventid' => [
				'description' => _('logeventid() - Event ID of last log entry matching regular expression V for period T (1 - match, 0 - no match)'),
				'params' => $this->period_optional + $this->param1Str,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>']
			],
			'logseverity' => [
				'description' => _('logseverity() - Log severity of the last log entry for period T'),
				'params' => $this->period_optional,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'logsource' => [
				'description' => _('logsource() - Log source of the last log entry matching parameter V for period T (1 - match, 0 - no match)'),
				'params' => $this->period_optional + $this->param1Str,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>']
			],
			'now' => [
				'description' => _('now() - Number of seconds since the Epoch'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'time' => [
				'description' => _('time() - Current time'),
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'nodata' => [
				'description' => _('nodata() - No data received during period of time T (1 - true, 0 - false), Mode (strict - ignore proxy time delay in sending data)'),
				'params' => $this->param2SecMode,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>']
			],
			'bitand' => [
				'description' => _('bitand() - Bitwise AND of last (most recent) T value and mask'),
				'params' => $this->paramSecIntCount,
				'allowed_types' => $this->allowedTypesInt,
				'operators' => ['=', '<>']
			],
			'forecast' => [
				'description' => _('forecast() - Forecast for next t seconds based on period T'),
				'params' => $this->paramForecast,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'timeleft' => [
				'description' => _('timeleft() - Time to reach threshold estimated based on period T'),
				'params' => $this->paramTimeleft,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'trendavg' => [
				'description' => _('trendavg() - Average value of a period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'trendcount' => [
				'description' => _('trendcount() - Number of successfully retrieved values V (which fulfill operator O) for period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesAny,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'trendmax' => [
				'description' => _('trendmax() - Maximum value for period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'trendmin' => [
				'description' => _('trendmin() - Minimum value for period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'trendsum' => [
				'description' => _('trendsum() - Sum of values of a period T with exact period shift'),
				'params' => $this->param1Period,
				'allowed_types' => $this->allowedTypesNumeric,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			]
		];

		CArrayHelper::sort($this->functions, ['description']);

		foreach ($this->functions as $function) {
			foreach ($function['operators'] as $operator) {
				$this->operators[$operator] = true;
			}
		}
	}

	protected function checkInput() {
		$fields = [
			'dstfrm' =>				'string|fatal',
			'dstfld1' =>			'string|not_empty',
			'expression' =>			'string',
			'itemid' =>				'db items.itemid',
			'parent_discoveryid' =>	'db items.itemid',
			'function' =>			'in '.implode(',', array_keys($this->functions)),
			'operator' =>			'in '.implode(',', array_keys($this->operators)),
			'params' =>				'',
			'paramtype' =>			'in '.implode(',', [PARAM_TYPE_TIME, PARAM_TYPE_COUNTS]),
			'value' =>				'string|not_empty',
			'hostid' =>				'db hosts.hostid',
			'groupid' =>			'db hosts_groups.hostgroupid',
			'add' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			if ($this->hasInput('add')) {
				$this->setResponse(
					(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
				);
			}
			else {
				$ret = true;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$expression_parser = new CExpressionParser(['lldmacros' => true]);

		$itemid = $this->getInput('itemid', 0);
		$function = $this->getInput('function', 'last');
		$operator = $this->getInput('operator', '=');
		$param_type = $this->getInput('paramtype', PARAM_TYPE_TIME);
		$dstfld1 = $this->getInput('dstfld1');
		$expression = $this->getInput('expression', '');
		$params = $this->getInput('params', []);
		$value = $this->getInput('value', 0);

		$item = false;

		// Opening the popup when editing an expression in the trigger constructor.
		if (($dstfld1 === 'expr_temp' || $dstfld1 === 'recovery_expr_temp') && $expression !== '') {
			$expression = utf8RawUrlDecode($expression);

			if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
				$math_function_token = null;
				$hist_function_token = null;
				$function_token_index = null;
				$tokens = $expression_parser->getResult()->getTokens();

				foreach ($tokens as $index => $token) {
					switch ($token['type']) {
						case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
							$math_function_token = $token;
							$function_token_index = $index;

							foreach ($token['data']['parameters'] as $parameter) {
								foreach ($parameter['data']['tokens'] as $parameter_token) {
									if ($parameter_token['type'] == CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION) {
										$hist_function_token = $parameter_token;
										break 2;
									}
								}
							}
							break 2;

						case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
							$hist_function_token = $token;
							$function_token_index = $index;
							break 2;
					}
				}

				if ($function_token_index !== null) {
					/*
					 * Try to find an operator and a value.
					 * The value and operator can be extracted only if they immediately follow the function.
					 */
					$index = $function_token_index + 1;

					if (array_key_exists($index, $tokens)
							&& $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_OPERATOR
							&& in_array($tokens[$index]['match'], ['=', '<>', '>', '<', '>=', '<='])) {
						$operator = $tokens[$index]['match'];
						$index++;

						if (array_key_exists($index, $tokens)) {
							if ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_NUMBER
									|| $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_MACRO
									|| $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_USER_MACRO
									|| $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_LLD_MACRO) {
								$value = $tokens[$index]['match'];
							}
							elseif ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_STRING) {
								$value = CExpressionParser::unquoteString($tokens[$index]['match']);
							}
							elseif ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_OPERATOR
									&& array_key_exists($index + 1, $tokens)
									&& $tokens[$index + 1]['type'] == CExpressionParserResult::TOKEN_TYPE_NUMBER) {
								$value = '-'.$tokens[$index + 1]['match'];
							}
						}
					}

					// Get function parameters.
					$parameters = null;

					if ($math_function_token) {
						$function = $math_function_token['data']['function'];

						if ($hist_function_token && $hist_function_token['data']['function'] === 'last') {
							$parameters = $hist_function_token['data']['parameters'];
						}
					}
					else {
						$function = $hist_function_token['data']['function'];
						$parameters = $hist_function_token['data']['parameters'];
					}

					if ($parameters !== null) {
						$host = $hist_function_token['data']['parameters'][0]['data']['host'];
						$key = $hist_function_token['data']['parameters'][0]['data']['item'];

						$items = API::Item()->get([
							'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
							'selectHosts' => ['name'],
							'webitems' => true,
							'filter' => [
								'host' => $host,
								'key_' => $key
							]
						]);

						if (!$items) {
							$items = API::ItemPrototype()->get([
								'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
								'selectHosts' => ['name'],
								'filter' => [
									'host' => $host,
									'key_' => $key
								]
							]);
						}

						if (($item = reset($items)) === false) {
							error(_('Unknown host item, no such item in selected host'));
						}
					}

					$params = [];

					if ($parameters !== null && array_key_exists(1, $parameters)) {
						if ($function === "nodata" || $function === "fuzzytime") {
							$params[] = ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED)
								? CHistFunctionParser::unquoteParam($parameters[1]['match'])
								: $parameters[1]['match'];
						}
						else {
							if ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_PERIOD) {
								$sec_num = $parameters[1]['data']['sec_num'];
								if ($sec_num !== '' && $sec_num[0] === '#') {
									$params[] = substr($sec_num, 1);
									$param_type = PARAM_TYPE_COUNTS;
								}
								else {
									$params[] = $sec_num;
									$param_type = PARAM_TYPE_TIME;
								}
								$params[] = $parameters[1]['data']['time_shift'];
							}
							else {
								$params[] = '';
								$params[] = '';
							}
						}

						for ($i = 2; $i < count($parameters); $i++) {
							$parameter = $parameters[$i];
							$params[] = $parameter['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED
								? CHistFunctionParser::unquoteParam($parameter['match'])
								: $parameter['match'];
						}
					}
				}
			}
		}
		// Opening an empty form or switching a function.
		else {
			$item = API::Item()->get([
				'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
				'selectHosts' => ['host', 'name'],
				'itemids' => $itemid,
				'webitems' => true,
				'filter' => ['flags' => null]
			]);

			$item = reset($item);
		}

		if ($item) {
			$items = CMacrosResolverHelper::resolveItemNames([$item]);
			$item = $items[0];

			$itemid = $item['itemid'];
			$item_value_type = $item['value_type'];
			$item_key = $item['key_'];
			$item_host_data = reset($item['hosts']);
			$description = $item_host_data['name'].NAME_DELIMITER.$item['name_expanded'];
		}
		else {
			$item_key = '';
			$description = '';
			$item_value_type = null;
		}

		if ($param_type === null && array_key_exists($function, $this->functions)
				&& array_key_exists('params', $this->functions[$function])
				&& array_key_exists('M', $this->functions[$function]['params'])) {
			$param_type = is_array($this->functions[$function]['params']['M'])
				? reset($this->functions[$function]['params']['M'])
				: $this->functions[$function]['params']['M'];
		}
		elseif ($param_type === null) {
			$param_type = PARAM_TYPE_TIME;
		}

		$data = [
			'parent_discoveryid' => $this->getInput('parent_discoveryid', ''),
			'dstfrm' => $this->getInput('dstfrm'),
			'dstfld1' => $dstfld1,
			'itemid' => $itemid,
			'value' => $value,
			'params' => $params,
			'paramtype' => $param_type,
			'item_description' => $description,
			'item_required' => !in_array($function, getStandaloneFunctions()),
			'functions' => $this->functions,
			'function' => $function,
			'operator' => $operator,
			'item_key' => $item_key,
			'itemValueType' => $item_value_type,
			'selectedFunction' => null,
			'groupid' => $this->getInput('groupid', 0),
			'hostid' => $this->getInput('hostid', 0)
		];

		// Check if submitted function is usable with selected item.
		foreach ($data['functions'] as $id => $f) {
			if (($data['itemValueType'] === null || array_key_exists($item_value_type, $f['allowed_types']))
					&& $id === $function) {
				$data['selectedFunction'] = $id;
				break;
			}
		}

		if ($data['selectedFunction'] === null) {
			$data['selectedFunction'] = 'last';
			$data['function'] = 'last';
		}

		// Remove functions that not correspond to chosen item.
		foreach ($data['functions'] as $id => $f) {
			if ($data['itemValueType'] !== null && !array_key_exists($data['itemValueType'], $f['allowed_types'])) {
				unset($data['functions'][$id]);

				// Take first available function from list and change to first available operator for that function.
				if ($id === $data['function']) {
					$data['function'] = key($data['functions']);
					$data['operator'] = reset($data['functions'][$data['function']]['operators']);
				}
			}
		}

		// Create and validate trigger expression before inserting it into textarea field.
		if ($this->getInput('add', false)) {
			try {
				if (in_array($function, getStandaloneFunctions())) {
					$data['expression'] = sprintf('%s()%s%s', $function, $operator,
						CExpressionParser::quoteString($data['value'])
					);
				}
				elseif ($data['item_description']) {
					// Quote function string parameters.
					foreach ($data['params'] as $param_key => $param) {
						if (!in_array($param_key, ['v', 'o', 'fit', 'mode', 'pattern'])
								|| !array_key_exists($param_key, $data['params'])
								|| $data['params'][$param_key] === '') {
							continue;
						}

						$data['params'][$param_key] = quoteFunctionParam($param, true);
					}

					// Combine sec|#num and <time_shift|period_shift> parameters into one.
					if (array_key_exists('last', $data['params'])) {
						if ($data['paramtype'] == PARAM_TYPE_COUNTS && zbx_is_int($data['params']['last'])) {
							$data['params']['last'] = '#'.$data['params']['last'];
						}
					}
					else {
						$data['params']['last'] = '';
					}

					if (array_key_exists('shift', $data['params']) && $data['params']['shift'] !== '') {
						$data['params']['last'] .= ':'.$data['params']['shift'];
					}
					elseif (array_key_exists('period_shift', $data['params'])
							&& $data['params']['period_shift'] !== '') {
						$data['params']['last'] .= ':'.$data['params']['period_shift'];
					}
					unset($data['params']['shift'], $data['params']['period_shift']);

					$mask = '';
					if ($function === 'bitand' && array_key_exists('mask', $data['params'])) {
						$mask = $data['params']['mask'];
						unset($data['params']['mask']);
					}

					$fn_params = rtrim(implode(',', $data['params']), ',');

					if ($function === 'abs') {
						$data['expression'] = sprintf('abs(last(/%s/%s)%s)%s%s',
							$item_host_data['host'],
							$data['item_key'],
							($fn_params === '') ? '' : ','.$fn_params,
							$operator,
							CExpressionParser::quoteString($data['value'])
						);
					}
					elseif ($function === 'bitand') {
						$data['expression'] = sprintf('bitand(last(/%s/%s%s)%s)%s%s',
							$item_host_data['host'],
							$data['item_key'],
							($fn_params === '') ? '' : ','.$fn_params,
							($mask === '') ? '' : ','.$mask,
							$operator,
							CExpressionParser::quoteString($data['value'])
						);
					}
					elseif ($function === 'length') {
						$data['expression'] = sprintf('length(last(/%s/%s))%s%s',
							$item_host_data['host'],
							$data['item_key'],
							$operator,
							CExpressionParser::quoteString($data['value'])
						);
					}
					else {
						$data['expression'] = sprintf('%s(/%s/%s%s)%s%s',
							$function,
							$item_host_data['host'],
							$data['item_key'],
							($fn_params === '') ? '' : ','.$fn_params,
							$operator,
							CExpressionParser::quoteString($data['value'])
						);
					}
				}
				else {
					error(_('Item not selected'));
				}

				if (array_key_exists('expression', $data)) {
					// Parse and validate trigger expression.
					if ($expression_parser->parse($data['expression']) == CParser::PARSE_SUCCESS) {
						$expression_validator = new CExpressionValidator();

						if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
							error($expression_validator->getError());
						}
					}
					else {
						error($expression_parser->getError());
					}
				}
			}
			catch (Exception $e) {
				error($e->getMessage());
				error(_('Cannot insert trigger expression'));
			}

			if (($messages = getMessages()) !== null) {
				$output = [
					'errors' => $messages->toString()
				];
			}
			else {
				$output = [
					'expression' => $data['expression'],
					'dstfld1' => $data['dstfld1'],
					'dstfrm' => $data['dstfrm']
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$this->setResponse(new CControllerResponseData(
				$data + [
					'title' => _('Condition'),
					'errors' => hasErrorMesssages() ? getMessages() : null,
					'user' => [
						'debug_mode' => $this->getDebugMode()
					]
				]
			));
		}
	}
}
