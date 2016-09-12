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

class testFormHostGroup extends CWebTest {
	private $hostGroup = 'Test Group';

	public function testFormHostGroup_backup() {
		DBsave_tables('groups');
	}

	public function testFormHostGroup_CheckLayout() {
		$this->zbxTestLogin('hostgroups.php?form=Create+host+group');
		$this->zbxTestCheckTitle('Configuration of host groups');
		$this->zbxTestCheckHeader('Host groups');
		$this->zbxTestTextPresent(['Group name', 'Hosts', 'Hosts in', 'Other hosts | Group']);

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'size', 20);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);

		$this->zbxTestAssertElementPresentId('twb_groupid');

		$this->zbxTestAssertElementPresentId('hosts_left');
		$this->zbxTestAssertAttribute("//select[@id='hosts_left']", 'size', 25);
		$this->zbxTestAssertAttribute("//select[@id='hosts_left']", 'style', 'width: 280px;');

		$this->zbxTestAssertElementPresentId('add');
		$this->zbxTestAssertElementPresentId('remove');

		$this->zbxTestAssertElementPresentId('hosts_right');
		$this->zbxTestAssertAttribute("//select[@id='hosts_right']", 'size', 25);
		$this->zbxTestAssertAttribute("//select[@id='hosts_right']", 'style', 'width: 280px;');

		$this->zbxTestAssertElementPresentXpath("//button[@id='add' and @type='submit']");
		$this->zbxTestAssertElementNotPresentId('clone');
		$this->zbxTestAssertElementNotPresentId('delete');
		$this->zbxTestAssertElementPresentId('cancel');

	}

	public function testFormHostGroup_CreateEmpty() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('form');

		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Page received incorrect data');
		$this->zbxTestTextPresent('Incorrect value for field "Group name": cannot be empty.');
	}

	public function testFormHostGroup_Create() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputTypeWait('name', $this->hostGroup);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Group added');

		$sql = "SELECT * FROM groups WHERE name='$this->hostGroup'";
		$this->assertEquals(1, DBcount($sql));
	}

	public function testFormHostGroup_CreateDuplicate() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputTypeWait('name', $this->hostGroup);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add group');
		$this->zbxTestTextPresent('Host group "'.$this->hostGroup.'" already exists.');
	}

	public function testFormHostGroup_UpdateEmpty() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickLinkTextWait($this->hostGroup);

		$this->zbxTestInputTypeOverwrite('name', ' ');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Page received incorrect data');
		$this->zbxTestTextPresent('Incorrect value for field "Group name": cannot be empty.');
	}

	public function testFormHostGroup_UpdateDuplicate() {
		$hostGroup = DBfetch(DBselect(
			'SELECT name FROM groups'.
			' WHERE name<>'.zbx_dbstr($this->hostGroup), 1
		));

		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickLinkTextWait($this->hostGroup);

		$this->zbxTestInputTypeOverwrite('name', $hostGroup['name']);
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update group');
		$this->zbxTestTextPresent('Host group "'.$hostGroup['name'].'" already exists.');
	}

	public function testFormHostGroup_Update() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickLinkTextWait($this->hostGroup);

		$this->zbxTestInputTypeOverwrite('name', $this->hostGroup.' 2');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Group updated');

		$sql = "SELECT * FROM groups WHERE name='$this->hostGroup ". 2 ."'";
		$this->assertEquals(1, DBcount($sql));
	}

	public function testFormHostGroup_Delete() {
		$this->zbxTestLogin('hostgroups.php');
		$this->zbxTestClickLinkTextWait($this->hostGroup.' 2');

		$this->zbxTestClickWait('delete');
		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Group deleted');

		$sql = "SELECT * FROM groups WHERE name='$this->hostGroup ". 2 ."'";
		$this->assertEquals(0, DBcount($sql));
	}

	public function testFormHostGroup_restore() {
		DBrestore_tables('groups');
	}
}
