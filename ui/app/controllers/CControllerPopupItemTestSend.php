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

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'item_type' => [
				['db items.type',
					'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_EXTERNAL,
						ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
						ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER, ITEM_TYPE_IPMI
					]
				]
			],
			'test_type' => ['integer', 'required',
				'in' => [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD,
					self::ZBX_TEST_TYPE_LLD_PROTOTYPE
				]
			],
			'show_final_result' => ['boolean'],
			'get_value' => ['boolean'],
			'test_with' => ['integer', 'in' => [self::TEST_WITH_SERVER, self::TEST_WITH_PROXY]],
			'proxyid' => ['db proxy.proxyid', 'required', 'when' => [
				['get_value', 'in' => [1]],
				['test_with', 'in'  => [self::TEST_WITH_PROXY]]
			]],
			'interface' => ['object',
				'fields' => [
					'useip' => ['boolean'],
					'details' => ['object',
						'fields' => [
							'version' => ['integer', 'required', 'in' => [SNMP_V1, SNMP_V2C, SNMP_V3]],
							'community' => ['string', 'required', 'not_empty',
								'when' => ['version', 'in' => [SNMP_V1, SNMP_V2C]]
							],
							'max_repetitions' => ['db interface_snmp.max_repetitions', 'required', 'not_empty',
								'min' => 1, 'max' => ZBX_MAX_INT32,
								'when' => ['version', 'in' => [SNMP_V2C, SNMP_V3]]
							],
							'contextname' => ['db interface_snmp.contextname', 'when' => ['version', 'in' => [SNMP_V3]]],
							'securityname' => ['db interface_snmp.securityname', 'when' => ['version', 'in' => [SNMP_V3]]],
							'securitylevel' => [
								'db interface_snmp.securitylevel', 'required',
								'in' => [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,
									ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
								],
								'when' => ['version', 'in' => [SNMP_V3]]
							],
							'authprotocol' => [
								'db interface_snmp.authprotocol', 'required',
								'in' => [ITEM_SNMPV3_AUTHPROTOCOL_MD5, ITEM_SNMPV3_AUTHPROTOCOL_SHA1,
									ITEM_SNMPV3_AUTHPROTOCOL_SHA224, ITEM_SNMPV3_AUTHPROTOCOL_SHA256,
									ITEM_SNMPV3_AUTHPROTOCOL_SHA384, ITEM_SNMPV3_AUTHPROTOCOL_SHA512
								],
								'when' => ['securitylevel', 'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,
									ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV
								]]
							],
							'authpassphrase' => ['db interface_snmp.authpassphrase', 'when' => [
								'securitylevel',
								'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]
							]],
							'privprotocol' => [
								'db interface_snmp.privprotocol', 'required',
								'in' => [ITEM_SNMPV3_PRIVPROTOCOL_DES, ITEM_SNMPV3_PRIVPROTOCOL_AES128,
									ITEM_SNMPV3_PRIVPROTOCOL_AES192, ITEM_SNMPV3_PRIVPROTOCOL_AES256,
									ITEM_SNMPV3_PRIVPROTOCOL_AES192C, ITEM_SNMPV3_PRIVPROTOCOL_AES256C
								],
								'when' => ['securitylevel', 'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]]
							],
							'privpassphrase' => ['db interface_snmp.privpassphrase', 'when' => [
								'securitylevel',
								'in' => [ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]
							]]
						],
						'when' => ['../item_type', 'in' => [ITEM_TYPE_SNMP]]
					],
					'address' => ['db interface.dns', 'not_empty', 'required',
						'when' => ['../item_type', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_IPMI, ITEM_TYPE_SIMPLE,
							ITEM_TYPE_SNMP, ITEM_TYPE_SSH, ITEM_TYPE_TELNET
						]]
					],
					'port' => ['db interface.port', 'not_empty', 'required',
						'use' => [CNumberValidator::class, ['usermacros' => true, 'with_float' => false,
							'min' => ZBX_MIN_PORT_NUMBER, 'max' => ZBX_MAX_PORT_NUMBER
						]],
						'when' => ['../item_type', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_IPMI, ITEM_TYPE_SNMP]]
					]
				],
				'when' => ['get_value', 'in' => [1]]
			],
			'value' => ['string'],
			'not_supported' => ['integer', 'in' => [self::SUPPORTED_STATE, self::NOT_SUPPORTED_STATE]],
			'runtime_error' => ['string'],
			'prev_value' => ['string'],
			'prev_time'	=> ['string',
				'use' => [CRelativeTimeValidator::class, ['allowed_suffixes' => self::$supported_time_suffixes,
					'allowed_types' => [CRelativeTimeParser::ZBX_TOKEN_OFFSET], 'max_now' => true, 'max_tokens' => 1
				]]
			],
			'eol' => ['integer', 'in' => [ZBX_EOL_LF, ZBX_EOL_CRLF]],
			'macros' => ['objects', 'fields' => [
				'name' => ['db globalmacro.macro', 'required'],
				'value' => ['db globalmacro.value', 'required']
			]],
			'time_change' => ['integer'],

			// Hidden form input data: passed from item form
			'hostid' => ['db hosts.hostid'],
			'authtype' => ['integer',
				'in' => [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
					ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY
				]
			],
			'headers' => ['objects', 'fields' => [
				'name' => ['string', 'required'],
				'value' => ['string', 'required']
			]],
			'http_authtype' => ['db items.authtype',
				'in' => [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
					ZBX_HTTP_AUTH_DIGEST
				]
			],
			'http_password' => ['string'],
			'http_proxy' => ['string'],
			'http_username' => ['string'],
			'flags' => ['integer',
				'in' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE,ZBX_FLAG_DISCOVERY_PROTOTYPE,
					ZBX_FLAG_DISCOVERY_CREATED, ZBX_FLAG_DISCOVERY_RULE_CREATED, ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE,
					ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE_CREATED
				]
			],
			'follow_redirects' => ['boolean'],
			'key' => [
				['db items.key_'],
				[
					'db items.key_', 'required', 'not_empty', 'use' => [CItemKey::class, []],
					'when' => [
						['get_value', 'in' => [1]],
						['item_type', 'in' => self::$item_types_has_key_mandatory]
					]
				],
				[
					'db items.key_', 'regex' => '/^(?!(vmware\\.|icmpping))/',
					'when' => [
						['get_value', 'in' => [1]],
						['item_type', 'in' => [ITEM_TYPE_SIMPLE]]
					]
				]
			],
			'ipmi_sensor' => ['string'],
			'jmx_endpoint' => ['string'],
			'output_format' => ['integer', 'in' => [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]],
			'params_ap' => ['string'],
			'params_es' => ['string'],
			'params_f' => [
				['db items.params', 'required', 'not_empty',
					'use' => [CCalcFormulaValidator::class, []],
					'when' => [
						['item_type', 'in' => [ITEM_TYPE_CALCULATED]],
						['test_type',
							'not_in' => [self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD_PROTOTYPE]
						]
					]
				],
				['db items.params', 'required', 'not_empty',
					'use' => [CCalcFormulaValidator::class, ['lldmacros' => true]],
					'when' => [
						['item_type', 'in' => [ITEM_TYPE_CALCULATED]],
						['test_type', 'in' => [self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD_PROTOTYPE]]
					]
				]
			],
			'script' => ['string'],
			'browser_script' => ['string'],
			'password' => ['string'],
			'post_type' => ['integer', 'in' => [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]],
			'posts' => ['string'],
			'privatekey' => ['string'],
			'publickey' => ['string'],
			'query_fields' => ['objects', 'fields' => [
				'name' => ['string', 'required'],
				'value' => ['string', 'required']
			]],
			'parameters' => ['objects', 'fields' => [
				'name' => ['string', 'required'],
				'value' => ['string', 'required']
			]],
			'request_method' => ['integer',
				'in' => [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]
			],
			'retrieve_mode' => ['integer',
				'in' => [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
					HTTPTEST_STEP_RETRIEVE_MODE_BOTH
				]
			],
			'snmp_oid' => ['string'],
			'ssl_cert_file' => ['string'],
			'ssl_key_password' => ['string'],
			'status_codes' => ['string'],
			'steps' => [
				array_merge(CItemGeneralHelper::getPreprocessingValidationRules(false),
					['when' => ['test_type', 'in' => [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_LLD]]]
				),
				array_merge(CItemGeneralHelper::getPreprocessingValidationRules(true),
					['when' => ['test_type',
						'in' => [self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD_PROTOTYPE]
					]]
				)
			],
			'timeout' => ['string'],
			'username' => ['string'],
			'url' => ['string'],
			'value_type' => ['integer',
				'in' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG,
					ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_JSON
				]
			],
			'valuemapid' => ['db valuemap.valuemapid'],
			'verify_host' => ['boolean'],
			'verify_peer' => ['boolean']
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput(self::getValidationRules());

		if ($ret) {
			$testable_item_types = self::getTestableItemTypes($this->getInput('hostid', '0'));
			$this->get_value_from_host = (bool) $this->getInput('get_value');
			$this->item_type = $this->hasInput('item_type') ? $this->getInput('item_type') : -1;
			$this->test_type = $this->getInput('test_type');
			$this->is_item_testable = in_array($this->item_type, $testable_item_types);

			$steps = $this->getInput('steps', []);
			$prepr_types = zbx_objectValues($steps, 'type');
			$this->use_prev_value = (count(array_intersect($prepr_types, self::$preproc_steps_using_prev_value)) > 0);
			$this->show_final_result = ($this->getInput('show_final_result') == 1);
		}

		$messages = array_column(get_and_clear_messages(), 'message');

		if (!$ret || $messages) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => $messages
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
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

		if ($this->get_value_from_host) {
			$history = [
				'value' => $this->getInput('value', ''),
				'timestamp' => $this->getInput('prev_time', '') === '' ? '' : $this->getPrevTime()
			];
			$data += $this->prepareTestData();
		}
		else {
			$history = [
				'value' => $this->getInput('prev_value', ''),
				'timestamp' => $this->getInput('prev_time', '')
			];
			$data['item']['value'] = $this->getInput('value', '');
			$data['options']['state'] = (int) $this->getInput('not_supported', self::SUPPORTED_STATE);

			if ($data['options']['state'] == self::NOT_SUPPORTED_STATE) {
				$data['options']['runtime_error'] = $this->getInput('runtime_error', '');
			}
		}

		if ($this->use_prev_value && $history['timestamp'] !== '') {
			$data['options']['history'] = $history;
		}

		$data['item']['value_type'] = $this->getInput('value_type', ITEM_VALUE_TYPE_STR);

		// Steps array can be empty if only value conversion is tested.
		$steps_data = $this->resolvePreprocessingStepMacros(
			normalizeItemPreprocessingSteps($this->getInput('steps', []))
		);

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
