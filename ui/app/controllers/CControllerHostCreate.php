<?php declare(strict_types = 0);
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
 * Controller for host creation.
 */
class CControllerHostCreate extends CControllerHostUpdateGeneral {

	public static function getValidationRules(): array {
		$host_inventory_fields = [];

		foreach (array_column(getHostInventories(), 'db_field') as $field_name) {
			$host_inventory_fields[$field_name] = ['db host_inventory.'.$field_name];
		}

		$api_uniq = [
			['host.get', ['host' => '{host}']],
			['host.get', ['name' => '{visiblename}']],
			['host.get', ['name' => '{host}']],
			['template.get', ['host' => '{host}']],
			['template.get', ['name' => '{visiblename}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'host' => ['db hosts.host', 'required', 'not_empty', 'regex' => '/^'.ZBX_PREG_HOST_FORMAT.'$/',
				'messages' => ['regex' => _('Incorrect characters used for host name.')]
			],
			'visiblename' => ['db hosts.host'],
			'description' => ['db hosts.description'],
			'status' => ['db hosts.status', 'required', 'in' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]],
			'monitored_by' => ['db hosts.monitored_by', 'in' => [ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP]],
			'proxyid' => ['db hosts.proxyid', 'required', 'when' => ['monitored_by', 'in' => [ZBX_MONITORED_BY_PROXY]]],
			'proxy_groupid' => ['db hosts.proxy_groupid', 'required', 'when' => ['monitored_by', 'in' => [ZBX_MONITORED_BY_PROXY_GROUP]]],
			'interfaces' => ['objects', 'fields' => [
				'isNew' => [],
				'type' => ['db interface.type', 'required', 'in' => [INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP,
					INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX
				]],
				'useip' => ['db interface.useip', 'required', 'in' => [INTERFACE_USE_DNS, INTERFACE_USE_IP]],
				'ip' => [
					['db interface.ip', 'use' => [CIPParser::class, [
							'usermacros' => true, 'lldmacros' => true, 'macros' => true, 'v6' => ZBX_HAVE_IPV6
						]],
						'messages' => ['use' => _('Invalid IP address.')]
					],
					['db interface.ip', 'required', 'not_empty', 'when' => ['useip', 'in' => [INTERFACE_USE_IP]]]
				],
				'dns' => [
					['db interface.dns', 'use' => [CDnsParser::class, [
							'usermacros' => true, 'lldmacros' => true, 'macros' => true
						]],
						'messages' => ['use' => _('Incorrect DNS provided.')]
					],
					['db interface.dns', 'required', 'not_empty', 'when' => ['useip', 'in' => [INTERFACE_USE_DNS]]]
				],
				'port' => ['db interface.port', 'required', 'not_empty',
					'use' => [CPortParser::class, ['usermacros' => true]],
					'messages' => ['use' => _('Incorrect port.')]
				],
				'details' => ['object', 'fields' => [
					'version' => ['db interface_snmp.version', 'required', 'in' => [SNMP_V1, SNMP_V2C, SNMP_V3]],
					'bulk' => ['db interface_snmp.bulk', 'required', 'in' => [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED]],
					'community' => ['db interface_snmp.community', 'required', 'not_empty', 'when' => [
						'version', 'in' => [SNMP_V1, SNMP_V2C]
					]],
					'max_repetitions' => ['db interface_snmp.max_repetitions', 'in 1:'.ZBX_MAX_INT32, 'when' => [
						'version', 'in' => [SNMP_V2C, SNMP_V3]
					]],
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
				]]
			]],
			'main_interface_'.INTERFACE_TYPE_AGENT => ['db interface.interfaceid'],
			'main_interface_'.INTERFACE_TYPE_SNMP => ['db interface.interfaceid'],
			'main_interface_'.INTERFACE_TYPE_IPMI => ['db interface.interfaceid'],
			'main_interface_'.INTERFACE_TYPE_JMX => ['db interface.interfaceid'],
			'groups_new' => ['array', 'field' => ['db hstgrp.name']],
			'groups' => [
				['array', 'field' => ['db hstgrp.groupid']],
				['array', 'required', 'not_empty', 'when' => ['groups_new', 'empty']]
			],
			'tags' => ['objects', 'uniq' => ['tag', 'value'],
				'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
				'fields' => [
					'value' => ['db host_tag.value'],
					'tag' => ['db host_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
				]
			],
			'templates' => ['array', 'field' => ['db hosts.hostid']],
			'add_templates' => ['array', 'field' => ['db hosts.hostid']],
			'ipmi_authtype' => ['db hosts.ipmi_authtype', 'required', 'in' => [IPMI_AUTHTYPE_DEFAULT,
				IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2, IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM,
				IPMI_AUTHTYPE_RMCP_PLUS
			]],
			'ipmi_privilege' => ['db hosts.ipmi_privilege', 'required', 'in' => [IPMI_PRIVILEGE_CALLBACK,
				IPMI_PRIVILEGE_USER, IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM
			]],
			'ipmi_username' => ['db hosts.ipmi_username'],
			'ipmi_password' => ['db hosts.ipmi_password'],
			'tls_connect' => ['db hosts.tls_connect', 'required', 'in' => [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK,
				HOST_ENCRYPTION_CERTIFICATE
			]],
			'tls_in_none' => ['boolean'],
			'tls_in_psk' => ['boolean'],
			'tls_in_cert' => ['boolean'],
			'tls_subject' => ['db hosts.tls_subject'],
			'tls_issuer' => ['db hosts.tls_issuer'],
			'tls_psk_identity' => [
				['db hosts.tls_psk_identity',
					'regex' => '/^'.ZBX_PREG_PSK_IDENTITY_FORMAT.'$/',
					'messages' => ['regex' => _('This value does not match pattern.')]
				],
				['db hosts.tls_psk_identity', 'not_empty', 'when' => ['tls_in_psk', true]],
				['db hosts.tls_psk_identity', 'not_empty', 'when' => ['tls_connect', 'in' => [HOST_ENCRYPTION_PSK]]]
			],
			'tls_psk' => [
				['db hosts.tls_psk',
					'regex' => '/^(.{2})+$/',
					'messages' => ['regex' => _('PSK must be an even number of characters.')]
				],
				['db hosts.tls_psk',
					'regex' => '/.{32,}/',
					'messages' => ['regex' => _('PSK must be at least 32 characters long.')]
				],
				['db hosts.tls_psk',
					'regex' => '/^[0-9a-f]*$/i',
					'messages' => ['regex' => _('PSK must contain only hexadecimal characters.')]
				],
				['db hosts.tls_psk', 'not_empty', 'when' => ['tls_in_psk', true]],
				['db hosts.tls_psk', 'not_empty', 'when' => ['tls_connect', 'in' => [HOST_ENCRYPTION_PSK]]]
			],
			'inventory_mode' => ['db host_inventory.inventory_mode', 'required', 'in' => [HOST_INVENTORY_DISABLED,
				HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC
			]],
			'host_inventory' => ['object', 'fields' => $host_inventory_fields],
			'macros' => ['objects', 'uniq' => ['macro'],
				'messages' => ['uniq' => _('Macro name is not unique.')],
				'fields' => [
					'hostmacroid' => ['db hostmacro.hostmacroid'],
					'type' => ['db hostmacro.type', 'required', 'in' => [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET,
						ZBX_MACRO_TYPE_VAULT
					]],
					'value' => [
						['db hostmacro.value'],
						['db hostmacro.value', 'required', 'not_empty',
							'use' => [CVaultSecretParser::class, ['provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)]],
							'when' => ['type', 'in' => [ZBX_MACRO_TYPE_VAULT]]
						]
					],
					'description' => ['db hostmacro.description'],
					'macro' => [
						['db hostmacro.macro', 'use' => [CUserMacroParser::class, []], 'messages' => ['use' => _('Expected user macro format is "{$MACRO}".')]],
						['db hostmacro.macro', 'required', 'not_empty', 'when' => ['value', 'not_empty']],
						['db hostmacro.macro', 'required', 'not_empty', 'when' => ['description', 'not_empty']]
					],
					'automatic' => ['db hostmacro.automatic', 'in' => [ZBX_USERMACRO_MANUAL, ZBX_USERMACRO_AUTOMATIC]],
					'discovery_state' => ['integer'],
					'inherited_type' => ['integer']
				]
			],
			'valuemaps' => ['objects', 'fields' => [
				'valuemapid' => ['db valuemap.valuemapid'],
				'name' => ['db valuemap.name', 'not_empty', 'required'],
				'mappings' => ['objects', 'not_empty', 'uniq' => ['type', 'value'],
					'messages' => ['uniq' => _('Mapping type and value combination is not unique.')],
					'fields' => [
						'type' => ['db valuemap_mapping.type', 'required', 'in' => [VALUEMAP_MAPPING_TYPE_EQUAL,
							VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
							VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP, VALUEMAP_MAPPING_TYPE_DEFAULT
						]],
						'value' => [
							['db valuemap_mapping.value', 'required', 'when' => ['type', 'in' => [
								VALUEMAP_MAPPING_TYPE_EQUAL
							]]],
							['db valuemap_mapping.value', 'required', 'not_empty', 'when' => ['type', 'in' => [
								VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
								VALUEMAP_MAPPING_TYPE_IN_RANGE, VALUEMAP_MAPPING_TYPE_REGEXP
							]]],
							['float', 'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								VALUEMAP_MAPPING_TYPE_LESS_EQUAL
							]]],
							['string',
								'use' => [CRangesParser::class, ['with_minus' => true, 'with_float' => true, 'with_suffix' => true]],
								'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_IN_RANGE]],
								'messages' => ['use' => _('Invalid range.')]
							],
							['string', 'use' => [CRegexValidator::class, []],
								'when' => ['type', 'in' => [VALUEMAP_MAPPING_TYPE_REGEXP]]
							]
						],
						'newvalue' => ['db valuemap_mapping.newvalue', 'required', 'not_empty']
					]
				]
			]],
			'clone' => ['integer', 'in' => [1]],
			'clone_hostid' => ['db hosts.hostid']
		]];
	}

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add host'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('clone_hostid') && $this->hasInput('clone')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('clone_hostid')
			]);

			if (!$hosts) {
				return false;
			}
		}

		return true;
	}

	protected function doAction(): void {
		$result = false;

		try {
			DBstart();

			$tls_accept = 0x00;
			if ($this->getInput('tls_in_none', 0)) {
				$tls_accept |= HOST_ENCRYPTION_NONE;
			}
			if ($this->getInput('tls_in_psk', 0)) {
				$tls_accept |= HOST_ENCRYPTION_PSK;
			}
			if ($this->getInput('tls_in_cert', 0)) {
				$tls_accept |= HOST_ENCRYPTION_CERTIFICATE;
			}

			if (!($tls_accept & HOST_ENCRYPTION_PSK) && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
				$tls_accept = HOST_ENCRYPTION_NONE;
			}

			$groups = $this->getInput('groups', []);
			$new_groups = $this->getInput('groups_new', []);

			$interfaces = $this->getInput('interfaces', []);
			foreach ($interfaces as $interfaceid => &$interface) {
				$interface['main'] = $this->getInput('main_interface_'.$interface['type'], 0) == $interfaceid
					? INTERFACE_PRIMARY
					: INTERFACE_SECONDARY;
			}
			unset($interface);

			$host = [
				'status' => $this->getInput('status', HOST_STATUS_NOT_MONITORED),
				'groups' => $this->processHostGroups($groups, $new_groups),
				'interfaces' => $this->processHostInterfaces($interfaces),
				'monitored_by' => $this->getInput('monitored_by', ZBX_MONITORED_BY_SERVER),
				'tags' => $this->processTags($this->getInput('tags', [])),
				'templates' => $this->processTemplates([
					$this->getInput('add_templates', []), $this->getInput('templates', [])
				]),
				'macros' => $this->processUserMacros($this->getInput('macros', [])),
				'inventory' => ($this->getInput('inventory_mode', HOST_INVENTORY_DISABLED) != HOST_INVENTORY_DISABLED)
					? $this->getInput('host_inventory', [])
					: [],
				'tls_connect' => $this->getInput('tls_connect', HOST_ENCRYPTION_NONE),
				'tls_accept' => $tls_accept
			];

			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$host['proxyid'] = $this->getInput('proxyid', 0);
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				$host['proxy_groupid'] = $this->getInput('proxy_groupid', 0);
			}

			$this->getInputs($host, [
				'host', 'visiblename', 'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
				'ipmi_password', 'tls_subject', 'tls_issuer', 'inventory_mode'
			]);

			if ($host['tls_connect'] == HOST_ENCRYPTION_PSK || $host['tls_accept'] & HOST_ENCRYPTION_PSK) {
				if ($this->hasInput('tls_psk_identity') && $this->getInput('tls_psk_identity', '') !== '') {
					$host['tls_psk_identity'] = $this->getInput('tls_psk_identity');
				}
				if ($this->hasInput('tls_psk') && $this->getInput('tls_psk', '') !== '') {
					$host['tls_psk'] = $this->getInput('tls_psk');
				}
			}

			if ($host['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
					&& !($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				unset($host['tls_issuer'], $host['tls_subject']);
			}

			$host = CArrayHelper::renameKeys($host, ['visiblename' => 'name']);

			$clone = $this->hasInput('clone');
			$src_hostid = $this->getInput('clone_hostid', 0);

			if ($clone && $src_hostid != 0) {
				$host = $this->extendHostCloneEncryption($host, $src_hostid);
			}

			$result = API::Host()->create($host);

			if ($result === false) {
				throw new Exception();
			}

			$host = ['hostid' => $result['hostids'][0]] + $host;

			if (!$this->createValueMaps($host['hostid'])
					|| ($clone && !$this->copyFromCloneSourceHost($src_hostid, $host))) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
			$result = false;
			DBend(false);
		}

		$output = [];

		if ($result) {
			$success = ['title' => _('Host added')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add host'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Copy write-only PSK fields values from source host to the new host. Used to clone host.
	 *
	 * @param array  $host                New host data to update.
	 * @param array  $host['tls_connect'] Type of connection to host.
	 * @param array  $host['tls_accept']  Type(s) of connection from host.
	 * @param string $src_hostid          ID of host to copy data from.
	 *
	 * @return array New host data with PSK, identity added (if applicable).
	 */
	private function extendHostCloneEncryption(array $host, string $src_hostid): array {
		if ($host['tls_connect'] == HOST_ENCRYPTION_PSK || ($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
			// Add values to PSK fields from cloned host.
			$clone_hosts = API::Host()->get([
				'output' => ['tls_psk_identity', 'tls_psk'],
				'hostids' => $src_hostid,
				'editable' => true
			]);

			if ($clone_hosts) {
				$host['tls_psk_identity'] = $this->getInput('tls_psk_identity', $clone_hosts[0]['tls_psk_identity']);
				$host['tls_psk'] = $this->getInput('tls_psk', $clone_hosts[0]['tls_psk']);
			}
		}

		return $host;
	}

	/**
	 * Create valuemaps.
	 *
	 * @param string $hostid      Target hostid.
	 *
	 * @return bool
	 */
	private function createValueMaps(string $hostid): bool {
		$valuemaps = $this->getInput('valuemaps', []);

		foreach ($valuemaps as $key => $valuemap) {
			unset($valuemap['valuemapid']);
			$valuemaps[$key] = $valuemap + ['hostid' => $hostid];
		}

		if ($valuemaps && !API::ValueMap()->create($valuemaps)) {
			return false;
		}

		return true;
	}

	/**
	 * Copy http tests, items, triggers, discovery rules and graphs from source host to target host.
	 *
	 * @param string $src_hostid
	 * @param array  $dst_host
	 *
	 * @return bool
	 */
	private function copyFromCloneSourceHost(string $src_hostid, array $dst_host): bool {
		// First copy web scenarios with web items, so that later regular items can use web item as their master item.
		return copyHttpTests($src_hostid, $dst_host['hostid'])
			&& CItemHelper::cloneHostItems($src_hostid, $dst_host)
			&& CTriggerHelper::cloneHostTriggers($src_hostid, $dst_host['hostid'])
			&& CGraphHelper::cloneHostGraphs($src_hostid, $dst_host['hostid'])
			&& CLldRuleHelper::cloneHostItems($src_hostid, $dst_host);
	}
}
