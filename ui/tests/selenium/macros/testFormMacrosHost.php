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


require_once dirname(__FILE__).'/../common/testFormMacros.php';

/**
 * @backup hosts, config
 *
 * @dataSource GlobalMacros
 *
 * @onBefore prepareHostMacrosData
 */
class testFormMacrosHost extends testFormMacros {
	protected $macro_resolve = '{$X_SECRET_HOST_MACRO_2_RESOLVE}';
	protected $update_vault_macro = '{$VAULT_HOST_MACRO3_CHANGED}';
	protected $hashi_error_field = '/1/macros/4/value';
	protected $cyber_error_field = '/1/macros/4/value';
	protected $vault_macro_index = 2;
	protected $vault_object = 'host';
	protected $revert_macro_1 = '{$SECRET_HOST_MACRO_REVERT}';
	protected $revert_macro_2 = '{$SECRET_HOST_MACRO_2_TEXT_REVERT}';
	protected $revert_macro_object = 'host';

	protected static $hostid_remove_inherited;
	protected static $macro_resolve_hostid;

	public function prepareHostMacrosData() {
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host with macros',
				'groups' => ['groupid' => self::ZABBIX_SERVERS_GROUPID],
				'macros' => [
					['macro' => '{$MACRO1}', 'value' => ''],
					['macro' => '{$MACRO2}', 'value' => '']
				]
			],
			[
				'host' => 'Host for removing macros',
				'groups' => ['groupid' => self::ZABBIX_SERVERS_GROUPID],
				'macros' => [
					['macro' => '{$MACRO_FOR_REMOVE1}', 'value' => ''],
					['macro' => '{$MACRO_FOR_REMOVE2}', 'value' => '']
				]
			],
			[
				'host' => 'Host for Inherited macros removing',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$TEST_MACRO123}',
						'value' => 'test123',
						'description' => 'description 123'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST1}',
						'value' => 'test1',
						'description' => 'description 1'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST2}',
						'value' => 'test2',
						'description' => 'description 2'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL1}',
						'value' => 'test global 1',
						'description' => 'global description 1'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL2}',
						'value' => 'test global 2',
						'description' => 'global description 2'
					],
					[
						'macro' => '{$SNMP_COMMUNITY}',
						'value' => 'redefined value',
						'description' => 'redefined description'
					]
				]
			],
			[
				'host' => 'Host for secret macros layout',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO}',
						'value' => 'some secret value',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$TEXT_HOST_MACRO}',
						'value' => 'some text value'
					],
					[
						'macro' => '{$VAULT_HOST_MACRO3}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' => ZBX_MACRO_TYPE_VAULT
					]
				]
			],
			[
				'host' => 'Empty Host for creating secret macros',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]]
			],
			[
				'host' => 'Host with secret macros',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'items' => [
					[
						'name' => 'Macro value: {$X_SECRET_HOST_MACRO_2_RESOLVE}',
						'key_' => 'trap[{$X_SECRET_HOST_MACRO_2_RESOLVE}]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO_REVERT}',
						'value' => 'Secret host value',
						'description' => 'Secret host macro description',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2 text',
						'description' => 'Secret host macro that will be changed to text',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}',
						'value' => 'Secret host value 2 B updated',
						'description' => 'Secret host macro that is going to be updated',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$TEXT_HOST_MACRO_2_SECRET}',
						'value' => 'Text host macro value',
						'description' => 'Text host macro that is going to become secret'
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_UPDATE}',
						'value' => 'Secret host macro value',
						'description' => 'Secret host macro that is going to stay secret',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$X_SECRET_HOST_MACRO_2_RESOLVE}',
						'value' => 'Value 2 B resolved',
						'description' => 'Host macro to be resolved'
					]
				]
			],
			[
				'host' => 'Host with vault macro',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$NEWMACROS}',
						'value' => 'something/value:key',
						'type' => 2
					]
				]
			],
			[
				'host' => 'Host for creating Vault macros',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]]
			],
			[
				'host' => 'Empty host without macros',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]]
			],
			[
				'host' => 'Host for updating Vault macros',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO}',
						'value' => 'some secret value',
						'description' => '',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$TEXT_HOST_MACRO}',
						'value' => 'some text value',
						'description' => ''
					],
					[
						'macro' => '{$VAULT_HOST_MACRO3}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' => ZBX_MACRO_TYPE_VAULT
					]
				]
			]
		]);
		self::$hostid_remove_inherited = $hosts['hostids']['Host for Inherited macros removing'];
		self::$macro_resolve_hostid = $hosts['hostids']['Host with secret macros'];
	}

	/**
	 * @dataProvider getCreateMacrosData
	 */
	public function testFormMacrosHost_Create($data) {
		$this->checkMacros($data, 'host');
	}

	/**
	 * @dataProvider getUpdateMacrosNormalData
	 * @dataProvider getUpdateMacrosCommonData
	 */
	public function testFormMacrosHost_Update($data) {
		$this->checkMacros($data, 'host', 'Host with macros', true);
	}

	public function testFormMacrosHost_RemoveAll() {
		$this->checkRemoveAll('Host for removing macros', 'host');
	}

	/**
	 * @dataProvider getCheckInheritedMacrosData
	 */
	public function testFormMacrosHost_ChangeInheritedMacro($data) {
		$this->checkChangeInheritedMacros($data, 'host');
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 */
	public function testFormMacrosHost_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'host', self::$hostid_remove_inherited, false, null,
				'Host for Inherited macros removing'
		);
	}

	/**
	 * @dataProvider getSecretMacrosLayoutData
	 */
	public function testFormMacrosHost_CheckSecretMacrosLayout($data) {
		$this->checkSecretMacrosLayout($data, 'zabbix.php?action=host.view', 'hosts',
				'Host for secret macros layout'
		);
	}

	/**
	 * @dataProvider getCreateSecretMacrosData
	 */
	public function testFormMacrosHost_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'zabbix.php?action=host.view', 'hosts',
				'Empty Host for creating secret macros'
		);
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormMacrosHost_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'zabbix.php?action=host.view', 'hosts',
				'Host with secret macros'
		);
	}

	public function getUpdateSecretMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 2,
					'macro' => '{$SECRET_HOST_MACRO_UPDATE}',
					'value' => [
						'text' => 'Updated secret value'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 3,
					'macro' => '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}',
					'value' => [
						'text' => 'New text value',
						'type' => 'Text'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 4,
					'macro' => '{$TEXT_HOST_MACRO_2_SECRET}',
					'value' => [
						'text' => 'New secret value',
						'type' => 'Secret text'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateSecretMacrosData
	 */
	public function testFormMacrosHost_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', 'Host with secret macros');
	}

	/**
	 * Test opens the list of items of "Host with secret macros" and "Latest data"
	 * and checks macro resolution in item fields.
	 *
	 * @dataProvider getResolveSecretMacroData
	 */
	public function testFormMacrosHost_ResolveSecretMacro($data) {
		$this->resolveSecretMacro($data, self::$macro_resolve_hostid,'host');
	}

	/**
	 * Check Vault macros validation.
	 */
	public function testFormMacrosHost_CheckVaultValidation() {
		$this->checkVaultValidation('zabbix.php?action=host.view', 'hosts', 'Host with vault macro');
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosHost_CreateVaultMacros($data) {
		$host = ($data['vault'] === 'Hashicorp') ? 'Host for creating Vault macros' : 'Empty host without macros';
		$this->createVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', $host);
	}

	/**
	 * @dataProvider getUpdateVaultMacrosNormalData
	 * @dataProvider getUpdateVaultMacrosCommonData
	 */
	public function testFormMacrosHost_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', 'Host for updating Vault macros');
	}
}
