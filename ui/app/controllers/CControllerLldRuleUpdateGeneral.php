<?php declare(strict_types=0);
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


abstract class CControllerLldRuleUpdateGeneral extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	protected function checkPermissions(): bool {
		if (!$this->hasInput('context')) {
			return false;
		}

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	abstract static function getValidationRulesApiUniq(): array;

	abstract static function getFieldsValidationRulesAdditional(): array;

	abstract static function getValidationRulesTypeField(): array;

	abstract static function isPrototype(): bool;

	public static function getItemTypes() {
		return [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
			ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
			ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT,
			ITEM_TYPE_BROWSER, ITEM_TYPE_NESTED
		];
	}

	public static function getValidationRules(): array {
		return ['object', 'api_uniq' => static::getValidationRulesApiUniq(), 'fields' => [
			'context' => ['string', 'in' => ['host', 'template']],
			'hostid' => ['db items.hostid', 'required'],
			'templateid' => ['db items.templateid'],
			'discovered' => ['boolean'],
			'host_discovered' => ['boolean', 'required'],
			'name' => ['db items.name', 'required', 'not_empty'],
			'type' => static::getValidationRulesTypeField(),
			'key' => [
				['db items.key_', 'required', 'not_empty',
					'use' => [CItemKeyValidator::class, ['lldmacros' => static::isPrototype()]]
				],
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
			'url' => ['db items.url', 'required', 'not_empty', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'query_fields' => ['objects',
				'fields' => [
					'value' => ['string', 'length' => 255],
					'name' => [
						['string', 'required', 'length' => 255],
						['string', 'required', 'length' => 255, 'not_empty', 'when' => ['value', 'not_empty']]
					],
					'sortorder' => ['integer']
				],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'parameters' => ['objects', 'uniq' => ['name'],
				'fields' => [
					'value' => ['db item_parameter.value'],
					'name' => [
						['db item_parameter.name'],
						['db item_parameter.name', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
					],
					'sortorder' => ['integer']
				],
				'when' => ['type', 'in' => [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER]]
			],
			'script' => ['db items.params', 'required', 'not_empty', 'when' => ['type', 'in' => [ITEM_TYPE_SCRIPT]]],
			'browser_script' => ['db items.params', 'required', 'not_empty',
				'when' => ['type', 'in' => [ITEM_TYPE_BROWSER]]
			],
			'request_method' => ['db items.request_method', 'required',
				'in' => [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'post_type' => ['db items.request_method', 'required',
				'in' => [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'posts' => [
				['db items.posts'],
				['db items.posts', 'required', 'not_empty',
					'when' => [
						['post_type', 'in' => [ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]],
						['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
					]
				]
			],
			'headers' => ['objects',
				'fields' => [
					'value' => ['string', 'length' => 2000],
					'name' => [
						['string', 'required', 'length' => 255],
						['string', 'required', 'length' => 255, 'not_empty', 'when' => ['value', 'not_empty']]
					]
				],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'status_codes' => ['db items.status_codes',
				'use' => [CRangesParser::class, ['usermacros' => true, 'lldmacros' => static::isPrototype(),
					'with_minus' => true
				]],
				'messages' => ['use' => _('Invalid HTTP status code or range.')],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'follow_redirects' => ['db items.follow_redirects',
				'in' => [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'retrieve_mode' => ['db items.retrieve_mode',
				'in' => [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS,
					HTTPTEST_STEP_RETRIEVE_MODE_BOTH
				],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'output_format' => ['db items.output_format', 'in' => [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'http_proxy' => ['db items.http_proxy', 'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]],
			'http_authtype' => ['db items.authtype',
				'in' => [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
					ZBX_HTTP_AUTH_DIGEST
				],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
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
			'master_itemid' => ['db items.master_itemid', 'required',
				'when' => ['type', 'in' => [ITEM_TYPE_DEPENDENT]]
			],
			'interfaceid' => ['db items.interfaceid', 'required',
				'messages' => ['required' => _('No interface found')],
				'when' => [
					['type', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_IPMI,
						ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_HTTPAGENT,
						ITEM_TYPE_SNMP
					]],
					['context', 'in' => ['host']]
				]
			],
			'snmp_oid' => ['db items.snmp_oid', 'not_empty', 'required', 'when' => ['type', 'in' => [ITEM_TYPE_SNMP]]],
			'ipmi_sensor' => ['db items.ipmi_sensor', 'not_empty', 'required',
				'when' => [
					['key', 'not_in' => ['ipmi.get']],
					['type', 'in' => [ITEM_TYPE_IPMI]]
				]
			],
			'authtype' => ['db items.authtype', 'in' => [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY],
				'when' => ['type', 'in' => [ITEM_TYPE_SSH, ITEM_TYPE_HTTPAGENT]]
			],
			'jmx_endpoint' => ['db items.jmx_endpoint', 'required', 'not_empty',
				'when' => ['type', 'in' => [ITEM_TYPE_JMX]]
			],
			'username' => [
				['db items.username',
					'when' => ['type', 'in' => [ITEM_TYPE_SIMPLE, ITEM_TYPE_JMX, ITEM_TYPE_DB_MONITOR]]
				],
				['db items.username', 'required', 'not_empty',
					'when' => ['type', 'in' => [ITEM_TYPE_SSH, ITEM_TYPE_TELNET]]
				]
			],
			'publickey' => ['db items.publickey', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [ITEM_TYPE_SSH]],
					['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
				]
			],
			'privatekey' => ['db items.privatekey', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [ITEM_TYPE_SSH]],
					['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
				]
			],
			'passphrase' => ['db items.password', 'when' => ['type', 'in' => [ITEM_TYPE_SSH]]],
			'password' => [
				['db items.password', 'when' => ['type',
					'in' => [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX]
				]],
				['db items.password', 'required', 'not_empty',
					'when' => [['type', 'in' => [ITEM_TYPE_JMX]], ['username', 'not_empty']]
				],
				['db items.password', 'required', 'in' => [''],
					'when' => [['type', 'in' => [ITEM_TYPE_JMX]], ['username', 'in' => ['']]],
					'messages' => ['in' => _('Both username and password should be either present or empty.')]
				]
			],
			'params_es' => ['db items.params', 'required', 'not_empty',
				'when' => ['type', 'in' => [ITEM_TYPE_SSH, ITEM_TYPE_TELNET]]
			],
			'params_ap' => ['db items.params', 'required', 'not_empty',
				'when' => ['type', 'in' => [ITEM_TYPE_DB_MONITOR]]
			],
			'delay_flex' => ['objects',
				'fields' => [
					'type' => ['integer', 'in' => [ITEM_DELAY_FLEXIBLE, ITEM_DELAY_SCHEDULING]],
					'schedule' => ['string', 'required',
						'use' => [CSchedulingIntervalParser::class, ['usermacros' => true,
							'lldmacros' => static::isPrototype()
						]],
						'messages' => ['use' => _('Invalid interval.')],
						'when' => ['type', 'in' => [ITEM_DELAY_SCHEDULING]]
					],
					'delay' => ['string', 'required',
						'use' => [CSimpleIntervalParser::class, ['usermacros' => true,
							'lldmacros' => static::isPrototype()
						]],
						'messages' => ['use' => _('Invalid interval.')],
						'when' => ['type', 'in' => [ITEM_DELAY_FLEXIBLE]]
					],
					'period' => [
						[
							'string', 'required',
							'use' => [CTimePeriodParser::class,
								['usermacros' => true, 'lldmacros' => static::isPrototype()]
							],
							'messages' => ['use' => _('Invalid period.')],
							'when' => ['type', 'in' => [ITEM_DELAY_FLEXIBLE]]
						],
						['string', 'required', 'not_empty', 'when' => ['delay', 'not_empty']]
					]
				],
				'when' => ['type',
					'not_in' => [ITEM_TYPE_SNMPTRAP, ITEM_TYPE_TRAPPER, ITEM_TYPE_DEPENDENT, ITEM_TYPE_NESTED]
				]
			],
			'delay' => [
				['string',
					'not_in' => ['0', ...array_map(fn (string $suffix) => "0$suffix", str_split(ZBX_TIME_SUFFIXES))],
					'messages' => ['not_in' => _('This field cannot be set to "0" without defining custom intervals.')],
					'when' => [
						['delay_flex', 'empty'],
						['type',
							'not_in' => [ITEM_TYPE_SNMPTRAP, ITEM_TYPE_TRAPPER, ITEM_TYPE_DEPENDENT, ITEM_TYPE_NESTED]
						]
					]
				],
				['db items.delay', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_DAY, 'usermacros' => true,
						'lldmacros' => static::isPrototype()
					]],
					'when' => ['type', 'in' => [
						ITEM_TYPE_DB_MONITOR, ITEM_TYPE_EXTERNAL, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_INTERNAL,
						ITEM_TYPE_IPMI, ITEM_TYPE_JMX, ITEM_TYPE_SCRIPT, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMP,
						ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_ZABBIX, ITEM_TYPE_BROWSER
					]]
				],
				['db items.delay', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_DAY, 'usermacros' => true,
						'lldmacros' => static::isPrototype()
					]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_ZABBIX_ACTIVE]],
						['key', 'regex' => '/^(?!mqtt\\.get)/']
					]
				]
			],
			'custom_timeout' => ['integer',
				'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED, ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED]
			],
			'timeout' => [
				['db items.timeout', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 10 * SEC_PER_MIN,
						'usermacros' => true, 'lldmacros' => static::isPrototype()
					]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_SIMPLE]],
						['custom_timeout', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]],
						['key', 'regex' => '/^(?!(vmware\\.|icmpping))/']
					]
				],
				['db items.timeout', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 10 * SEC_PER_MIN,
						'usermacros' => true, 'lldmacros' => static::isPrototype()
					]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_SNMP]],
						['custom_timeout', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]],
						['snmp_oid', 'regex' => '/^(get\\[|walk\\[)/']
					]
				],
				['db items.timeout', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 10 * SEC_PER_MIN,
						'usermacros' => true, 'lldmacros' => static::isPrototype()
					]],
					'when' => [
						['type', 'in' => [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL,
							ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_HTTPAGENT,
							ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
						]],
						['custom_timeout', 'in' => [ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]]
					]
				]
			],
			'lifetime_type' => ['db items.lifetime_type',
				'in' => [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY]
			],
			'lifetime' => ['db items.enabled_lifetime', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_HOUR, 'max' => 25 * SEC_PER_YEAR,
					'usermacros' => true, 'lldmacros' => static::isPrototype()
				]],
				'when' => ['lifetime_type', 'in' => [ZBX_LLD_DELETE_AFTER]]
			],
			'enabled_lifetime_type' => ['db items.enabled_lifetime_type',
				'in' => [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY],
				'when' => ['lifetime_type', 'not_in' => [ZBX_LLD_DELETE_IMMEDIATELY]]
			],
			'enabled_lifetime' => ['db items.enabled_lifetime',  'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_HOUR, 'max' => 25 * SEC_PER_YEAR,
					'usermacros' => true, 'lldmacros' => static::isPrototype()
				]],
				'when' => ['enabled_lifetime_type', 'in' => [ZBX_LLD_DISABLE_AFTER]]
			],
			'allow_traps' => ['db items.allow_traps', 'in' => [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON],
				'when' => ['type', 'in' => [ITEM_TYPE_HTTPAGENT]]
			],
			'trapper_hosts' => [
				['db items.trapper_hosts',
					'use' => [CIPRangeParser::class, ['v6' => ZBX_HAVE_IPV6, 'dns' => true, 'usermacros' => true,
						'macros' => ['{HOST.HOST}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{HOST.DNS}']
					]],
					'when' => ['allow_traps', 'in' => [HTTPCHECK_ALLOW_TRAPS_ON]]
				],
				['db items.trapper_hosts',
					'use' => [CIPRangeParser::class, ['v6' => ZBX_HAVE_IPV6, 'dns' => true, 'usermacros' => true,
						'macros' => ['{HOST.HOST}', '{HOST.NAME}', '{HOST.CONN}', '{HOST.IP}', '{HOST.DNS}']
					]],
					'when' => ['type', 'in' => [ITEM_TYPE_TRAPPER]]
				]
			],
			'description' => ['db items.description'],
			'status' => ['db items.status', 'in' => [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]],
			'lld_macro_paths' => ['objects', 'uniq' => ['lld_macro'], 'fields' => [
				'path' => ['db lld_macro_path.path'],
				'lld_macro' => [
					['db lld_macro_path.lld_macro', 'use' => [CLLDMacroParser::class],
						'messages' => ['use' => _('Expected LLD macro format is "{#MACRO}".')]
					],
					['db lld_macro_path.lld_macro', 'required', 'not_empty', 'when' => ['path', 'not_empty']]
				]
			]],
			'evaltype' => ['integer', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND,
				CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]
			],
			'formula' => ['db items.formula', 'required', 'not_empty',
				'use' => [CConditionFormulaParser::class],
				'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
			],
			'conditions' => [
				['objects', 'required', 'fields' => [
					'formulaid' => ['string', 'required',
						'when' => ['../evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
					],
					'operator' => ['integer', 'required',
						'in' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS,
							CONDITION_OPERATOR_NOT_EXISTS
						]
					],
					'value' => ['db item_condition.value', 'required',
						'when' => ['operator', 'in' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]]
					],
					'macro' => [
						['db item_condition.macro', 'use' => [CLLDMacroParser::class],
							'messages' => ['use' => _('Expected LLD macro format is "{#MACRO}".')]
						],
						['db item_condition.macro', 'required', 'not_empty',
							'when' => [
								['operator', 'in' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]],
								['value', 'not_empty']
							]
						]
					]
				]]
			],
			'overrides' => ['objects', 'uniq' => ['name'], 'fields' => [
				'name' => ['db lld_override.name', 'required', 'not_empty'],
				'step' => ['db lld_override.step', 'required', 'min' => 1, 'max' => ZBX_MAX_INT32],
				'stop'  => ['db lld_override.step', 'required',
					'in' => [ZBX_LLD_OVERRIDE_STOP_NO, ZBX_LLD_OVERRIDE_STOP_YES]
				],
				'filter' => ['object',
					'fields' => [
						'evaltype' => ['integer', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND,
							CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]
						],
						'formula' => ['db lld_override.formula', 'required',
							'use' => [CConditionFormulaParser::class],
							'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
						],
						'conditions' => ['objects', 'fields' => [
							'formulaid' => ['string', 'required',
								'when' => ['../evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
							],
							'operator' => ['integer', 'required',
								'in' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS,
									CONDITION_OPERATOR_NOT_EXISTS
								]
							],
							'value' => ['db lld_override_condition.value', 'required',
								'when' => ['operator', 'in' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]]
							],
							'macro' => [
								['db lld_override_condition.macro', 'use' => [CLLDMacroParser::class],
									'messages' => ['use' => _('Expected LLD macro format is "{#MACRO}".')]
								],
								['db lld_override_condition.macro', 'required', 'not_empty',
									'when' => [
										['operator', 'in' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]],
										['value', 'not_empty']
									]
								]
							]
						]]
					]
				],
				'operations' => ['objects', 'fields' => [
					'operationobject' => ['integer', 'required',
						'in' => [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE,
							OPERATION_OBJECT_GRAPH_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE,
							OPERATION_OBJECT_LLD_RULE_PROTOTYPE
						]
					],
					'operator' => ['integer',
						'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
							CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP
						]
					],
					'value' => ['db lld_override_operation.value'],
					'opdiscover' => ['object', 'fields' => [
						'discover' => ['integer', 'required',
							'in' => [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]
						]
					]],
					'opstatus' => ['object',
						'fields' => [
							'status' => ['integer', 'required',
								'in' => [ZBX_PROTOTYPE_STATUS_ENABLED, ZBX_PROTOTYPE_STATUS_DISABLED]
							]
						],
						'when' => ['operationobject',
							'in' => [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE,
								OPERATION_OBJECT_HOST_PROTOTYPE, OPERATION_OBJECT_LLD_RULE_PROTOTYPE
							]
						]
					],
					'opperiod' => ['object',
						'fields' => [
							'delay' => ['db lld_override_opperiod.delay', 'required']
						],
						'when' => ['operationobject',
							'in' => [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_LLD_RULE_PROTOTYPE]
						]
					],
					'ophistory' => ['object',
						'fields' => [
							'history' => ['db lld_override_ophistory.history', 'required', 'not_empty',
								'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_HOUR, 'max'=> 25 * SEC_PER_YEAR,
									'accept_zero' => true, 'lldmacros' => true, 'usermacros' => true
								]]
							]
						],
						'when' => ['operationobject', 'in' => [OPERATION_OBJECT_ITEM_PROTOTYPE]]
					],
					'optrends' => ['object',
						'fields' => [
							'trends' => ['db lld_override_optrends.trends', 'required', 'not_empty',
								'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max'=> 25 * SEC_PER_YEAR,
									'accept_zero' => true, 'lldmacros' => true, 'usermacros' => true
								]]
							]
						],
						'when' => ['operationobject', 'in' => [OPERATION_OBJECT_ITEM_PROTOTYPE]]
					],
					'opseverity' => ['object',
						'fields' => [
							'severity' => ['integer', 'required',
								'in' => [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION,
									TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH,
									TRIGGER_SEVERITY_DISASTER
								]
							]
						],
						'when' => ['operationobject', 'in' => [OPERATION_OBJECT_TRIGGER_PROTOTYPE]]
					],
					'optag' => ['objects',
						'fields' => [
							'value' => ['db optag.value'],
							'tag' => [
								['db optag.tag'],
								['db optag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
							]
						],
						'when' => ['operationobject',
							'in' => [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE,
								OPERATION_OBJECT_HOST_PROTOTYPE
							]
						]
					],
					'optemplate' => ['objects', 'uniq' => ['templateid'],
						'fields' => [
							'templateid' => ['db optemplate.optemplateid', 'required']
						],
						'when' => ['operationobject', 'in' => [OPERATION_OBJECT_HOST_PROTOTYPE]]
					],
					'opinventory' => ['object',
						'fields' => [
							'inventory_mode' => ['integer', 'required',
								'in' => [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
							]
						],
						'when' => ['operationobject', 'in' => [OPERATION_OBJECT_HOST_PROTOTYPE]]
					]
				]]
			]],
			'preprocessing' => CItemGeneralHelper::getPreprocessingValidationRules(static::isPrototype())
		] + static::getFieldsValidationRulesAdditional()];
	}
}
