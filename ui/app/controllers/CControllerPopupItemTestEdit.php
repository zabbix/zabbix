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


/**
 * Controller to build preprocessing test dialog.
 */
class CControllerPopupItemTestEdit extends CControllerPopupItemTest {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'authtype'				=> 'in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'data'					=> 'array',
			'delay'					=> 'string',
			'get_value'				=> 'in 0,1',
			'test_with'				=> 'in '.implode(',', [self::TEST_WITH_SERVER, self::TEST_WITH_PROXY]),
			'proxyid'				=> 'id',
			'headers'				=> 'array',
			'hostid'				=> 'db hosts.hostid',
			'http_authtype'			=> 'in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'http_password'			=> 'string',
			'http_proxy'			=> 'string',
			'http_username'			=> 'string',
			'follow_redirects'		=> 'in 0,1',
			'key'					=> 'string',
			'interfaceid'			=> 'db interface.interfaceid',
			'ipmi_sensor'			=> 'string',
			'itemid'				=> 'db items.itemid',
			'item_type'				=> 'in '.implode(',', [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]),
			'jmx_endpoint'			=> 'string',
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params_ap'				=> 'string',
			'params_es'				=> 'string',
			'params_f'				=> 'string',
			'script'				=> 'string',
			'browser_script'		=> 'string',
			'password'				=> 'string',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'string',
			'privatekey'			=> 'string',
			'publickey'				=> 'string',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'show_final_result'		=> 'in 0,1',
			'snmp_oid'				=> 'string',
			'step_obj'				=> 'required|int32',
			'steps'					=> 'array',
			'ssl_cert_file'			=> 'string',
			'ssl_key_file'			=> 'string',
			'ssl_key_password'		=> 'string',
			'status_codes'			=> 'string',
			'test_type'				=> 'required|in '.implode(',', [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD]),
			'timeout'				=> 'string',
			'username'				=> 'string',
			'url'					=> 'string',
			'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY]),
			'valuemapid'			=> 'int32',
			'verify_host'			=> 'in 0,1',
			'verify_peer'			=> 'in 0,1'
		];

		if (getRequest('interfaceid') == INTERFACE_TYPE_OPT) {
			unset($fields['interfaceid']);
			unset($_REQUEST['interfaceid']);
		}

		$result = $this->validateInput($fields) && $this->checkTestInputs();

		if (!$result) {
			$output = [];

			if ($messages = get_and_clear_messages()) {
				$output['error']['messages'] = array_column($messages, 'message');
			}

			$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
		}

		return $result;
	}

	private function checkTestInputs(): bool {
		$testable_item_types = self::getTestableItemTypes((string) $this->getInput('hostid', '0'));
		$this->item_type = $this->hasInput('item_type') ? (int) $this->getInput('item_type') : -1;
		$this->test_type = (int) $this->getInput('test_type');
		$this->is_item_testable = in_array($this->item_type, $testable_item_types);

		// Check if key is valid for item types it's mandatory.
		if (in_array($this->item_type, $this->item_types_has_key_mandatory)) {
			$item_key_parser = new CItemKey();

			if ($item_key_parser->parse($this->getInput('key', '')) != CParser::PARSE_SUCCESS) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'key_', $item_key_parser->getError()));

				return false;
			}
		}

		/*
		* Either the item must be testable or at least one preprocessing test must be passed ("Test" button should
		* be disabled otherwise).
		*/
		$steps = $this->getInput('steps', []);

		if ($steps) {
			$steps = normalizeItemPreprocessingSteps($steps);

			switch ($this->test_type) {
				case self::ZBX_TEST_TYPE_ITEM:
					$api_input_rules = CItem::getPreprocessingValidationRules();
					break;

				case self::ZBX_TEST_TYPE_ITEM_PROTOTYPE:
					$api_input_rules = CItemPrototype::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO);
					break;

				case self::ZBX_TEST_TYPE_LLD:
					$api_input_rules = CDiscoveryRule::getPreprocessingValidationRules();
					break;
			}

			if (!CApiInputValidator::validate($api_input_rules, $steps, '/', $error)) {
				error($error);

				return false;
			}

			if ($this->test_type != self::ZBX_TEST_TYPE_LLD) {
				$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['type', 'params']], 'fields' => [
					'type' =>	['type' => API_ANY],
					'params' =>	['type' => API_ANY]
				]];
				$_steps = [];

				foreach ($steps as $i => $step) {
					if ($step['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
						[$match_type] = explode("\n", $step['params']);

						if ($match_type == ZBX_PREPROC_MATCH_ERROR_ANY) {
							$_steps[$i] = [
								'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
								'params' => ZBX_PREPROC_MATCH_ERROR_ANY
							];
						}
					}
				}

				if (!CApiInputValidator::validateUniqueness($api_input_rules, $_steps, '', $error)) {
					error($error);

					return false;
				}
			}
		}
		elseif (!$this->is_item_testable) {
			error(_s('Test of "%1$s" items is not supported.', item_type2str($this->item_type)));

			return false;
		}

		return true;
	}

	protected function doAction() {
		// VMware and icmpping simple checks are not supported.
		$key = $this->hasInput('key') ? $this->getInput('key') : '';

		if ($this->item_type == ITEM_TYPE_SIMPLE && (strpos($key, 'vmware.') === 0 || strpos($key, 'icmpping') === 0)) {
			$this->is_item_testable = false;
		}

		// Get item and host properties and values from cache.
		$data = $this->getInput('data', []);
		$inputs = $this->getItemTestProperties($this->getInputAll());

		// Work with preprocessing steps.
		$preprocessing_steps = CItemGeneralHelper::sortPreprocessingSteps($this->getInput('steps', []));
		$preprocessing_steps = normalizeItemPreprocessingSteps($preprocessing_steps);
		$preprocessing_types = array_column($preprocessing_steps, 'type');
		$preprocessing_names = get_preprocessing_types(null, false, $preprocessing_types);
		$support_lldmacros = ($this->test_type == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE);
		$show_prev = (count(array_intersect($preprocessing_types, self::$preproc_steps_using_prev_value)) > 0);

		// Collect item texts and macros to later check their usage.
		$texts_support_macros = [];
		$texts_support_user_macros = [];
		$texts_support_lld_macros = [];
		$supported_macros = [];

		foreach (array_keys(array_intersect_key($inputs['item'], $this->macros_by_item_props)) as $field) {
			// Special processing for calculated item formula.
			if ($field === 'params_f') {
				$expression_parser = new CExpressionParser([
					'usermacros' => true,
					'lldmacros' => $support_lldmacros,
					'calculated' => true,
					'host_macro' => true,
					'empty_host' => true
				]);

				if ($expression_parser->parse($inputs['item'][$field]) == CParser::PARSE_SUCCESS) {
					$tokens = $expression_parser->getResult()->getTokensOfTypes([
						CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
						CExpressionParserResult::TOKEN_TYPE_LLD_MACRO,
						CExpressionParserResult::TOKEN_TYPE_STRING,
						CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION
					]);
					foreach ($tokens as $token) {
						switch ($token['type']) {
							case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
								$texts_support_user_macros[] = $token['match'];
								break;

							case CExpressionParserResult::TOKEN_TYPE_LLD_MACRO:
								$texts_support_lld_macros[] = $token['match'];
								break;

							case CExpressionParserResult::TOKEN_TYPE_STRING:
								$text = CExpressionParser::unquoteString($token['match']);
								$texts_support_user_macros[] = $text;
								$texts_support_lld_macros[] = $text;
								break;

							case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
								foreach ($token['data']['parameters'] as $parameter) {
									switch ($parameter['type']) {
										case CHistFunctionParser::PARAM_TYPE_QUERY:
											foreach ($parameter['data']['filter']['tokens'] as $filter_token) {
												switch ($filter_token['type']) {
													case CFilterParser::TOKEN_TYPE_USER_MACRO:
														$texts_support_user_macros[] = $filter_token['match'];
														break;

													case CFilterParser::TOKEN_TYPE_LLD_MACRO:
														$texts_support_lld_macros[] = $filter_token['match'];
														break;

													case CFilterParser::TOKEN_TYPE_STRING:
														$text = CFilterParser::unquoteString($filter_token['match']);
														$texts_support_user_macros[] = $text;
														$texts_support_lld_macros[] = $text;
														break;
												}
											}
											break;

										case CHistFunctionParser::PARAM_TYPE_QUOTED:
											$match = CHistFunctionParser::unquoteParam($parameter['match']);
											$texts_support_user_macros[] = $match;
											$texts_support_lld_macros[] = $match;
											break;

										case CHistFunctionParser::PARAM_TYPE_PERIOD:
										case CHistFunctionParser::PARAM_TYPE_UNQUOTED:
											$texts_support_user_macros[] = $parameter['match'];
											$texts_support_lld_macros[] = $parameter['match'] ;
											break;
									}
								}
								break;
						}
					}
				}
				continue;
			}

			$macros = $this->macros_by_item_props[$field];
			unset($macros['support_lld_macros'], $macros['support_user_macros']);

			if (in_array($field, ['query_fields', 'headers', 'parameters'])) {
				if (!array_key_exists($field, $inputs['item']) || !$inputs['item'][$field]) {
					continue;
				}

				foreach ($inputs['item'][$field] as $num => $row) {
					if ($row['name'] === '') {
						unset($inputs['item'][$field][$num]);
					}
				}

				$texts_having_macros = array_merge(
					array_column($inputs['item'][$field], 'name'),
					array_column($inputs['item'][$field], 'value')
				);
				$texts_having_macros = array_filter($texts_having_macros, static function(string $str): bool {
					return (strstr($str, '{') !== false);
				});

				if ($texts_having_macros) {
					$supported_macros = array_merge_recursive($supported_macros, $macros);
					$texts_support_macros = array_merge($texts_support_macros, $texts_having_macros);
					$texts_support_user_macros = array_merge($texts_support_user_macros, $texts_having_macros);

					if ($support_lldmacros) {
						$texts_support_lld_macros = array_merge($texts_support_lld_macros, $texts_having_macros);
					}
				}
			}
			elseif (strstr($inputs['item'][$field], '{') !== false) {
				if ($field === 'key') {
					$item_key_parser = new CItemKey();

					$texts_having_macros = $item_key_parser->parse($key) == CParser::PARSE_SUCCESS
						? CMacrosResolverGeneral::getItemKeyParameters($item_key_parser->getParamsRaw())
						: [];
				}
				else {
					$texts_having_macros = [$inputs['item'][$field]];
				}

				// Field support macros like {HOST.*}, {ITEM.*} etc.
				if ($macros) {
					$supported_macros = array_merge_recursive($supported_macros, $macros);
					$texts_support_macros = array_merge($texts_support_macros, $texts_having_macros);
				}

				// Check if LLD macros are supported in field.
				if ($support_lldmacros && $this->macros_by_item_props[$field]['support_lld_macros']) {
					$texts_support_lld_macros = array_merge($texts_support_lld_macros, $texts_having_macros);
				}

				// Check if user macros are supported in field.
				if ($this->macros_by_item_props[$field]['support_user_macros']) {
					$texts_support_user_macros = array_merge($texts_support_user_macros, $texts_having_macros);
				}
			}
		}

		// Unset duplicate macros.
		foreach ($supported_macros as &$item_macros_type) {
			$item_macros_type = array_unique($item_macros_type);
		}
		unset($item_macros_type);

		// Extract macros and apply effective values for each of them.
		$usermacros = CMacrosResolverHelper::extractItemTestMacros([
			'steps' => $preprocessing_steps,
			'delay' => $show_prev ? $this->getInput('delay', ZBX_ITEM_DELAY_DEFAULT) : '',
			'supported_macros' => $supported_macros,
			'support_lldmacros' => $support_lldmacros,
			'texts_support_macros' => $texts_support_macros,
			'texts_support_user_macros' => $texts_support_user_macros,
			'texts_support_lld_macros' => $texts_support_lld_macros,
			'hostid' => $this->host ? $this->host['hostid'] : 0,
			'macros_values' => $this->getSupportedMacros($inputs['item']
				+ CArrayHelper::getByKeys($inputs['host'], ['interfaceid'])
				+ ['interfaceid' => $this->getInput('interfaceid', 0)]
			)
		]);

		$show_warning = false;

		if (array_key_exists('interface', $inputs['host'])) {
			if (array_key_exists('address', $inputs['host']['interface'])
					&& strpos($inputs['host']['interface']['address'], ZBX_SECRET_MASK) !== false) {
				$inputs['host']['interface']['address'] = '';
				$show_warning = true;
			}

			if (array_key_exists('port', $inputs['host']['interface'])
					&& $inputs['host']['interface']['port'] === ZBX_SECRET_MASK) {
				$inputs['host']['interface']['port'] = '';
				$show_warning = true;
			}

			if (array_key_exists('details', $inputs['host']['interface'])) {
				foreach ($inputs['host']['interface']['details'] as $field => $value) {
					if (strpos($value, ZBX_SECRET_MASK) !== false) {
						$inputs['host']['interface']['details'][$field] = '';
						$show_warning = true;
					}
				}
			}
		}

		// Set resolved macros to previously specified values.
		foreach (array_keys($usermacros['macros']) as $macro_name) {
			if ($usermacros['macros'] && array_key_exists('macros', $data) && is_array($data['macros'])
					&& array_key_exists($macro_name, $data['macros'])) {
				// Macro values were set by user. Which means those could be intentional asterisks or empty fields.
				$usermacros['macros'][$macro_name] = $data['macros'][$macro_name];
			}
			elseif ($usermacros['macros'][$macro_name] === ZBX_SECRET_MASK) {
				/*
				 * Macro values were not set by user, so this means form was opened for the first time. So in this
				 * case check if there are secret macros. If there are, clear the values and show warning message box.
				 */

				$usermacros['macros'][$macro_name] = '';
				$show_warning = true;
			}
		}

		// Get previous value and time.
		$prev_value = '';
		$prev_time = '';
		if ($show_prev && array_key_exists('prev_value', $data) && $data['prev_value'] !== '') {
			$prev_value = $data['prev_value'];

			// Get previous value time.
			if (array_key_exists('prev_time', $data)) {
				$prev_time = $data['prev_time'];
			}
			else {
				$delay = timeUnitToSeconds($usermacros['delay']);
				$prev_time = ($delay !== null && $delay > 0)
					? 'now-'.$usermacros['delay']
					: 'now';
			}
		}

		// Sort macros.
		ksort($usermacros['macros']);

		// Add step number and name for each preprocessing step.
		$num = 0;
		foreach ($preprocessing_steps as &$step) {
			$step['name'] = $preprocessing_names[$step['type']];
			$step['num'] = ++$num;
		}
		unset($step);

		if (in_array($this->item_type, $this->items_support_proxy)) {
			if (array_key_exists('proxyid', $data)) {
				$proxyid = $data['proxyid'];
			}
			elseif ($this->host['status'] != HOST_STATUS_TEMPLATE) {
				$proxyid = $this->host['proxyid'];
			}
			else {
				$proxyid = 0;
			}

			if (array_key_exists('test_with', $data)) {
				$test_with = $data['test_with'];
			}
			else {
				$test_with = $this->getInput('test_with', $proxyid == 0
					? self::TEST_WITH_SERVER
					: self::TEST_WITH_PROXY
				);
			}
		}
		else {
			$test_with = self::TEST_WITH_SERVER;
			$proxyid = 0;
		}

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test item'),
			'steps' => $preprocessing_steps,
			'value' => array_key_exists('value', $data) ? $data['value'] : '',
			'not_supported' => array_key_exists('not_supported', $data) ? $data['not_supported'] : 0,
			'runtime_error' => array_key_exists('runtime_error', $data) ? $data['runtime_error'] : '',
			'eol' => array_key_exists('eol', $data) ? (int) $data['eol'] : ZBX_EOL_LF,
			'macros' => $usermacros['macros'],
			'show_prev' => $show_prev,
			'prev_value' => $prev_value,
			'prev_time' => $prev_time,
			'hostid' => $this->getInput('hostid'),
			'interfaceid' => $this->getInput('interfaceid', 0),
			'test_type' => $this->test_type,
			'step_obj' => $this->getInput('step_obj'),
			'show_final_result' => $this->getInput('show_final_result'),
			'valuemapid' => $this->getInput('valuemapid', 0),
			'get_value' => array_key_exists('get_value', $data)
				? $data['get_value']
				: $this->getInput('get_value', 0),
			'is_item_testable' => $this->is_item_testable,
			'inputs' => $inputs,
			'test_with' => $test_with,
			'ms_proxy' => $proxyid != 0
				? CArrayHelper::renameObjectsKeys(API::Proxy()->get([
					'output' => ['proxyid', 'name'],
					'proxyids' => [$proxyid]
				]), ['proxyid' => 'id'])
				: [],
			'proxies_enabled' => in_array($this->item_type, $this->items_support_proxy),
			'interface_address_enabled' => (array_key_exists($this->item_type, $this->items_require_interface)
				&& $this->items_require_interface[$this->item_type]['address']
			),
			'interface_port_enabled' => (array_key_exists($this->item_type, $this->items_require_interface)
				&& $this->items_require_interface[$this->item_type]['port']
			),
			'show_snmp_form' => ($this->item_type == ITEM_TYPE_SNMP),
			'show_warning' => $show_warning,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
