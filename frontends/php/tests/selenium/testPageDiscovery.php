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
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageDiscovery extends CWebTest
{
	// Returns all discovery rules
	public static function allRules()
	{
		return DBdata('select * from drules');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_SimpleTest($rule)
	{
		$this->login('discoveryconf.php');
		$this->assertTitle('Configuration of discovery');

		$this->ok('DISCOVERY');
		$this->ok('CONFIGURATION OF DISCOVERY');
//		$this->ok('Displaying');
		$this->ok(array('Name','IP range','Delay','Checks','Status'));
		$this->ok($rule['name']);
		$this->ok($rule['iprange']);
		$this->ok($rule['delay']);
		$this->dropdown_select('go','Enable selected');
		$this->dropdown_select('go','Disable selected');
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_SimpleUpdate($rule)
	{
		$name=$rule['name'];
		$druleid=$rule['druleid'];

		$sql1="select * from drules where name='$name' order by druleid";
		$oldHashRules=DBhash($sql1);
		$sql2="select * from dchecks where druleid=$druleid order by dcheckid";
		$oldHashChecks=DBhash($sql2);

		$this->login('discoveryconf.php');
		$this->assertTitle('Configuration of discovery');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Configuration of discovery');
		$this->ok('Discovery rule updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF DISCOVERY');

		$this->assertEquals($oldHashRules,DBhash($sql1));
		$this->assertEquals($oldHashChecks,DBhash($sql2));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassDelete($rule)
	{
		$druleid=$rule['druleid'];

		DBsave_tables(array('drules','dchecks'));

		$this->chooseOkOnNextConfirmation();

		$this->login('discoveryconf.php');
		$this->assertTitle('Configuration of discovery');
		$this->checkbox_select("g_druleid[$druleid]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->assertTitle('Configuration of discovery');
		$this->ok('Discovery rules deleted');

		$sql="select * from drules where druleid=$druleid";
		$this->assertEquals(0,DBcount($sql));
		$sql="select * from dchecks where druleid=$druleid";
		$this->assertEquals(0,DBcount($sql));

		DBrestore_tables(array('drules','dchecks'));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_ChangeStatus($rule)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageDiscovery_MassEnableAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassEnable($rule)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageDiscovery_MassDisableAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassDisable($rule)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageDiscovery_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
