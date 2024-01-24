<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testFormMacros.php';

/**
 * @backup hosts, config
 *
 * @onBefore prepareHostMacrosData
 */
class testFormMacrosHost extends testFormMacros {

	/**
	 * Create new macros for host.
	 */
	public function prepareHostMacrosData() {
		CDataHelper::call('host.update', [
			[
				'hostid' => 50010,
				'macros' => [
					'macro' => '{$NEWMACROS}',
					'value' => 'something/value:key',
					'type' => 2
				]
			]
		]);
	}

	/**
	 * The name of the host for updating macros, id=20006.
	 *
	 * @var string
	 */
	protected $host_name_update = 'Host for trigger description macros';

	/**
	 * The name of the host for removing macros, id=30010.
	 *
	 * @var string
	 */
	protected $host_name_remove = 'Host for macros remove';

	/**
	 * The id of the host for removing inherited macros.
	 *
	 * @var integer
	 */
	protected static $hostid_remove_inherited;

	public $macro_resolve = '{$X_SECRET_HOST_MACRO_2_RESOLVE}';
	public $macro_resolve_hostid = 99135;

	public $vault_object = 'host';
	public $hashi_error_field = '/1/macros/3/value';
	public $cyber_error_field = '/1/macros/4/value';
	public $update_vault_macro = '{$VAULT_HOST_MACRO3_CHANGED}';
	public $vault_macro_index = 2;

	public $revert_macro_1 = '{$SECRET_HOST_MACRO_REVERT}';
	public $revert_macro_2 = '{$SECRET_HOST_MACRO_2_TEXT_REVERT}';
	public $revert_macro_object = 'host';

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
		$this->checkMacros($data, 'host', $this->host_name_update, true);
	}

	public function testFormMacrosHost_RemoveAll() {
		$this->checkRemoveAll($this->host_name_remove, 'host');
	}

	/**
	 * @dataProvider getCheckInheritedMacrosData
	 */
	public function testFormMacrosHost_ChangeInheritedMacro($data) {
		$this->checkChangeInheritedMacros($data, 'host');
	}

	public function prepareHostRemoveMacrosData() {
		$response = CDataHelper::call('host.create', [
				'host' => 'Host for Inherited macros removing',
				'groups' => [
					['groupid' => '4']
				],
				'interfaces' => [
					'type'=> 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '192.168.3.1',
					'dns' => '',
					'port' => '10050'
				],
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
		]);
		$this->assertArrayHasKey('hostids', $response);
		self::$hostid_remove_inherited = $response['hostids'][0];
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 *
	 * @onBeforeOnce prepareHostRemoveMacrosData
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
		$this->checkSecretMacrosLayout($data, 'zabbix.php?action=host.view', 'hosts', 'Host for suppression');
	}

	/**
	 * @dataProvider getCreateSecretMacrosData
	 */
	public function testFormMacrosHost_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', 'Available host');
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormMacrosHost_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'zabbix.php?action=host.view', 'hosts', 'Available host in maintenance');
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
		$this->updateSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', 'Available host in maintenance');
	}

	/**
	 * Test opens the list of items of "Available host in maintenance" and "Latest data"
	 * and checks macro resolution in item fields.
	 *
	 * @dataProvider getResolveSecretMacroData
	 */
	public function testFormMacrosHost_ResolveSecretMacro($data) {
		$this->resolveSecretMacro($data, 'host');
	}

	/**
	 * Check Vault macros validation.
	 */
	public function testFormMacrosHost_checkVaultValidation() {
		$this->checkVaultValidation('zabbix.php?action=host.view', 'hosts', 'Host for different items types');
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosHost_CreateVaultMacros($data) {
		$host = ($data['vault'] === 'Hashicorp') ? 'Host 1 from first group' : 'Empty host';
		$this->createVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', $host);
	}

	/**
	 * @dataProvider getUpdateVaultMacrosNormalData
	 * @dataProvider getUpdateVaultMacrosCommonData
	 */
	public function testFormMacrosHost_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', 'Host for suppression');
	}
}
