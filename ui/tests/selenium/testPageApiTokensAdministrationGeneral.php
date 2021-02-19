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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup token
 * @backup profiles
 *
 * @on-before prepareTokenData
 */
class testPageApiTokensAdministrationGeneral extends CWebTest {

	public static $timestamp;

	const STATUS_CHANGE_TOKEN = 'Admin: expired token for admin';
	const DELETE_TOKEN = 'filter-create: future token for filter-create';

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	public static function prepareTokenData() {
		CDataHelper::setSessionId(null);

		self::$timestamp = time() + 172800;

		$responce = CDataHelper::call('token.create', [
			[
				'name' => 'Admin: future token for admin',
				'userid' => 1,
				'description' => 'admin token to be used in update scenarios',
				'status' => '0',
				'expires_at' => '1798754399'
			],
			[
				'name' => 'Admin: expired token for admin',
				'userid' => 1,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1609452360'
			],
			[
				'name' => 'Admin: aktīvs токен - 頑張って',
				'userid' => 1,
				'description' => 'Token that is generated for Admin',
				'status' => '1'
			],
			[
				'name' => 'Admin: token for filter-create',
				'userid' => 92,
				'description' => 'admin token created for filter-create user',
				'status' => '1',
				'expires_at' => self::$timestamp
			],
			[
				'name' => 'filter-create: future token for filter-create',
				'userid' => 92,
				'description' => 'filter-create token created for filter-create user',
				'status' => '0',
				'expires_at' => '1798754399'
			],
			[
				'name' => 'filter-create: expired token for filter-create',
				'userid' => 92,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1609452360'
			],
			[
				'name' => 'filter-create: aktīvs токен - 頑張って',
				'userid' => 92,
				'description' => 'Token that is generated for filter-create',
				'status' => '1'
			],
			[
				'name' => 'filter-create: token for Admin',
				'userid' => 1,
				'description' => 'filter-create token created for Admin user',
				'status' => '1',
				'expires_at' => self::$timestamp
			]
		]);

		DBexecute('UPDATE token SET creator_userid=92 WHERE name LIKE \'filter-create: %\'');
		DBexecute('UPDATE token SET created_at=1609452001');

		// Update token "Last accessed" timestamp to be different for each token.
		$i = 1;
		foreach ($responce['tokenids'] as $tokenid) {
			DBexecute('UPDATE token SET lastaccess='.(1609452001+$i).' WHERE tokenid='.$tokenid);
			$i++;
		}
	}

	public function testPageApiTokensAdministrationGeneral_Layout() {
		$token_data = [
			[
				'Name' => 'Admin: aktīvs токен - 頑張って',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => 'Never',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:04',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'Admin: expired token for admin',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => '2021-01-01 00:06:00',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:03',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Admin: future token for admin',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => '2026-12-31 23:59:59',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:02',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Admin: token for filter-create',
				'User' => 'filter-create',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp),
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:05',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'filter-create: aktīvs токен - 頑張って',
				'User' => 'filter-create',
				'Expires at' => 'Never',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:08',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'filter-create: expired token for filter-create',
				'User' => 'filter-create',
				'Expires at' => '2021-01-01 00:06:00',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:07',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'filter-create: future token for filter-create',
				'User' => 'filter-create',
				'Expires at' => '2026-12-31 23:59:59',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:06',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'filter-create: token for Admin',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp),
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:09',
				'Status' => 'Disabled'

			]
		];

		// Open API tokens page and check header.
		$this->page->login()->open('zabbix.php?action=token.list');
		$this->assertEquals('API tokens', $this->query('tag:h1')->one()->getText());

		// Check status of buttons on the API tokens page.
		$form_buttons = [
			'Create API token' => true,
			'Enable' => false,
			'Disable' => false,
			'Delete' => false
		];

		foreach ($form_buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check displaying and hiding the filter.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('xpath://a[contains(text(), "Filter")]')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

		// Check that all filter fields are present.
		$filter_fields = ['Name', 'Users', 'Expires in less than', 'Created by users', 'Status'];
		$this->assertEquals($filter_fields, $filter_form->getLabels()->asText());

		// Check the count of returned tokens and the count of selected tokens.
		$count = CDBHelper::getCount('SELECT tokenid FROM token');
		$this->assertTableStats($count);
		$selected_count = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_count->getText());
		$all_tokens = $this->query('id:all_tokens')->one()->asCheckbox();
		$all_tokens->set(true);
		$this->assertEquals($count.' selected', $selected_count->getText());

		// Check that buttons became enabled.
		foreach (['Enable', 'Disable', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled());
		}

		$all_tokens->set(false);
		$this->assertEquals('0 selected', $selected_count->getText());

		// Check tokens table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();

		// Remove empty element from headers array.
		array_shift($headers);
		$reference_headers = ['Name', 'User', 'Expires at', 'Created at', 'Created by user', 'Last accessed at', 'Status'];
		$this->assertSame($reference_headers, $headers);

		foreach ($headers as $header) {
			if ($header === 'Created at') {
				$this->assertFalse($table->query('xpath:.//a[text()="'.$header.'"]')->one(false)->isValid());
			}
			else {
				$this->assertTrue($table->query('xpath:.//a[contains(text(), "'.$header.'")]')->one()->isClickable());
			}
		}

		// Check parameters of tokens in the token list table.
		$this->assertTableData($token_data);
	}

	public function testPageApiTokensAdministrationGeneral_ChangeStatus() {
		$this->page->login()->open('zabbix.php?action=token.list');

		// Disable API token.
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', self::STATUS_CHANGE_TOKEN);
		$row->getColumn('Status')->click();
		// Check token disabled.
		$this->checkTokenStatus($row, 'disabled');

		// Enable API token.
		$row->getColumn('Status')->click();

		// Check token enabled.
		$this->checkTokenStatus($row, 'enabled');

		// Disable API token via button.
		$row->select();
		$this->query('button:Disable')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->checkTokenStatus($row, 'disabled');

		// Enable API token via button.
		$row->select();
		$this->query('button:Enable')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->checkTokenStatus($row, 'enabled');
	}

	private function checkTokenStatus($row, $expected) {
		if ($expected === 'enabled') {
			$message_title = 'API token enabled';
			$column_status = 'Enabled';
			$db_status = '0';
		}
		else {
			$message_title = 'API token disabled';
			$column_status = 'Disabled';
			$db_status = '1';
		}

		$this->assertMessage(TEST_GOOD, $message_title );
		$this->assertEquals($column_status, $row->getColumn('Status')->getText());
		$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM token WHERE name=\''.self::STATUS_CHANGE_TOKEN.'\''));
	}

	public function getFilterData() {
		return [
			// Exact name match with special symbols.
			[
				[
					'filter' => [
						'Name' => 'Admin: aktīvs токен - 頑張って'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って'
					]
				]
			],
			// Partial name match.
			[
				[
					'filter' => [
						'Name' => 'Admin:'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create'
					]
				]
			],
			// Partial name match with space in between.
			[
				[
					'filter' => [
						'Name' => 'ken fo'
					],
					'expected' => [
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Filter by name with trailing and leading spaces.
			// Uncomment the below data provider once ZBX-18995 is fixed.
//			[
//				[
//					'filter' => [
//						'Name' => '   future token   '
//					],
//					'expected' => [
//						'Admin: future token for admin',
//						'filter-create: future token for filter-create'
//					]
//				]
//			],
			// Wrong name in filter field "Name".
			[
				[
					'filter' => [
						'Name' => 'No data should be returned'
					],
					'no_data' => true
				]
			],
			// Retrieve only Enabled tokens.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'expected' => [
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create'
					]
				]
			],
			// Retrieve only Disabled tokens.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created by "filter-create" user.
			[
				[
					'filter' => [
						'Created by users' => 'filter-create'
					],
					'expected' => [
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created by "Admin" user.
			[
				[
					'filter' => [
						'Created by users' => 'Admin'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create'
					]
				]
			],
			// Retrieve tokens that were created by one of the "filter-create" or "Admin" users.
			[
				[
					'filter' => [
						'Created by users' => ['Admin', 'filter-create']
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created by "guest" user.
			[
				[
					'filter' => [
						'Created by users' => 'guest'
					],
					'no_data' => true
				]
			],
			// Retrieve only tokens that were created for "Admin" user.
			[
				[
					'filter' => [
						'Users' => 'Admin'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created for "filter-create" user.
			[
				[
					'filter' => [
						'Users' => 'filter-create'
					],
					'expected' => [
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create'
					]
				]
			],
			// Retrieve tokens that were created for one of the "filter-create" or "Admin" users.
			[
				[
					'filter' => [
						'Users' => ['Admin', 'filter-create']
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created for "guest" user.
			[
				[
					'filter' => [
						'Users' => 'guest'
					],
					'no_data' => true
				]
			],
			// Retrieve tokens that will expire in less that 2 days.
			[
				[
					'filter' => [],
					'Expires in less than' => 2,
					'expected' => [
						'Admin: token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve tokens that will expire in less that 1 day.
			[
				[
					'filter' => [],
					'Expires in less than' => 1,
					'no_data' => true
				]
			],
			// Retrieve tokens created for "filter-create" user that will expire in less that 2 days.
			[
				[
					'filter' => [
						'Users' => 'filter-create'
					],
					'Expires in less than' => 2,
					'expected' => [
						'Admin: token for filter-create'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageApiTokensAdministrationGeneral_Filter($data) {
		$this->page->login()->open('zabbix.php?action=token.list');

		// Apply and submit the filter from data provider.
		$form = $this->query('name:zbx_filter')->asForm()->one();

		if (array_key_exists('Expires in less than', $data)) {
			$form->query('xpath:.//label[@for="filter-expires-state"]/span')->asCheckbox()->one()->set(true);
			$form->query('xpath:.//input[@id="filter-expires-days"]')->one()->fill($data['Expires in less than']);
		}

		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'no_data')) {
			$this->assertTableData();
		}
		else {
			// Using token name check that only the expected filters are returned in the list.
			$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected'));
		}

		// Reset the filter and check that all API tokens are displayed.
		$this->query('button:Reset')->one()->click();
		$count = CDBHelper::getCount('SELECT tokenid FROM token');
		$this->assertTableStats($count);
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'filter-create: token for Admin',
						'filter-create: future token for filter-create',
						'filter-create: expired token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'Admin: token for filter-create',
						'Admin: future token for admin',
						'Admin: expired token for admin',
						'Admin: aktīvs токен - 頑張って'
					]
				]
			],
			[
				[
					'sort_field' => 'User',
					'expected' => [
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'filter-create',
						'filter-create',
						'filter-create',
						'filter-create'
					]
				]
			],
			[
				[
					'sort_field' => 'Expires at',
					'expected' => [
						'Never',
						'Never',
						'2021-01-01 00:06:00',
						'2021-01-01 00:06:00',
						'change_to_timestamp',
						'change_to_timestamp',
						'2026-12-31 23:59:59',
						'2026-12-31 23:59:59'
					]
				]
			],
			[
				[
					'sort_field' => 'Created by user',
					'expected' => [
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'filter-create',
						'filter-create',
						'filter-create',
						'filter-create'
					]
				]
			],
			[
				[
					'sort_field' => 'Last accessed at',
					'expected' => [
						'2021-01-01 00:00:02',
						'2021-01-01 00:00:03',
						'2021-01-01 00:00:04',
						'2021-01-01 00:00:05',
						'2021-01-01 00:00:06',
						'2021-01-01 00:00:07',
						'2021-01-01 00:00:08',
						'2021-01-01 00:00:09'
					]
				]
			],
			[
				[
					'sort_field' => 'Status',
					'expected' => [
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Disabled',
						'Disabled',
						'Disabled',
						'Disabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSortData
	 */
	public function testPageApiTokensAdministrationGeneral_Sort($data) {
		// Place $timestamp variable value in data provider as the data providers are formed before execution of on-before.
		if ($data['sort_field'] === 'Expires at') {
			foreach ($data['expected'] as $i => $value) {
				if ($value === 'change_to_timestamp') {
					$data['expected'][$i] = date('Y-m-d H:i:s', self::$timestamp);
				}
			}
		}

		$this->page->login()->open('zabbix.php?action=token.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="'.$data['sort_field'].'"]')->one();
		$header->click();

		$sorted_once = [];
		foreach ($table->getRows() as $row) {
			$sorted_once[] = $row->getColumn($data['sort_field'])->getText();
		}
		$this->assertEquals($data['expected'], $sorted_once);

		// Check column sorting in the oposite direction.
		$header->click();
		$sorted_twice = [];
		foreach ($table->getRows() as $row) {
			$sorted_twice[] = $row->getColumn($data['sort_field'])->getText();
		}
		$this->assertEquals(array_reverse($data['expected']), $sorted_twice);
	}

	public function testPageApiTokensAdministrationGeneral_Delete() {
		$this->page->login()->open('zabbix.php?action=token.list');

		// Delete API token.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', self::DELETE_TOKEN)->select();
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that token is deleted from DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT tokenid FROM token WHERE name = \''.self::DELETE_TOKEN.'\''));
	}
}
