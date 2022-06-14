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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup users, config
 */
class testTimezone extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Change zabbix timezone from GUI and check that system time displayed correctly according to timezone.
	 */
	public function testTimezone_Gui() {
		$this->page->userLogin('Admin', 'zabbix');
		$this->setTimezone('System', 'gui');
		$this->page->open('zabbix.php?action=problem.view');
		$etc_time = $this->getProblemTime('Trigger for tag permissions Oracle');

		// UTC -3 hours.
		$this->setTimezone('UTC', 'gui');
		date_modify($etc_time,'-3 hours');

		// Return to problem page and check time.
		$this->page->open('zabbix.php?action=problem.view');
		$utc_time = $this->getProblemTime('Trigger for tag permissions Oracle');
		$this->assertEquals($etc_time, $utc_time);
	}

	public static function getUserSettingsData() {
		return [
			[
				[
					'user_timezone' => 'UTC',
					'timezone_db' => 'UTC',
					'time_diff' => '-3 hours'
				]
			],
			[
				[
					'user_timezone' => 'System default',
					'timezone_db' => 'default',
					'time_diff' => '+0 hours'
				]
			],
			[
				[
					'user_timezone' => 'Pacific/Niue',
					'timezone_db' => 'Pacific/Niue',
					'time_diff' => '-14 hours'
				]
			],
			[
				[
					'user_timezone' => 'Asia/Magadan',
					'timezone_db' => 'Asia/Magadan',
					'time_diff' => '+8 hours'
				]
			]
		];
	}

	/**
	 * @dataProvider getUserSettingsData
	 *
	 * Change timezones for user, from user setting. Check that time for user displayed correctly according
	 * to chosen timezone.
	 */
	public function testTimezone_UserSettings($data) {
		// Set system timezone.
		$this->page->userLogin('Admin', 'zabbix');
		$this->setTimezone('System', 'gui');
		$this->page->open('zabbix.php?action=problem.view');
		$system_time = $this->getProblemTime('Trigger for tag permissions Oracle');
		$this->page->logout();

		// User timezone change.
		$this->page->userLogin('test-timezone', 'zabbix');
		$this->setTimezone($data['user_timezone'], 'userprofile');
		date_modify($system_time, $data['time_diff']);

		// User timezone check.
		$this->page->open('zabbix.php?action=problem.view');
		$user_time = $this->getProblemTime('Trigger for tag permissions Oracle');
		$this->assertEquals($system_time, $user_time);
		$this->assertEquals($data['timezone_db'], CDBHelper::getValue('SELECT timezone FROM users WHERE username='.zbx_dbstr('test-timezone')));
		$this->assertEquals('system', CDBHelper::getValue('SELECT default_timezone FROM config WHERE configid='.zbx_dbstr('1')));
		$this->page->logout();
	}

	public static function getCreateUsersData() {
		return [
			[
				[
					'fields' => [
						'Username' => 'test_utc',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test4567',
						'Password (once again)' => 'test4567',
						'Time zone' => 'UTC'
					],
					'time_diff' => '-3 hours',
					'timezone_db' => 'UTC'
				]
			],
			[
				[
					'fields' => [
						'Username' => 'test_def',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test4567',
						'Password (once again)' => 'test4567',
						'Time zone' => 'System default'
					],
					'time_diff' => '+0 hours',
					'timezone_db' => 'default'
				]
			],
			[
				[
					'fields' => [
						'Username' => 'test_amer',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test4567',
						'Password (once again)' => 'test4567',
						'Time zone' => 'Pacific/Niue'
					],
					'time_diff' => '-14 hours',
					'timezone_db' => 'Pacific/Niue'
				]
			],
			[
				[
					'fields' => [
						'Username' => 'test_asia',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test4567',
						'Password (once again)' => 'test4567',
						'Time zone' => 'Asia/Magadan'
					],
					'time_diff' => '+8 hours',
					'timezone_db' => 'Asia/Magadan'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateUsersData
	 *
	 * Create users with different timezones and check that time displayed for them correctly, according
	 * to chosen timezone.
	 */
	public function testTimezone_CreateUsers($data) {
		$this->page->userLogin('Admin', 'zabbix');
		$this->setTimezone('System', 'gui');
		$this->page->open('zabbix.php?action=problem.view');
		$system_time = $this->getProblemTime('Trigger for tag permissions Oracle');
		$this->page->open('zabbix.php?action=user.edit');
		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		if (CTestArrayHelper::get($data, 'fields.Time zone')) {
			$data['fields']['Time zone'] = CDateTimeHelper::getTimeZoneFormat($data['fields']['Time zone']);
		}
		$form->fill($data['fields']);
		$form->selectTab('Permissions');
		$form->fill(['Role' => 'Super admin role']);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'User added');
		$this->page->logout();
		$this->page->userLogin($data['fields']['Username'], $data['fields']['Password']);
		$this->page->open('zabbix.php?action=problem.view');

		// Expected time after timezone change.
		date_modify($system_time, $data['time_diff']);

		// Actual time after timezone change.
		$this->page->open('zabbix.php?action=problem.view');
		$user_time = $this->getProblemTime('Trigger for tag permissions Oracle');
		$this->assertEquals($system_time, $user_time);
		$this->assertEquals($data['timezone_db'], CDBHelper::getValue('SELECT timezone FROM users WHERE username='.
				zbx_dbstr($data['fields']['Username'])));
		$this->assertEquals('system', CDBHelper::getValue('SELECT default_timezone FROM config WHERE configid='.zbx_dbstr('1')));
		$this->page->logout();
	}

	/**
	 * Time received from Monitoring->Problems table.
	 *
	 * @param string $problem   problem name from monitoring->problems
	 */
	private function getProblemTime($problem) {
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Problem', $problem);
		return date_create($row->getColumn('Time')->getText());
	}

	/**
	 * Change time zone in GUI or in user settings.
	 *
	 * @param string $timezone  time zone
	 * @param string $page      part of page link you want to open
	 */
	private function setTimezone($timezone, $page) {
		if ($page === 'gui') {
			$field_name = 'Default time zone';
			$message = 'Configuration updated';
		}
		else {
			$field_name = 'Time zone';
			$message = 'User updated';
		}

		$this->page->open('zabbix.php?action='.$page.'.edit');
		$form = $this->query('xpath://form[@aria-labeledby="page-title-general"]')->one()->asForm();
		$timezone = CDateTimeHelper::getTimeZoneFormat($timezone);
		$form->fill([$field_name => $timezone]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $message);
	}
}
