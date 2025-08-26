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


require_once __DIR__.'/../common/testFormValueMappings.php';

/**
 * @backup valuemap, hosts
 *
 * @onBefore prepareTemplateValueMappings
 */
class testFormValueMappingsTemplate extends testFormValueMappings {
	/**
	 * Function creates the given value mappings for the specified template.
	 */
	public static function prepareTemplateValueMappings() {
		CDataHelper::call('valuemap.create', [
			[
				'name' => self::UPDATE_VALUEMAP1,
				'hostid' => self::TEMPLATEID,
				'mappings' => [
					[
						'value' => '',
						'newvalue' => 'reference newvalue'
					]
				]
			],
			[
				'name' => self::UPDATE_VALUEMAP2,
				'hostid' => self::TEMPLATEID,
				'mappings' => [
					[
						'value' => '',
						'newvalue' => 'no data'
					],
					[
						'value' => '1',
						'newvalue' => 'one'
					],
					[
						'value' => '2',
						'newvalue' => 'two'
					],
					[
						'value' => '3',
						'newvalue' => 'three'
					]
				]
			],
			[
				'name' => self::DELETE_VALUEMAP,
				'hostid' => self::TEMPLATEID,
				'mappings' => [
					[
						'type' => '0',
						'value' => '1010101010101010101010101010101',
						'newvalue' => 'default value1010101010101010101010101010101'
					],
					[
						'type' => '4',
						'value' => '424242424242424242424242424242424242424242424242',
						'newvalue' => 'Answer to the Ultimate Question of Life, Universe and Everything'
					],
					[
						'type' => '3',
						'value' => '123458945-987653341',
						'newvalue' => 'from 123458945 to 987653341'
					],
					[
						'type' => '1',
						'value' => '12',
						'newvalue' => 'greater or equals 12'
					],
					[
						'type' => '2',
						'value' => '11',
						'newvalue' => 'less or equals 11'
					],
					[
						'type' => '5',
						'newvalue' => 'default value'
					]
				]
			]
		]);
	}

	public function testFormValueMappingsTemplate_Layout() {
		$this->checkLayout('template');
	}

	/**
	 * @backupOnce valuemap
	 *
	 * @dataProvider getValuemapData
	 */
	public function testFormValueMappingsTemplate_Create($data) {
		$this->checkAction($data, 'template', 'create');
	}

	/**
	 * @backupOnce valuemap
	 *
	 * @dataProvider getValuemapData
	 */
	public function testFormValueMappingsTemplate_Update($data) {
		$this->checkAction($data, 'template', 'update');
	}

	public function testFormValueMappingsTemplate_SimpleUpdate() {
		$this->checkSimpleUpdate('template');
	}

	public function testFormValueMappingsTemplate_Cancel() {
		$this->checkCancel('template');
	}

	/**
	 * @backup valuemap
	 */
	public function testFormValueMappingsTemplate_Delete() {
		$this->checkDelete('template');
	}

	/**
	 * Scenario for checking that the entered valuemap data is not lost if there is an error when saving the template.
	 */
	public function testFormValueMappingsTemplate_ErrorWhileSaving() {
		$this->checkSavingError('template');
	}

	/**
	 * Scenario for verifying that value mappings are correctly copied to the clone of the template.
	 */
	public function testFormValueMappingsTemplate_Clone() {
		$this->checkClone('template');
	}

	/**
	 * Scenario for verifying position of draggable element in value mapping of the templates for mass update case.
	 */
	public function testFormValueMappingsTemplate_ValuemappingScreenshot() {
		$this->checkMassValuemappingScreenshot('templates');
	}
}
