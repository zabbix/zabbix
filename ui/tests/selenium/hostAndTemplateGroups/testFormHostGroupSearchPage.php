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


require_once dirname(__FILE__).'/../common/testFormGroups.php';

/**
 * @backup hosts
 *
 * @onBefore prepareGroupData
 *
 * @dataSource DiscoveredHosts, HostTemplateGroups
 */
class testFormHostGroupSearchPage extends testFormGroups {

	protected $link = 'zabbix.php?action=search&search=group';
	protected $object = 'host';
	protected $search = 'true';
	protected static $update_group = 'Group for Update test';

	public function testFormHostGroupSearchPage_Layout() {
		$this->link = 'zabbix.php?action=search&search=Zabbix+servers';
		$this->layout('Zabbix servers');
	}

	public function testFormHostGroupSearchPage_DiscoveredLayout() {
		$this->link = 'zabbix.php?action=search&search='.self::DISCOVERED_GROUP;
		$this->layout(self::DISCOVERED_GROUP, true);
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
						'Group name' => 'Templates/Update'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => str_repeat('updat', 51)
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
	public function testFormHostGroupSearchPage_Update($data) {
		$this->link = 'zabbix.php?action=search&search=updat';
		$this->checkForm($data, 'update');
	}

	/**
	 * Test group simple update without changing data.
	 */
	public function testFormHostGroupSearchPage_SimpleUpdate() {
		$this->link = 'zabbix.php?action=search&search='.self::DISCOVERED_GROUP;
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
	public function testFormHostGroupSearchPage_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostGroupSearchPage_Cancel($data) {
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
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 * @dataProvider getHostDeleteData
	 */
	public function testFormHostGroupSearchPage_Delete($data) {
		$this->delete($data);
	}

	public static function getSubgroupPermissionsData() {
		return [
			[
				[
					'apply_permissions' => 'Europe/Test',
					// Permission inheritance doesn't apply from first level group e.x. 'Streets'
					// when changing the name of existing group to new subgroup name.
					'open_form' => 'Europe group for test on search page',
					'create' => 'Streets/Dzelzavas',
					'groups_after' => [
						[
							'groups' => ['Europe/Test', 'Europe/Test/Zabbix'],
							'Permissions' => 'Read-write'
						],
						[
							'groups' => ['Cities/Cesis', 'Europe/Latvia'],
							'Permissions' => 'Read'
						],
						[
							'groups' => ['Europe', 'Streets'],
							'Permissions' => 'Deny'
						]
					],
					'tags_after' => [
						[
							'Host groups' => 'Cities/Cesis',
							'Tags' => 'city: Cesis'
						],
						[
							'Host groups' => 'Europe',
							'Tags' => 'world'
						],
						[
							'Host groups' => 'Europe/Test',
							'Tags' => 'country: test'
						],
						[
							'Host groups' => 'Europe/Test/Zabbix',
							'Tags' => 'country: test'
						],
						[
							'Host groups' => 'Streets',
							'Tags' => 'street'
						]
					]
				]
			],
			[
				[
					'apply_permissions' => 'Europe',
					// After renaming a subgroup, all permissions remain the same.
					'open_form' => 'Europe/Test',
					'create' => 'Streets/Terbatas',
					'groups_after' => [
						[
							'groups' => 'Streets/Terbatas',
							'Permissions' => 'Read-write'
						],
						[
							'groups' => 'Cities/Cesis',
							'Permissions' => 'Read'
						],
						[
							'groups' => ['Europe', 'Europe/Latvia', 'Europe/Test/Zabbix', 'Europe/Latvia/Riga/Zabbix',
								'Streets'],
							'Permissions' => 'Deny'
						]
					],
					'tags_after' => [
						[
							'Host groups' => 'Cities/Cesis',
							'Tags' => 'city: Cesis'
						],
						[
							'Host groups' => 'Europe',
							'Tags' => 'world'
						],
						[
							'Host groups' => 'Europe/Latvia',
							'Tags' => 'world'
						],
						[
							'Host groups' => 'Europe/Latvia/Riga/Zabbix',
							'Tags' => 'world'
						],
						[
							'Host groups' => 'Europe/Test/Zabbix',
							'Tags' => 'world'
						],
						[
							'Host groups' => 'Streets',
							'Tags' => 'street'
						],
						[
							'Host groups' => 'Streets/Terbatas',
							'Tags' => 'country: test'
						]
					]
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgroupPermissionsData
	 */
	public function testFormHostGroupSearchPage_ApplyPermissionsToSubgroups($data) {
		$this->link = 'zabbix.php?action=search&search=europe';
		$this->checkSubgroupsPermissions($data);
	}
}
