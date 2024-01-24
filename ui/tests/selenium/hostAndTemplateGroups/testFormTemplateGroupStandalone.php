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
 * @dataSource HostTemplateGroups
 */
class testFormTemplateGroupStandalone extends testFormGroups {

	protected $standalone = true;
	protected $link = 'zabbix.php?action=templategroup.edit&groupid=';
	protected $object = 'template';
	protected static $update_group = 'Group for Update test';

	public function testFormTemplateGroupStandalone_Layout() {
		$this->layout('Templates');
	}

	public static function getTemplateValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Templates'
					],
					'error' => 'Template group "Templates" already exists.'
				]
			]
		];
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => STRING_255
					]
				]
			]
		];
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Group name' => str_repeat('long_', 51)
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 * @dataProvider getTemplateValidationData
	 * @dataProvider getTemplateCreateData
	 */
	public function testFormTemplateGroupStandalone_Create($data) {
		$this->checkForm($data, 'create');
	}

	/**
	 * @dataProvider getUpdateData
	 * @dataProvider getTemplateValidationData
	 * @dataProvider getTemplateUpdateData
	 */
	public function testFormTemplateGroupStandalone_Update($data) {
		$this->checkForm($data, 'update');
	}

	/**
	 * Test group simple update without changing data.
	 */
	public function testFormTemplateGroupStandalone_SimpleUpdate() {
		$this->simpleUpdate('Templates/Databases');
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormTemplateGroupStandalone_Clone($data) {
		$this->clone($data);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormTemplateGroupStandalone_Cancel($data) {
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
	public function testFormTemplateGroupStandalone_Delete($data) {
		$this->delete($data);
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgroupsData
	 */
	public function testFormTemplateGroupStandalone_ApplyPermissionsToSubgroups($data) {
		$this->checkSubgroupsPermissions($data);
	}
}
