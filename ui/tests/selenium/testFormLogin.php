<?php
/*
 * * Zabbix
 * * Copyright (C) 2001-2022 Zabbix SIA
 * *
 * * This program is free software; you can redistribute it and/or modify
 * * it under the terms of the GNU General Public License as published by
 * * the Free Software Foundation; either version 2 of the License, or
 * * (at your option) any later version.
 * *
 * * This program is distributed in the hope that it will be useful,
 * * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * * GNU General Public License for more details.
 * *
 * * You should have received a copy of the GNU General Public License
 * * along with this program; if not, write to the Free Software
 * * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * */

require_once dirname(__FILE__).'/../include/CWebTest.php';

class testFormLogin extends CWebTest {

	public static function getLoginLogoutData() {
		return [
			[
				[
					'login' => 'disabled-user',
					'password' => 'zabbix',
					'success_expected' => false,
					'dbCheck' => false
				]
			],
			[
				[
					'login' => 'no-access-to-the-frontend',
					'password' => 'zabbix',
					'success_expected' => false,
					'dbCheck' => false
				]
			],
			[
				[
					'login' => 'admin',
					'password' => 'zabbix',
					'success_expected' => false,
					'dbCheck' => false
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => 'Zabbix',
					'success_expected' => false,
					'dbCheck' => true
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => '',
					'success_expected' => false,
					'dbCheck' => true
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => '!@$#%$&^*(\"\'\\*;:',
					'success_expected' => false,
					'dbCheck' => true
				]
			],
			[
				[
					'login' => 'Admin',
					'password' => 'zabbix',
					'success_expected' => true,
					'dbCheck' => false
				]
			],
			[
				[
					'login' => 'guest',
					'password' => '',
					'success_expected' => true,
					'dbCheck' => false
				]
			]
		];
	}

	/**
	 * Function is using previously defined data in order to login into system by checking different type of
	 * user permissions. When expected view is opened with the user, function is logging out of system.
	 * Additionally function checks if database is gathering correct data.
	 *
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 *
	 * @dataProvider getLoginLogoutData
	 */
	public function testFormLogin_LoginLogout($data) {
		$this->page->userLogin($data['login'], $data['password']);
		switch ($data['login']) {
			case 'disabled-user':
				$this->assertEquals('No permissions for system access.', $this->query('class:red')
						->waitUntilVisible()->one()->getText());
				break;

			case 'no-access-to-the-frontend':
				$this->assertEquals('GUI access disabled.', $this->query('class:red')
						->waitUntilVisible()->one()->getText());
				break;

			case 'admin':
				$this->assertEquals('Incorrect user name or password or account is temporarily blocked.', $this->query('class:red')
						->waitUntilVisible()->one()->getText());
				break;
		}

		if ($data['success_expected']) {
			$this->page->assertHeader('Global view');
			$this->query('class:icon-signout')->one()->click();
			$this->assertEquals('Remember me for 30 days', $this->query('xpath://label[@for="autologin"]')->one()->getText());
		}
		elseif ($data['dbCheck']) {
			$this->assertEquals('Incorrect user name or password or account is temporarily blocked.', $this->query('class:red')
					->waitUntilVisible()->one()->getText());
			$sql = "SELECT * FROM users WHERE attempt_failed>0 AND alias='".$data['login']."'";
			$this->assertEquals(1, CDBHelper::getCount($sql));
			$sql = "SELECT * FROM users WHERE attempt_clock>0 AND alias='".$data['login']."'";
			$this->assertEquals(1, CDBHelper::getCount($sql));
			$sql = "SELECT * FROM users WHERE attempt_ip<>'' AND alias='".$data['login']."'";
			$this->assertEquals(1, CDBHelper::getCount($sql));
		}
	}

	/**
	 * Function is creating failed authentication in order to block account, afterwards by halting it's work for
	 * 30 seconds, function checks if correctly inserted authentication data for account gives access to view
	 * and properly returns message stating how many times failed attempts have been made to login into account.
	 */
	public function testFormLogin_BlockAccountAndRecoverAfter30Seconds() {
		$this->page->open('index.php');
		for ($i = 1; $i < 5; $i++) {
			$this->page->userLogin('user-for-blocking', '!@$#%$&^*(\"\'\\*;:');
			$this->assertEquals('Incorrect user name or password or account is temporarily blocked.', $this->query('class:red')
					->waitUntilVisible()->one()->getText());
			$sql = 'SELECT attempt_failed FROM users WHERE alias=\'user-for-blocking\'';
			$this->assertEquals($i, CDBHelper::getValue($sql));
			$sql = 'SELECT * FROM users WHERE alias=\'user-for-blocking\' AND attempt_clock>0';
			$this->assertEquals(1, CDBHelper::getCount($sql));
			$sql = "SELECT * FROM users WHERE alias='user-for-blocking' AND attempt_ip<>''";
			$this->assertEquals(1, CDBHelper::getCount($sql));
		}

		$this->page->userLogin('user-for-blocking', '!@$#%$&^*(\"\'\\*;:');
		$this->assertEquals('Incorrect user name or password or account is temporarily blocked.', $this->query('class:red')
				->waitUntilVisible()->one()->getText());

		// Account is blocked, waiting 30 sec and trying to login.
		sleep(30);
		$this->page->userLogin('user-for-blocking', 'zabbix');
		$this->page->assertHeader('Global view');
		$this->assertStringContainsString('5 failed login attempts logged.', $this->query('class:msg-bad')
				->waitUntilVisible()->one()->getText());
	}

	/**
	 * Function makes two authentifications with different data to different Zabbix views, separately clicking on
	 * sign in button and checking by views header, if correct url is opened.
	 */
	public function testFormLogin_LoginWithRequest() {
		foreach (['index.php?request=hosts.php', 'index.php?request=zabbix.php%3Faction%3Dproxy.list'] as $url) {
			$this->page->userLogin('Admin', 'zabbix', $url);
			$header = ($url === 'index.php?request=hosts.php') ? 'Hosts' : 'Proxies';
			$this->page->assertHeader($header);
		}
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public static function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)');
	}
}
