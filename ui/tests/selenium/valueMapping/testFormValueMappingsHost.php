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
 * @onBefore prepareHostValueMappings
 */
class testFormValueMappingsHost extends testFormValueMappings {

	/**
	 * Function creates the given value mappings for the specified host.
	 */
	public static function prepareHostValueMappings() {
		CDataHelper::call('valuemap.create', [
			[
				'name' => self::UPDATE_VALUEMAP1,
				'hostid' => self::HOSTID,
				'mappings' => [
					[
						'value' => '',
						'newvalue' => 'reference newvalue'
					]
				]
			],
			[
				'name' => self::UPDATE_VALUEMAP2,
				'hostid' => self::HOSTID,
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
				'hostid' => self::HOSTID,
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

	public function testFormValueMappingsHost_Layout() {
		$this->checkLayout('host');
	}

	/**
	 * @backupOnce valuemap
	 *
	 * @dataProvider getValuemapData
	 */
	public function testFormValueMappingsHost_Create($data) {
		$this->checkAction($data, 'host', 'create');
	}

	/**
	 * @backupOnce valuemap
	 *
	 * @dataProvider getValuemapData
	 */
	public function testFormValueMappingsHost_Update($data) {
		$this->checkAction($data, 'host', 'update');
	}

	public function testFormValueMappingsHost_SimpleUpdate() {
		$this->checkSimpleUpdate('host');
	}

	public function testFormValueMappingsHost_Cancel() {
		$this->checkCancel('host');
	}

	/**
	 * @backup valuemap
	 */
	public function testFormValueMappingsHost_Delete() {
		$this->checkDelete('host');
	}

	/**
	 * Scenario for checking that the entered valuemap data is not lost if there is an error when saving the template.
	 */
	public function testFormValueMappingsHost_ErrorWhileSaving() {
		$this->checkSavingError('host');
	}

	/**
	 * Scenario for verifying that value mappings are correctly copied to the clone of the host.
	 */
	public function testFormValueMappingsHost_Clone() {
		$this->checkClone('host');
	}

	/**
	 * Scenario for verifying position of draggable element in value mapping of the hosts for mass update case.
	 */
	public function testFormValueMappingsHost_ValuemappingScreenshot() {
		$this->checkMassValuemappingScreenshot('hosts');
	}
}
