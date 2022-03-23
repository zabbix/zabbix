<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormMacros.php';

/**
 * @backup hosts
 */
class testFormMacrosHostPrototype extends testFormMacros {

	// Parent LLD for Host prototypes 'Discovery rule 1' host: 'Host for host prototype tests'.
	const LLD_ID		= 90001;
	const IS_PROTOTYPE	= true;

	use MacrosTrait;

	/**
	 * The name of the host for updating macros, id=99200.
	 *
	 * @var string
	 */
	protected $host_name_update = 'Host prototype for macros {#UPDATE}';

	/**
	 * The name of the host for removing macros, id=99201.
	 *
	 * @var string
	 */
	protected $host_name_remove = 'Host prototype for macros {#DELETE}';

	/**
	 * The id of the host prototype for removing inherited macros.
	 *
	 * @var integer
	 */
	protected static $host_prototoypeid_remove_inherited;

	public $vault_object = 'host prototype';
	public $vault_error_field = '/1/macros/6/value';
	public $update_vault_macro = '{$VAULT_HOST_MACRO3_CHANGED}';
	public $vault_macro_index = 0;

	public $revert_macro_1 = '{$Z_HOST_PROTOTYPE_MACRO_REVERT}';
	public $revert_macro_2 = '{$Z_HOST_PROTOTYPE_MACRO_2_TEXT_REVERT}';
	public $revert_macro_object = 'host';

	/**
	 * @dataProvider getCreateMacrosData
	 */
	public function testFormMacrosHostPrototype_Create($data) {
		$this->checkMacros($data, 'host prototype', null, false, self::IS_PROTOTYPE, self::LLD_ID);
	}

	/**
	 * @dataProvider getUpdateMacrosData
	 */
	public function testFormMacrosHostPrototype_Update($data) {
		$this->checkMacros($data, 'host prototype', $this->host_name_update, true, self::IS_PROTOTYPE, self::LLD_ID);
	}

	public function testFormMacrosHostPrototype_RemoveAll() {
		$this->checkRemoveAll($this->host_name_remove, 'host prototype', self::IS_PROTOTYPE, self::LLD_ID);
	}

	/**
	 * @dataProvider getCheckInheritedMacrosData
	 */
	public function testFormMacrosHostPrototype_ChangeInheritedMacro($data) {
		$this->checkChangeInheritedMacros($data, 'host prototype', self::IS_PROTOTYPE, self::LLD_ID);
	}

	public function prepareHostPrototypeRemoveMacrosData() {
		$response = CDataHelper::call('hostprototype.create', [
				'host' => 'Host prototype for Inherited {#MACROS} removing',
				'ruleid' => self::LLD_ID,
				'groupLinks' =>  [
					[
						'groupid'=> 4
					]
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
		self::$host_prototoypeid_remove_inherited = $response['hostids'][0];
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 *
	 * @onBeforeOnce prepareHostPrototypeRemoveMacrosData
	 */
	public function testFormMacrosHostPrototype_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'host prototype', self::$host_prototoypeid_remove_inherited,
				self::IS_PROTOTYPE, self::LLD_ID);
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
		$this->createSecretMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid=90001&hostid=99205',
				'host-prototype'
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
		$this->updateSecretMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid=90001&hostid=99206', 'host-prototype');
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormMacrosHostPrototype_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid=90001&hostid=99206', 'host-prototype');
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosHostPrototype_CreateVaultMacros($data) {
		$this->createVaultMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid=90001&hostid=99205', 'host-prototype');
	}

	/**
	 * @dataProvider getUpdateVaultMacrosData
	 */
	public function testFormMacrosHostPrototype_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'host_prototypes.php?form=update&context=host&parent_discoveryid=90003&hostid=90008', 'host-prototype');
	}
}
