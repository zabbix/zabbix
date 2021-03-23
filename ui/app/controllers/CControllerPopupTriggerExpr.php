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
				'C' => 'T',
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
			'v' => [
				'C' => 'V',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'o' => [
				'C' => 'O',
				'T' => T_ZBX_STR,
				'A' => false
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
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
			'mask' => [
				'C' => _('Mask'),
				'T' => T_ZBX_STR,
				'A' => true
			],
			'shift' => [
				'C' => _('Time shift'),
				'T' => T_ZBX_INT,
				'A' => false
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
			'last' => [
				'description' => _('last() - Last (most recent) T value'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesAny,
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
			'str' => [
				'description' => _('str() - Find string V in last (most recent) value (1 - found, 0 - not found)'),
				'params' => $this->param2SecCount,
				'allowed_types' => $this->allowedTypesStr,
				'operators' => ['=', '<>']
			],
			'strlen' => [
				'description' => _('strlen() - Length of last (most recent) T value in characters'),
				'params' => $this->param1SecCount,
				'allowed_types' => $this->allowedTypesStr,
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
				'description' => _('logeventid() - Event ID of last log entry matching regular expression T (1 - match, 0 - no match)'),
				'params' => $this->param1Str,
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>']
			],
			'logseverity' => [
				'description' => _('logseverity() - Log severity of the last log entry'),
				'allowed_types' => $this->allowedTypesLog,
				'operators' => ['=', '<>', '>', '<', '>=', '<=']
			],
			'logsource' => [
				'description' => _('logsource() - Log source of the last log entry matching parameter T (1 - match, 0 - no match)'),
				'params' => $this->param1Str,
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
			'band' => [
				'description' => _('band() - Bitwise AND of last (most recent) T value and mask'),
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

			$expression_data = new CTriggerExpression();
			$result = $expression_data->parse($expression);

			if ($result) {
				$function_tokens = $result->getTokensOfTypes([CTriggerExprParserResult::TOKEN_TYPE_FUNCTION]);

				if ($function_tokens) {
					$function_token = $function_tokens[0];
					$function = $function_token->function;

					// Determine param type.
					$params = $function_token->params_raw['parameters'];
					if (array_key_exists(0, $params)
							&& $params[0]->type == CTriggerExprParserResult::TOKEN_TYPE_QUERY) {
						array_shift($params);
					}

					$is_num = (array_key_exists(0, $params) && substr($params[0]->match, 0, 1) === '#');

					if (!in_array($function, ['fuzzytime', 'nodata']) && $is_num) {
						$param_type = PARAM_TYPE_COUNTS;
						$params[0]->match = substr($params[0]->match, 1);
					}
					else {
						$param_type = PARAM_TYPE_TIME;
					}

					$params = array_column($params, 'match');

					/*
					 * Try to find an operator, a value and item.
					 * The value and operator can be extracted only if they immediately follow the function.
					 */
					$tokens = $result->getTokens();
					$items = [];

					foreach ($tokens as $key => $token) {
						if ($token->type == CTriggerExprParserResult::TOKEN_TYPE_FUNCTION) {
							if (!array_key_exists($key + 2, $tokens)) {
								break;
							}

							if ($tokens[$key + 1]->type == CTriggerExprParserResult::TOKEN_TYPE_OPERATOR) {
								$operator_token = $tokens[$key + 1];
								$value_token = $tokens[$key + 2];
							}
							elseif (array_key_exists($key + 3, $tokens)
									&& $tokens[$key + 2]->type == CTriggerExprParserResult::TOKEN_TYPE_OPERATOR) {
								$operator_token = $tokens[$key + 2];
								$value_token = $tokens[$key + 3];
							}
							else {
								break;
							}

							$fn_name = $token->function;
							if (array_key_exists($fn_name, $this->functions)
									&& in_array($operator_token->match, $this->functions[$fn_name]['operators'])) {
								$operator = $operator_token->match;
								$value = array_key_exists('string', $value_token->data)
									? $value_token->data['string']
									: '';
							}
							else {
								break;
							}

							if (!in_array($fn_name, getStandaloneFunctions())) {
								$items = API::Item()->get([
									'output' => ['itemid', 'hostid', 'name', 'key_', 'value_type'],
									'selectHosts' => ['name'],
									'webitems' => true,
									'filter' => [
										'host' => $function_token->getHosts()[0],
										'key_' => $function_token->getItems()[0],
										'flags' => null
									]
								]);

								if (($item = reset($items)) === false) {
									error(_('Unknown host item, no such item in selected host'));
								}
							}
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
				if ($data['item_description']) {
					if ($data['paramtype'] == PARAM_TYPE_COUNTS
							&& array_key_exists('last', $data['params'])
							&& $data['params']['last'] !== '') {
						$data['params']['last'] = zbx_is_int($data['params']['last'])
							? '#'.$data['params']['last']
							: $data['params']['last'];
					}
					elseif ($data['paramtype'] == PARAM_TYPE_TIME && in_array($function, ['last', 'band', 'strlen'])) {
						$data['params']['last'] = '';
					}

					// Combince sec|#num and <time_shift> parameters into one.
					if (array_key_exists('last', $data['params'])) {
						array_unshift($data['params'], implode(':', array_filter([
							$data['params']['last'],
							array_key_exists('shift', $data['params']) ? $data['params']['shift'] : null
						])));
						unset($data['params']['last'], $data['params']['shift']);
					}

					// Quote function param.
					$quoted_params = [];
					foreach ($data['params'] as $param) {
						$quoted_params[] = quoteFunctionParam($param);
					}

					if (in_array($function, ['date', 'dayofmonth', 'dayofweek', 'now', 'time'])) {
						$data['expression'] = sprintf('%s()%s%s',
							$function,
							$operator,
							CTriggerExpression::quoteString($data['value'])
						);
					}
					else {
						$fn_params = rtrim(implode(',', $quoted_params), ',');
						$data['expression'] = sprintf('%s(/%s/%s%s)%s%s',
							$function,
							$item_host_data['host'],
							$data['item_key'],
							($fn_params === '') ? '' : ','.$fn_params,
							$operator,
							CTriggerExpression::quoteString($data['value'])
						);
					}

					// Validate trigger expression.
					$trigger_expression = new CTriggerExpression();

					if (($result = $trigger_expression->parse($data['expression'])) !== false) {
						// Validate trigger function.
						$trigger_function_validator = new CFunctionValidator();
						$fn = $result->getTokens()[0];

						if (!$trigger_function_validator->validate($fn)
								|| !$trigger_function_validator->validateValueType($data['itemValueType'], $fn)) {
							error($trigger_function_validator->getError());
						}
					}
					else {
						error($trigger_expression->error);
					}

					// Quote function param.
					if (array_key_exists('insert', $data)) {
						foreach ($data['params'] as $pnum => $param) {
							$data['params'][$pnum] = quoteFunctionParam($param);
						}
					}
				}
				else {
					error(_('Item not selected'));
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
