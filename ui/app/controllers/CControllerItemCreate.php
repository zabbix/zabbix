<?php declare(strict_types=0);
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


class CControllerItemCreate extends CControllerItem {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules()) && $this->validateInputExtended();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add item'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	public function doAction() {
		$output = [];
		$item = $this->getInputForApi();
		$result = API::Item()->create($item);
		$messages = array_column(get_and_clear_messages(), 'message');

		if ($result) {
			$output['success']['title'] = _('Item added');

			if ($messages) {
				$output['success']['messages'] = $messages;
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add item'),
				'messages' => $messages
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function getInputForApi(): array {
		$input = $this->getFormValues();
		$input = CItemHelper::convertFormInputForApi($input);
		$input['hosts'] = API::Host()->get([
			'output' => ['hostid', 'status'],
			'hostids' => [$this->getInput('hostid')],
			'templated_hosts' => true,
			'editable' => true
		]);

		return ['hostid' => $this->getInput('hostid')] + getSanitizedItemFields($input);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			['item.get', ['key_' => '{key}', 'hostid' => '{hostid}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			// Functional fields.
			'context' => ['string', 'in' => ['host', 'template']],
			'hostid' => ['db items.hostid', 'required'],
			'templateid' => ['db items.templateid'],

			// Form.
			'name' => ['db items.name', 'required', 'not_empty'],
			'type' => ['db items.type', 'required', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMP, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_INTERNAL, ITEM_TYPE_TRAPPER,
				ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
				ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED, ITEM_TYPE_HTTPTEST, ITEM_TYPE_DEPENDENT,
				ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
			]],
			'key' => [
				['db items.key_', 'required', 'not_empty', 'use' => [CItemKeyValidator::class, []]],
				['string', 'regex' => '/^(?!'.preg_quote(ZBX_DEFAULT_KEY_DB_MONITOR, '/').')/',
					'messages' => ['regex' => _('Check the key, please. Default example was passed.')],
					'when' => ['type', 'in' => [ITEM_TYPE_DB_MONITOR]]
				],
				['string', 'regex' => '/^(?!'.preg_quote(ZBX_DEFAULT_KEY_SSH, '/').')/',
					'messages' => ['regex' => _('Check the key, please. Default example was passed.')],
					'when' => ['type', 'in' => [ITEM_TYPE_SSH]]
				],
				['string', 'regex' => '/^(?!'.preg_quote(ZBX_DEFAULT_KEY_TELNET, '/').')/',
					'messages' => ['regex' => _('Check the key, please. Default example was passed.')],
					'when' => ['type', 'in' => [ITEM_TYPE_TELNET]]
				]
			],
			'value_type' => ['db items.value_type', 'required', 'in' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY
			]],
			'url' => ['db items.url', 'required', 'not_empty', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'query_fields' => ['objects',
				'fields' => [
					'name' => ['string', 'required', 'not_empty', 'length' => 255],
					'value' => ['string', 'length' => 255],
					'sortorder' => ['integer']
				],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'parameters' => ['objects', 'uniq' => ['name'],
				'fields' => [
					'value' => ['db item_parameter.value'],
					'name' => ['db item_parameter.name', 'required', 'not_empty', 'when' => ['value', 'not_empty']],
					'sortorder' => ['integer']
				],
				'when' => ['type', 'in' => [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]]
			],
			'script' => ['db items.params', 'required', 'not_empty', 'when' => ['type', 'in' => [ITEM_TYPE_SCRIPT]]],
			'browser_script' => ['db items.params', 'required', 'not_empty', 'when' => ['type', 'in' => [
				ITEM_TYPE_BROWSER
			]]],
			'request_method' => ['db items.request_method', 'required', 'in' => [HTTPCHECK_REQUEST_GET,
				HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD
			], 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'post_type' => ['db items.request_method', 'required', 'in' => [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON,
				ZBX_POSTTYPE_XML
			], 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'posts' => ['db items.posts', 'required', 'not_empty', 'when' => [
				['post_type', 'in' => [ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]],
				['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			]],
			'headers' => ['objects',
				'fields' => [
					'name' => ['string', 'required', 'not_empty', 'length' => 255],
					'value' => ['string', 'length' => 2000]
				],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'status_codes' => ['db items.status_codes',
				'use' => [CRangesParser::class, ['usermacros' => true, 'lldmacros' => false, 'with_minus' => true]],
				'messages' => ['use' => _('Invalid range expression.')],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'follow_redirects' => ['db items.follow_redirects', 'in' => [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF,
				HTTPTEST_STEP_FOLLOW_REDIRECTS_ON
			], 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'retrieve_mode' => ['db items.retrieve_mode', 'in' => [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT,
				HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH
			], 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'output_format' => ['db items.output_format', 'in' => [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'http_proxy' => ['db items.http_proxy', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'http_authtype' => ['db items.authtype', 'in' => [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC,
				ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST
			], 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'http_username' => ['db items.username', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'http_password' => ['db items.password', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'verify_peer' => ['db items.verify_peer', 'in' => [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'verify_host' => ['db items.verify_host', 'in' => [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'ssl_cert_file' => ['db items.ssl_cert_file', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'ssl_key_file' => ['db items.ssl_key_file', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'ssl_key_password' => ['db items.ssl_key_password', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'master_itemid' => ['db items.master_itemid', 'required', 'when' => ['type', 'in' => [ITEM_TYPE_DEPENDENT]]],
			'interfaceid' => ['db items.interfaceid', 'required',
				'messages' => ['required' => _('No interface found')],
				'when' => [
					['type', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI,
						ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_HTTPAGENT,
						ITEM_TYPE_SNMP
					]],
					['context', 'in' => ['host']]
				]
			],
			'snmp_oid' => ['db items.snmp_oid', 'not_empty', 'required', 'when' => ['type', 'in' => [ITEM_TYPE_SNMP]]],
			'ipmi_sensor' => ['db items.ipmi_sensor', 'not_empty', 'required', 'when' => [
				['key', 'not_in' => ['ipmi.get']],
				['type', 'in' => [ITEM_TYPE_IPMI]]
			]],
			'authtype' => ['db items.authtype', 'in' => [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY],
				'when' => ['type', 'in' => [ITEM_TYPE_SSH, ITEM_TYPE_HTTPAGENT]]
			],
			'jmx_endpoint' => ['db items.jmx_endpoint', 'required', 'not_empty', 'when' => ['type', 'in' => [
				ITEM_TYPE_JMX
			]]],
			'username' => [
				['db items.username', 'when' => ['type', 'in' => [ITEM_TYPE_JMX, ITEM_TYPE_SIMPLE]]],
				['db items.username', 'required', 'not_empty', 'when' => ['type', 'in' => [ITEM_TYPE_SSH, ITEM_TYPE_TELNET]]]
			],
			'publickey' => ['db items.publickey', 'required', 'not_empty', 'when' => [
				['type', 'in' => [ITEM_TYPE_SSH]],
				['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
			]],
			'privatekey' => ['db items.privatekey', 'required', 'not_empty', 'when' => [
				['type', 'in' => [ITEM_TYPE_SSH]],
				['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
			]],
			'passphrase' => ['db items.password', 'when' => ['type', 'in' => [ITEM_TYPE_SSH]]],
			'password' => ['db items.password', 'when' => ['type', 'in' => [ITEM_TYPE_SSH]]],
			'params_es' => ['db items.params', 'required', 'not_empty', 'when' => ['type', 'in' => [ITEM_TYPE_SSH,
				ITEM_TYPE_TELNET
			]]],
			'params_ap' => ['db items.params', 'required', 'not_empty', 'when' => ['type', 'in' => [
				ITEM_TYPE_DB_MONITOR
			]]],
			'params_f' => ['db items.params', 'required', 'not_empty',
				'use' => [CCalcFormulaValidator::class, ['lldmacros' => false]],
				'when' => ['type', 'in' => [ITEM_TYPE_CALCULATED]]
			],
			'units' => ['db items.units', 'when' => ['value_type', 'in' => [ITEM_VALUE_TYPE_FLOAT,
				ITEM_VALUE_TYPE_UINT64
			]]],
			'delay_flex' => ['objects', 'fields' => [
				'type' => ['integer', 'in' => [ITEM_DELAY_FLEXIBLE, ITEM_DELAY_SCHEDULING]],
				'schedule' => ['string', 'required', 'not_empty',
					'use' => [CSchedulingIntervalParser::class, ['usermacros' => true]],
					'messages' => ['use' => _('Invalid interval.')],
					'when' => ['type', 'in' => [ITEM_DELAY_SCHEDULING]]
				],
				'delay' => ['string', 'required', 'not_empty',
					'use' => [CSimpleIntervalParser::class, ['usermacros' => true]],
					'messages' => ['use' => _('Invalid interval.')],
					'when' => ['type', 'in' => [ITEM_DELAY_FLEXIBLE]]
				],
				'period' => ['string', 'required', 'not_empty',
					'use' => [CTimePeriodParser::class, ['usermacros' => true]],
					'messages' => ['use' => _('Invalid period.')],
					'when' => ['type', 'in' => [ITEM_DELAY_FLEXIBLE]]
				]
			]],
			'delay' => [
				['string', 'not_in' => ['0', ...array_map(fn (string $suffix) => "0$suffix", str_split(ZBX_TIME_SUFFIXES))],
					'messages' => ['not_in' => _('This field cannot be set to "0" without defining custom intervals.')],
					'when' => ['delay_flex', 'empty']
				],
				['db items.delay', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_DAY, 'usermacros' => true]],
					'when' => ['type', 'in' => [
						ITEM_TYPE_CALCULATED, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_EXTERNAL, ITEM_TYPE_HTTPAGENT,
						ITEM_TYPE_INTERNAL, ITEM_TYPE_IPMI, ITEM_TYPE_JMX, ITEM_TYPE_SCRIPT, ITEM_TYPE_SIMPLE,
						ITEM_TYPE_SNMP, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_ZABBIX, ITEM_TYPE_BROWSER
					]]
				],
				['db items.delay', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_DAY, 'usermacros' => true]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_ZABBIX_ACTIVE]],
						['key', 'regex' => '/^(?!mqtt\\.get)/']
					]
				]
			],
			'custom_timeout' => ['integer', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED,
				ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED
			]],
			'timeout' => [
				['db items.timeout', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 10 * SEC_PER_MIN, 'usermacros' => true]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_SIMPLE]],
						['custom_timeout', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]],
						['key', 'regex' => '/^(?!(vmware\\.|icmpping))/']
					]
				],
				['db items.timeout', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 10 * SEC_PER_MIN, 'usermacros' => true]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_SNMP]],
						['custom_timeout', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]],
						['snmp_oid', 'regex' => '/^(get\\[|walk\\[)/']
					]
				],
				['db items.timeout', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 10 * SEC_PER_MIN, 'usermacros' => true]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL,
							ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_HTTPAGENT,
							ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
						]],
						['custom_timeout', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]]
					]
				]
			],
			'history_mode' => ['integer', 'in' => [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]],
			'history' => ['db items.history', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_HOUR, 'max' => 25 * SEC_PER_YEAR, 'usermacros' => true]],
				'when' => ['history_mode', 'in' => [ITEM_STORAGE_CUSTOM]]
			],
			'trends_mode' => ['integer', 'in' => [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]],
			'trends' => ['db items.trends', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR, 'usermacros' => true]],
				'when' => [
					['value_type', 'in' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]],
					['trends_mode', 'in' => [ITEM_STORAGE_CUSTOM]]
				]
			],
			'logtimefmt' => ['db items.logtimefmt'],
			'valuemapid' => ['db items.valuemapid'],
			'allow_traps' => ['db items.allow_traps', 'in' => [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'trapper_hosts' => ['db items.trapper_hosts',
				'use' => [CIPRangeParser::class, ['v6' => ZBX_HAVE_IPV6, 'dns' => true, 'usermacros' => true, 'macros' => ['{HOST.HOST}', '{HOSTNAME}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}']]],
				'when' => ['allow_traps', 'in' => [HTTPCHECK_ALLOW_TRAPS_ON]]
			],
			'inventory_link' => ['db items.inventory_link', 'in' => array_keys([0 => null] + getHostInventories())],
			'description' => ['db items.description'],
			'status' => ['db items.status', 'in' => [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]],
			'tags' => ['objects', 'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db item_tag.value'],
					'tag' => ['db item_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
				]
			],
			'preprocessing' => CItemGeneralHelper::getPreprocessingValidationRules(allow_lld_macro: false)
		]];
	}
}
