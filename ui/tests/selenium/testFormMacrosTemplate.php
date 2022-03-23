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

	public $vault_object = 'template';
	public $vault_error_field = '/1/macros/6/value';
	public $update_vault_macro = '{$VAULT_HOST_MACRO_CHANGED}';
	public $vault_macro_index = 0;

	public $revert_macro_1 = '{$SECRET_TEMPLATE_MACRO_REVERT}';
	public $revert_macro_2 = '{$SECRET_TEMPLATE_MACRO_2_TEXT_REVERT}';
	public $revert_macro_object = 'template';

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

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosTemplate_CreateVaultMacros($data) {
		$this->createVaultMacros($data, 'templates.php?form=update&templateid=99022', 'templates');
	}

	/**
	 * @dataProvider getUpdateVaultMacrosData
	 */
	public function testFormMacrosTemplate_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'templates.php?form=update&templateid=99014', 'templates');
	}
}
