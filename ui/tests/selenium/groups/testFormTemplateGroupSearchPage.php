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
 */
class testFormTemplateGroupSearchPage extends testFormGroups {

	public $link = 'zabbix.php?action=search&search=group';
	public $object = 'template';
	public $search = 'true';
	public static $update_group = 'Group for Update test';

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
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 * @dataProvider getTemplateUpdateData
	 */
	public function testFormTemplateGroupSearchPage_Update($data) {
		$this->link = 'zabbix.php?action=search&search=update';
		$this->update($data);
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
	public function ttestFormTemplateGroupSearchPage_Clone($data) {
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
					'name' => 'One group for Delete',
					'error' => 'Template "Template for group testing" cannot be without template group.'
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

	public static function getSubgoupsPermissionsData() {
		return [
			[
				[
					'apply_permissions' => 'Europe',
					// Permission inheritance doesn't apply when changing the name of existing group.
					'open_form' => 'Europe group for test on search page',
					'create' => 'Streets/Dzelzavas',
					'groups_after' => [
						'Cities/Cesis' => 'Read',
						'Europe (including subgroups)' => 'Deny',
						'Streets' => 'Deny',
						'Streets/Dzelzavas' => 'None'
					],
					'tags_before' => [
						['Host group' => 'Cities/Cesis', 'Tags' => 'city: Cesis'],
						['Host group' => 'Europe', 'Tags' => 'world'],
						['Host group' => 'Europe/Test', 'Tags' => 'country: test'],
						['Host group' => 'Streets', 'Tags' => 'street']
					]
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareSubgroupData
	 * @dataProvider getSubgoupsPermissionsData
	 */
	public function testFormTemplateGroupSearchPage_ApplyPermissionsToSubgroups($data) {
		$this->link = 'zabbix.php?action=search&search=europe';
		$this->checkSubgroupsPermissions($data);
	}
}
