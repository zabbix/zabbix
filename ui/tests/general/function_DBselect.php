<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../include/CTest.php';

class function_DBselect extends CTest {
	public function test_DBselectOK() {
		$result=DBselect('select * from users');
		$this->assertTrue(is_resource($result) ||is_object($result));
	}

	public function test_DBselectRange() {
		$this->assertTrue(0 == CDBHelper::getCount('select * from items', 0));
		$this->assertTrue(1 == CDBHelper::getCount('select * from items', 1));
		$this->assertTrue(100 == CDBHelper::getCount('select * from items', 100));
		$this->assertTrue(1 == CDBHelper::getCount('select * from items', '1'));
	}
}
