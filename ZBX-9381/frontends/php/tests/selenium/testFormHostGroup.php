<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class testFormHostGroup extends CWebTest {
	private $hostGroup = 'Test Group';

	public function testFormHostGroup_CheckLayout() {
		$this->zbxTestLogin('hostgroups.php?form=Create+host+group');
		$this->zbxTestCheckTitle('Configuration of host groups');
		$this->zbxTestTextPresent('CONFIGURATION OF HOST GROUPS');
		$this->zbxTestTextPresent('Host group');
		$this->zbxTestTextPresent(array('Group name', 'Hosts', 'Hosts in', 'Other hosts | Group'));

		$this->assertElementPresent('name');
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='name']/@maxlength", 64);

		$this->assertElementPresent('twb_groupid');

		$this->assertElementPresent('hosts_left');
		$this->assertAttribute("//select[@id='hosts_left']/@size", 25);
		$this->assertAttribute("//select[@id='hosts_left']/@style", 'width: 280px;');

		$this->assertElementPresent('add');
		$this->assertElementPresent('remove');

		$this->assertElementPresent('hosts_right');
		$this->assertAttribute("//select[@id='hosts_right']/@size", 25);
		$this->assertAttribute("//select[@id='hosts_right']/@style", 'width: 280px;');

		$this->assertElementPresent('save');
		$this->assertElementNotPresent('clone');
		$this->assertElementNotPresent('delete');
		$this->assertElementPresent('cancel');

	}

	public function testFormHostGroup_CreateEmpty() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('form');

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('ERROR: Page received incorrect data');
		$this->zbxTestTextPresent('Incorrect value for field "name": cannot be empty.');
	}

	public function testFormHostGroup_Create() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('form');

		$this->input_type('name', $this->hostGroup);
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Group added');
	}

	public function testFormHostGroup_CreateDuplicate() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('form');

		$this->input_type('name', $this->hostGroup);
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('ERROR: Cannot add group');
		$this->zbxTestTextPresent('Host group "'.$this->hostGroup.'" already exists.');
	}

	public function testFormHostGroup_UpdateEmpty() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('link='.$this->hostGroup);

		$this->input_type('name', '');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('ERROR: Page received incorrect data');
		$this->zbxTestTextPresent('Incorrect value for field "name": cannot be empty.');
	}

	public function testFormHostGroup_UpdateDuplicate() {
		$hostGroup = DBfetch(DBselect(
			'SELECT name FROM groups'.
			' WHERE name<>'.zbx_dbstr($this->hostGroup), 1
		));

		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('link='.$this->hostGroup);

		$this->input_type('name', $hostGroup['name']);
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('ERROR: Cannot update group');
		$this->zbxTestTextPresent('Host group "'.$hostGroup['name'].'" already exists.');
	}

	public function testFormHostGroup_Update() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('link='.$this->hostGroup);

		$this->input_type('name', $this->hostGroup.' 2');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Group updated');
	}

	public function testFormHostGroup_Delete() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('link='.$this->hostGroup.' 2');

		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Group deleted');
	}
}
