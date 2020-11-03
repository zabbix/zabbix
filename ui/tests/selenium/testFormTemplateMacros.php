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

require_once dirname(__FILE__) . '/common/testFormMacros.php';

/**
 * @backup hosts
 */
class testFormTemplateMacros extends testFormMacros {

	use MacrosTrait;

	/**
	 * The name of the template for updating macros, id=40000.
	 *
	 * @var string
	 */
	protected $template_name_update = 'Form test template';

	/**
	 * The name of the template for removing macros, id=99016.
	 *
	 * @var string
	 */
	protected $template_name_remove = 'Template to test graphs';

	public static function getCreateTemplateMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'Name' => 'Template With Macros',
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
						'success_message' => 'Template added'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Template Without dollar in Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}'
						]
					],
					'error_message' => 'Cannot add template',
					'error_details' => 'Invalid macro "{MACRO}": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Template With empty Macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error_message' => 'Cannot add template',
					'error_details' => 'Invalid macro "": macro is empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Template With repeated Macros',
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
					'error_message' => 'Cannot add template',
					'error_details' => 'Macro "{$MACRO}" is not unique.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Template With repeated regex Macros',
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
					'error_message' => 'Cannot add template',
					'error_details' => 'Macro "{$MACRO:regex:"^[0-9].*$"}" is not unique.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateTemplateMacrosData
	 */
	public function testFormTemplateMacros_Create($data) {
		$this->checkCreate($data, 'templates', 'template');
	}

	public static function getUpdateTemplateMacrosData() {
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
							'description' => 'updated description 1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated value 2',
							'description' => 'Updated description 2'
						]
					],
					'success_message' => 'Template updated'
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
							'description' => 'Updated Description 3',
						]
					],
					'success_message' => 'Template updated'
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
					'success_message' => 'Template updated'
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
					'success_message' => 'Template updated'
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
					'error_message' => 'Cannot update template',
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
					'error_message' => 'Cannot update template',
					'error_details' => 'Invalid macro "": macro is empty.'
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
					'error_message' => 'Cannot update template',
					'error_details' => 'Macro "{$MACRO}" is not unique.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex Macros',
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
					'error_message' => 'Cannot update template',
					'error_details' => 'Macro "{$M:regex:"[a-z]"}" is not unique.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex Macros and quotes',
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
					'error_message' => 'Cannot update template',
					'error_details' => 'Macro "{$MACRO:regex:^[0-9].*$}" is not unique.'
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateTemplateMacrosData
	 */
	public function testFormTemplateMacros_Update($data) {
		$this->checkUpdate($data, $this->template_name_update, 'templates', 'template');
	}

	public function testFormTemplateMacros_Remove() {
		$this->checkRemove($this->template_name_remove, 'templates', 'template');
	}

	public function testFormTemplateMacros_ChangeRemoveInheritedMacro() {
		$this->checkChangeRemoveInheritedMacro('templates', 'template');
	}

	public function getCreateSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'macro' => '{$SECRET_MACRO}',
						'value' => [
							'text' => 'template secret value',
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
						'macro' => '{$TEXT_MACRO}',
						'value' => [
							'text' => 'template plain text value',
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
						'macro' => '{$SECRET_EMPTY_MACRO}',
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
	public function testFormTemplateMacros_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'templates.php?form=update&templateid=99022', 'templates');
	}

	public function getRevertSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => '{$SECRET_TEMPLATE_MACRO_REVERT}',
						'value' => 'Secret template value'
					]
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$SECRET_TEMPLATE_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret template value 2'
					],
					'set_to_text' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormTemplateMacros_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'templates.php?form=update&templateid=99137', 'templates');
	}

	public function getUpdateSecretMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 2,
					'macro' => '{$SECRET_TEMPLATE_MACRO_UPDATE}',
					'value' => [
						'text' => 'Updated secret value'
					]
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 3,
					'macro' => '{$SECRET_TEMPLATE_MACRO_UPDATE_2_TEXT}',
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
					'macro' => '{$TEXT_TEMPLATE_MACRO_2_SECRET}',
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
	public function testFormTemplateMacros_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'templates.php?form=update&templateid=99137', 'templates');
	}

	public function testFormTemplateMacros_SecretMacroResolution() {
		$macro = [
			'macro' => '{$X_SECRET_HOST_MACRO_2_RESOLVE}',
			'value' => 'Value 2 B resolved'
		];
		$this->resolveSecretMacro($macro, 'hosts.php?form=update&hostid=99135', 'hosts', 'host');
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
					'title' => 'Template updated'
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
					'title' => 'Template updated'
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
					'title' => 'Cannot update template',
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
					'title' => 'Cannot update template',
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
					'title' => 'Cannot update template',
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
					'title' => 'Cannot update template',
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
					'title' => 'Cannot update template',
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
					'title' => 'Cannot update template',
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
					'title' => 'Cannot update template',
					'message' => 'Invalid value for macro "{$VAULT_MACRO9}": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormTemplateMacros_CreateVaultMacros($data) {
		$this->createVaultMacros($data, 'templates.php?form=update&templateid=99022', 'templates');
	}

	public function getUpdateVaultMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => 0,
					'macro' => '{$VAULT_HOST_MACRO_CHANGED}',
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
					'macro' => '{$VAULT_HOST_MACRO_CHANGED}',
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
					'macro' => '{$VAULT_HOST_MACRO_CHANGED}',
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
	public function testFormTemplateMacros_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'templates.php?form=update&templateid=99014', 'templates');
	}
}
