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
	public function testFormProfile_SimpleUpdate()
	{
		$this->login('profile.php');

		$this->assertTitle('User profile');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
	}

	public function testFormProfile_Cancel()
	{
		$this->login('profile.php');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
	}

	public function testFormProfile_PasswordChange()
	{
		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1','zabbix');
		$this->input_type('password2','zabbix');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
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
		$this->login('profile.php');

		$this->dropdown_select('theme','Original blue');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
	}

	public function testFormProfile_AutologinSet()
	{
		$this->login('profile.php');

		$this->checkbox_select('autologin');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
	}
}
?>
