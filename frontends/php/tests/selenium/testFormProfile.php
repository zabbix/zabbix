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

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->logout();
	}

	public function testFormProfile_Cancel()
	{
		$this->login('profile.php');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->logout();
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

		$this->logout();
	}

	public function testFormProfile_ThemeChange()
	{
		$this->login('profile.php');

		$this->dropdown_select('theme','Original blue');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->logout();
	}

	public function testFormProfile_AutologinSet()
	{
		$this->login('profile.php');

		$this->checkbox_select('autologin');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->logout();
	}

	public function atestFormHostGroup()
	{
		$name='Test Group';

		$this->chooseOkOnNextConfirmation();

		$this->login();
		// Create Host Group
		$this->open('hostgroups.php');
		$this->waitForPageToLoad();
		$this->click('form');
		$this->waitForPageToLoad();
		$this->type('gname',$name);
		$this->click('save');
		$this->waitForPageToLoad();
		// Update Host Group
		$this->login();
		$this->open('hostgroups.php');
		$this->waitForPageToLoad();
		$this->click("link=$name");
		$this->waitForPageToLoad();
		$this->type('gname',$name.'2');
		$this->click('save');
		$this->waitForPageToLoad();
		// Delete Host Group
		$this->open('hostgroups.php');
		$this->waitForPageToLoad();
		$this->click("link=$name".'2');
		$this->waitForPageToLoad();
		$this->click('delete');
		$this->waitForPageToLoad();
		$this->getConfirmation();
		$this->logout();
	}

	public function atestFormScreen()
	{
		$name='Test Screen';

		$this->chooseOkOnNextConfirmation();

		$this->login();
		// Create Screen
		$this->open('screenconf.php');
		$this->waitForPageToLoad();
		$this->click('form');
		$this->waitForPageToLoad();
		$this->type('name',$name);
		$this->click('save');
		$this->waitForPageToLoad();
	}
}
?>
