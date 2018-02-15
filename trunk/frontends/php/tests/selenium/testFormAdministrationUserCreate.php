<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

/**
 * @backup users
 */
class testFormAdministrationUserCreate extends CWebTest {

	public function testFormAdministrationUserCreate_CreateUser() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestClick('form');
		$this->zbxTestInputType('alias', 'User alias');
		$this->zbxTestInputType('name', 'User name');
		$this->zbxTestInputType('surname', 'User surname');
		$this->zbxTestClickButtonMultiselect('user_groups_');
		$this->zbxTestLaunchOverlayDialog('User groups');
		$this->zbxTestCheckboxSelect('item_7');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');
		$this->zbxTestInputTypeWait('password1', '123');
		$this->zbxTestInputType('password2', '123');
		$this->zbxTestClick('add');
		$this->zbxTestTextPresent('User added');

		$sql = 'SELECT * FROM users WHERE alias=\'User alias\'';
		$this->assertEquals(1, DBcount($sql), 'User with such alias has not been added');
	}
}
