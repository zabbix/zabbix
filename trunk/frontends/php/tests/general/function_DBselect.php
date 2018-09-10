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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class function_DBselect extends CZabbixTest {
	public function test_DBselectOK() {
		$result=DBselect('select * from users');
		$this->assertTrue(is_resource($result) ||is_object($result));
	}

	public function test_DBselectRange() {
		$this->assertTrue(0 == DBcount('select * from items', 0));
		$this->assertTrue(1 == DBcount('select * from items', 1));
		$this->assertTrue(100 == DBcount('select * from items', 100));
		$this->assertTrue(1 == DBcount('select * from items', '1'));
	}

	public function test_DBselectWrongParameters() {
		$this->assertTrue(false == DBselect('select * from items', 'ZZZ'));
		$this->assertTrue(false == DBselect('select * from items', 1.5));
		$this->assertTrue(false == DBselect('select * from items', 1.5));
		$this->assertTrue(false == DBselect('select * from items', -1));
	}

	public function test_DBselectFail() {
		// TODO
		$this->markTestIncomplete();
/* Does not work this way
		$result=DBselect('select * from users_typo');
		$this->assertTrue($result == false);
*/
	}
}
