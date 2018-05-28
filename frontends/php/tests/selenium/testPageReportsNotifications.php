<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageReportsNotifications extends CWebTest {

	public function testPageReportsNotifications_CheckLayout() {
		$this->zbxTestLogin('report4.php');
		$this->zbxTestCheckTitle('Notification report');
		$this->zbxTestCheckHeader('Notifications');

		// Check dropdown elements
		$this->zbxTestDropdownHasOptions('media_type', ['all', 'Email', 'Jabber', 'SMS', 'SMS via IP']);
		$this->zbxTestDropdownHasOptions('period', ['Daily', 'Weekly', 'Monthly', 'Yearly']);
		$this->zbxTestDropdownHasOptions('year', ['2012', '2013', '2014', '2015', '2016', '2017']);
		// Check default selected dropdown values
		$this->zbxTestDropdownAssertSelected('media_type', 'all');
		$this->zbxTestDropdownAssertSelected('period', 'Weekly');
		$this->zbxTestDropdownAssertSelected('year', '2018');
		// Check media type links
		$media_types = [];
		$media_types = DBdata('SELECT mediatypeid, description FROM media_type', false);
		foreach ($media_types as $media) {
			$media = $media[0];
			$this->zbxTestAssertElementText("//a[contains(@href, 'mediatypeid=".$media['mediatypeid']."')]", $media['description']);
		}

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
							'notifications' => [ '', '', '', '4 (2/0/2/0)', '', '', '', '', '', '', '', '12 (6/0/6/0)']
						],
						[
							'alias' => 'guest',
							'notifications' => [ '', '2 (1/0/1/0)', '', '', '', '', '', '', '', '10 (5/0/5/0)', '', '']
						],
						[
							'alias' => 'test-user',
							'notifications' => [ '', '', '3 (1/0/2/0)', '', '', '', '', '', '', '', '11 (5/0/6/0)', '']
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
							'notifications' => [ '', '', '', '', '', '', '', '', '', '', '15 (6/0/9/0)', '']
						],
						[
							'alias' => 'user-for-blocking',
							'notifications' => [ '', '', '', '', '', '', '14 (6/0/8/0)', '', '', '', '', '']
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
							'notifications' => [ '', '', '', '', '', '16 (8/0/8/0)', '']
						],
						[
							'alias' => 'disabled-user',
							'notifications' => [ '', '', '', '', '15 (6/0/9/0)', '7 (3/0/4/0)', '']
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

		// Select period
		if (array_key_exists('period', $data)) {
			$this->zbxTestDropdownSelect('period', $data['period']);
			if ($data['period'] === 'Yearly') {
				$this->zbxTestAssertElementNotPresentId('year');
			}
		}

		// Select year
		if (array_key_exists('year', $data)) {
			$this->zbxTestDropdownSelect('year', $data['year']);
		}

		// Select media
		if (array_key_exists('media_type', $data)) {
			$this->zbxTestDropdownSelect('media_type', $data['media_type']);
			$this->zbxTestAssertElementNotPresentId('year');
			// Check media links not displayed
			$media_types = [];
			$media_types = DBdata('SELECT mediatypeid FROM media_type', false);
			foreach ($media_types as $media) {
				$media = $media[0];
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
			$get_user_rows = $this->webDriver->findElements(WebDriverBy::xpath('//table/tbody/tr/td['.$user_column_number[$user['alias']].']'));
			foreach ($get_user_rows as $row) {
				$user_notifications[] = $row->getText();
			}
			$this->assertEquals($user['notifications'], $user_notifications);
		}
	}
}
