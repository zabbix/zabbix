<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
require_once dirname(__FILE__).'/behaviors/MessageBehavior.php';

/**
 * @backup users
 *
 * @backup config
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

	public function testTimezone_Gui() {
		$this->userLogin('Admin', 'zabbix');
		$this->timezoneChanger('Europe/Riga', 'gui');
		$this->page->open('zabbix.php?action=problem.view');
		$etc_time = $this->timeFinder();

		// UTC -3 hours.
		$this->timezoneChanger('UTC', 'gui');
		date_modify($etc_time,'-3 hours');

		// Return to problem page and check time.
		$this->page->open('zabbix.php?action=problem.view');
		$utc_time = $this->timeFinder();
		$this->assertEquals($etc_time, $utc_time);
	}

	public static function getUserTimezoneData() {
		return [
			[
				[
					'user_timezone' => 'UTC',
					'time_diff' => '-3 hours'
				]
			],
			[
				[
					'user_timezone' => 'System default',
					'time_diff' => '+0 hours'
				]
			],
			[
				[
					'user_timezone' => 'America/Inuvik',
					'time_diff' => '-9 hours'
				]
			],
			[
				[
					'user_timezone' => 'Asia/Magadan',
					'time_diff' => '+8 hours'
				]
			]
		];
	}

	/**
	 * @dataProvider getUserTimezoneData
	 */
	public function testTimezone_Users($data) {
		// Set system timezone
		$this->userLogin('Admin', 'zabbix');
		$this->timezoneChanger('Europe/Riga', 'gui');
		$this->page->open('zabbix.php?action=problem.view');
		$system_time = $this->timeFinder();
		$this->page->logout();

		// User timezone change
		$this->userLogin('test-timezone', 'zabbix');
		$this->timezoneChanger($data['user_timezone'], 'userprofile');
		date_modify($system_time,$data['time_diff']);

		// User timezone check.
		$this->page->open('zabbix.php?action=problem.view');
		$user_time = $this->timeFinder();
		$this->assertEquals($system_time, $user_time);
		$timezone_db = ($data['user_timezone'] == 'System default') ? 'default' : $data['user_timezone'];
		$this->assertEquals($timezone_db, CDBHelper::getValue('SELECT timezone FROM users WHERE alias="test-timezone"'));
		$this->assertEquals('Europe/Riga', CDBHelper::getValue('SELECT default_timezone FROM config WHERE configid="1"'));
		$this->page->logout();
	}

	public static function getCreateUserTimeData() {
		return [
			[
				[
					'fields' => [
						'Alias' => 'test_utc',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test',
						'Password (once again)' => 'test',
						'Time zone' => 'UTC'
					],
					'time_diff' => '-3 hours'
				]
			],
			[
				[
					'fields' => [
						'Alias' => 'test_def',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test',
						'Password (once again)' => 'test',
						'Time zone' => 'System default'
					],
					'time_diff' => '+0 hours'
				]
			],
			[
				[
					'fields' => [
						'Alias' => 'test_amer',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test',
						'Password (once again)' => 'test',
						'Time zone' => 'America/Inuvik'
					],
					'time_diff' => '-9 hours'
				]
			],
			[
				[
					'fields' => [
						'Alias' => 'test_asia',
						'Groups' => [
							'Selenium user group'
						],
						'Password' => 'test',
						'Password (once again)' => 'test',
						'Time zone' => 'Asia/Magadan'
					],
					'time_diff' => '+8 hours'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateUserTimeData
	 */
	public function testTimezone_CreateUser($data) {
		$this->userLogin('Admin', 'zabbix');
		$this->timezoneChanger('Europe/Riga', 'gui');
		$this->page->open('zabbix.php?action=problem.view');
		$system_time = $this->timeFinder();
		$this->page->open('zabbix.php?action=user.edit');
		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form->fill($data['fields']);
		$this->query('id:tab_permissionsTab')->one()->click();
		$form->fill(['User type' => 'Zabbix Super Admin']);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'User added');
		$this->page->logout();
		$this->userLogin($data['fields']['Alias'], $data['fields']['Password']);
		$this->page->open('zabbix.php?action=problem.view');

		// Expected time after timezone change.
		date_modify($system_time,$data['time_diff']);

		// Actual time after timezone change.
		$this->page->open('zabbix.php?action=problem.view');
		$user_time = $this->timeFinder();
		$this->assertEquals($system_time, $user_time);
		$timezone_db = ($data['fields']['Time zone'] == 'System default') ? 'default' : $data['fields']['Time zone'];
		$this->assertEquals($timezone_db, CDBHelper::getValue('SELECT timezone FROM users WHERE alias='.
				zbx_dbstr($data['fields']['Alias'])));
		$this->assertEquals('Europe/Riga', CDBHelper::getValue('SELECT default_timezone FROM config WHERE configid="1"'));
		$this->page->logout();
	}

	private function timeFinder() {
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Problem', 'Trigger for tag permissions Oracle');
		$time = $row->query('class:timeline-date')->one()->getText();
		return date_create($time);
	}

	private function timezoneChanger($timezone, $page) {
		$field_name = ($page == 'gui') ? 'Default time zone' : 'Time zone';
		$message = ($page == 'gui') ? 'Configuration updated' : 'User updated';
		$this->page->open('zabbix.php?action='.$page.'.edit');
		$form = $this->query('xpath://form[@aria-labeledby="page-title-general"]')->one()->asForm();
		$form->fill([$field_name => $timezone]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $message);
	}

	private function userLogin($alias, $password) {
		$this->page->open('index.php');
		$this->query('id:name')->waitUntilVisible()->one()->fill($alias);
		$this->query('id:password')->one()->fill($password);
		$this->query('xpath://button[@type="submit"]')->one()->click();
	}
}
