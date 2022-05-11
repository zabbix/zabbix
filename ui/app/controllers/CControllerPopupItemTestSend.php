<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	/**
	 * Time suffixes supported by Zabbix server.
	 *
	 * @var array
	 */
	protected static $supported_time_suffixes = ['w', 'd', 'h', 'm', 's'];

	protected function checkInput() {
		$fields = [
			'authtype'				=> 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'get_value'				=> 'in 0,1',
			'eol'					=> 'in '.implode(',', [ZBX_EOL_LF, ZBX_EOL_CRLF]),
			'headers'				=> 'array',
			'proxy_hostid'			=> 'id',
			'hostid'				=> 'db hosts.hostid',
			'http_authtype'			=> 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'http_password'			=> 'string',
			'http_proxy'			=> 'string',
			'http_username'			=> 'string',
			'flags'					=> 'in '. implode(',', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]),
			'follow_redirects'		=> 'in 0,1',
			'key'					=> 'string',
			'interface'				=> 'array',
			'ipmi_sensor'			=> 'string',
			'item_type'				=> 'in '.implode(',', [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT]),
			'jmx_endpoint'			=> 'string',
			'macros'				=> 'array',
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params_ap'				=> 'string',
			'params_es'				=> 'string',
			'params_f'				=> 'string',
			'script'				=> 'string',
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
			'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'valuemapid'			=> 'id',
			'verify_host'			=> 'in 0,1',
			'verify_peer'			=> 'in 0,1',
			'not_supported'			=> 'in 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$testable_item_types = self::getTestableItemTypes($this->getInput('hostid', '0'));
			$this->get_value_from_host = (bool) $this->getInput('get_value');
			$this->item_type = $this->hasInput('item_type') ? $this->getInput('item_type') : -1;
			$this->preproc_item = self::getPreprocessingItemClassInstance($this->getInput('test_type'));
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
			if ($steps && ($error = $this->preproc_item->validateItemPreprocessingSteps($steps)) !== true) {
				error($error);
				$ret = false;
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
		}

		if (($messages = getMessages()) !== null) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'messages' => $messages->toString(),
						'steps' => [],
						'user' => [
							'debug_mode' => $this->getDebugMode()
						]
					])
				]))->disableView()
			);
			$ret = false;
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		/*
		 * Define values used to test preprocessing steps.
		 * Steps array can be empty if only value conversion is tested.
		 */
		$preproc_test_data = [
			'value' => $this->getInput('value', ''),
			'steps' => $this->getInput('steps', []),
			'single' => !$this->show_final_result,
			'state' => 0
		];

		// Get previous value and time.
		if ($this->use_prev_value) {
			$prev_value = $this->getInput('prev_value', '');
			$prev_time = $this->getInput('prev_time', '');

			if ($prev_value !== '' || $prev_time !== '') {
				$preproc_test_data['history'] = [
					'value' => $prev_value,
					'timestamp' => $prev_time
				];
			}
		}

		$output = [
			'steps' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$valuemap = ($this->getInput('valuemapid', 0) != 0)
			? API::ValueMap()->get([
				'output' => [],
				'selectMappings' => ['type', 'newvalue', 'value'],
				'valuemapids' => $this->getInput('valuemapid')
			])[0]
			: [];

		// Get value from host.
		if ($this->get_value_from_host) {
			// Get post data for particular item type.
			$item_test_data = $this->getItemTestProperties($this->getInputAll(), true);

			// Apply effective macros values to properties.
			$item_test_data = $this->resolveItemPropertyMacros($item_test_data);

			// Rename fields according protocol.
			$item_test_data = CArrayHelper::renameKeys($item_test_data, [
				'params_ap' => 'params',
				'params_es' => 'params',
				'params_f' => 'params',
				'script' => 'params',
				'http_username' => 'username',
				'http_password' => 'password',
				'http_authtype' => 'authtype',
				'item_type' => 'type'
			]);

			if (array_key_exists('headers', $item_test_data)) {
				$item_test_data['headers'] = $this->transformHeaderFields($item_test_data['headers']);
			}

			if (array_key_exists('query_fields', $item_test_data)) {
				$item_test_data['query_fields'] = $this->transformQueryFields($item_test_data['query_fields']);
			}

			if (array_key_exists('parameters', $item_test_data)) {
				$item_test_data['parameters'] = $this->transformParametersFields($item_test_data['parameters']);
			}

			if ($item_test_data['type'] == ITEM_TYPE_CALCULATED) {
				$item_test_data['host']['hostid'] = $this->getInput('hostid');
			}

			// Only non-empty fields need to be sent to server.
			$item_test_data = $this->unsetEmptyValues($item_test_data);

			/*
			 * Server will turn off status code check if field value is empty. If field is not present, then server will
			 * default to check if status code is 200.
			 */
			if ($this->item_type == ITEM_TYPE_HTTPAGENT && !array_key_exists('status_codes', $item_test_data)) {
				$item_test_data['status_codes'] = '';
			}

			if ($this->item_type != ITEM_TYPE_CALCULATED) {
				unset($item_test_data['value_type']);
			}

			// Send test to be executed on Zabbix server.
			$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::ITEM_TEST_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
			);
			$result = $server->testItem($item_test_data, CSessionHelper::getId());

			// Handle the response.
			if ($result === false) {
				error($server->getError());
			}
			elseif (is_array($result)) {
				if (array_key_exists('result', $result)) {
					// Move current value to previous value field.
					if ($this->use_prev_value && $preproc_test_data['value'] !== '') {
						$preproc_test_data['history']['value'] = $preproc_test_data['value'];
						$preproc_test_data['history']['timestamp'] = $this->getPrevTime();

						$output['prev_value'] = $preproc_test_data['value'];
						$output['prev_time'] = $preproc_test_data['history']['timestamp'];
					}

					// Apply new value to preprocessing test.
					$preproc_test_data['value'] = $result['result'];
					$output['value'] = $result['result'];
					$output['eol'] = (strstr($result['result'], "\r\n") === false) ? ZBX_EOL_LF : ZBX_EOL_CRLF;
				}

				if (array_key_exists('error', $result) && $result['error'] !== '') {
					if ($preproc_test_data['steps']
							&& $preproc_test_data['steps'][0]['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
						$preproc_test_data['state'] = 1;
					}
					else {
						error($result['error']);
					}
				}
			}

			if (($messages = getMessages(false)) !== null) {
				$output['messages'] = $messages->toString();
			}
		}
		else {
			$preproc_test_data['state'] = $this->getInput('not_supported', 0);
		}

		// Test preprocessing steps.
		$this->eol = parent::getInput('eol', ZBX_EOL_LF);

		if (!array_key_exists('messages', $output)) {
			$preproc_test_data['value_type'] = $this->getInput('value_type', ITEM_VALUE_TYPE_STR);

			$preproc_test_data['steps'] = $this->resolvePreprocessingStepMacros($preproc_test_data['steps']);

			// Send test details to Zabbix server.
			$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
				timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::ITEM_TEST_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
			);
			$result = $server->testPreprocessingSteps($preproc_test_data, CSessionHelper::getId());

			if ($result === false) {
				error($server->getError());
			}
			elseif (is_array($result)) {
				$test_failed = false;
				$test_outcome = null;

				foreach ($preproc_test_data['steps'] as $i => &$step) {
					if ($test_failed) {
						// If test is failed, proceesing steps are skipped from results.
						unset($preproc_test_data['steps'][$i]);
						continue;
					}
					elseif (array_key_exists($i, $result['steps'])) {
						$step += $result['steps'][$i];

						if (array_key_exists('error', $step)) {
							// If error happened and no value is set, frontend shows label 'No value'.
							if (!array_key_exists('action', $step) || $step['action'] != ZBX_PREPROC_FAIL_SET_VALUE) {
								unset($step['result']);
								$test_failed = true;
							}
						}
						elseif ($step['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED) {
							$step['result'] = $preproc_test_data['value'];
						}
					}

					unset($step['type'], $step['params'], $step['error_handler'], $step['error_handler_params']);

					// Latest executed step due to the error or end of preprocessing.
					$test_outcome = $step + ['action' => ZBX_PREPROC_FAIL_DEFAULT];
				}
				unset($step);

				if (array_key_exists('error', $result)) {
					error($result['error']);
				}
				elseif ($this->show_final_result) {
					if (array_key_exists('result', $result)) {
						$output['final'] = [
							'action' => _s('Result converted to %1$s',
								itemValueTypeString($preproc_test_data['value_type'])
							),
							'result' => $result['result']
						];

						if ($valuemap) {
							$output['mapped_value'] = CValueMapHelper::applyValueMap($preproc_test_data['value_type'],
								$result['result'], $valuemap
							);
						}
					}
					elseif (array_key_exists('error', $result)) {
						$output['final'] = [
							'action' => ($test_outcome['action'] == ZBX_PREPROC_FAIL_SET_ERROR)
								? _('Set error to')
								: '',
							'error' => $result['error']
						];
					}

					if ($output['final']['action'] !== '') {
						$output['final']['action'] = (new CSpan($output['final']['action']))
							->addClass(ZBX_STYLE_GREY)
							->toString();
					}
				}

				$output['steps'] = $preproc_test_data['steps'];
			}

			if (($messages = getMessages(false)) !== null) {
				$output['messages'] = $messages->toString();
			}
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($output)]))->disableView());
	}
}
