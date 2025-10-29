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


require_once __DIR__ . '/../common/testFormMacros.php';

/**
 * @backup hosts, config
 *
 * @onBefore prepareTemplateMacrosData
 */
class testFormMacrosTemplate extends testFormMacros {

	const TEMPLATES_GROUP = 1;
	protected $vault_object = 'template';
	protected $hashi_error_field = '/1/macros/4/value';
	protected $cyber_error_field = '/1/macros/4/value';
	protected $update_vault_macro = '{$VAULT_TEMPLATE_MACRO_CHANGED}';
	protected $vault_macro_index = 0;
	protected $revert_macro_1 = '{$SECRET_TEMPLATE_MACRO_REVERT}';
	protected $revert_macro_2 = '{$SECRET_TEMPLATE_MACRO_2_TEXT_REVERT}';
	protected $revert_macro_object = 'template';

	protected static $templateid_remove_inherited;

	public function prepareTemplateMacrosData() {
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template with macros',
				'groups' => ['groupid' => self::TEMPLATES_GROUP],
				'macros' => [
					['macro' => '{$TEMPLATE_MACRO1}', 'value' => ''],
					['macro' => '{$TEMPLATE_MACRO2}', 'value' => '']
				]
			],
			[
				'host' => 'Template for removing macros',
				'groups' => ['groupid' => self::TEMPLATES_GROUP],
				'macros' => [
					['macro' => '{$MACRO_FOR_REMOVE1}', 'value' => ''],
					['macro' => '{$MACRO_FOR_REMOVE2}', 'value' => '']
				]
			],
			[
				'host' => 'Template for Inherited macros removing',
				'groups' => ['groupid' => self::TEMPLATES_GROUP],
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
				'host' => 'Template for secret macros layout',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]],
				'macros' => [
					[
						'macro' => '{$SECRET_TEMPLATE_MACRO}',
						'value' => 'some secret value',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$TEXT_TEMPLATE_MACRO}',
						'value' => 'some text value'
					],
					[
						'macro' => '{$VAULT_TEMPLATE_MACRO3}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' => ZBX_MACRO_TYPE_VAULT
					]
				]
			],
			[
				'host' => 'Empty Template for creating secret macros',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]]
			],
			[
				'host' => 'Template with secret macros',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]],
				'items' => [
					[
						'name' => 'Macro value: {$X_SECRET_TEMPLATE_MACRO_2_RESOLVE}',
						'key_' => 'trap[{$X_SECRET_TEMPLATE_MACRO_2_RESOLVE}]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'macros' => [
					[
						'macro' => '{$SECRET_TEMPLATE_MACRO_REVERT}',
						'value' => 'Secret host value',
						'description' => 'Secret host macro description',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$SECRET_TEMPLATE_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2 text',
						'description' => 'Secret host macro that will be changed to text',
						'type' =>  ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$SECRET_TEMPLATE_MACRO_UPDATE_2_TEXT}',
						'value' => 'Secret host value 2 B updated',
						'description' => 'Secret host macro that is going to be updated',
						'type' =>  ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$TEXT_TEMPLATE_MACRO_2_SECRET}',
						'value' => 'Text host macro value',
						'description' => 'Text host macro that is going to become secret'
					],
					[
						'macro' => '{$SECRET_TEMPLATE_MACRO_UPDATE}',
						'value' => 'Secret host macro value',
						'description' => 'Secret host macro that is going to stay secret',
						'type' =>  ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$X_SECRET_TEMPLATE_MACRO_2_RESOLVE}',
						'value' => 'Value 2 B resolved',
						'description' => 'Host macro to be resolved'
					]
				]
			],
			[
				'host' => 'Template with vault macro',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]],
				'macros' => [
					[
						'macro' => '{$NEWMACROS}',
						'value' => 'something/value:key',
						'type' => 2
					]
				]
			],
			[
				'host' => 'Template for creating Vault macros',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]]
			],
			[
				'host' => 'Empty Template without macros',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]]
			],
			[
				'host' => 'Template for updating Vault macros',
				'groups' => [['groupid' => self::TEMPLATES_GROUP]],
				'macros' => [
					[
						'macro' => '{$VAULT_HOST_MACRO}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' =>  ZBX_MACRO_TYPE_VAULT
					]
				]
			]
		]);
		self::$templateid_remove_inherited = $templates['templateids']['Template for Inherited macros removing'];
	}

	/**
	 * @dataProvider getCreateMacrosData
	 */
	public function testFormMacrosTemplate_Create($data) {
		$this->checkMacros($data, 'template');
	}

	/**
	 * @dataProvider getUpdateMacrosNormalData
	 * @dataProvider getUpdateMacrosCommonData
	 */
	public function testFormMacrosTemplate_Update($data) {
		$this->checkMacros($data, 'template', 'Template with macros', true);
	}

	public function testFormMacrosTemplate_RemoveAll() {
		$this->checkRemoveAll('Template for removing macros', 'template');
	}

	/**
	 * @dataProvider getCheckInheritedMacrosData
	 */
	public function testFormMacrosTemplate_ChangeInheritedMacro($data) {
		$this->checkChangeInheritedMacros($data, 'template');
	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 */
	public function testFormMacrosTemplate_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'template', self::$templateid_remove_inherited,
				false, null, 'Template for Inherited macros removing'
		);
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
		$this->createSecretMacros($data, 'zabbix.php?action=template.list&filter_name='.
				'Empty Template for creating secret macros&filter_set=1',
				'templates', 'Empty Template for creating secret macros'
		);
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormMacrosTemplate_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'zabbix.php?action=template.list&filter_name='.
				'Template with secret macros&filter_set=1',
				'templates', 'Template with secret macros'
		);
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
		$this->updateSecretMacros($data, 'zabbix.php?action=template.list&filter_name=Template with secret macros&filter_set=1',
				'templates', 'Template with secret macros'
		);
	}

	/**
	 * Check Vault macros validation.
	 */
	public function testFormMacrosTemplate_CheckVaultValidation() {
		$this->checkVaultValidation('zabbix.php?action=template.list&filter_name=Template with vault macro&filter_set=1',
			'templates', 'Template with vault macro'
		);
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 */
	public function testFormMacrosTemplate_CreateVaultMacros($data) {
		$template_name = ($data['vault'] === 'Hashicorp') ? 'Template for creating Vault macros' : 'Empty Template without macros';
		$this->createVaultMacros($data, 'zabbix.php?action=template.list&filter_name='.$template_name.'&filter_set=1',
				'templates', $template_name
		);
	}

	/**
	 * @dataProvider getUpdateVaultMacrosNormalData
	 * @dataProvider getUpdateVaultMacrosCommonData
	 */
	public function testFormMacrosTemplate_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'zabbix.php?action=template.list&filter_name='.
				'Template for updating Vault macros&filter_set=1',
				'templates', 'Template for updating Vault macros'
		);
	}
}
