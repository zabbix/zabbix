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
 * @backup hosts, config
 *
 * @onBefore prepareHostPrototypeMacrosData
 */
class testFormMacrosHostPrototype extends testFormMacros {

	const HOSTMACROS_UPDATE = 'Host prototype for macros {#UPDATE}';
	const HOSTMACROS_REMOVE = 'Host prototype for macros {#DELETE}';
	protected static $lldid;
	protected static $inherited_macros_prototypeid;
	protected static $create_secret_macros_prototypeid;
	protected static $update_secret_macros_prototypeid;
	protected static $vault_macros_validation_prototypeid;
	protected static $vault_macros_create_prototypeid;
	protected static $hashi_macros_create_prototypeid;
	protected static $vault_macros_update_prototypeid;

	protected $vault_object = 'host prototype';
	protected $hashi_error_field = '/1/macros/4/value';
	protected $cyber_error_field = '/1/macros/4/value';
	protected $update_vault_macro = '{$VAULT_HOST_MACRO3_CHANGED}';
	protected $vault_macro_index = 0;

	protected $revert_macro_1 = '{$Z_HOST_PROTOTYPE_MACRO_REVERT}';
	protected $revert_macro_2 = '{$Z_HOST_PROTOTYPE_MACRO_2_TEXT_REVERT}';
	protected $revert_macro_object = 'host';

	public function prepareHostPrototypeMacrosData() {
		CDataHelper::createHosts([
			[
				'host' => 'Host for host prototypes macros testing',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'discoveryrules' => [
					[
						'name' => 'Main LLD',
						'key_' => 'main_lld',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);
		self::$lldid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr('Main LLD'));

		CDataHelper::call('hostprototype.create', [
			[
				'host' => 'Host prototype for Inherited {#MACROS} removing',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]],
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
				'host' => self::HOSTMACROS_UPDATE,
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$UPDATE_MACRO_1}',
						'value' => 'Update macro value 1',
						'description' => 'Update macro description 1'
					],
					[
						'macro' => '{$UPDATE_MACRO_2}',
						'value' => 'Update macro value 2',
						'description' => 'Update macro description 2'
					]
				]
			],
			[
				'host' => self::HOSTMACROS_REMOVE,
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$DELETE_MACRO_1}',
						'value' => 'Delete macro value 1',
						'description' => 'Delete macro description 1'
					],
					[
						'macro' => '{$DELETE_MACRO_2}',
						'value' => 'Delete macro value 2',
						'description' => 'Delete macro description 2'
					]
				]
			],
			[
				'host' => 'Host prototype for {#SECRET_MACROS} create',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]]
			],
			[
				'host' => 'Host prototype for {#SECRET_MACROS} update',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$PROTOTYPE_SECRET_2_SECRET}',
						'value' => 'This text should stay secret',
						'description' => 'Secret macro to me updated',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$PROTOTYPE_SECRET_2_TEXT}',
						'value' => 'This text should become visible',
						'description' => 'Secret macro to become visible',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$PROTOTYPE_TEXT_2_SECRET}',
						'value' => 'This text should become secret',
						'description' => 'Text macro to become secret',
						'type' => ZBX_MACRO_TYPE_TEXT
					],
					[
						'macro' => '{$Z_HOST_PROTOTYPE_MACRO_REVERT}',
						'value' => 'Secret host value',
						'description' => 'Value change Revert',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$Z_HOST_PROTOTYPE_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2',
						'description' => 'Value and type change revert',
						'type' => ZBX_MACRO_TYPE_SECRET
					]
				]
			],
			[
				'host' => 'Host prototype for {#VAULT_MACROS} validation',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$NEWMACROS}',
						'value' => 'something/value:key',
						'type' => ZBX_MACRO_TYPE_VAULT
					]
				]
			],
			[
				'host' => 'Empty prototype for {#VAULT_MACROS} create',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]]
			],
			[
				'host' => 'Empty prototype for {#VAULT_MACROS} create Hashicorp',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]]
			],
			[
				'host' => 'Host prototype for {#VAULT_MACROS} update',
				'ruleid' => self::$lldid,
				'groupLinks' => [['groupid'=> self::ZABBIX_SERVERS_GROUPID]],
				'macros' => [
					[
						'macro' => '{$VAULT_HOST_MACRO}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' => ZBX_MACRO_TYPE_VAULT
					]
				]
			]
		]);
		$host_prototypes = CDataHelper::getIds('host');

		self::$inherited_macros_prototypeid = $host_prototypes['Host prototype for Inherited {#MACROS} removing'];
		self::$create_secret_macros_prototypeid = $host_prototypes['Host prototype for {#SECRET_MACROS} create'];
		self::$update_secret_macros_prototypeid = $host_prototypes['Host prototype for {#SECRET_MACROS} update'];
		self::$vault_macros_validation_prototypeid = $host_prototypes['Host prototype for {#VAULT_MACROS} validation'];
		self::$vault_macros_create_prototypeid = $host_prototypes['Empty prototype for {#VAULT_MACROS} create'];
		self::$hashi_macros_create_prototypeid = $host_prototypes['Empty prototype for {#VAULT_MACROS} create Hashicorp'];
		self::$vault_macros_update_prototypeid = $host_prototypes['Host prototype for {#VAULT_MACROS} update'];
	}

	/**
	 * @dataProvider getCreateMacrosData
	 */
	public function testFormMacrosHostPrototype_Create($data) {
		$this->checkMacros($data, 'host prototype', null, false, true, self::$lldid);
	}

	/**
	 * @dataProvider getUpdateMacrosNormalData
	 * @dataProvider getUpdateMacrosCommonData
	 */
	public function testFormMacrosHostPrototype_Update($data) {
		$this->checkMacros($data, 'host prototype', self::HOSTMACROS_UPDATE, true, true, self::$lldid);
	}

	public function testFormMacrosHostPrototype_RemoveAll() {
		$this->checkRemoveAll(self::HOSTMACROS_REMOVE, 'host prototype', true, self::$lldid);
	}

	/**
	 * @dataProvider getCheckInheritedMacrosData
	 */
	public function testFormMacrosHostPrototype_ChangeInheritedMacro($data) {
		$this->checkChangeInheritedMacros($data, 'host prototype', true, self::$lldid);
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 */
	public function testFormMacrosHostPrototype_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'host prototype', self::$inherited_macros_prototypeid,
				true, self::$lldid
		);
	}

	public function getCreateSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_SECRET_MACRO}',
						'value' => [
							'text' => 'secret value',
							'type' => 'Secret text'
						],
						'description' => 'secret description'
					],
					'check_default_type' => true
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_TEXT_MACRO}',
						'value' => [
							'text' => 'plain text value',
							'type' => 'Secret text'
						],
						'description' => 'plain text description'
					],
					'back_to_text' => true
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_SECRET_EMPTY_MACRO}',
						'value' => [
							'text' => '',
							'type' => 'Secret text'
						],
						'description' => 'secret empty value'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateSecretMacrosData
	 */
	public function testFormMacrosHostPrototype_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid='.
				self::$lldid.'&hostid='.self::$create_secret_macros_prototypeid, 'host-prototype'
		);
	}

	public function getUpdateSecretMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 0,
					'macro' => '{$PROTOTYPE_SECRET_2_SECRET}',
					'value' => [
						'text' => 'Updated secret value'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 1,
					'macro' => '{$PROTOTYPE_SECRET_2_TEXT}',
					'value' => [
						'text' => 'Updated text value',
						'type' => 'Text'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 2,
					'macro' => '{$PROTOTYPE_TEXT_2_SECRET}',
					'value' => [
						'text' => 'Updated new secret value',
						'type' => 'Secret text'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateSecretMacrosData
	 */
	public function testFormMacrosHostPrototype_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid='.
				self::$lldid.'&hostid='.self::$update_secret_macros_prototypeid, 'host-prototype'
		);
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormMacrosHostPrototype_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid='.
				self::$lldid.'&hostid='.self::$update_secret_macros_prototypeid, 'host-prototype'
		);
	}

	/**
	 * Check Vault macros validation.
	 */
	public function testFormMacrosHostPrototype_CheckVaultValidation() {
		$this->checkVaultValidation('host_prototypes.php?form=update&context=host&parent_discoveryid='.
				self::$lldid.'&hostid='.self::$vault_macros_validation_prototypeid, 'host-prototype'
		);
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 * ! This scenario should be only ran with all cases, hashi_error_field and cyber_error_field
	 * may not work correctly when run single case. !
	 */
	public function testFormMacrosHostPrototype_CreateVaultMacros($data) {
		$hostid = ($data['vault'] === 'Hashicorp')
			? self::$hashi_macros_create_prototypeid
			: self::$vault_macros_create_prototypeid;

		$this->createVaultMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid='.
				self::$lldid.'&hostid='.$hostid, 'host-prototype'
		);
	}

	/**
	 * @dataProvider getUpdateVaultMacrosNormalData
	 * @dataProvider getUpdateVaultMacrosCommonData
	 */
	public function testFormMacrosHostPrototype_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid=
				'.self::$lldid.'&hostid='.self::$vault_macros_update_prototypeid, 'host-prototype'
		);
	}
}
