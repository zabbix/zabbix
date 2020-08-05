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
class testFormHostMacros extends testFormMacros {

	use MacrosTrait;

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

	public static function getCreateHostMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'Name' => 'Host With Macros',
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
						'success_message' => 'Host added'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host Without dollar in Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}'
						]
					],
					'error_message' => 'Cannot add host',
					'error_details' => 'Invalid macro "{MACRO}": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host With empty Macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error_message' => 'Cannot add host',
					'error_details' => 'Invalid macro "": macro is empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host With repeated Macros',
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
					'error_message' => 'Cannot add host',
					'error_details' => 'Macro "{$MACRO}" is not unique.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Host With repeated regex Macros',
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
					'error_message' => 'Cannot add host',
					'error_details' => 'Macro "{$MACRO:regex:"^[0-9].*$"}" is not unique.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateHostMacrosData
	 */
	public function testFormHostMacros_Create($data) {
		$this->checkCreate($data, 'hosts', 'host');
	}

	public static function getUpdateHostMacrosData() {
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
					'success_message' => 'Host updated'
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
					'success_message' => 'Host updated'
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
					'success_message' => 'Host updated'
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
					'success_message' => 'Host updated'
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
					'error_message' => 'Cannot update host',
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
					'error_message' => 'Cannot update host',
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
					'error_message' => 'Cannot update host',
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
					'error_message' => 'Cannot update host',
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
					'error_message' => 'Cannot update host',
					'error_details' => 'Macro "{$MACRO:regex:^[0-9].*$}" is not unique.'
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateHostMacrosData
	 */
	public function testFormHostMacros_Update($data) {
		$this->checkUpdate($data, $this->host_name_update, 'hosts', 'host');
	}

	public function testFormHostMacros_Remove() {
		$this->checkRemove($this->host_name_remove, 'hosts', 'host');
	}

	public function testFormHostMacros_ChangeRemoveInheritedMacro() {
		$this->checkChangeRemoveInheritedMacro('hosts', 'host');
	}

	public function getSecretMacrosLayoutData() {
		return [
			[
				[
					'macro' => '{$SECRET_HOST_MACRO}',
					'type' => 'Secret text'
				]
			],
			[
				[
					'macro' => '{$SECRET_HOST_MACRO}',
					'type' => 'Secret text',
					'chenge_type' => true
				]
			],
			[
				[
					'macro' => '{$TEXT_HOST_MACRO}',
					'type' => 'Text'
				]
			],
			[
				[
					'global' => true,
					'macro' => '{$X_TEXT_2_SECRET}',
					'type' => 'Text'
				]
			],
			[
				[
					'global' => true,
					'macro' => '{$X_SECRET_2_SECRET}',
					'type' => 'Secret text'
				]
			]
		];
	}

	/**
	 * @dataProvider getSecretMacrosLayoutData
	 */
	public function testFormHostMacros_CheckSecretMacrosLayout($data) {
		$this->checkSecretMacrosLayout($data, 'hosts.php?form=update&hostid=99011', 'hosts');
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
							'text' => 'host secret value',
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
							'text' => 'host plain text value',
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
	public function testFormHostMacros_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'hosts.php?form=update&hostid=99134', 'hosts');
	}

	public function getRevertSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => '{$SECRET_HOST_MACRO_REVERT}',
						'value' => 'Secret host value'
					]
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$SECRET_HOST_MACRO_2_TEXT_REVERT}',
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
	public function testFormHostMacros_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'hosts.php?form=update&hostid=99135', 'hosts');
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
	public function testFormHostMacros_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'hosts.php?form=update&hostid=99135', 'hosts');
	}

	public function testFormHostMacros_ResolveSecretMacro() {
		$macro = [
			'macro' => '{$X_SECRET_HOST_MACRO_2_RESOLVE}',
			'value' => 'Value 2 B resolved'
		];
		$this->resolveSecretMacro($macro, 'hosts.php?form=update&hostid=99135', 'hosts', 'host');
	}
}
