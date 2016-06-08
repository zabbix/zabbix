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

class testFormScreen extends CWebTest {
	public $testscreen = 'Test Screen';
	public $testscreen_ = 'Test screen (simple graph)';

	public function testFormScreen_Create() {
		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestInputType('name', $this->testscreen);
		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Screen added');
	}

	public function testFormScreen_ZBX6030() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestClickWait('link='.$this->testscreen_);
		$this->zbxTestClickWait('link=Change');
		$this->assertElementNotPresent('//input[@id=\'dynamic\']/@checked');
		$this->zbxTestCheckboxSelect('dynamic');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Item updated');
		$this->zbxTestClickWait('link=Change');
		$this->assertElementPresent('//input[@id=\'dynamic\']/@checked');
		$this->zbxTestCheckboxSelect('dynamic', false);
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Item updated');
		$this->zbxTestClickWait('link=Change');
		$this->assertElementNotPresent('//input[@id=\'dynamic\']/@checked');
	}

}
