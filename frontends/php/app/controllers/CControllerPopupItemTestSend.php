<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	 * @var bool
	 */
	protected $show_final_result;

	/**
	 * @var bool
	 */
	protected $use_prev_value;

	/**
	 * @var bool
	 */
	protected $get_value_from_host;

	/**
	 * @var int
	 */
	protected $eol;

	/**
	 * Time suffixes supported by Zabbix server.
	 *
	 * @var array
	 */
	protected static $supported_time_suffixes = ['w', 'd', 'h', 'm', 's'];

	protected function checkInput() {
		$fields = [
			'authtype'				=> 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'get_value'				=> 'in 0,1',
			'eol'					=> 'in '.implode(',', [ZBX_EOL_LF, ZBX_EOL_CRLF]),
			'headers'				=> 'array',
			'host_proxy'			=> 'db hosts.proxy_hostid',
			'http_proxy'			=> 'string',
			'follow_redirects'		=> 'in 0,1',
			'key'					=> 'string',
			'interface'				=> 'array',
			'ipmi_sensor'			=> 'string',
			'item_type'				=> 'in '.implode(',', [ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C, ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT]),
			'jmx_endpoint'			=> 'string',
			'macros'				=> 'array',
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params'				=> 'string',
			'password'				=> 'string',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'string',
			'prev_time'				=> 'string',
			'prev_value'			=> 'string',
			'privatekey'			=> 'string',
			'publickey'				=> 'string',
			'query_fields'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'show_final_result'		=> 'in 0,1',
			'snmp_oid'				=> 'string',
			'snmp_community'		=> 'string',
			'snmpv3_securityname'	=> 'string',
			'snmpv3_contextname'	=> 'string',
			'snmpv3_securitylevel'	=> 'string',
			'snmpv3_authprotocol'	=> 'in '.implode(',', [ITEM_AUTHPROTOCOL_MD5, ITEM_AUTHPROTOCOL_SHA]),
			'snmpv3_authpassphrase'	=> 'string',
			'snmpv3_privprotocol'	=> 'string',
			'snmpv3_privpassphrase'	=> 'string',
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
			'valuemapid'			=> 'int32',
			'verify_host'			=> 'in 0,1',
			'verify_peer'			=> 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->get_value_from_host = (bool) $this->getInput('get_value');
			$this->item_type = $this->hasInput('item_type') ? $this->getInput('item_type') : -1;
			$this->preproc_item = self::getPreprocessingItemClassInstance($this->getInput('test_type'));
			$this->is_item_testable = in_array($this->item_type, self::$testable_item_types);

			$interface = $this->getInput('interface', []);
			$steps = $this->getInput('steps', []);
			$prepr_types = zbx_objectValues($steps, 'type');
			$this->use_prev_value = (count(array_intersect($prepr_types, self::$preproc_steps_using_prev_value)) > 0);
			$this->show_final_result = ($this->getInput('show_final_result') == 1);

			/*
			 * Check if key is not empty if 'get value from host' is checked and test is made for item with mandatory
			 * key.
			 */
			if ($this->get_value_from_host && $this->getInput('key', '') === ''
					&& in_array($this->item_type, $this->item_types_has_key_mandatory)) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'key_', _('cannot be empty')));
				$ret = false;
			}

			// Test interface properties.
			if ($this->get_value_from_host) {
				if (!array_key_exists('address', $interface) || $interface['address'] === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Host address'), _('cannot be empty')));
					$ret = false;
				}

				if (!array_key_exists('port', $interface) || $interface['port'] === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Port'), _('cannot be empty')));
					$ret = false;
				}
			}

			// Check preprocessing steps.
			if ($steps && ($error = $this->preproc_item->validateItemPreprocessingSteps($steps)) !== true) {
				error($error);
				$ret = false;
			}

			// Check previous time.
			if ($this->use_prev_value) {
				$prev_time = $this->getInput('prev_time', '');

				$relative_time_parser = new CRelativeTimeParser();
				if ($relative_time_parser->parse($prev_time) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
						_('a relative time is expected')
					));
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
					'main_block' => CJs::encodeJson([
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

		// Define values used to test preprocessing steps.
		$preproc_test_data = [
			'value' => $this->getInput('value', ''),
			'value_type' => $this->getInput('value_type', ITEM_VALUE_TYPE_STR),
			'steps' => $this->getInput('steps', []), // Steps can be empty to test value convertation.
			'single' => !$this->show_final_result
		];

		// Get previous value and time.
		if ($this->use_prev_value) {
			$preproc_test_data['history'] = [
				'value' => $this->getInput('prev_value', ''),
				'timestamp' => $this->getInput('prev_time')
			];
		}

		$output = [
			'steps' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Get value from host.
		if ($this->get_value_from_host) {
			$item_test_data = $this->getItemTestProperties($this->getInputAll());
			$item_test_data = $this->unsetEmptyValues($item_test_data);

			// Send test to be executed on Zabbix server.
			$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
			$result = $server->testItem($item_test_data, get_cookie('zbx_sessionid'));

			if ($result === false) {
				error($server->getError());
			}
			elseif (is_array($result)) {
				if (array_key_exists('result', $result)) {
					// Move current value to previous value field.
					if ($this->use_prev_value) {
						$preproc_test_data['history']['value'] = $preproc_test_data['value'];
						$preproc_test_data['history']['timestamp'] = $this->getPrevTime();

						$output['prev_value'] = $preproc_test_data['value'];
						$output['prev_time'] = $preproc_test_data['history']['timestamp'];
					}

					// Apply new value to preprocessing test.
					$preproc_test_data['value'] = $result['result'];
					$output['value'] = $result['result'];
				}

				if (array_key_exists('error', $result) && $result['error'] !== '') {
					error($result['error']);
				}
			}

			if (($messages = getMessages(false)) !== null) {
				$output['messages'] = $messages->toString();
			}
		}

		// Test preprocessing steps.
		$this->eol = parent::getInput('eol', ZBX_EOL_LF);

		if (!array_key_exists('messages', $output)) {
			$preproc_test_data['steps'] = $this->resolvePreprocessingStepMacros($preproc_test_data['steps']);

			// Send test details to Zabbix server.
			$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
			$result = $server->testPreprocessingSteps($preproc_test_data, get_cookie('zbx_sessionid'));

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
					}

					unset($step['type']);
					unset($step['params']);
					unset($step['error_handler']);
					unset($step['error_handler_params']);

					// Latest executed step due to the error or end of preprocessing.
					$test_outcome = $step + ['action' => ZBX_PREPROC_FAIL_DEFAULT];
				}
				unset($step);

				if (array_key_exists('previous', $result) && $result['previous'] === true) {
					error(_s('Incorrect value for "%1$s" field.', _('Previous value')));
				}
				elseif ($this->show_final_result) {
					if (array_key_exists('result', $result)) {
						$output['final'] = [
							'action' => _s('Result converted to %1$s',
								itemValueTypeString($preproc_test_data['value_type'])),
							'result' => $result['result']
						];

						if ($this->getInput('valuemapid', 0)) {
							$mapped_value = getMappedValue($result['result'], $this->getInput('valuemapid'));
							if ($mapped_value !== false) {
								$output['mapped_value'] = $mapped_value;
							}
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

		$this->setResponse((new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView());
	}

	/**
	 * Resolve macros used in preprocessing step parameter fields.
	 *
	 * @param array $steps  Steps from item test input form.
	 *
	 * @return array
	 */
	protected function resolvePreprocessingStepMacros(array $steps) {
		// Resolve macros used in parameter fields.
		$macros_posted = $this->getInput('macros', []);
		$macros_types = ($this->preproc_item instanceof CItemPrototype)
			? ['usermacros' => true, 'lldmacros' => true]
			: ['usermacros' => true];

		foreach ($steps as &$step) {
			/*
			 * Values received from user input form may be transformed so we must remove redundant "\r" before
			 * sending data to Zabbix server.
			 */
			$step['params'] = str_replace("\r\n", "\n", $step['params']);

			// Resolve macros in parameter fields before send data to Zabbix server.
			foreach (['params', 'error_handler_params'] as $field) {
				$matched_macros = (new CMacrosResolverGeneral)->getMacroPositions($step[$field], $macros_types);

				foreach (array_reverse($matched_macros, true) as $pos => $macro) {
					$macro_value = array_key_exists($macro, $macros_posted)
						? $macros_posted[$macro]
						: '';

					$step[$field] = substr_replace($step[$field], $macro_value, $pos, strlen($macro));
				}
			}
		}
		unset($step);

		return $steps;
	}

	public function getInput($var, $default = null) {
		$value = parent::getInput($var, $default);
		if ($var === 'value' || $var === 'prev_value') {
			$value = str_replace("\r\n", "\n", $value);

			if ($this->eol == ZBX_EOL_CRLF) {
				$value = str_replace("\n", "\r\n", $value);
			}
		}

		return $value;
	}
}
