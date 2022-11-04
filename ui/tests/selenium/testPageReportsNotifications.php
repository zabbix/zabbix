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


require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @dataSource LoginUsers
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
		$this->zbxTestDropdownHasOptions('media_type', $all_media);
		$this->zbxTestDropdownHasOptions('period', ['Daily', 'Weekly', 'Monthly', 'Yearly']);
		$this->zbxTestDropdownHasOptions('year', ['2012', '2013', '2014', '2015', '2016', '2017', '2018', '2019', '2020']);
		// Check default selected dropdown values
		$this->zbxTestDropdownAssertSelected('media_type', 'all');
		$this->zbxTestDropdownAssertSelected('period', 'Weekly');
		$this->zbxTestDropdownAssertSelected('year', date('Y'));

		// Get users from DB
		$user_alias = [];
		$get_user_alias = DBselect('SELECT alias FROM users');
		while ($row = DBfetch($get_user_alias)) {
			$user_alias[] = $row['alias'];
		}
		sort($user_alias);

		$users = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//th[@class="vertical_rotation"]'));
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
							'alias' => 'admin-zabbix',
							'notifications' => [ '', '', '', '4', '', '', '', '', '', '', '', '12']
						],
						[
							'alias' => 'guest',
							'notifications' => [ '', '2', '', '', '', '', '', '', '', '10', '', '']
						],
						[
							'alias' => 'test-user',
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
							'alias' => 'admin-zabbix',
							'notifications' => [ '', '', '', '', '', '', '', '', '', '', '', '']
						],
						[
							'alias' => 'disabled-user',
							'notifications' => [ '', '', '', '', '', '', '', '', '', '', '15', '']
						],
						[
							'alias' => 'user-for-blocking',
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
							'alias' => 'admin-zabbix',
							'notifications' => [ '', '', '', '', '', '16', '']
						],
						[
							'alias' => 'disabled-user',
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
							'alias' => 'Tag-user',
							'notifications' => [ '', '', '', '', '', '', '']
						],
						[
							'alias' => 'admin-zabbix',
							'notifications' => [ '', '', '', '', '', '8', '']
						],
						[
							'alias' => 'disabled-user',
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
		// TODO: without sleep test fail because the "zabbix-sidebar-logo" element receive the click.
		sleep(1);

		// Select period
		if (array_key_exists('period', $data)) {
			$this->zbxTestDropdownSelectWait('period', $data['period']);
			sleep(1);
			if ($data['period'] === 'Yearly') {
				$this->zbxTestAssertElementNotPresentId('year');
			}
		}

		// Select year
		if (array_key_exists('year', $data)) {
			$this->zbxTestDropdownSelectWait('year', $data['year']);
		}

		// Select media
		if (array_key_exists('media_type', $data)) {
			$this->zbxTestDropdownSelectWait('media_type', $data['media_type']);
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
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//th[@class="vertical_rotation"]'));
		foreach ($elements as $index => $element) {
			// 2 is column of month plus column count begin from 1 not from 0
			$user_column_number[$element->getText()] = $index + 2;
		}

		// Compare user data from table and from data provider
		foreach ($data['users'] as $user) {
			$user_notifications = [];
			if ($data['period'] === 'Yearly') {
				for ($i = 0; $i <= 7; $i++) {
					$get_user_rows = $this->webDriver->findElements(WebDriverBy::xpath('//table/tbody/tr['.$i.']/td['.$user_column_number[$user['alias']].']'));
					foreach ($get_user_rows as $row) {
						$user_notifications[] = $row->getText();
					}
				}
			}
			else {
				$get_user_rows = $this->webDriver->findElements(WebDriverBy::xpath('//table/tbody/tr/td['.$user_column_number[$user['alias']].']'));
				foreach ($get_user_rows as $row) {
					$user_notifications[] = $row->getText();
				}
			}
			$this->assertEquals($user['notifications'], $user_notifications);
		}
	}
}
