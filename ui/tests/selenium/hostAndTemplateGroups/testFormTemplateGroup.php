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


require_once __DIR__.'/../common/testFormGroups.php';

/**
 * @backup hosts
 *
 * @onBefore prepareGroupData
 *
 * @dataSource HostTemplateGroups
 */
class testFormTemplateGroup extends testFormGroups {

	protected $link = 'zabbix.php?action=templategroup.list';
	protected $object = 'template';
	protected static $update_group = 'Group for Update test';

	public function testFormTemplateGroup_Layout() {
		$this->layout('Templates');
	}

	public static function getTemplateCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Zabbix servers'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Templates'
					],
					'error' => 'Template group "Templates" already exists.'
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
	 * @dataProvider getTemplateCreateData
	 */
	public function testFormTemplateGroup_Create($data) {
		$this->checkForm($data, 'create');
	}

	public static function getTemplateUpdateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => 'Discovered hosts'
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
						'Group name' => str_repeat('long_', 51)
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 * @dataProvider getTemplateUpdateData
	 */
	public function testFormTemplateGroup_Update($data) {
		$this->checkForm($data, 'update');
	}

	/**
	 * Test group simple update without changing data.
	 */
	public function testFormTemplateGroup_SimpleUpdate() {
		$this->simpleUpdate('Templates');
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormTemplateGroup_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormTemplateGroup_Cancel($data) {
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
	public function testFormTemplateGroup_Delete($data) {
		$this->delete($data);
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgroupsData
	 */
	public function testFormTemplateGroup_ApplyPermissionsToSubgroups($data) {
		$this->checkSubgroupsPermissions($data);
	}
}
