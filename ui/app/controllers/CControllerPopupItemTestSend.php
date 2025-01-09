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
 * Controller to perform preprocessing test or 'get item value from host' test or both.
 */
class CControllerPopupItemTestSend extends CControllerPopupItemTest {

	/**
	 * Show final result in item test dialog.
	 *
	 * @var bool
	 */
	protected $show_final_result;

	/**
	 * Use previous value for preprocessing test.
	 *
	 * @var bool
	 */
	protected $use_prev_value;

	/**
	 * Retrieve value from host.
	 *
	 * @var bool
	 */
	protected $get_value_from_host;

	private const SUPPORTED_STATE = 0;
	private const NOT_SUPPORTED_STATE = 1;

	/**
	 * Time suffixes supported by Zabbix server.
	 *
	 * @var array
	 */
	protected static $supported_time_suffixes = ['w', 'd', 'h', 'm', 's'];

	protected function checkInput() {
		$fields = [
			'authtype'				=> 'in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'get_value'				=> 'in 0,1',
			'eol'					=> 'in '.implode(',', [ZBX_EOL_LF, ZBX_EOL_CRLF]),
			'headers'				=> 'array',
			'test_with'				=> 'in '.implode(',', [self::TEST_WITH_SERVER, self::TEST_WITH_PROXY]),
			'proxyid'				=> 'id',
			'hostid'				=> 'db hosts.hostid',
			'http_authtype'			=> 'in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'http_password'			=> 'string',
			'http_proxy'			=> 'string',
			'http_username'			=> 'string',
			'flags'					=> 'in '. implode(',', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]),
			'follow_redirects'		=> 'in 0,1',
			'key'					=> 'string',
			'interface'				=> 'array',
			'ipmi_sensor'			=> 'string',
			'item_type'				=> 'in '.implode(',', [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]),
			'jmx_endpoint'			=> 'string',
			'macros'				=> 'array',
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params_ap'				=> 'string',
			'params_es'				=> 'string',
			'params_f'				=> 'string',
			'script'				=> 'string',
			'browser_script'		=> 'string',
			'password'				=> 'string',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'string',
			'prev_time'				=> 'string',
			'prev_value'			=> 'string',
			'privatekey'			=> 'string',
			'publickey'				=> 'string',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'show_final_result'		=> 'in 0,1',
			'snmp_oid'				=> 'string',
			'steps'					=> 'array',
			'ssl_cert_file'			=> 'string',
			'ssl_key_file'			=> 'string',
			'ssl_key_password'		=> 'string',
			'status_codes'			=> 'string',
			'test_type'				=> 'required|in '.implode(',', [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD]),
			'time_change'			=> 'int32',
			'timeout'				=> 'string',
			'username'				=> 'string',
			'url'					=> 'string',
			'value'					=> 'string',
			'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY]),
			'valuemapid'			=> 'id',
			'verify_host'			=> 'in 0,1',
			'verify_peer'			=> 'in 0,1',
			'not_supported'			=> 'in '.implode(',', [self::SUPPORTED_STATE, self::NOT_SUPPORTED_STATE]),
			'runtime_error'			=> 'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$testable_item_types = self::getTestableItemTypes($this->getInput('hostid', '0'));
			$this->get_value_from_host = (bool) $this->getInput('get_value');
			$this->item_type = $this->hasInput('item_type') ? $this->getInput('item_type') : -1;
			$this->test_type = $this->getInput('test_type');
			$this->is_item_testable = in_array($this->item_type, $testable_item_types);

			$interface = $this->getInput('interface', []);
			$steps = $this->getInput('steps', []);
			$prepr_types = zbx_objectValues($steps, 'type');
			$this->use_prev_value = (count(array_intersect($prepr_types, self::$preproc_steps_using_prev_value)) > 0);
			$this->show_final_result = ($this->getInput('show_final_result') == 1);

			// If 'get value from host' is checked, check if key is valid for item types it's mandatory.
			if ($this->get_value_from_host && in_array($this->item_type, $this->item_types_has_key_mandatory)) {
				$key = $this->getInput('key', '');

				/*
				 * VMware and icmpping simple checks are not supported.
				 * This normally cannot be achieved from UI so no need for error message.
				 */
				if ($this->item_type == ITEM_TYPE_SIMPLE
						&& (substr($key, 0, 7) === 'vmware.' || substr($key, 0, 8) === 'icmpping')) {
					$this->get_value_from_host = false;
					$ret = false;
				}
				else {
					$item_key_parser = new CItemKey();

					if ($item_key_parser->parse($key) != CParser::PARSE_SUCCESS) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'key_', $item_key_parser->getError()));
						$ret = false;
					}
				}
			}

			// Test if item is testable and check interface properties.
			if ($this->get_value_from_host && !$this->is_item_testable) {
				error(_s('Test of "%1$s" items is not supported.', item_type2str($this->item_type)));
				$ret = false;
			}
			elseif ($this->get_value_from_host && array_key_exists($this->item_type, $this->items_require_interface)) {
				if (!$this->validateInterface($interface)) {
					$ret = false;
				}
			}

			// Check preprocessing steps.
			if ($steps) {
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
					$ret = false;
				}
			}

			// Check previous time.
			if ($this->use_prev_value && $this->getInput('prev_value', '') !== '') {
				$prev_time = $this->getInput('prev_time', '');

				$relative_time_parser = new CRelativeTimeParser();
				if ($relative_time_parser->parse($prev_time) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
						_('a relative time is expected')
					));
					$ret = false;
				}
				else {
					$tokens = $relative_time_parser->getTokens();

					if (count($tokens) > 1) {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
							_('only one time unit is allowed')
						));
					}
					elseif ($tokens && $tokens[0]['type'] == CRelativeTimeParser::ZBX_TOKEN_PRECISION) {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
							_('a relative time is expected')
						));
					}
					elseif ($tokens && !in_array($tokens[0]['suffix'], self::$supported_time_suffixes)) {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
							_('unsupported time suffix')
						));
					}
					elseif ($tokens && $tokens[0]['sign'] !== '-') {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
							_('should be less than current time')
						));
					}
				}
			}

			if ($this->item_type == ITEM_TYPE_CALCULATED) {
				$expression_parser = new CExpressionParser([
					'usermacros' => true,
					'lldmacros' => ($this->getInput('test_type') == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE),
					'calculated' => true,
					'host_macro' => true,
					'empty_host' => true
				]);

				if ($expression_parser->parse($this->getInput('params_f')) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Formula'),
						$expression_parser->getError()
					));
				}
				else {
					$expression_validator = new CExpressionValidator([
						'usermacros' => true,
						'lldmacros' => ($this->getInput('test_type') == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE),
						'calculated' => true
					]);

					if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Formula'),
							$expression_validator->getError()
						));
					}
				}
			}

			if ($this->hasInput('test_with') && $this->getInput('test_with') == self::TEST_WITH_PROXY
					&& $this->getInput('proxyid', 0) == 0) {
				error(_s('Incorrect value for field "%1$s": %2$s.',
					_s('%1$s: %2$s', _('Test with'), _('Proxy')), _('cannot be empty')
				));

				$ret = false;
			}
		}

		if ($messages = array_column(get_and_clear_messages(), 'message')) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => $messages
					]
				])])
			);

			$ret = false;
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$data = [
			'options' => [
				'single' => !$this->show_final_result,
				'state' => self::SUPPORTED_STATE
			]
		];

		if ($this->use_prev_value) {
			$prev_value = $this->get_value_from_host ? $this->getInput('value', '') : $this->getInput('prev_value', '');
			$prev_time = $this->getInput('prev_time', '');

			if ($prev_value !== '' || $prev_time !== '') {
				$data['options']['history'] = [
					'value' => $prev_value,
					'timestamp' => $prev_time
				];
			}
		}

		if ($this->get_value_from_host) {
			$data += $this->prepareTestData();
		}
		else {
			$data['item']['value'] = $this->getInput('value', '');
			$data['options']['state'] = (int) $this->getInput('not_supported', self::SUPPORTED_STATE);

			if ($data['options']['state'] == self::NOT_SUPPORTED_STATE) {
				$data['options']['runtime_error'] = $this->getInput('runtime_error', '');
			}
		}

		$data['item']['value_type'] = $this->getInput('value_type', ITEM_VALUE_TYPE_STR);

		// Steps array can be empty if only value conversion is tested.
		$steps_data = $this->resolvePreprocessingStepMacros($this->getInput('steps', []));

		if ($steps_data) {
			$data['item']['steps'] = $steps_data;
		}

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::ITEM_TEST_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);
		$result = $server->testItem($data, CSessionHelper::getId());
		$output = ['user' => ['debug_mode' => $this->getDebugMode()]];

		if ($result === false) {
			error($server->getError());
		}
		else {
			$this->processTestResult($data, $steps_data, $result, $output);
		}

		$messages = get_and_clear_messages();

		if ($messages) {
			foreach ($messages as &$message) {
				if ($message['message'] === '') {
					$message['message'] = _('<empty string>');
				}
			}
			unset($message);

			$output['error']['messages'] = array_column($messages, 'message');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	private function processTestResult(array $data, array $steps_data, array $result, array &$output = []): void {
		if (array_key_exists('error', $result)) {
			error($result['error']);

			return;
		}
		elseif ($steps_data && !array_key_exists('preprocessing', $result)) {
			return;
		}

		$result_preproc = (array_key_exists('preprocessing', $result) ? $result['preprocessing'] : [])
			+ ['steps' => []];
		$result_item = array_key_exists('item', $result) ? $result['item'] : [];

		if (array_key_exists('error', $result_item) && $result_item['error'] !== '') {
			if ($steps_data	&& $steps_data[0]['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
				$output['runtime_error'] = $result_item['error'];
				$output['not_supported'] = self::NOT_SUPPORTED_STATE;
			}
			else {
				error($result_item['error']);

				return;
			}
		}
		elseif (array_key_exists('result', $result_item)) {
			$output['value'] = $result_item['result'];
			$output['eol'] = $result_item['eol'] === 'CRLF' ? ZBX_EOL_CRLF : ZBX_EOL_LF;

			if ($this->use_prev_value) {
				$output['prev_value'] = array_key_exists('history', $data['options'])
					? $data['options']['history']['value']
					: $result_item['result'];
				$output['prev_time'] = $this->getPrevTime();
			}

			if (array_key_exists('truncated', $result_item) && $result_item['truncated']) {
				$output['value_warning'] = _s('Result is truncated due to its size (%1$s).',
					convertUnits(['value' => $result_item['original_size'], 'units' => 'B'])
				);
			}
		}

		$test_outcome = ['action' => ZBX_PREPROC_FAIL_DEFAULT];
		$test_failed = false;
		$clear_step_fields = array_flip(['type', 'params', 'error_handler', 'error_handler_params',
			'truncated', 'original_size'
		]);

		foreach ($steps_data as $i => &$step) {
			// If test considered failed, further steps are skipped.
			if ($test_failed) {
				unset($result_preproc['steps'][$i]);
				continue;
			}

			if (array_key_exists($i, $result_preproc['steps'])) {
				$step += $result_preproc['steps'][$i];

				// If error happened and no value override set, frontend shows 'No value'.
				if (array_key_exists('error', $step)) {
					if (array_key_exists('action', $step)) {
						switch ($step['action']) {
							case ZBX_PREPROC_FAIL_DISCARD_VALUE:
								unset($step['result']);
								$test_failed = true;
							break;

							case ZBX_PREPROC_FAIL_SET_VALUE:
								// Code is not missing here.
								break;

							case ZBX_PREPROC_FAIL_SET_ERROR:
								$test_failed = $step['type'] != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED;
								break;
						}
					}
					else {
						unset($step['result']);
						$test_failed = $step['type'] != ZBX_PREPROC_VALIDATE_NOT_SUPPORTED;
					}

					$step['error'] = $step['error']['value'];
				}
				elseif (array_key_exists('truncated', $step) && $step['truncated']) {
					$step['warning'] = _s('Result is truncated due to its size (%1$s).',
						convertUnits(['value' => $step['original_size'], 'units' => 'B'])
					);
				}
			}

			$step = array_diff_key($step, $clear_step_fields);

			// Latest executed step due to the error or end of preprocessing.
			$test_outcome = $step + ['action' => ZBX_PREPROC_FAIL_DEFAULT];
		}
		unset($step);

		$output['steps'] = $steps_data;

		if (array_key_exists('error', $result_preproc)) {
			error($result_preproc['error']);

			return;
		}

		if ($this->show_final_result) {
			if (array_key_exists('result', $result_preproc)) {
				$output['final'] = [
					'action' => _s('Result converted to %1$s', itemValueTypeString($data['item']['value_type'])),
					'result' => $result_preproc['result']
				];

				if (array_key_exists('truncated', $result_preproc) && $result_preproc['truncated']) {
					$output['final']['warning'] = _s('Result is truncated due to its size (%1$s).',
						convertUnits(['value' => $result_preproc['original_size'], 'units' => 'B'])
					);
				}

				$valuemap = $this->getInput('valuemapid', 0) == 0
					? []
					: API::ValueMap()->get([
						'output' => [],
						'selectMappings' => ['type', 'newvalue', 'value'],
						'valuemapids' => $this->getInput('valuemapid')
					])[0];

				if ($valuemap) {
					$output['mapped_value'] = CValueMapHelper::applyValueMap($data['item']['value_type'],
						$result_preproc['result'], $valuemap
					);
				}
			}
			elseif (array_key_exists('error', $result_preproc)) {
				$output['final'] = [
					'action' => $test_outcome['action'] == ZBX_PREPROC_FAIL_SET_ERROR
						? _('Set error to')
						: '',
					'error' => $result_preproc['error']
				];
			}

			if (array_key_exists('final', $output) && $output['final']['action'] !== '') {
				$output['final']['action'] = (new CSpan($output['final']['action']))
					->addClass(ZBX_STYLE_GREY)
					->toString();
			}
		}
	}
}
