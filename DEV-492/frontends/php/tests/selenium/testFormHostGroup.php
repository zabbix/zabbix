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

class testFormHostGroup extends CTest
{
	public $hostgroup = 'Test Group';

	public function testFormHostGroup_Create()
	{

		$this->login('hostgroups.php');
		$this->button_click('form');
		$this->wait();
		$this->input_type('gname',$this->hostgroup);
		$this->button_click('save');
		$this->wait();
		$this->ok('Group added');
		$this->logout();
	}

	public function testFormHostGroup_Update()
	{
		$this->login('hostgroups.php');
		$this->click('link='.$this->hostgroup);
		$this->wait();
		$this->input_type('gname',$this->hostgroup.'2');
		$this->button_click('save');
		$this->wait();
		$this->ok('Group updated');
		$this->logout();
	}

	public function testFormHostGroup_Delete()
	{
		$this->chooseOkOnNextConfirmation();

		$this->login('hostgroups.php');
		$this->click('link='.$this->hostgroup.'2');
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->ok('Group deleted');
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
