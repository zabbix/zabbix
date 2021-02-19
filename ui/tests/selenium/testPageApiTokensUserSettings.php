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
class testPageApiTokensUserSettings extends CWebTest {

	public static $timestamp;

	const STATUS_CHANGE_TOKEN = 'Expired token for admin';
	const DELETE_TOKEN = 'Future token for admin';

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
				'name' => 'Future token for admin',
				'userid' => 1,
				'description' => 'admin token to be used in update scenarios',
				'status' => '0',
				'expires_at' => self::$timestamp + 864000
			],
			[
				'name' => 'Expired token for admin',
				'userid' => 1,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1609452360'
			],
			[
				'name' => 'Aktīvs токен - 頑張って',
				'userid' => 1,
				'description' => 'Token that is generated for Admin',
				'status' => '1'
			],
			[
				'name' => 'Token that will expire in 2 days',
				'userid' => 1,
				'description' => 'admin token created for filter-create user',
				'status' => '1',
				'expires_at' => self::$timestamp
			]
		]);

		DBexecute('UPDATE token SET created_at=1609452001');

		// Update token "Last accessed" timestamp to be different for each token.
		$i = 1;
		foreach ($responce['tokenids'] as $tokenid) {
			DBexecute('UPDATE token SET lastaccess='.(1609452001+$i).' WHERE tokenid='.$tokenid);
			$i++;
		}
	}

	public function testPageApiTokensUserSettings_Layout() {
		$token_data = [
			[
				'Name' => 'Aktīvs токен - 頑張って',
				'Expires at' => 'Never',
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:04',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'Expired token for admin',
				'Expires at' => '2021-01-01 00:06:00',
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:03',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Future token for admin',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp + 864000),
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:02',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Token that will expire in 2 days',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp),
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:05',
				'Status' => 'Disabled'

			]
		];

		// Open API tokens page and check header.
		$this->page->login()->open('zabbix.php?action=user.token.list');
		$this->assertEquals('API tokens', $this->query('tag:h1')->one()->getText());

		// Check status of buttons on the tokens page.
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
		$filter_fields = ['Name', 'Expires in less than', 'Status'];
		$this-> assertEquals($filter_fields, $filter_form->getLabels()->asText());

		// Check the count of returned tokens and the count of selected tokens.
		$count = CDBHelper::getCount('SELECT tokenid FROM token WHERE userid=1');
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
		$reference_headers = ['Name', 'Expires at', 'Created at', 'Last accessed at', 'Status'];
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

	public function testPageApiTokensUserSettings_ChangeStatus() {
		$this->page->login()->open('zabbix.php?action=user.token.list');

		// Disable API token.
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', self::STATUS_CHANGE_TOKEN);
		$status = $row->getColumn('Status')->query('xpath:.//a')->one();
		$status->click();

		// Check token disabled.
		$this->checkTokenStatus($row, 'disabled');

		// Enable API token.
		$status->click();

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
						'Name' => 'Aktīvs токен - 頑張って'
					],
					'expected' => [
						'Aktīvs токен - 頑張って'
					]
				]
			],
			// Partial name match.
			[
				[
					'filter' => [
						'Name' => 'admin'
					],
					'expected' => [
						'Expired token for admin',
						'Future token for admin'
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
						'Expired token for admin',
						'Future token for admin'
					]
				]
			],
			// Filter by name with trailing and leading spaces.
			// Uncomment the below data provider once ZBX-18995 is fixed.
//			[
//				[
//					'filter' => [
//						'Name' => '   oken   '
//					],
//					'expected' => [
//						'Expired token for admin',
//						'Future token for admin',
//						'Token that will expire in 2 days'
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
						'Expired token for admin',
						'Future token for admin'
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
						'Aktīvs токен - 頑張って',
						'Token that will expire in 2 days'
					]
				]
			],
			// Retrieve tokens that will expire in less that 12 days.
			[
				[
					'filter' => [],
					'Expires in less than' => 12,
					'expected' => [
						'Future token for admin',
						'Token that will expire in 2 days'
					]
				]
			],
			// Retrieve tokens that will expire in less that 2 days.
			[
				[
					'filter' => [],
					'Expires in less than' => 2,
					'expected' => [
						'Token that will expire in 2 days'
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
			// Retrieve Enabled tokens that will expire in less that 12 days.
			[
				[
					'filter' => [
						'Status' => 'Enabled',
					],
					'Expires in less than' => 12,
					'expected' => [
						'Future token for admin'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageApiTokensUserSettings_Filter($data) {
		$this->page->login()->open('zabbix.php?action=user.token.list');

		// Apply and submit the filter from data provider.
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Place $timestamp variable value in data provider as the data providers are formed befor execution of on-before.
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
		$count = CDBHelper::getCount('SELECT tokenid FROM token WHERE userid=1');
		$this->assertTableStats($count);
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'Token that will expire in 2 days',
						'Future token for admin',
						'Expired token for admin',
						'Aktīvs токен - 頑張って'
					]
				]
			],
			[
				[
					'sort_field' => 'Expires at',
					'expected' => [
						'Never',
						'2021-01-01 00:06:00',
						'2 days in the future',
						'12 days in the future'
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
						'2021-01-01 00:00:05'
					]
				]
			],
			[
				[
					'sort_field' => 'Status',
					'expected' => [
						'Enabled',
						'Enabled',
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
	public function testPageApiTokensUserSettings_Sort($data) {
		// Place $timestamp variable value in data provider as the data providers are formed befor execution of on-before.
		if ($data['sort_field'] === 'Expires at') {
			foreach ($data['expected'] as $i => $value) {
				if ($value === '2 days in the future') {
					$data['expected'][$i] = date('Y-m-d H:i:s', self::$timestamp);
				}
				elseif ($value === '12 days in the future') {
					$data['expected'][$i] = date('Y-m-d H:i:s', self::$timestamp + 864000);
				}
			}
		}

		$this->page->login()->open('zabbix.php?action=user.token.list');
		$table = $this->query('class:list-table')->asTable()->one();

		// In user settings API tokens list field Status is too wide, so sorting requires to click on the header link.
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

	public function testPageApiTokensUserSettings_Delete() {
		$this->page->login()->open('zabbix.php?action=user.token.list');

		// Delete API token.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', self::DELETE_TOKEN)->select();
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that token is deleted from DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT tokenid FROM token WHERE name = \''.self::DELETE_TOKEN.'\''));
	}
}
