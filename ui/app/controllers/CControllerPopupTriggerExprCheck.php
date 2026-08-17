<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerPopupTriggerExprCheck extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$rules = ['object', 'fields' => [
			'dstfrm' => ['string', 'required'],
			'dstfld1' => ['string', 'required', 'not_empty'],
			'context' => ['string', 'required', 'in' => ['host', 'template']],
			'function' => ['string', 'required',
				'in' => array_keys(CTriggerConditionFunctionData::getValueTypes())
			],
			'item_value_type' => ['integer'],
			'function_select' => [
				['string', 'required', 'not_empty',
					'in' => CTriggerConditionFunctionData::getFunctionNames(ITEM_VALUE_TYPE_FLOAT),
					'when' => ['item_value_type', 'in' => [ITEM_VALUE_TYPE_FLOAT]],
					'messages' => ['in' => _('Incorrect item value type')]
				],
				['string', 'required', 'not_empty',
					'in' => CTriggerConditionFunctionData::getFunctionNames(ITEM_VALUE_TYPE_UINT64),
					'when' => ['item_value_type', 'in' => [ITEM_VALUE_TYPE_UINT64]],
					'messages' => ['in' => _('Incorrect item value type')]
				],
				['string', 'required', 'not_empty',
					'in' => CTriggerConditionFunctionData::getFunctionNames(ITEM_VALUE_TYPE_STR),
					'when' => ['item_value_type', 'in' => [ITEM_VALUE_TYPE_STR]],
					'messages' => ['in' => _('Incorrect item value type')]
				],
				['string', 'required', 'not_empty',
					'in' => CTriggerConditionFunctionData::getFunctionNames(ITEM_VALUE_TYPE_TEXT),
					'when' => ['item_value_type', 'in' => [ITEM_VALUE_TYPE_TEXT]],
					'messages' => ['in' => _('Incorrect item value type')]
				],
				['string', 'required', 'not_empty',
					'in' => CTriggerConditionFunctionData::getFunctionNames(ITEM_VALUE_TYPE_LOG),
					'when' => ['item_value_type', 'in' => [ITEM_VALUE_TYPE_LOG]],
					'messages' => ['in' => _('Incorrect item value type')]
				],
				['string', 'required', 'not_empty', 'in' => CTriggerConditionFunctionData::getFunctionNames(),
					'messages' => ['in' => _('Invalid function')]
				]
			],
			'function_type' => ['integer', 'required',
				'in' => [ZBX_FUNCTION_TYPE_AGGREGATE, ZBX_FUNCTION_TYPE_BITWISE, ZBX_FUNCTION_TYPE_DATE_TIME,
					ZBX_FUNCTION_TYPE_HISTORY, ZBX_FUNCTION_TYPE_MATH, ZBX_FUNCTION_TYPE_OPERATOR,
					ZBX_FUNCTION_TYPE_PREDICTION, ZBX_FUNCTION_TYPE_STRING]
			],
			'parent_discoveryid' => ['db items.itemid']
		]];

		return $rules;
	}

	private function getValidationRulesByFunction(string $name, int $type): ?array {
		$base_rules = self::getValidationRules();
		$allow_lld = $this->getInput('parent_discoveryid', '') !== '';
		$rules = CTriggerConditionFunctionData::getValidationRules($allow_lld);

		if (array_key_exists($name, $rules) && array_key_exists($type, $rules[$name])) {
			$base_rules['fields'] = array_merge($base_rules['fields'], $rules[$name][$type]);
		}
		else {
			return null;
		}

		return $base_rules;
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if ($ret) {
			$rules = $this->getValidationRulesByFunction(
				$this->getInput('function'),
				$this->getInput('function_type')
			);

			$ret = $rules ? $this->validateInput($rules) : false;
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot insert trigger expression'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
		$expression_validator = new CExpressionValidator([
			'usermacros' => true,
			'lldmacros' => true,
			'partial' => true
		]);

		$itemid = $this->getInput('itemid', '');
		$operator = $this->getInput('operator', '=');
		$dstfld1 = $this->getInput('dstfld1');
		$params = $this->getInput('params', []);
		$value = $this->getInput('value', 0);
		$function = $this->getInput('function', '');
		$item = null;

		if ($itemid) {
			$item = API::Item()->get([
				'output' => ['itemid', 'name', 'key_', 'value_type'],
				'selectHosts' => ['host', 'name'],
				'itemids' => $itemid,
				'webitems' => true,
				'filter' => ['flags' => null]
			]);
			$item = reset($item);
		}

		if ($item) {
			if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY || $item['value_type'] == ITEM_VALUE_TYPE_JSON) {
				error(_s('Item "%1$s" cannot be used in trigger: unsupported data type.', $item['key_']));
			}

			$itemid = $item['itemid'];
			$item_key = $item['key_'];
			$item_host_data = reset($item['hosts']);
			$description = $item_host_data['name'].NAME_DELIMITER.$item['name'];
		}
		else {
			$itemid = '';
			$item_key = '';
			$description = '';
		}

		$data = [
			'parent_discoveryid' => $this->getInput('parent_discoveryid', ''),
			'dstfrm' => $this->getInput('dstfrm'),
			'dstfld1' => $dstfld1,
			'context' => $this->getInput('context'),
			'itemid' => $itemid,
			'value' => $value,
			'params' => $params,
			'paramtype' => $this->getInput('paramtype', PARAM_TYPE_TIME),
			'item_description' => $description,
			'function' => $function,
			'operator' => $operator,
			'item_key' => $item_key
		];

		// Create and validate trigger expression before inserting it into textarea field.
		try {
			if (in_array($function, getFunctionsConstants())) {
				$data['expression'] = sprintf('%s()', $function);
			}
			elseif (in_array($function, getStandaloneFunctions())) {
				$data['expression'] = sprintf('%s()%s%s', $function, $operator,
					CExpressionParser::quoteString($data['value'])
				);
			}
			elseif ($data['item_description']) {
				// Quote function string parameters.
				$quote_params = [
					'algorithm',
					'chars',
					'fit',
					'mode',
					'o',
					'pattern',
					'path',
					'replace',
					'season_unit',
					'string',
					'v'
				];
				$quote_params = array_intersect_key($data['params'], array_fill_keys($quote_params, ''));
				$quote_params = array_filter($quote_params, 'strlen');

				foreach ($quote_params as $param_key => $param) {
					$data['params'][$param_key] = CExpressionParser::quoteString($param);
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

				// Functions where item is wrapped in last() like func(last(/host/item)).
				$last_functions = [
					'abs', 'acos', 'ascii', 'asin', 'atan', 'atan2', 'between', 'bitand', 'bitlength', 'bitlshift',
					'bitnot', 'bitor', 'bitrshift', 'bitxor', 'bytelength', 'cbrt', 'ceil', 'char', 'concat', 'cos',
					'cosh', 'cot', 'degrees', 'exp', 'expm1', 'floor', 'in', 'insert', 'jsonpath', 'left', 'length',
					'log', 'log10', 'ltrim', 'mid', 'mod', 'power', 'radians', 'repeat', 'replace', 'right', 'round',
					'rtrim', 'signum', 'sin', 'sinh', 'sqrt', 'tan', 'trim', 'truncate', 'xmlxpath'
				];

				if (in_array($function, $last_functions)) {
					$last_params = $data['params']['last'];
					unset($data['params']['last']);
					$fn_params = rtrim(implode(',', $data['params']), ',');

					$data['expression'] = sprintf('%s(last(/%s/%s%s)%s)%s%s',
						$function,
						$item_host_data['host'],
						$data['item_key'],
						($last_params === '') ? '' : ','.$last_params,
						($fn_params === '') ? '' : ','.$fn_params,
						$operator,
						CExpressionParser::quoteString($data['value'])
					);
				}
				else {
					$fn_params = rtrim(implode(',', $data['params']), ',');

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
					if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
						error(_s('Invalid condition: %1$s.', $expression_validator->getError()));
					}
				}
				else {
					error($expression_parser->getError());
				}
			}
		}
		catch (Exception $e) {
			error($e->getMessage());
		}

		if ($messages = get_and_clear_messages()) {
			$output = [
				'error' => [
					'title' => _('Cannot insert trigger expression'),
					'messages' => array_column($messages, 'message')
				]
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
}
