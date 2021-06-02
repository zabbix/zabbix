<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormValueMappings.php';

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
		CDataHelper::setSessionId(null);

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
						'value' => 'oneoneoneoneoneoneoneoneoneoneone',
						'newvalue' => '11111111111'
					],
					[
						'value' => 'two',
						'newvalue' => '2'
					],
					[
						'value' => 'threethreethreethreethreethreethreethreethreethree',
						'newvalue' => '3333333333'
					],
					[
						'value' => 'four',
						'newvalue' => '4'
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
	 * Scenario for verifying that value mappings are correctly copied to the full clone of the host.
	 */
	public function testFormValueMappingsHost_FullClone() {
		$this->checkClone('host', 'Full clone');
	}
}
