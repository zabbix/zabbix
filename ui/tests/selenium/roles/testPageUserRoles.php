<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup role
 * @onBefore prepareRoleData
 * @dataSource LoginUsers, ExecuteNowAction
 */
class testPageUserRoles extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Function used to create roles.
	 */
	public function prepareRoleData() {
		CDataHelper::call('role.create', [
			[
				'name' => 'Remove_role_1',
				'type' => 1
			],
			[
				'name' => 'Remove_role_2',
				'type' => 2
			],
			[
				'name' => 'Remove_role_3',
				'type' => 3
			],
			[
				'name' => '$^&#%*',
				'type' => 1
			],
			[
				'name' => 'role_with_min end',
				'type' => 1
			]
		]);
	}

	/**
	 * Check layout in user roles list.
	 */
	public function testPageUserRoles_Layout() {
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->page->assertTitle('Configuration of user roles');
		$this->page->assertHeader('User roles');

		// Table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();
		array_shift($headers);
		$this->assertEquals(['Name', '#', 'Users'], $headers);

		// Filter form fields.
		$this->assertEquals(['Name'], $this->query('name:zbx_filter')->asForm()->one()->getLabels()->asText());

		// Check that non-sortable headers is not clickable.
		foreach (['#', 'Users'] as $header) {
			$this->assertFalse($table->query('xpath://th/a[text()="'.$header.'"]')->one(false)->isValid());
		}

		// Check roles list sort order.
		$before_listing = $this->getTableColumnData('Name');
		$name_header = $this->query('xpath://a[text()="Name"]')->one();
		$name_header->click();
		$after_listing = $this->getTableColumnData('Name');
		$this->assertEquals($after_listing, array_reverse($before_listing));
		$name_header->click();

		// Check that check boxes for all roles are enabled except Super admin.
		$db_roles = CDBHelper::getAll('SELECT roleid, name FROM role');
		foreach ($db_roles as $id) {
			if ($id['name'] === 'Super admin role') {
				$this->assertFalse($this->query('id:roleids_'.$id['roleid'])->one()->isEnabled());
			}
			else {
				$this->assertTrue($this->query('id:roleids_'.$id['roleid'])->one()->isEnabled());
			}
		}

		// Check number of displayed user roles.
		$roles_count = CDBHelper::getCount('SELECT roleid FROM role');
		$this->assertTableStats($roles_count);

		// Check filter collapse/expand.
		$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
		foreach ([true, false] as $status) {
			$this->assertEquals($status, $this->query('xpath://div[contains(@class, "filter-container")]')->one()->isDisplayed());
			$filter_tab->click();
		}

		// Filters buttons are enabled.
		foreach (['Apply', 'Reset', 'Create user role'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isClickable());
		}

		// Check selected rows counter.
		$delete_button = $this->query('button:Delete')->one();
		$this->assertFalse($delete_button->isEnabled());
		$this->query('id:all_roles')->asCheckbox()->one()->check();
		$this->assertTrue($delete_button->isEnabled());
		$selected = $table->query('class:row-selected')->all()->count();
		$this->assertEquals($roles_count - 1, $selected);
		$this->assertEquals($selected.' selected', $this->query('id:selected_count')->one()->getText());

		$table_data = [
			[
				'Name' => '$^&#%*',
				'#' => 'Users',
				'Users' => ''
			],
			[
				'Name' => 'Admin role',
				'#' => 'Users 2',
				'Users' => 'admin-zabbix, http-auth-admin'
			],
			[
				'Name' => 'Guest role',
				'#' => 'Users 1',
				'Users' => 'guest'
			],
			[
				'Name' => 'Remove_role_1',
				'#' => 'Users',
				'Users' => ''
			],
			[
				'Name' => 'Remove_role_2',
				'#' => 'Users',
				'Users' => ''
			],
			[
				'Name' => 'Remove_role_3',
				'#' => 'Users',
				'Users' => ''
			],
			[
				'Name' => 'role_with_min end',
				'#' => 'Users',
				'Users' => ''
			],
			[
				'Name' => 'Super admin role',
				'#' => 'Users 6',
				'Users' => 'Admin (Zabbix Administrator), filter-create, filter-delete, filter-update, LDAP user, test-timezone'
			],
			[
				'Name' => 'UR1-executenow-on',
				'#' => 'Users 1',
				'Users' => 'U1-r-on'
			],
			[
				'Name' => 'UR2-executenow-off',
				'#' => 'Users 2',
				'Users' => 'U2-r-off, U3-rw-off'
			],
			[
				'Name' => 'User role',
				'#' => 'Users 6',
				'Users' => 'disabled-user, no-access-to-the-frontend, Tag-user, test-user, user-for-blocking, user-zabbix'
			]
		];
		$this->assertTableData($table_data);
	}

	public static function getFilterData() {
		return [
			[
				[
					'name' => 'Admin',
					'result' => [
						'Admin role',
						'Super admin role'
					]
				]
			],
			[
				[
					'name' => 'gu est',
					'result' => []
				]
			],
			[
				[
					'name' => 'min ro',
					'result' => [
						'Admin role',
						'Super admin role'
					]
				]
			],
			[
				[
					'name' => ' ',
					'result' => [
						'Admin role',
						'Guest role',
						'role_with_min end',
						'Super admin role',
						'User role'
					]
				]
			],
			[
				[
					'name' => '',
					'result' => [
						'$^&#%*',
						'Admin role',
						'Guest role',
						'Remove_role_1',
						'Remove_role_2',
						'Remove_role_3',
						'role_with_min end',
						'Super admin role',
						'UR1-executenow-on',
						'UR2-executenow-off',
						'User role'
					]
				]
			],
			[
				[
					'name' => 'Super admin role',
					'result' => [
						'Super admin role'
					]
				]
			],
			[
				[
					'name' => 'GUEST ROLE',
					'result' => [
						'Guest role'
					]
				]
			],
			[
				[
					'name' => 'non_existing',
					'result' => []
				]
			],
			[
				[
					'name' => '*',
					'result' => [
						'$^&#%*'
					]
				]
			]
		];
	}

	/**
	 * Filter user roles.
	 *
	 * @dataProvider getFilterData
	 */
	public function testPageUserRoles_Filter($data) {
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['Name' => $data['name']])->submit();
		$this->assertTableDataColumn($data['result'], 'Name');
	}

	public static function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'message_header' => 'Cannot delete user roles',
					'message_details' => 'Cannot delete assigned user role "Admin role".',
					'roles' => [
						'Admin role',
						'Remove_role_1'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'message_header' => 'Cannot delete user role',
					'message_details' => 'Cannot delete assigned user role "Admin role".',
					'roles' => [
						'Admin role'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'message_header' => 'Cannot delete user roles',
					'message_details' => 'Cannot delete assigned user role "User role".',
					'roles' => [
						'All'
					]
				]
			],
			[
				[
					'message_header' => 'User role deleted',
					'roles' => [
						'Remove_role_1'
					]
				]
			],
			[
				[
					'message_header' => 'User roles deleted',
					'roles' => [
						'Remove_role_2',
						'Remove_role_3'
					]
				]
			]
		];
	}

	/**
	 * Delete user roles.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageUserRoles_Delete($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$hash_before = CDBHelper::getHash('SELECT * FROM role');
		}

		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->query('button:Reset')->one()->click();
		$before_delete = $this->getTableColumnData('Name');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach ($data['roles'] as $role) {
			if ($role === 'All') {
				$table->getRows()->select();
			}
			else {
				$table->findRow('Name', $role)->select();
			}
		}

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// After deleting role check role list and database.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);
			$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM role'));
		}
		else {
			$this->assertMessage(TEST_GOOD, $data['message_header']);
			$after_delete = array_values(array_diff($before_delete, $data['roles']));
			$this->assertTableDataColumn($after_delete, 'Name');
			foreach ($data['roles'] as $role_name) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM role WHERE name='.zbx_dbstr($role_name)));
			}
		}
	}
}
