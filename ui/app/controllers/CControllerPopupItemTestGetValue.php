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
 * Controller to perform 'get value from host' action in item test dialog.
 */
class CControllerPopupItemTestGetValue extends CControllerPopupItemTest {

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
			'test_with' => ['integer', 'in' => [self::TEST_WITH_SERVER, self::TEST_WITH_PROXY]],
			'proxyid' => ['db proxy.proxyid', 'required', 'when' => ['test_with', 'in'  => [self::TEST_WITH_PROXY]]],
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
				]
			],
			'value' => ['string'],
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
					'when' => ['item_type', 'in' => self::$item_types_has_key_mandatory]
				],
				[
					'db items.key_', 'regex' => '/^(?!(vmware\\.|icmpping))/',
					'when' => ['item_type', 'in' => [ITEM_TYPE_SIMPLE]]
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
			'timeout' => ['string'],
			'username' => ['string'],
			'url' => ['string'],
			'value_type' => ['integer',
				'in' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG,
					ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_JSON
				]
			],
			'verify_host' => ['boolean'],
			'verify_peer' => ['boolean']
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}
		else {
			$this->item_type = $this->getInput('item_type');
			$this->test_type = $this->getInput('test_type');
			$this->is_item_testable = true;
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$output = [
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Send test to be executed on Zabbix server.
		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::ITEM_TEST_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);
		$result = $server->testItem($this->prepareTestData(), CSessionHelper::getId());

		// Handle the response.
		if ($result === false) {
			error($server->getError());
		}
		elseif (array_key_exists('error', $result)) {
			error($result['error']);
		}
		elseif (array_key_exists('error', $result['item'])) {
			error($result['item']['error']);
		}
		else {
			$output['prev_value'] = $this->getInput('value', '');
			$output['prev_time'] = $this->getPrevTime();
			$output['value'] = $result['item']['result'];
			$output['eol'] = $result['item']['eol'] === 'CRLF' ? ZBX_EOL_CRLF : ZBX_EOL_LF;

			if (array_key_exists('truncated', $result['item']) && $result['item']['truncated']) {
				$output['value_warning'] = _s('Result is truncated due to its size (%1$s).',
					convertUnits(['value' => $result['item']['original_size'], 'units' => 'B'])
				);
			}
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
}
