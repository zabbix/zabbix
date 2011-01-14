<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
	public function testFormLogin_LoginOK()
	{
		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Login name');
		$this->ok('Password');

		$this->input_type('name','Admin');
		$this->input_type('password','zabbix');
		$this->click('enter');
		$this->wait();

		$this->nok('Password');
		$this->nok('Login name');
	}

	public function testFormLogin_LoginIncorrectPassword()
	{
		$this->login('dashboard.php');
		$this->assertTitle('Dashboard');
		$this->click('link=Logout');
		$this->wait();
		$this->ok('Login name');
		$this->ok('Password');

		$this->input_type('name','Admin');
		$this->input_type('password','!@$#%$&^*(\"\'\\*;:');
		$this->click('enter');
		$this->wait();
		$this->ok('Login name or password is incorrect');
		$this->ok('Login name');
		$this->ok('Password');

		$sql="select * from users where alias='Admin' and attempt_failed>0";
		$this->assertEquals(1,DBcount($sql),"Chuck Norris: Field users.attempt_failed should not be zero after incorrect login.");
		$sql="select * from users where alias='Admin' and attempt_clock>0";
		$this->assertEquals(1,DBcount($sql),"Chuck Norris: Field users.attempt_clock should not be zero after incorrect login.");
		$sql="select * from users where alias='Admin' and attempt_ip<>''";
		$this->assertEquals(1,DBcount($sql),"Chuck Norris: Field users.attempt_ip should not be empty after incorrect login.");
	}

	public function testFormLogin_LoginAfterIncorrectLogin()
	{
		// TODO
		// Make sure to check for 'N  failed login attempts logged. Last failed attempt was from';
		$this->markTestIncomplete();
	}

	public function testFormLogin_BlockAccount()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function testFormLogin_BlockAccountAndRecoverAfter30Seconds()
	{
		// TODO
		$this->markTestIncomplete();
	}
}
?>
