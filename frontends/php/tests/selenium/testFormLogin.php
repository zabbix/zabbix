<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testFormLogin extends CWebTest
{
	public function testFormLogin_ClearIds() {

	DBexecute("delete from ids");

	}

	public function testFormLogin_LoginOK() {

		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Username');
		$this->ok('Password');

		$this->input_type('name', 'Admin');
		$this->input_type('password', 'zabbix');
		$this->click('enter');
		$this->wait();

		$this->nok('Password');
		$this->nok('Username');
	}

	public function testFormLogin_LoginIncorrectPassword() {
		DBsave_tables('users');

		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Username');
		$this->ok('Password');

		$this->input_type('name', 'Admin');
		$this->input_type('password', '!@$#%$&^*(\"\'\\*;:');
		$this->click('enter');
		$this->wait();
		$this->ok('Login name or password is incorrect');
		$this->ok('Username');
		$this->ok('Password');

		$sql = "select * from users where alias='Admin' and attempt_failed>0";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_failed should not be zero after incorrect login.');
		$sql = "select * from users where alias='Admin' and attempt_clock>0";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_clock should not be zero after incorrect login.');
		$sql = "select * from users where alias='Admin' and attempt_ip<>''";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_ip should not be empty after incorrect login.');

		DBrestore_tables('users');
	}

	public function testFormLogin_BlockAccount() {
		DBsave_tables('users');

		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Username');
		$this->ok('Password');

		for ($i = 1; $i <= 5; $i++) {
			$this->input_type('name', 'Admin');
			$this->input_type('password', '!@$#%$&^*(\"\'\\*;:');
			$this->click('enter');
			$this->wait();
			$this->ok('Login name or password is incorrect');
			$this->ok('Username');
			$this->ok('Password');

			$sql = "select * from users where alias='Admin' and attempt_failed=$i";
			$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_failed should be equal '.$i.' after '.$i.' incorrect login.');
			$sql = "select * from users where alias='Admin' and attempt_clock>0";
			$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_clock should not be zero after incorrect login.');
			$sql = "select * from users where alias='Admin' and attempt_ip<>''";
			$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_ip should not be empty after incorrect login.');
		}

		$this->input_type('name', 'Admin');
		$this->input_type('password', '!@$#%$&^*(\"\'\\*;:');
		$this->click('enter');
		$this->wait();
		$this->ok('Account is blocked for');
		$this->ok('seconds');
		$this->ok('Username');
		$this->ok('Password');

		DBrestore_tables('users');
	}

	public function testFormLogin_BlockAccountAndRecoverAfter30Seconds() {
		DBsave_tables('users');

		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Username');
		$this->ok('Password');

		for ($i = 1; $i <= 5; $i++) {
			$this->input_type('name', 'Admin');
			$this->input_type('password', '!@$#%$&^*(\"\'\\*;:');
			$this->click('enter');
			$this->wait();
			$this->ok('Login name or password is incorrect');
			$this->ok('Username');
			$this->ok('Password');

			$sql = "select * from users where alias='Admin' and attempt_failed=$i";
			$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_failed should be equal '.$i.' after '.$i.' incorrect login.');
			$sql = "select * from users where alias='Admin' and attempt_clock>0";
			$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_clock should not be zero after incorrect login.');
			$sql = "select * from users where alias='Admin' and attempt_ip<>''";
			$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Field users.attempt_ip should not be empty after incorrect login.');
		}

		$this->input_type('name', 'Admin');
		$this->input_type('password', '!@$#%$&^*(\"\'\\*;:');
		$this->click('enter');
		$this->wait();
		$this->ok('Account is blocked for');
		$this->ok('seconds');
		$this->ok('Username');
		$this->ok('Password');

		// account is blocked, waiting 35 sec and trying to login
		sleep(35);

		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Username');
		$this->ok('Password');

		$this->input_type('name', 'Admin');
		$this->input_type('password', 'zabbix');
		$this->click('enter');
		$this->wait();

		$this->nok('Password');
		$this->nok('Username');

		DBrestore_tables('users');

	}
}
?>
