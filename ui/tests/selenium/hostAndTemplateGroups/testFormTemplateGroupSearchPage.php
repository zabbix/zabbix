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
 * @dataSource HostTemplateGroups
 */
class testFormTemplateGroupSearchPage extends testFormGroups {

	protected $link = 'zabbix.php?action=search&search=group';
	protected $object = 'template';
	protected $search = 'true';
	protected static $update_group = 'Group for Update test';

	public function testFormTemplateGroupSearchPage_Layout() {
		$this->link = 'zabbix.php?action=search&search=Templates';
		$this->layout('Templates');
	}

	public static function getTemplateUpdateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Hosts/Update'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Templates',
						'Apply permissions to all subgroups' => true
					],
					'error' => 'Template group "Templates" already exists.'
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
	 * @dataProvider getTemplateUpdateData
	 */
	public function testFormTemplateGroupSearchPage_Update($data) {
		$this->link = 'zabbix.php?action=search&search=updat';
		$this->checkForm($data, 'update');
	}

	/**
	 * Test group simple update without changing data.
	 */
	public function testFormTemplateGroupSearchPage_SimpleUpdate() {
		$this->link = 'zabbix.php?action=search&search=Templates';
		$this->simpleUpdate('Templates');
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormTemplateGroupSearchPage_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormTemplateGroupSearchPage_Cancel($data) {
		$this->cancel($data);
	}

	public static function getTemplateDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => self::DELETE_ONE_GROUP,
					'error' => 'Template "Template for template group testing" cannot be without template group.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 * @dataProvider getTemplateDeleteData
	 */
	public function testFormTemplateGroupSearchPage_Delete($data) {
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
					'tags_before' => [
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
					'tags_before' => [
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
							'Host groups' => 'Streets',
							'Tags' => 'street'
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
	public function testFormTemplateGroupSearchPage_ApplyPermissionsToSubgroups($data) {
		$this->link = 'zabbix.php?action=search&search=europe';
		$this->checkSubgroupsPermissions($data);
	}
}
