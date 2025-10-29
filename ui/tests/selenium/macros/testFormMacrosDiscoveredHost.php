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


require_once __DIR__.'/../common/testFormMacros.php';

/**
 * @onBefore prepareDiscoveredHostMacrosData
 *
 * @dataSource GlobalMacros
 *
 * @backup hosts, config
 */
class testFormMacrosDiscoveredHost extends testFormMacros {

	protected static $inherit_hostid;
	protected static $hosts = [];

	protected $vault_object = 'host';
	protected $hashi_error_field = '/1/macros/4/value';
	protected $cyber_error_field = '/1/macros/4/value';
	protected $update_vault_macro = '{$VAULT_HOST_MACRO3_CHANGED}';
	protected $vault_macro_index = 2;

	protected $revert_macro_1 = '{$SECRET_HOST_MACRO_REVERT}';
	protected $revert_macro_2 = '{$SECRET_HOST_MACRO_2_TEXT_REVERT}';
	protected $revert_macro_object = 'host';

	/**
	 * Create new hosts for discovery rules and prototypes with macros.
	 */
	public function prepareDiscoveredHostMacrosData() {
		$cases = [
			'macros_update',
			'macros_remove',
			'secret_macros_layout',
			'secret_macros_create',
			'secret_macros_revert',
			'vault_validation',
			'empty',
			'vault_create',
			'macros_inheritance'
		];

		// Create prototypes and discovered host names and ids.
		foreach ($cases as $i => $case) {
			self::$hosts[$i] = [
				'prototype_name' => '{#KEY} Discovered host '.$case,
				'name' => $i.' Discovered host '.$case,
				'hostid' => $i + 700000,
				'interfaceid' => $i + 800000,
				'host_groupid' => $i + 900000
			];
		}

		// Define macros for each discovered host.
		$host_macros = [
			// '0 Discovered host macros_update'.
			[
				'hostid' => self::$hosts[0]['hostid'],
				'macros' => [
					[
						'macro' => '{$MACRO1}',
						'value' => '',
						'description' => '',
						'type' => 0
					],
					[
						'macro' => '{$MACRO2}',
						'value' => '',
						'description' => '',
						'type' => 0
					]
				]
			],
			// '1 Discovered host macros_remove'.
			[
				'hostid' => self::$hosts[1]['hostid'],
				'macros' => [
					[
						'macro' => '{$TEST_MACRO123}',
						'value' => 'test123',
						'description' => 'description 123',
						'type' => 0
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST1}',
						'value' => 'test1',
						'description' => 'description 1',
						'type' => 0
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST2}',
						'value' => 'test2',
						'description' => 'description 2',
						'type' => 0
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL1}',
						'value' => 'test global 1',
						'description' => 'global description 1',
						'type' => 0
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL2}',
						'value' => 'test global 2',
						'description' => 'global description 2',
						'type' => 0
					],
					[
						'macro' => '{$SNMP_COMMUNITY}',
						'value' => 'redefined value',
						'description' => 'redefined description',
						'type' => 0
					]
				]
			],
			// '2 Discovered host macros_layout'.
			[
				'hostid' => self::$hosts[2]['hostid'],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO}',
						'value' => 'some secret value',
						'description' => '',
						'type' => 1
					],
					[
						'macro' => '{$TEXT_HOST_MACRO}',
						'value' => 'some text value',
						'description' => '',
						'type' => 0
					],
					[
						'macro' => '{$VAULT_HOST_MACRO3}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' => 2
					]
				]
			],
			// '3 Discovered host macros_create'.
			[
				'hostid' => self::$hosts[3]['hostid'],
				'macros' => []
			],
			// '4 Discovered host macros_revert'.
			[
				'hostid' => self::$hosts[4]['hostid'],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2 text',
						'description' => 'Secret host macro that will be changed to text',
						'type' => 1
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_REVERT}',
						'value' => 'Secret host value',
						'description' => 'Secret host macro description',
						'type' => 1
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_UPDATE}',
						'value' => 'Secret host macro value',
						'description' => 'Secret host macro that is going to stay secret',
						'type' => 1
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}',
						'value' => 'Secret host value 2 B updated',
						'description' => 'Secret host macro that is going to be updated',
						'type' => 1
					],
					[
						'macro' => '{$TEXT_HOST_MACRO_2_SECRET}',
						'value' => 'Text host macro value',
						'description' => 'Text host macro that is going to become secret',
						'type' => 0
					],
					[
						'macro' => '{$X_SECRET_HOST_MACRO_2_RESOLVE}',
						'value' => 'Value 2 B resolved',
						'description' => 'Host macro to be resolved',
						'type' => 0
					]
				]
			],
			// '5 Discovered host vault_validation'.
			[
				'hostid' => self::$hosts[5]['hostid'],
				'macros' => [
					[
						'macro' => '{$NEWMACROS}',
						'value' => 'something/value:key',
						'description' => '',
						'type' => 2
					]
				]
			],
			// '6 Discovered host vault_validation'.
			[
				'hostid' => self::$hosts[6]['hostid'],
				'macros' => []
			],
			// '7 Discovered host vault_create'.
			[
				'hostid' => self::$hosts[7]['hostid'],
				'macros' => []
			],
			// '8 Discovered host macros_inheritance'.
			[
				'hostid' => self::$hosts[8]['hostid'],
				'macros' => [
					[
						'macro' => '{$HOST_MACRO}',
						'value' => 'host_macro_value',
						'description' => '',
						'type' => 0
					],
					[
						'macro' => '{$HOST_SECRET}',
						'value' => 'host_secret_value',
						'description' => 'secret value inherited from host',
						'type' => 1
					],
					[
						'macro' => '{$HOST_VAULT}',
						'value' => 'host/vault:key',
						'description' => 'host vault macro',
						'type' => 2
					],
					[
						'macro' => '{$PROTO_MACRO}',
						'value' => 'proto_macro_value',
						'description' => 'prototype macro',
						'type' => 0
					],
					[
						'macro' => '{$PROTO_SECRET}',
						'value' => 'proto_secret_value',
						'description' => 'prototype secret macro',
						'type' => 1
					],
					[
						'macro' => '{$PROTO_VAULT}',
						'value' => 'proto/vault:key',
						'description' => '',
						'type' => 2
					]
				]
			]
		];

		// Create parent hosts for discoveries and prototypes.
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Parent host for discovered hosts macros',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'interfaces' => ['type'=> 1, 'main' => 1, 'useip' => 1, 'ip' => '127.0.0.1', 'dns' => '', 'port' => 10050]
			],
			[
				'host' => 'Parent host for macros inheritance',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'interfaces' => ['type'=> 1, 'main' => 1, 'useip' => 1, 'ip' => '127.0.0.1', 'dns' => '', 'port' => 10050],
				'macros' => [
					[
						'macro' => '{$HOST_MACRO}',
						'value' => 'host_macro_value',
						'description' => '',
						'type' => 0
					],
					[
						'macro' => '{$HOST_SECRET}',
						'value' => 'host_secret_value',
						'description' => 'secret value inherited from host',
						'type' => 1
					],
					[
						'macro' => '{$HOST_VAULT}',
						'value' => 'host/vault:key',
						'description' => 'host vault macro',
						'type' => 2
					]
				]
			]
		]);

		$hostid = $hosts['hostids'][0];
		self::$inherit_hostid = $hosts['hostids'][1];

		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.zbx_dbstr($hostid));
		$inherit_interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.zbx_dbstr(self::$inherit_hostid));

		// Create discovery rules.
		$llds = [
			'Test discovered hosts' => ['hostid' => $hostid, 'interface' => $interfaceid],
			'Test discovered macros inheritance' => ['hostid' => self::$inherit_hostid, 'interface' => $inherit_interfaceid]
		];

		$lld_data = [];
		foreach ($llds as $name => $params) {
			$lld_data[] = [
				'name' => $name,
				'key_' => 'vfs.fs.discovery',
				'hostid' => $params['hostid'],
				'type' => ITEM_TYPE_ZABBIX,
				'interfaceid' => $params['interface'],
				'delay' => 30
			];
		}

		$lld_result = CDataHelper::call('discoveryrule.create', $lld_data);
		$lldid = $lld_result['itemids'][0];
		$inherit_lldid = $lld_result['itemids'][1];

		$prototypes_data = [];

		// Create host prototypes with macros.
		foreach ([0, 1, 2, 4, 5] as $k) {
			$prototypes_data[] = [
				'host' => self::$hosts[$k]['prototype_name'],
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'macros' => $host_macros[$k]['macros']
			];
		}

		// Create host prototypes without macros.
		foreach ([3, 6, 7] as $l) {
			$prototypes_data[] = [
				'host' => self::$hosts[$l]['prototype_name'],
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]]
			];
		}

		// Create host prototype with macros for inheritance test.
		$prototypes_data[] = [
			'host' => self::$hosts[8]['prototype_name'],
			'ruleid' => $inherit_lldid,
			'groupLinks' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
			'macros' => [
				[
					'macro' => '{$PROTO_MACRO}',
					'value' => 'proto_macro_value',
					'description' => 'prototype macro',
					'type' => 0
				],
				[
					'macro' => '{$PROTO_SECRET}',
					'value' => 'proto_secret_value',
					'description' => 'prototype secret macro',
					'type' => 1
				],
				[
					'macro' => '{$PROTO_VAULT}',
					'value' => 'proto/vault:key',
					'description' => '',
					'type' => 2
				]
			]
		];

		CDataHelper::call('hostprototype.create', $prototypes_data);
		$prototypeids = CDataHelper::getIds('host');

		// Emulate host discoveries in DB.
		foreach (self::$hosts as $host) {
			DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr($host['hostid']).
					", ".zbx_dbstr($host['name']).", ".zbx_dbstr($host['name']).", 0, 4, '')"
			);
			DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr($host['hostid']).", ".
					zbx_dbstr($prototypeids[$host['prototype_name']]).")"
			);
			DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".
					zbx_dbstr($host['interfaceid']).", ".zbx_dbstr($host['hostid']).", 1, 1, 1, '127.0.0.1', '', '10050')"
			);
			DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr($host['host_groupid']).
					", ".zbx_dbstr($host['hostid']).", 4)"
			);
		}

		// Write macros to discovered hosts.
		$j = 0;
		foreach ($host_macros as $hostmacro) {
			foreach ($hostmacro['macros'] as $macro) {
				DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (".
						(9000000 + $j).", ".zbx_dbstr($hostmacro['hostid']).", ".zbx_dbstr($macro['macro']).", ".
						zbx_dbstr($macro['value']).", ".zbx_dbstr($macro['description']).", ".zbx_dbstr($macro['type']).", 1)"
				);
				$j++;
			}
		}
	}

	public static function getDiscoveredHostUpdateMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'updated value 1',
							'description' => 'updated description 1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'value' => 'Updated value 2',
							'description' => 'Updated description 2'
						]
					],
					'expected_macros' => [['macro' => '{$MACRO1}'], ['macro' => '{$MACRO2}']]
				]
			]
		];
	}

	/**
	 * @dataProvider getDiscoveredHostUpdateMacrosData
	 * @dataProvider getUpdateMacrosCommonData
	 */
	public function testFormMacrosDiscoveredHost_Update($data) {
		$this->checkMacros($data, 'host', self::$hosts[0]['name'], true);
	}

	/**
	 * @backupOnce hosts
	 */
	public function testFormMacrosDiscoveredHost_RemoveAll() {
		$this->checkRemoveAll(self::$hosts[1]['name'], 'host');
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 */
	public function testFormMacrosDiscoveredHost_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'host', self::$hosts[1]['hostid'], false, null,
				self::$hosts[1]['name']
		);
	}

	/**
	 * @dataProvider getSecretMacrosLayoutData
	 */
	public function testFormMacrosDiscoveredHost_CheckSecretMacrosLayout($data) {
		$this->checkSecretMacrosLayout($data, 'zabbix.php?action=host.view', 'hosts', self::$hosts[2]['name'], true);
	}

	/**
	 * @dataProvider getCreateSecretMacrosData
	 */
	public function testFormMacrosDiscoveredHost_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', self::$hosts[3]['name']);
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 *
	 * @backupOnce hosts
	 */
	public function testFormMacrosDiscoveredHost_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'zabbix.php?action=host.view', 'hosts', self::$hosts[4]['name'], true);
	}

	public function getUpdateSecretMacrosData() {
		return [
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 2,
						'value' => [
							'text' => 'Updated secret value'
						]
					],
					'expected' => [
						'macro' => '{$SECRET_HOST_MACRO_UPDATE}',
						'value' => [
							'text' => 'Updated secret value'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 3,
						'value' => [
							'text' => 'New text value',
							'type' => 'Text'
						]
					],
					'expected' => [
						'macro' => '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}',
						'value' => [
							'text' => 'New text value',
							'type' => 'Text'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 4,
						'value' => [
							'text' => 'New secret value',
							'type' => 'Secret text'
						]
					],
					'expected' => [
						'macro' => '{$TEXT_HOST_MACRO_2_SECRET}',
						'value' => [
							'text' => 'New secret value',
							'type' => 'Secret text'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateSecretMacrosData
	 */
	public function testFormMacrosDiscoveredHost_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', self::$hosts[4]['name'], true);
	}

	/**
	 * Check Vault macros validation.
	 */
	public function testFormMacrosDiscoveredHost_CheckVaultValidation() {
		$this->checkVaultValidation('zabbix.php?action=host.view', 'hosts', self::$hosts[5]['name'], true);
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosDiscoveredHost_CreateVaultMacros($data) {
		$host = ($data['vault'] === 'Hashicorp') ? self::$hosts[7]['name'] : self::$hosts[6]['name'];
		$this->createVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', $host);
	}

	public function getUpdateVaultMacrosDiscoveredData() {
		return [
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => $this->vault_macro_index,
						'value' => [
							'text' => 'secret/path:key'
						],
						'description' => ''
					],
					'vault' => 'Hashicorp',
					'expected_macros' => [
						'fields' => [
							'macro' => '{$VAULT_HOST_MACRO3}',
							'value' => [
								'text' => 'secret/path:key'
							],
							'description' => ''
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateVaultMacrosDiscoveredData
	 * @dataProvider getUpdateVaultMacrosCommonData
	 */
	public function testFormMacrosDiscoveredHost_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', self::$hosts[2]['name']);
	}

	/**
	 * Check discovered host macros which are inherited from both host and prototype.
	 */
	public function testFormMacrosDiscoveredHost_CheckInheritedMacros() {
		$this->page->login()->open('zabbix.php?action=host.view&filter_selected=0&filter_reset=1')->waitUntilReady();
		$this->query('link', self::$hosts[8]['name'])->asPopupButton()->one()->select('Host');
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$form->selectTab('Macros');

		$expected_macros = [
			[
				'macro' => '{$HOST_MACRO}',
				'value' => 'host_macro_value',
				'description' => '',
				'type' => 0
			],
			[
				'macro' => '{$HOST_SECRET}',
				'value' => '******',
				'description' => 'secret value inherited from host',
				'type' => 1
			],
			[
				'macro' => '{$HOST_VAULT}',
				'value' => 'host/vault:key',
				'description' => 'host vault macro',
				'type' => 2
			],
			[
				'macro' => '{$PROTO_MACRO}',
				'value' => 'proto_macro_value',
				'description' => 'prototype macro',
				'type' => 0
			],
			[
				'macro' => '{$PROTO_SECRET}',
				'value' => '******',
				'description' => 'prototype secret macro',
				'type' => 1
			],
			[
				'macro' => '{$PROTO_VAULT}',
				'value' => 'proto/vault:key',
				'description' => '',
				'type' => 2
			]
		];

		$this->assertEquals($expected_macros, $this->getMacros(true));

		for ($i = 0; $i < count($this->getMacros()); $i++) {
			// Check that all macros fields are disabled.
			foreach (['macro', 'value', 'description'] as $field) {
				$this->assertFalse($form->query('id:macros_'.$i.'_'.$field)->one()->isEnabled());
			}

			// Check that Change and Remove buttons are clickable for each row.
			foreach (['change_state', 'remove'] as $button) {
				$this->assertTrue($form->query('id:macros_'.$i.'_'.$button)->one()->isClickable());
			}

			// Check info text presents for each row.
			$this->assertTrue($form->query("xpath:.//button[@id=\"macros_".$i.
					"_remove\"]/../..//span[text()=\"(created by host discovery)\"]")->one()->isVisible()
			);
		}

		COverlayDialogElement::find()->one()->close();
	}
}
