<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
class testFormHostPrototypeMacros extends testFormMacros {

	// Parent LLD for Host prototypes 'Discovery rule 1' host: 'Host for host prototype tests'.
	const LLD_ID			= 90001;
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

	public static function getCreateHostPrototypeMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'Name' => 'Host prototype With {#MACROS}',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$1234}',
							'value' => '!@#$%^&*()_+/*',
							'description' => '!@#$%^&*()_+/*'
						],
						[
							'macro' => '{$M:regex:^[0-9a-z]}',
							'value' => 'regex',
							'description' => 'context macro with regex'
						],
						[
							'macro' => '{$MACRO1}',
							'value' => 'Value_1',
							'description' => 'Test macro Description 1'
						],
						[
							'macro' => '{$MACRO3}',
							'value' => '',
							'description' => ''
						],
						[
							'macro' => '{$MACRO4}',
							'value' => 'value',
							'description' => ''
						],
						[
							'macro' => '{$MACRO5}',
							'value' => '',
							'description' => 'DESCRIPTION'
						],
						[
							'macro' => '{$MACRO6}',
							'value' => 'Значение',
							'description' => 'Описание'
						],
						[
							'macro' => '{$MACRO:A}',
							'value' => '{$MACRO:A}',
							'description' => '{$MACRO:A}'
						],
						[
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => '',
							'description' => ''
						]
					],
					'success_message' => 'Host prototype added'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Without dollar in {#MACROS}',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}'
						]
					],
					'error_message' => 'Cannot add host prototype',
					'error_details' => 'Invalid macro "{MACRO}": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host prototype With empty {#MACRO}',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error_message' => 'Cannot add host prototype',
					'error_details' => 'Invalid parameter "/1/macros/1/macro": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host prototype With repeated {#MACROS}',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_message' => 'Cannot add host prototype',
					'error_details' => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host prototype With repeated regex {#MACROS}',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_message' => 'Cannot add host prototype',
					'error_details' => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO:regex:"^[0-9].*$"}) already exists.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateHostPrototypeMacrosData
	 */
	public function testFormHostPrototypeMacros_Create($data) {
		$this->checkCreate($data, 'host-prototype', 'host', self::IS_PROTOTYPE, self::LLD_ID);
	}

	public static function getUpdateHostPrototypeMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$UPDATED_MACRO1}',
							'value' => 'updated value1',
							'description' => 'updated description 1',
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated value 2',
							'description' => 'Updated description 2',
						]
					],
					'success_message' => 'Host prototype updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$UPDATED_MACRO1}',
							'value' => '',
							'description' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated Value 2',
							'description' => ''
						],
						[
							'macro' => '{$UPDATED_MACRO3}',
							'value' => '',
							'description' => 'Updated Description 3'
						]
					],
					'success_message' => 'Host prototype updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:A}',
							'value' => '{$MACRO:B}',
							'description' => '{$MACRO:C}'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO_1}',
							'value' => '',
							'description' => 'DESCRIPTION'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$UPDATED_MACRO_2}',
							'value' => 'Значение',
							'description' => 'Описание'
						]
					],
					'success_message' => 'Host prototype updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:regex:"^[a-z]"}',
							'value' => 'regex',
							'description' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO:regex:^[0-9a-z]}',
							'value' => '',
							'description' => 'DESCRIPTION'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$UPDATED_MACRO_2}',
							'value' => 'Значение',
							'description' => 'Описание'
						]
					],
					'success_message' => 'Host prototype updated'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Without dollar in Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}'
						]
					],
					'error_message' => 'Cannot update host prototype',
					'error_details' => 'Invalid macro "{MACRO}": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With empty Macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error_message' => 'Cannot update host prototype',
					'error_details' => 'Invalid parameter "/1/macros/1/macro": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_message' => 'Cannot update host prototype',
					'error_details' => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex {#MACROS}',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$M:regex:"[a-z]"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$M:regex:"[a-z]"}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_message' => 'Cannot update host prototype',
					'error_details' => 'Invalid parameter "/1/macros/2": value (macro)=({$M:regex:"[a-z]"}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex {#MACROS} and quotes',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO:regex:^[0-9].*$}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_message' => 'Cannot update host prototype',
					'error_details' => 'Macro "{$MACRO:regex:^[0-9].*$}" is not unique.'
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateHostPrototypeMacrosData
	 */
	public function testFormHostPrototypeMacros_Update($data) {
		$this->checkUpdate($data, $this->host_name_update, 'host-prototype', 'host', self::IS_PROTOTYPE, self::LLD_ID);
	}

	public function testFormHostPrototypeMacros_Remove() {
		$this->checkRemove($this->host_name_remove, 'host-prototype', 'host', self::IS_PROTOTYPE, self::LLD_ID);
	}

	public function testFormHostPrototypeMacros_ChangeRemoveInheritedMacro() {
		$this->checkChangeRemoveInheritedMacro('host-prototype', 'host', self::IS_PROTOTYPE, self::LLD_ID);
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
	public function testFormHostPrototypeMacros_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'host_prototypes.php?form=update&parent_discoveryid=90001&hostid=99205', 'host-prototype');
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
	public function testFormHostPrototypeMacros_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'host_prototypes.php?form=update&parent_discoveryid=90001&hostid=99206', 'host-prototype');
	}

	public function getRevertSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_HOST_PROTOTYPE_MACRO_REVERT}',
						'value' => 'Secret host value'
					]
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$Z_HOST_PROTOTYPE_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2'
					],
					'set_to_text' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormHostPrototypeMacros_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'host_prototypes.php?form=update&parent_discoveryid=90001&hostid=99206', 'host-prototype');
	}

	public function getCreateVaultMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO}',
						'value' => [
							'text' => 'secret/path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description'
					],
					'title' => 'Host prototype updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO2}',
						'value' => [
							'text' => 'one/two/three/four/five/six:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description7'
					],
					'title' => 'Host prototype updated'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO3}',
						'value' => [
							'text' => 'secret/path:',
							'type' => 'Vault secret'
						],
						'description' => 'vault description2'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO3}": incorrect syntax near "path:".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO4}',
						'value' => [
							'text' => '/path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description3'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO4}": incorrect syntax near "/path:key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO5}',
						'value' => [
							'text' => 'path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description4'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO5}": incorrect syntax near "path:key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO6}',
						'value' => [
							'text' => ':key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description5'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO6}": incorrect syntax near ":key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO7}',
						'value' => [
							'text' => 'secret/path',
							'type' => 'Vault secret'
						],
						'description' => 'vault description6'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO7}": incorrect syntax near "path".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO8}',
						'value' => [
							'text' => '/secret/path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description8'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO8}": incorrect syntax near "/secret/path:key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO9}',
						'value' => [
							'text' => '',
							'type' => 'Vault secret'
						],
						'description' => 'vault description9'
					],
					'title' => 'Cannot update host prototype',
					'message' => 'Invalid value for macro "{$VAULT_MACRO9}": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormHostPrototypeMacros_CreateVaultMacros($data) {
		$this->createVaultMacros($data, 'host_prototypes.php?form=update&parent_discoveryid=90001&hostid=99205', 'host-prototype');
	}

	public function getUpdateVaultMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 0,
					'macro' => '{$VAULT_HOST_MACRO3_CHANGED}',
					'value' => [
						'text' => 'secret/path:key'
					],
					'description' => ''
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 0,
					'macro' => '{$VAULT_HOST_MACRO3_CHANGED}',
					'value' => [
						'text' => 'new/path/to/secret:key'
					],
					'description' => ''
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 0,
					'macro' => '{$VAULT_HOST_MACRO3_CHANGED}',
					'value' => [
						'text' => 'new/path/to/secret:key'
					],
					'description' => 'Changing description'
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateVaultMacrosData
	 */
	public function testFormHostPrototypeMacros_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'host_prototypes.php?form=update&parent_discoveryid=90003&hostid=90008', 'host-prototype');
	}
}
