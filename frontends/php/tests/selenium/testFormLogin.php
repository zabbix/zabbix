<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class testFormLogin extends CWebTest {

	public static function data() {
		return [
			[[
				'login' => 'disabled-user',
				'password' => 'zabbix',
				'success_expected' => false,
				'dbCheck' => false
			]],
			[[
				'login' => 'no-access-to-the-frontend',
				'password' => 'zabbix',
				'success_expected' => false,
				'dbCheck' => false
			]],
			[[
				'login' => 'admin',
				'password' => 'zabbix',
				'success_expected' => false,
				'dbCheck' => false
			]],
			[[
				'login' => 'Admin',
				'password' => 'Zabbix',
				'success_expected' => false,
				'dbCheck' => true
			]],
			[[
				'login' => 'Admin',
				'password' => '',
				'success_expected' => false,
				'dbCheck' => true
			]],
			[[
				'login' => 'Admin',
				'password' => '!@$#%$&^*(\"\'\\*;:',
				'success_expected' => false,
				'dbCheck' => true
			]],
			[[
				'login' => 'Admin',
				'password' => 'zabbix',
				'success_expected' => true,
				'dbCheck' => false
			]],
			[[
				'login' => 'guest',
				'password' => '',
				'success_expected' => true,
				'dbCheck' => false
			]],
		];
	}

	/**
	 * @dataProvider data
	 */
	public function testFormLogin_LoginLogout($data) {
		$this->zbxTestOpen('index.php');
		$this->zbxTestInputTypeOverwrite('name', $data['login']);
		$this->zbxTestInputTypeOverwrite('password', $data['password']);
		$this->zbxTestClickWait('enter');

		switch($data['login'])	{
			case 'disabled-user':
				$this->zbxTestAssertElementText("//form//div[@class='red']", 'No permissions for system access.');
				$this->zbxTestTextPresent(['Username', 'Password']);
				break;
			case 'no-access-to-the-frontend':
				$this->zbxTestAssertElementText("//form//div[@class='red']", 'GUI access disabled.');
				$this->zbxTestTextPresent(['Username', 'Password']);
				break;
			case 'admin':
				$this->zbxTestAssertElementText("//form//div[@class='red']", 'Login name or password is incorrect.');
				$this->zbxTestTextPresent(['Username', 'Password']);
				break;
		}

		if ($data['success_expected']) {
		$this->zbxTestTextNotPresent('Login name or password is incorrect.');
		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestTextNotPresent('Password');
		$this->zbxTestTextNotPresent('Username');

		$this->zbxTestClickXpathWait("//ul[@class='top-nav-icons']//a[@class='top-nav-signout']");
		$this->zbxTestTextPresent('Username');
		$this->zbxTestTextPresent('Password');
		$this->zbxTestTextNotPresent('Dashboard');
	}
		elseif ($data['dbCheck']) {
			$this->zbxTestAssertElementText("//form//div[@class='red']", 'Login name or password is incorrect.');
			$this->zbxTestTextPresent(['Username', 'Password']);
			$this->assertEquals(1, DBcount("SELECT * FROM users WHERE attempt_failed>0 AND alias='".$data['login']."'"));
			$this->assertEquals(1, DBcount("SELECT * FROM users WHERE attempt_clock>0 AND alias='".$data['login']."'"));
			$this->assertEquals(1, DBcount("SELECT * FROM users WHERE attempt_ip<>'' AND alias='".$data['login']."'"));
	}
		}

	public function testFormLogin_BlockAccountAndRecoverAfter30Seconds() {
		$this->zbxTestOpen('index.php');

		for ($i = 1; $i <= 5; $i++) {
			$this->zbxTestInputTypeWait('name', 'user-for-blocking');
			$this->zbxTestInputTypeWait('password', '!@$#%$&^*(\"\'\\*;:');
			$this->zbxTestClickWait('enter');
			$this->zbxTestTextPresent('Login name or password is incorrect');
			$this->zbxTestTextPresent('Username');
			$this->zbxTestTextPresent('Password');

			$sql = 'SELECT * FROM users WHERE alias=\'user-for-blocking\' AND attempt_failed='.$i.'';
			$this->assertEquals(1, DBcount($sql));
			$sql = 'SELECT * FROM users WHERE alias=\'user-for-blocking\' AND attempt_clock>0';
			$this->assertEquals(1, DBcount($sql));
			$sql = "SELECT * FROM users WHERE alias='user-for-blocking' AND attempt_ip<>''";
			$this->assertEquals(1, DBcount($sql));
		}

		$this->zbxTestInputType('name', 'user-for-blocking');
		$this->zbxTestInputType('password', '!@$#%$&^*(\"\'\\*;:');
		$this->zbxTestClickWait('enter');
		$this->zbxTestTextPresent(['Account is blocked for', 'seconds', 'Username', 'Password']);
		// account is blocked, waiting 30 sec and trying to login
		sleep(30);

		$this->zbxTestInputTypeWait('name', 'user-for-blocking');
		$this->zbxTestInputTypeWait('password', 'zabbix');
		$this->zbxTestClickWait('enter');
		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', '5 failed login attempts logged.');
		$this->zbxTestTextNotPresent('Password');
		$this->zbxTestTextNotPresent('Username');
	}
}
