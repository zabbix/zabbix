<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * @backup hstgrp
 */
class testFormHostGroup extends CLegacyWebTest {
	private $hostGroup = 'Test Group';

	public function testFormHostGroup_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.edit');
		$this->zbxTestCheckTitle('Configuration of host group');
		$this->zbxTestCheckHeader('New host group');
		$this->zbxTestTextPresent(['Group name']);

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);

		$this->zbxTestAssertElementPresentXpath("//button[@id='add' and @type='submit']");
		$this->zbxTestAssertElementNotPresentId('clone');
		$this->zbxTestAssertElementNotPresentId('delete');
		$this->assertTrue($this->query('button:Cancel')->one()->isPresent());
	}

	public function testFormHostGroup_CreateEmpty() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.list');
		$this->zbxTestContentControlButtonClickTextWait('Create host group');

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Add')->waitUntilClickable()->one()->click();
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add host group');
		$this->zbxTestTextPresent('Invalid parameter "/1/name": cannot be empty.');
		$dialog->close();
	}

	public function testFormHostGroup_Create() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.edit');
		$this->zbxTestInputTypeWait('name', $this->hostGroup);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host group added');

		$sql = "SELECT * FROM hstgrp WHERE name='$this->hostGroup'";
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public function testFormHostGroup_CreateDuplicate() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.edit');

		$this->zbxTestInputTypeWait('name', $this->hostGroup);
		$this->zbxTestClickXpathWait("//button[@id='add' and @type='submit']");
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add host group');
		$this->zbxTestTextPresent('Host group "'.$this->hostGroup.'" already exists.');
	}

	public function testFormHostGroup_UpdateEmpty() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.list');
		$this->page->waitUntilReady();
		$this->query('link', $this->hostGroup)->waitUntilVisible()->one()->forceClick();

		$form = COverlayDialogElement::find()->one()->waitUntilReady()->asForm();
		$form->getField('Group name')->clear();
		$this->query('button:Update')->one()->click();
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update host group');
		$this->zbxTestTextPresent('Invalid parameter "/1/name": cannot be empty.');
		COverlayDialogElement::find()->one()->close();
	}

	public function testFormHostGroup_UpdateDuplicate() {
		$hostGroup = DBfetch(DBselect(
			'SELECT name FROM hstgrp'.
			' WHERE type=0 AND name<>'.zbx_dbstr($this->hostGroup), 1
		));

		$this->zbxTestLogin('zabbix.php?action=hostgroup.list');
		$this->page->waitUntilReady();
		$this->query('link', $this->hostGroup)->waitUntilVisible()->one()->forceClick();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:hostgroupForm')->asForm()->one();
		$form->query('id:name')->one()->overwrite($hostGroup['name']);
		$form->submit();
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update host group');
		$this->zbxTestTextPresent('Host group "'.$hostGroup['name'].'" already exists.');
		$dialog->close();
	}

	public function testFormHostGroup_Update() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.list');
		$this->page->waitUntilReady();
		$this->query('link', $this->hostGroup)->waitUntilVisible()->one()->forceClick();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:hostgroupForm')->asForm()->one();
		$form->query('id:name')->one()->overwrite($this->hostGroup.' 2');
		$form->submit();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host group updated');

		$sql = "SELECT * FROM hstgrp WHERE name='$this->hostGroup ". 2 ."'";
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	/**
	 * @depends testFormHostGroup_Update
	 */
	public function testFormHostGroup_Delete() {
		$this->zbxTestLogin('zabbix.php?action=hostgroup.list');
		$this->page->waitUntilReady();
		$this->query('link', $this->hostGroup.' 2')->waitUntilVisible()->one()->forceClick();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		$dialog->query('button:Delete')->one()->click();
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host group deleted');

		$sql = "SELECT * FROM hstgrp WHERE name='$this->hostGroup ". 2 ."'";
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}
}
