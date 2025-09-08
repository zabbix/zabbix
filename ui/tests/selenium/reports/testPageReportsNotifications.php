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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @dataSource LoginUsers, Actions, UserPermissions
 */
class testPageReportsNotifications extends CLegacyWebTest {

	public function testPageReportsNotifications_CheckLayout() {
		$this->zbxTestLogin('report4.php');
		$this->zbxTestCheckTitle('Notification report');
		$this->zbxTestCheckHeader('Notifications');

		// Check dropdown elements
		$all_media = [];
		foreach (CDBHelper::getAll('SELECT name FROM media_type ORDER BY LOWER(name) ASC') as $name) {
			$all_media[] = $name['name'];
		}
		$dropdowns = [
			'media_type' => array_merge(['All'], $all_media),
			'period' => ['Daily', 'Weekly', 'Monthly', 'Yearly'],
			'year' => array_reverse(range(date("Y"), 2012))
		];
		$default_selected = [
			'media_type' => 'All',
			'period' => 'Weekly',
			'year' => date('Y')
		];

		foreach ($dropdowns as $name => $options) {
			$dropdown = $this->query('name', $name)->asDropdown()->one();
			// Check dropdown options.
			$this->assertEquals($options, $dropdown->getOptions()->asText());
			// Check default selected dropdown values
			$this->assertEquals($default_selected[$name], $dropdown->getText());
		}

		// Get users from DB
		$user_alias = [];
		$get_user_alias = DBselect('SELECT username FROM users');
		while ($row = DBfetch($get_user_alias)) {
			$user_alias[] = $row['username'];
		}
		sort($user_alias);

		$users = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//th/z-vertical'));
		foreach ($elements as $i => $element) {
			$users[] = $element->getText();
		}
		sort($users);

		// Check that all users from DB exist in table header on page
		foreach ($users as $i => $user) {
			$this->assertRegexp('/^'.$user_alias[$i].'( \(.+\))*$/', $users[$i]);
		}
	}

	public static function getUsersNotificationsData() {
		return [
			// Check report by month and for 2017 year
			[
				[
					'period' => 'Monthly',
					'year' => '2017',
					'users' => [
						[
							'username' => 'admin-zabbix',
							'notifications' => [ '', '', '', '4', '', '', '', '', '', '', '', '12']
						],
						[
							'username' => 'guest',
							'notifications' => [ '', '2', '', '', '', '', '', '', '', '10', '', '']
						],
						[
							'username' => 'test-user',
							'notifications' => [ '', '', '3', '', '', '', '', '', '', '', '11', '']
						]
					]
				]
			],
			// Check report by month and for 2016 year
			[
				[
					'period' => 'Monthly',
					'year' => '2016',
					'users' => [
						[
							'username' => 'admin-zabbix',
							'notifications' => [ '', '', '', '', '', '', '', '', '', '', '', '']
						],
						[
							'username' => 'disabled-user',
							'notifications' => [ '', '', '', '', '', '', '', '', '', '', '15', '']
						],
						[
							'username' => 'user-for-blocking',
							'notifications' => [ '', '', '', '', '', '', '14', '', '', '', '', '']
						]
					]
				]
			],
			// Check report only for yearly period
			[
				[
					'period' => 'Yearly',
					'users' => [
						[
							'username' => 'admin-zabbix',
							'notifications' => [ '', '', '', '', '', '16', '']
						],
						[
							'username' => 'disabled-user',
							'notifications' => [ '', '', '', '', '15', '7', '']
						]
					]
				]
			],
			// Check report by year and for Email media type
			[
				[
					'period' => 'Yearly',
					'media_type' => 'Email',
					'users' => [
						[
							'username' => 'Tag-user',
							'notifications' => [ '', '', '', '', '', '', '']
						],
						[
							'username' => 'admin-zabbix',
							'notifications' => [ '', '', '', '', '', '8', '']
						],
						[
							'username' => 'disabled-user',
							'notifications' => [ '', '', '', '', '6', '3', '']
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUsersNotificationsData
	 */
	public function testPageReportsNotifications_CheckFilters($data) {
		$this->zbxTestLogin('report4.php');
		$this->page->waitUntilReady();
		$table = $this->query('class:list-table')->one();
		$table->waitUntilVisible();

		// Select period
		if (array_key_exists('period', $data)) {
			$this->query('name:period')->asDropdown()->one()->select($data['period']);
			$table->waitUntilReloaded();
			if ($data['period'] === 'Yearly') {
				$this->zbxTestAssertElementNotPresentId('year');
			}
		}

		// Select year
		if (array_key_exists('year', $data)) {
			$this->query('name:year')->asDropdown()->one()->select($data['year']);
			$table->waitUntilReloaded();
		}

		// Select media
		if (array_key_exists('media_type', $data)) {
			$this->query('name:media_type')->asDropdown()->one()->select($data['media_type']);
			$table->waitUntilReloaded();
			$this->zbxTestAssertElementNotPresentId('year');
			// Check media links not displayed
			$media_types = [];
			$media_types = CDBHelper::getAll('SELECT mediatypeid FROM media_type');
			foreach ($media_types as $media) {
				$this->zbxTestAssertElementNotPresentXpath("//a[contains(@href, 'mediatypeid=".$media['mediatypeid']."')]");
			}
		}

		// Get user column number in table
		$user_column_number = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//th/z-vertical'));
		foreach ($elements as $index => $element) {
			// 2 is column of month plus column count begin from 1 not from 0
			$user_column_number[$element->getText()] = $index + 2;
		}

		// Compare user data from table and from data provider
		foreach ($data['users'] as $user) {
			$user_notifications = [];
			if ($data['period'] === 'Yearly') {
				for ($i = 0; $i <= 7; $i++) {
					$get_user_rows = $this->webDriver->findElements(WebDriverBy::xpath('//table/tbody/tr['.$i.']/td['.$user_column_number[$user['username']].']'));
					foreach ($get_user_rows as $row) {
						$user_notifications[] = $row->getText();
					}
				}
			}
			else {
				$get_user_rows = $this->webDriver->findElements(WebDriverBy::xpath('//table/tbody/tr/td['.$user_column_number[$user['username']].']'));
				foreach ($get_user_rows as $row) {
					$user_notifications[] = $row->getText();
				}
			}
			$this->assertEquals($user['notifications'], $user_notifications);
		}
	}
}
