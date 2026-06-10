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


class CControllerPopupTriggerExprEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'dstfrm' =>				'string|fatal',
			'dstfld1' =>			'string|not_empty',
			'context' =>			'required|string|in host,template',
			'expression' =>			'string',
			'parent_discoveryid' =>	'db items.itemid',
			'hostid' =>				'db hosts.hostid'
		];

		$ret = $this->validateInput($fields);

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			|| $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$dstfld1 = $this->getInput('dstfld1');
		$expression = ($dstfld1 === 'expr_temp' || $dstfld1 === 'recovery_expr_temp')
			? $this->getInput('expression', '')
			: '';
		$values = $this->extractFunction($expression);

		if ($values['item_value_type'] == ITEM_VALUE_TYPE_BINARY || $values['item_value_type'] == ITEM_VALUE_TYPE_JSON) {
			error(_s('Item "%1$s" cannot be used in trigger: unsupported data type.', $values['item_key']));
			$values['itemid'] = '';
			$values['item_description'] = '';
			$values['item_key'] = '';
			$values['item_value_type'] = null;
		}

		$data = [
			'parent_discoveryid' => $this->getInput('parent_discoveryid', ''),
			'dstfrm' => $this->getInput('dstfrm'),
			'dstfld1' => $dstfld1,
			'context' => $this->getInput('context'),
			'values' => $values,
			'params_fields' => CTriggerConditionFunctionData::getParamsFields(),
			'functions' => $this->getFunctions(),
			'operators' => array_combine(CTriggerConditionFunctionData::OPERATORS,
				CTriggerConditionFunctionData::OPERATORS),
			'hostid' => $this->getInput('hostid', 0),
			'is_new' => ($expression === '')
		];

		$this->setResponse(new CControllerResponseData(
			$data + [
				'title' => _('Condition'),
				'messages' => hasErrorMessages() ? getMessages() : null,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]
		));
	}

	private function extractFunction(string $expression): array {
		$expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
		$item = null;
		$function = 'last';
		$function_type = ZBX_FUNCTION_TYPE_HISTORY;
		$operator = '=';
		$params = [];
		$value = 0;
		$param_type = null;

		if ($expression !== '' && $expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
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
						&& in_array($tokens[$index]['match'], CTriggerConditionFunctionData::OPERATORS)) {
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
				$outer_parameters = [];

				if ($math_function_token) {
					$function = $math_function_token['data']['function'];

					if ($hist_function_token && $hist_function_token['data']['function'] === 'last') {
						$parameters = $hist_function_token['data']['parameters'];
						$outer_parameters = $math_function_token['data']['parameters'];
						unset($outer_parameters[0]);
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
						'output' => ['itemid', 'name', 'key_', 'value_type'],
						'selectHosts' => ['name'],
						'webitems' => true,
						'filter' => [
							'host' => $host,
							'key_' => $key
						]
					]);

					if (!$items) {
						$items = API::ItemPrototype()->get([
							'output' => ['itemid', 'name', 'key_', 'value_type'],
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
				$function_parameters = CTriggerConditionFunctionData::getParameter($function);
				$function_type = array_key_first($function_parameters);

				$key_map = array_key_exists('params', $function_parameters[$function_type])
					? array_keys($function_parameters[$function_type]['params'])
					: [];

				$time_params = array_intersect(['last', 'shift', 'period_shift'], $key_map);
				$param_index = count($time_params);

				foreach ($time_params as $time_param) {
					$params[$time_param] = '';
				}

				if ($parameters !== null && array_key_exists(1, $parameters)) {
					if ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_PERIOD) {
						$sec_num = $parameters[1]['data']['sec_num'];

						if ($sec_num !== '' && $sec_num[0] === '#') {
							$params[$key_map[$param_index-1]] = substr($sec_num, 1);
							$param_type = PARAM_TYPE_COUNTS;
						}
						else {
							$params[$key_map[$param_index-1]] = $sec_num;
							$param_type = PARAM_TYPE_TIME;
						}

						if ($param_index > 1) {
							$params[$key_map[0]] = $parameters[1]['data']['time_shift'];
						}
					}

					$param_count = min(count($key_map), count($parameters));

					while ($param_index < $param_count) {
						$parameter = $parameters[$param_index];

						$params[$key_map[$param_index]] = str_starts_with($parameter['match'], '"')
							? CHistFunctionParser::unquoteParam($parameter['match'])
							: $parameter['match'];

						$param_index++;
					}
				}

				if ($function === 'in' && array_key_exists($param_index, $key_map)) {
					$params[$key_map[$param_index]] = implode(',', array_column($outer_parameters, 'match'));
				}
				else {
					foreach ($outer_parameters as $parameter) {
						if (array_key_exists($param_index, $key_map)) {
							$params[$key_map[$param_index]] = str_starts_with($parameter['match'], '"')
								? CHistFunctionParser::unquoteParam($parameter['match'])
								: $parameter['match'];

							$param_index++;
						}
					}
				}
			}
		}

		$item_description = '';

		if ($item) {
			$item_host_data = reset($item['hosts']);
			$item_description = $item_host_data['name'].NAME_DELIMITER.$item['name'];
		}

		return [
			'itemid' => $item ? $item['itemid'] : '',
			'item_description' => $item_description,
			'item_key' => $item ? $item['key_'] : '',
			'item_value_type' => $item ? $item['value_type'] : null,
			'function' => $function,
			'function_type' => $function_type,
			'paramtype' => $param_type,
			'params' => $params,
			'operator' => $operator,
			'value' => $value
		];
	}

	private function getFunctions(): array {
		$functions = [];

		foreach (CTriggerConditionFunctionData::getDescriptions() as $function_name => $function_description) {
			$functions[$function_name] = ['description' => $function_description, 'types' => []];
		}

		foreach (CTriggerConditionFunctionData::getValueTypes() as $function_name => $value_types) {
			foreach ($value_types as $function_type => $item_types) {
				$functions[$function_name]['types'] += [$function_type => ['allowed_types' => $item_types]];
			}
		}

		foreach (CTriggerConditionFunctionData::getParameters() as $function_name => $type_parameters) {
			foreach ($type_parameters as $function_type => $parameters) {
				$functions[$function_name]['types'][$function_type] += ['parameters' => $parameters];
			}
		}

		$default_rules = CControllerPopupTriggerExprCheck::getValidationRules();
		$allow_lld = $this->getInput('parent_discoveryid', '') !== '';
		$function_rules = CTriggerConditionFunctionData::getValidationRules($allow_lld);

		foreach ($function_rules as $function_name => $type_rules) {
			foreach ($type_rules as $function_type => $rules) {
				$used_rules = $default_rules;
				$used_rules['fields'] = array_merge($used_rules['fields'], $rules);
				$functions[$function_name]['types'][$function_type] += [
					'rules' => (new CFormValidator($used_rules))->getRules()
				];
			}
		}

		CArrayHelper::sort($functions, ['description']);

		return $functions;
	}
}
