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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormHostGroup extends CWebTest {
	private $nameSize = 50;
	private $nameMaxLength = 64;

	private $hostGroup = 'Test Group';


	public function testFormHostGroup_CheckLayout() {
		$this->login('hostgroups.php');
		$this->button_click('form');
		$this->wait();

		$this->ok('CONFIGURATION OF HOST GROUPS');
		$this->ok('Host group');
		$this->ok(array('Group name', 'Hosts', 'Hosts in', 'Other hosts | Group'));

		$this->assertElementPresent('name');
		$this->assertElementPresent('twb_groupid');
		$this->assertElementPresent('hosts_left');
		$this->assertElementPresent('add');
		$this->assertElementPresent('remove');
		$this->assertElementPresent('hosts_right');
		$this->assertElementPresent('save');
		$this->assertElementNotPresent('clone');
		$this->assertElementNotPresent('delete');
		$this->assertElementPresent('cancel');

		$this->assertAttribute("//input[@id='name']/@size", $this->nameSize);
		$this->assertAttribute("//input[@id='name']/@maxlength", $this->nameMaxLength);
	}

	public function testFormHostGroup_CreateEmpty() {
		$this->login('hostgroups.php');
		$this->button_click('form');
		$this->wait();

		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "name": cannot be empty.');
	}

	public function testFormHostGroup_Create() {
		$this->login('hostgroups.php');
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', $this->hostGroup);
		$this->button_click('save');
		$this->wait();
		$this->ok('Group added');
	}

	public function testFormHostGroup_CreateDuplicate() {
		$this->login('hostgroups.php');
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', $this->hostGroup);
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot add group');
		$this->ok('Host group "'.$this->hostGroup.'" already exists.');
	}

	public function testFormHostGroup_UpdateEmpty() {
		$this->login('hostgroups.php');
		$this->click('link='.$this->hostGroup);
		$this->wait();

		$this->input_type('name', '');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "name": cannot be empty.');
	}

	public function testFormHostGroup_UpdateDuplicate() {
		$hostGroup = DBfetch(DBselect(
			'SELECT name FROM groups'.
			' WHERE name<>'.zbx_dbstr($this->hostGroup), 1
		));

		$this->login('hostgroups.php');
		$this->click('link='.$this->hostGroup);
		$this->wait();

		$this->input_type('name', $hostGroup['name']);
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot update group');
		$this->ok('Host group "'.$hostGroup['name'].'" already exists.');
	}

	public function testFormHostGroup_Update() {
		$this->login('hostgroups.php');
		$this->click('link='.$this->hostGroup);
		$this->wait();

		$this->input_type('name', $this->hostGroup.' 2');
		$this->button_click('save');
		$this->wait();
		$this->ok('Group updated');
	}

	public function testFormHostGroup_Delete() {
		$this->login('hostgroups.php');
		$this->click('link='.$this->hostGroup.' 2');
		$this->wait();

		$this->chooseOkOnNextConfirmation();
		$this->click('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->ok('Group deleted');
	}
}
?>
