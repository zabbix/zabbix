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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup role
 * @backup role_rule
 * @on-before prepareRoleData
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
			]
		]);
	}

	/**
	 * Check everything in user roles list.
	 */
	public function testPageUserRoles_Layout() {
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->assertPageTitle('Configuration of user roles');
		$this->assertPageHeader('User roles');

		// Table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();
		array_shift($headers);
		$this->assertEquals(['Name', '#', 'Users'], $headers);

		// Check that headers is not clickable.
		foreach (['#', 'Users'] as $header) {
			$this->assertFalse($table->query('xpath://th/a[text()="'.$header.'"]')->one(false)->isValid());
		}

		// Check roles list sort order.
		$before_listing = $this->getTableResult('Name');
		$name_header = $this->query('xpath://a[text()="Name"]')->one();
		$name_header->click();
		$after_listing = $this->getTableResult('Name');
		$this->assertEquals($after_listing, array_reverse($before_listing));
		$name_header->click();

		// Check that check boxes for all roles are enabled except Super admin.
		$roleids = CDBHelper::getAll('SELECT roleid FROM role');
		foreach ($roleids as $id) {
			if ($id['roleid'] === '3') {
				$this->assertFalse($this->query('id:roleids_'.$id['roleid'])->one()->isEnabled());
			}
			else {
				$this->assertTrue($this->query('id:roleids_'.$id['roleid'])->one()->isEnabled());
			}
		}

		// Check number of displayed user roles.
		$this->assertTableStats(CDBHelper::getCount('SELECT roleid FROM role'));

		// Check selected rows counter.
		$this->query('id:all_roles')->asCheckbox()->one()->check();
		$selected = $table->query('class:row-selected')->all()->count();
		$this->assertEquals(7, $selected);
		$this->assertEquals($selected.' selected', $this->query('id:selected_count')->one()->getText());

		// Check that number displayed near Users and # columns are equal.
		foreach ($table->getRows() as $row) {
			$users_count = $row->getColumn('Users')->query('xpath:./*[contains(@class, "link-alt")]')->count();
			if ($users_count !== 0) {
				$users_amount = $row->getColumn('#')->query('xpath:./sup')->one()->getText();
				$this->assertEquals($users_count, intval($users_amount));
			}
			else {
				$this->assertFalse($table->query('xpath://td/a[text()="Users"]/sup')->one(false)->isValid());
			}
		}
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
					'name' => ' ',
					'result' => [
						'Admin role',
						'Guest role',
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
						'Super admin role',
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

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		$before_filtering = $this->getTableResult('Name');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['Name' => $data['name']])->submit();
		$this->assertTableDataColumn($data['result'], 'Name');

		// Check that result reseted and Name field is empty after pressing reset button.
		$this->query('button:Reset')->one()->click();
		$form->checkValue('Name', '');
		$this->assertTableDataColumn($before_filtering, 'Name');
	}

	public static function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'message_header' => 'Cannot delete user roles',
					'message_details' => 'The role "Admin role" is assigned to at least one user and cannot be deleted',
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
					'message_details' => 'The role "Admin role" is assigned to at least one user and cannot be deleted.',
					'roles' => [
						'Admin role'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'message_header' => 'Cannot delete user roles',
					'message_details' => 'The role "Admin role" is assigned to at least one user and cannot be deleted.',
					'roles' => [
						'All'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'message_header' => 'User role deleted',
					'roles' => [
						'Remove_role_1'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
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
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->query('button:Reset')->one()->click();
		$before_delete = $this->getTableResult('Name');
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertFalse($this->query('button:Delete')->one()->isEnabled());

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
		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, $data['message_header']);
			$after_delete = array_values(array_diff($before_delete, $data['roles']));
			$this->assertTableDataColumn($after_delete, 'Name');
			foreach ($data['roles'] as $role_name) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM role WHERE name='.zbx_dbstr($role_name)));
			}
		}
		else {
			$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);
		}
	}

	/**
	 * Get data from chosen column.
	 *
	 * @param string $column		Column name, where value should be get
	 */
	private function getTableResult($column) {
		$table = $this->query('class:list-table')->asTable()->one();
		$result = [];
		foreach ($table->getRows() as $row) {
			$result[] = $row->getColumn($column)->getText();
		}
		return $result;
	}
}
