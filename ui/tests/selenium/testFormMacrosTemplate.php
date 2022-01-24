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

require_once dirname(__FILE__) . '/common/testFormMacros.php';

/**
 * @backup hosts
 */
class testFormMacrosTemplate extends testFormMacros {

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

	/**
	 * The id of the template for removing inherited macros.
	 *
	 * @var integer
	 */
	protected static $templateid_remove_inherited;

	/**
	 * @dataProvider getCreateMacrosData
	 */
	public function testFormMacrosTemplate_Create($data) {
		$this->checkMacros($data, 'template');
	}

	/**
	 * @dataProvider getUpdateMacrosData
	 */
	public function testFormMacrosTemplate_Update($data) {
		$this->checkMacros($data, 'template', $this->template_name_update, true);
	}

	public function testFormMacrosTemplate_RemoveAll() {
		$this->checkRemoveAll($this->template_name_remove, 'template');
	}

	/**
	 * @dataProvider getCheckInheritedMacrosData
	 */
	public function testFormMacrosTemplate_ChangeInheritedMacro($data) {
		$this->checkChangeInheritedMacros($data, 'template');
	}

	public function prepareTemplateRemoveMacrosData() {
		$response = CDataHelper::call('template.create', [
				'host' => 'Template for Inherited macros removing',
				'groups' => [
					['groupid' => '4']
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
		$this->assertArrayHasKey('templateids', $response);
		self::$templateid_remove_inherited = $response['templateids'][0];
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 *
	 * @onBeforeOnce prepareTemplateRemoveMacrosData
	 */
	public function testFormMacrosTemplate_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'template', self::$templateid_remove_inherited);
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
	public function testFormMacrosTemplate_CreateSecretMacros($data) {
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
	public function testFormMacrosTemplate_RevertSecretMacroChanges($data) {
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
	public function testFormMacrosTemplate_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'templates.php?form=update&templateid=99137', 'templates');
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
					'message' => 'Invalid parameter "/1/macros/6/value": incorrect syntax near "path:".'
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
					'message' => 'Invalid parameter "/1/macros/6/value": incorrect syntax near "/path:key".'
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
					'message' => 'Invalid parameter "/1/macros/6/value": incorrect syntax near "path:key".'
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
					'message' => 'Invalid parameter "/1/macros/6/value": incorrect syntax near ":key".'
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
					'message' => 'Invalid parameter "/1/macros/6/value": incorrect syntax near "path".'
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
					'message' => 'Invalid parameter "/1/macros/6/value": incorrect syntax near "/secret/path:key".'
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
					'message' => 'Invalid parameter "/1/macros/6/value": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosTemplate_CreateVaultMacros($data) {
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
	public function testFormMacrosTemplate_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'templates.php?form=update&templateid=99014', 'templates');
	}
}
