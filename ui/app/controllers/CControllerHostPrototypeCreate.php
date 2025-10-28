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


class CControllerHostPrototypeCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		$api_uniq = [
			[
				'hostprototype.get',
				['host' => '{host}'],
				null,
				null,
				['discoveryids' => '{parent_discoveryid}']
			],
			[
				'hostprototype.get',
				['name' => '{name}'],
				null,
				null,
				['discoveryids' => '{parent_discoveryid}']
			]
		];

		return ['object', 'api_uniq' => $api_uniq,
			'fields' => [
				'context' => ['string', 'required', 'in' => ['host', 'template']],
				'parent_discoveryid' => ['db items.itemid', 'required'],
				'host' => [
					'db hosts.host', 'required', 'not_empty',
					'use' => [CHostNameValidator::class, ['lldmacros' => true]]
				],
				'name' => ['db hosts.name'],
				'templates' => ['array', 'field' => ['db hosts.hostid']],
				'add_templates' => ['array', 'field' => ['db hosts.hostid']],
				'group_links' => ['array', 'required', 'not_empty', 'field' => ['db hstgrp.groupid']],
				'group_prototypes' => ['objects', 'uniq' => ['name'],
					'messages' => ['uniq' => _('Group prototype is not unique.')],
					'fields' => [
						'name' => ['db hstgrp.name', 'use' => [CHostGroupNameValidator::class, ['lldmacros' => true]]],
						'group_prototypeid' => ['string']
					]
				],
				'custom_interfaces' => ['db hosts.custom_interfaces', 'in' => [HOST_PROT_INTERFACES_INHERIT,
					HOST_PROT_INTERFACES_CUSTOM
				]],
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
						'bulk' => ['db interface_snmp.bulk', 'required',
							'in' => [SNMP_BULK_DISABLED, SNMP_BULK_ENABLED]
						],
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
				'status' => ['db hosts.status', 'required', 'in' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]],
				'discover' => ['db hosts.discover', 'required',
					'in' => [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]
				],
				'tags' => ['objects', 'uniq' => ['tag', 'value'],
					'messages' => ['uniq' => _('Tag name and value combination is not unique.')],
					'fields' => [
						'value' => ['db host_tag.value'],
						'tag' => ['db host_tag.tag', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
					]
				],
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
								'use' => [
									CVaultSecretParser::class,
									['provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)]
								],
								'when' => ['type', 'in' => [ZBX_MACRO_TYPE_VAULT]]
							]
						],
						'description' => ['db hostmacro.description'],
						'macro' => [
							['db hostmacro.macro', 'use' => [CUserMacroParser::class, []],
								'messages' => ['use' => _('Expected user macro format is "{$MACRO}".')]
							],
							['db hostmacro.macro', 'required', 'not_empty', 'when' => ['value', 'not_empty']],
							['db hostmacro.macro', 'required', 'not_empty', 'when' => ['description', 'not_empty']]
						],
						'discovery_state' => ['integer'],
						'inherited_type' => ['integer']
					]
				],
				'inventory_mode' => ['db host_inventory.inventory_mode', 'required', 'in' => [HOST_INVENTORY_DISABLED,
					HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC
				]]
			]
		];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput($this->getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add host prototype'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!($this->getInput('context') === 'host'
				? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES))) {
			return false;
		}

		return true;
	}

	protected function doAction(): void {
		$macros = cleanInheritedMacros($this->getInput('macros', []));
		$macros = array_filter($macros, static fn(array $macro): bool => (bool) array_filter(
			array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
		));

		$interfaces = array_filter($this->getInput('interfaces', []), function ($interface) {
			unset($interface['main']);

			return $interface;
		});

		foreach ($interfaces as $index => &$interface) {
			$interface['main'] = $this->getInput('main_interface_'.$interface['type'], 0) == $index
				? INTERFACE_PRIMARY
				: INTERFACE_SECONDARY;
		}
		unset($interface);

		$data = [
			'host' => $this->getInput('host', DB::getDefault('hosts', 'host')),
			'name' => $this->getInput($this->getInput('name', '') === '' ? 'host' : 'name',
				DB::getDefault('hosts', 'name')
			),
			'custom_interfaces' => $this->getInput('custom_interfaces', DB::getDefault('hosts', 'custom_interfaces')),
			'status' => $this->getInput('status', HOST_STATUS_NOT_MONITORED),
			'discover' => $this->getInput('discover', HOST_NO_DISCOVER),
			'interfaces' => prepareHostPrototypeInterfaces($interfaces),
			'groupLinks' => prepareHostPrototypeGroupLinks($this->getInput('group_links', [])),
			'groupPrototypes' => prepareHostPrototypeGroupPrototypes($this->getInput('group_prototypes', [])),
			'templates' => zbx_toObject(
				array_merge($this->getInput('templates', []), $this->getInput('add_templates', [])),
				'templateid'
			),
			'tags' => prepareHostPrototypeTags($this->getInput('tags', [])),
			'macros' => prepareHostPrototypeMacros($macros),
			'inventory_mode' => $this->getInput('inventory_mode',
				CSettingsHelper::get(CSettingsHelper::DEFAULT_INVENTORY_MODE)
			)
		];

		$host_prototype = ['ruleid' => $this->getInput('parent_discoveryid')] + getSanitizedHostPrototypeFields(
			['templateid' => 0] + $data
		);

		$result = API::HostPrototype()->create($host_prototype);

		if ($result) {
			$output['success']['title'] = _('Host prototype added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add host prototype'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
