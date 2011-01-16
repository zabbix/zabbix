<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../include/class.czabbixtest.php');

class db_test extends CZabbixTest
{
	public function test_DBconnect()
	{
		DBclose();
		return DBconnect($error);
	}

	public function test_DBconnectIfAlreadyConnected()
	{
		DBclose();
		DBconnect($error);
		$this->assertFalse(DBconnect($error),"Chuck Norris: DBconnect() must return False if the database is already opened");
	}

	public function test_DBclose()
	{
		DBconnect($error);
		return DBclose();
	}

	public function test_DBcloseOfClosedDatabase()
	{
		DBconnect($error);
		DBclose();
		$this->assertFalse(DBclose(),"Chuck Norris: DBclose() must return False if the datbase is already closed");
	}

	public function test_DBloadfile()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBstart()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBend()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBcommit()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBrollback()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBselect()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBexecute()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBfetch()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBid2nodeid()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBin_node()
	{
		// TODO
		$this->markTestIncomplete();
	}

	public function test_DBcondition()
	{
		// TODO
		$this->markTestIncomplete();
	}
}
?>
