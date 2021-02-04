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

class testPageUserRoles extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Check everything in user roles list.
	 */
	public function testPageUserRoles_RoleList() {
		$this->page->login()->open('zabbix.php?action=userrole.list');

		// Table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name', '#', 'Users'], $table->getHeadersText());

		// Check roles list sort order.
		$before_listing = $this->getTableResult('Name');
		$this->query('xpath://a[text()="Name"]')->one()->click();
		$after_listing = $this->getTableResult('Name');
		$this->assertEquals($after_listing, array_reverse($before_listing));
		$this->query('xpath://a[text()="Name"]')->one()->click();

		// Super admin role check box is disabled.
		$this->assertTrue($this->query('id:roleids_3')->one()->isEnabled(false));

		// Check that number displayed near Users and # columns are equal.
		$users_count = $table->getRow(0)->getColumn('Users')->query('xpath:./*[contains(@class, "link-alt")]')->count();
		$users_amount = $table->getRow(0)->getColumn('#')->query('xpath:./sup')->one()->getText();
		$this->assertEquals($users_count, intval($users_amount));

		// Check filter.
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['Name' => 'admin'])->submit();
		$this->assertEquals(['Admin role', 'Super admin role'], $this->getTableResult('Name'));
		$this->query('button:Reset')->one()->click();
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
	 * @backup-once role
	 * @backup-once role_rule
	 * @dataProvider getDeleteData
	 */
	public function testPageUserRoles_Delete($data) {
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$before_delete = $this->getTableResult('Name');
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertTrue($this->query('button:Delete')->one()->isEnabled(false));

		foreach ($data['roles'] as $role) {
			($data['roles'] === ['All'])
				? $table->getRows()->select()
				: $table->findRow('Name', $role)->select();
		}
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_BAD:
				$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);
				break;

			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, $data['message_header']);
				$after_delete = array_values(array_diff($before_delete, $data['roles']));
				$this->assertEquals($after_delete, $this->getTableResult('Name'));
				break;
		}

		$this->query('button:Reset')->one()->click();
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