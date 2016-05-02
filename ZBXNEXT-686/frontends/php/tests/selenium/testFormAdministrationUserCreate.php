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

class testFormAdministrationUserCreate extends CWebTest {
	public function testFormAdministrationUserCreate_CreateUser() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestClick('form');
		$this->input_type('alias', 'User alias');
		$this->input_type('name', 'User name');
		$this->input_type('surname', 'User surname');
		$this->zbxTestClick('add_group');
		$this->zbx_wait_window_and_switch_to_it('zbx_popup');
		$this->zbxTestCheckboxSelect('new_groups_7');
		$this->zbxTestClick('select');
		$this->webDriver->switchTo()->window('');
		$this->wait_input_type('password1', '123');
		$this->input_type('password2', '123');
		$this->zbxTestClick('add');
		$this->zbxTestTextPresent('User added');
	}
}
