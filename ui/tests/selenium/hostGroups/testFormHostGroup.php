<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testFormGroups.php';

/**
 * @backup hosts
 *
 * @onBefore prepareGroupData
 *
 * @dataSource DiscoveredHosts, HostGroups
 */
class testFormHostGroup extends testFormGroups {

	protected $link = 'hostgroups.php?form=update&groupid=';
	protected static $update_group = 'Group for Update test';

	public function testFormHostGroup_Layout() {
		$this->layout('Zabbix servers');
	}

	public function testFormHostGroups_DiscoveredLayout() {
		$this->layout(self::DISCOVERED_GROUP, true);
	}

	public static function getGroupCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => STRING_255
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 * @dataProvider getGroupCreateData
	 */
	public function testFormHostGroup_Create($data) {
		$this->checkForm($data, 'create');
	}

	public static function getGroupUpdateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => str_repeat('long_', 51)
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 * @dataProvider getGroupUpdateData
	 */
	public function testFormHostGroup_Update($data) {
		$this->checkForm($data, 'update');
	}

	public function testFormHostGroup_SimpleUpdate() {
		$this->simpleUpdate(self::DISCOVERED_GROUP);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostGroup_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostGroup_Cancel($data) {
		$this->cancel($data);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostGroup_Delete($data) {
		$this->delete($data);
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgoupsData
	 */
	public function testFormHostGroup_ApplyPermissionsToSubgroups($data) {
		$this->checkSubgroupsPermissions($data);
	}
}
