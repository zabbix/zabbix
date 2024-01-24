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
 * @dataSource DiscoveredHosts, HostTemplateGroups
 */
class testFormHostGroupStandalone extends testFormGroups {

	protected $standalone = true;
	protected $link = 'zabbix.php?action=hostgroup.edit&groupid=';
	protected $object = 'host';
	protected static $update_group = 'Group for Update test';

	public function testFormHostGroupStandalone_Layout() {
		$this->layout('Zabbix servers');
	}

	public function testFormHostGroupStandalone_DiscoveredLayout() {
		$this->layout(self::DISCOVERED_GROUP, self::LLD);
	}

	public static function getHostValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => self::DISCOVERED_GROUP
					],
					'error' => 'Host group "'.self::DISCOVERED_GROUP.'" already exists.'
				]
			]
		];
	}

	public static function getHostCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Zabbix servers'
					],
					'error' => 'Host group "Zabbix servers" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Templates'
					]
				]
			],
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
	 * @dataProvider getHostValidationData
	 * @dataProvider getHostCreateData
	 */
	public function testFormHostGroupStandalone_Create($data) {
		$this->checkForm($data, 'create');
	}

	public static function getHostUpdateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Zabbix servers',
						'Apply permissions and tag filters to all subgroups' => true
					],
					'error' => 'Host group "Zabbix servers" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Templates/Applications'
					]
				]
			],
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
	 * @dataProvider getHostValidationData
	 * @dataProvider getHostUpdateData
	 */
	public function testFormHostGroupStandalone_Update($data) {
		$this->checkForm($data, 'update');
	}

	/**
	 * Test group simple update without changing data.
	 */
	public function testFormHostGroupStandalone_SimpleUpdate() {
		$this->simpleUpdate(self::DISCOVERED_GROUP, true);
	}

	public static function getHostCloneData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => self::DISCOVERED_GROUP,
					'fields' => [
						'Group name' => self::DISCOVERED_GROUP.' cloned group'
					],
					'discovered' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 * @dataProvider getHostCloneData
	 */
	public function testFormHostGroupStandalone_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostGroupStandalone_Cancel($data) {
		$this->cancel($data);
	}

	public static function getHostDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => self::DELETE_ONE_GROUP,
					'error' => 'Host "Host for host group testing" cannot be without host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Maintenance',
					'error' => 'Cannot delete host group "Group for Maintenance" because maintenance'.
						' "Maintenance for host group testing" must contain at least one host or host group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Correlation',
					'error' => 'Group "Group for Correlation" cannot be deleted, because it is used in a correlation condition.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Script',
					'error' => 'Host group "Group for Script" cannot be deleted, because it is used in a global script.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Group for Host prototype',
					'error' => 'Group "Group for Host prototype" cannot be deleted, because it is used by a host prototype.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discovered hosts',
					'error' => 'Host group "Discovered hosts" is group for discovered hosts and cannot be deleted.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 * @dataProvider getHostDeleteData
	 */
	public function testFormHostGroupStandalone_Delete($data) {
		$this->delete($data);
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgroupsData
	 */
	public function testFormHostGroupStandalone_ApplyPermissionsToSubgroups($data) {
		$this->checkSubgroupsPermissions($data);
	}
}
