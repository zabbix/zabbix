<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @dataSource DiscoveredHosts
 */
class testFormHostGroup extends testFormGroups {

	public $link = 'hostgroups.php?form=update&groupid=';
	public static $update_group = 'Group for Update test';

	public function testFormHostGroup_Layout() {
		$this->layout('Zabbix servers');
	}

	public function testFormHostGroups_DiscoveredLayout() {
		$this->layout(self::DISCOVERED_GROUP, true);
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostGroup_Create($data) {
		$this->create($data);
	}

	/**
	 * @dataProvider getUpdateData
	 */
	public function testFormHostGroup_Update($data) {
		$this->update($data);
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
