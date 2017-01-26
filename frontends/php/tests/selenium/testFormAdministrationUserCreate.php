<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class testFormAdministrationUserCreate extends CWebTest {

	public function testFormAdministrationUserCreate_backup() {
		DBsave_tables('users');
	}

	public function testFormAdministrationUserCreate_CreateUser() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestClick('form');
		$this->zbxTestInputType('alias', 'User alias');
		$this->zbxTestInputType('name', 'User name');
		$this->zbxTestInputType('surname', 'User surname');
		$this->zbxTestClick('add_group');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestCheckboxSelect('new_groups_7');
		$this->zbxTestClick('select');
		$this->webDriver->switchTo()->window('');
		$this->zbxTestInputTypeWait('password1', '123');
		$this->zbxTestInputType('password2', '123');
		$this->zbxTestClick('add');
		$this->zbxTestTextPresent('User added');

		$sql = 'SELECT * FROM users WHERE alias=\'User alias\'';
		$this->assertEquals(1, DBcount($sql), 'User with such alias has not been added');
	}

	public function testFormAdministrationUserCreate_restore() {
		DBrestore_tables('users');
	}
}
