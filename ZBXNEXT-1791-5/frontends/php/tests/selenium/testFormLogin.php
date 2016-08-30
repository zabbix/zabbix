<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	public function testFormLogin_ClearIds() {
		DBexecute('DELETE FROM ids');
	}

	public function testFormLogin_LoginLogout() {
		$this->zbxTestOpen('index.php');
		$this->zbxTestInputTypeWait('name', 'Admin');
		$this->zbxTestInputTypeWait('password', 'zabbix');
		$this->zbxTestClickWait('enter');
		$this->zbxTestTextNotPresent('Login name or password is incorrect.');

		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestTextNotPresent('Password');
		$this->zbxTestTextNotPresent('Username');

		$this->zbxTestClickXpathWait("//ul[@class='top-nav-icons']//a[@class='top-nav-signout']");
		$this->zbxTestTextPresent('Username');
		$this->zbxTestTextPresent('Password');
		$this->zbxTestTextNotPresent('Dashboard');
	}

	public function testFormLogin_LoginIncorrectPassword() {
		DBsave_tables('users');

		$this->zbxTestOpen('index.php');

		$this->zbxTestInputTypeWait('name', 'Admin');
		$this->zbxTestInputTypeWait('password', '!@$#%$&^*(\"\'\\*;:');
		$this->zbxTestClickWait('enter');
		$this->zbxTestTextPresent(['Login name or password is incorrect', 'Username', 'Password']);

		$sql = "SELECT * FROM users WHERE attempt_failed>0 AND alias='Admin'";
		$this->assertEquals(1, DBcount($sql));
		$sql = "SELECT * FROM users WHERE attempt_clock>0 AND alias='Admin'";
		$this->assertEquals(1, DBcount($sql));
		$sql = "SELECT * FROM users WHERE attempt_ip<>'' AND alias='Admin'";
		$this->assertEquals(1, DBcount($sql));

		DBrestore_tables('users');
	}

	public function testFormLogin_BlockAccount() {
		DBsave_tables('users');

		$this->zbxTestOpen('index.php');

		for ($i = 1; $i <= 5; $i++) {
			$this->zbxTestInputTypeWait('name', 'Admin');
			$this->zbxTestInputTypeWait('password', '!@$#%$&^*(\"\'\\*;:');
			$this->zbxTestClickWait('enter');
			$this->zbxTestTextPresent(['Login name or password is incorrect', 'Username', 'Password']);

			$sql = 'SELECT * FROM users WHERE alias=\'Admin\' AND attempt_failed='.$i.'';
			$this->assertEquals(1, DBcount($sql));
			$sql = 'SELECT * FROM users WHERE alias=\'Admin\' AND attempt_clock>0';
			$this->assertEquals(1, DBcount($sql));
			$sql = "SELECT * FROM users WHERE alias='Admin' AND attempt_ip<>''";
			$this->assertEquals(1, DBcount($sql));
		}

		$this->zbxTestInputType('name', 'Admin');
		$this->zbxTestInputType('password', '!@$#%$&^*(\"\'\\*;:');
		$this->zbxTestClickWait('enter');
		$this->zbxTestTextPresent(['Account is blocked for', 'seconds', 'Username', 'Password']);

		DBrestore_tables('users');
	}

	public function testFormLogin_BlockAccountAndRecoverAfter30Seconds() {
		DBsave_tables('users');

		$this->zbxTestOpen('index.php');

		for ($i = 1; $i <= 5; $i++) {
			$this->zbxTestInputTypeWait('name', 'Admin');
			$this->zbxTestInputTypeWait('password', '!@$#%$&^*(\"\'\\*;:');
			$this->zbxTestClickWait('enter');
			$this->zbxTestTextPresent('Login name or password is incorrect');
			$this->zbxTestTextPresent('Username');
			$this->zbxTestTextPresent('Password');

			$sql = 'SELECT * FROM users WHERE alias=\'Admin\' AND attempt_failed='.$i.'';
			$this->assertEquals(1, DBcount($sql));
			$sql = 'SELECT * FROM users WHERE alias=\'Admin\' AND attempt_clock>0';
			$this->assertEquals(1, DBcount($sql));
			$sql = "SELECT * FROM users WHERE alias='Admin' AND attempt_ip<>''";
			$this->assertEquals(1, DBcount($sql));
		}

		$this->zbxTestInputType('name', 'Admin');
		$this->zbxTestInputType('password', '!@$#%$&^*(\"\'\\*;:');
		$this->zbxTestClickWait('enter');
		$this->zbxTestTextPresent(['Account is blocked for', 'seconds', 'Username', 'Password']);

		// account is blocked, waiting 30 sec and trying to login
		sleep(30);

		$this->zbxTestInputTypeWait('name', 'Admin');
		$this->zbxTestInputTypeWait('password', 'zabbix');
		$this->zbxTestClickWait('enter');
		$this->zbxTestCheckHeader('Dashboard');

		$this->zbxTestTextNotPresent('Password');
		$this->zbxTestTextNotPresent('Username');

		DBrestore_tables('users');
	}

}
