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
require_once(dirname(__FILE__).'/class.ctest.php');

class testFormProfile extends CTest
{
	public $oldHash;

	public function hashUsersExcept($user)
	{
		$this->oldHash=$this->DBhash("select * from users where alias<>'$user'");
	}

	protected function assertPreConditions()
	{
		$this->oldHash=$this->hashUsersExcept('Admin');
	}

	protected function assertPostConditions()
	{
	}

	public function testFormProfile_SimpleUpdate()
	{
		$this->login('profile.php');

		$this->assertTitle('User profile');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}

	public function testFormProfile_Cancel()
	{
		$this->login('profile.php');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
	}

	public function testFormProfile_PasswordChange1()
	{
		$pwd="'\'$\"\"!$@$#^%$+-=~`\`\\";

		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1',$pwd);
		$this->input_type('password2',$pwd);

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$row=DBfetch(DBselect("select passwd from users where alias='Admin'"));
		$this->assertEquals(md5($pwd),$row['passwd']);

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}

	public function testFormProfile_PasswordChange2()
	{
		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1','zabbix');
		$this->input_type('password2','zabbix');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}

	public function testFormProfile_EmptyPasswords()
	{
		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1','');
		$this->input_type('password2','');

		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Password should not be empty');
		$this->assertTitle('User profile');

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}

	public function testFormProfile_DifferentPasswords()
	{
		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1','abc');
		$this->input_type('password2','def');

		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot update user. Both passwords must be equal.');
		$this->assertTitle('User profile');
	}

	public function testFormProfile_ThemeChange()
	{
		global $DB;

		$this->login('profile.php');

		$this->dropdown_select('theme','Original blue');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$row=DBfetch(DBselect("select theme from users where alias='Admin'"));
		$this->assertEquals('css_ob.css',$row['theme']);

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}

	public function testFormProfile_AutologinSet()
	{
		$this->login('profile.php');

		$this->checkbox_select('autologin');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$row=DBfetch(DBselect("select autologin from users where alias='Admin'"));
		$this->assertEquals(1,$row['autologin']);

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}

	public function testFormProfile_AutologinUnSet()
	{
		$this->login('profile.php');

		$this->checkbox_unselect('autologin');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$row=DBfetch(DBselect("select autologin from users where alias='Admin'"));
		$this->assertEquals(0,$row['autologin']);

		$this->assertEquals($this->oldHash,$this->hashUsersExcept('Admin'));
	}
}
?>
