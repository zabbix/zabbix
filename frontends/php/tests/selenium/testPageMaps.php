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
require_once(dirname(__FILE__).'/class.ctest.php');

class testPageMaps extends CTest
{
	// Returns all maps
	public static function allMaps()
	{
		return DBdata('select * from sysmaps');
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleTest($map)
	{
		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');

		$this->ok('Maps');
// TODO
		$this->ok('Configuration of network maps');
//		$this->ok('CONFIGURATION OF NETWORK MAPS');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		$this->ok(array('Name','Width','Height','Edit'));
		$this->ok(array($map['name'],$map['width'],$map['height']));
		$this->dropdown_select('go','Delete selected');
		$this->dropdown_select('go','Export selected');
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleEdit($map)
	{
// TODO
// It is not clear how to confirm dialog after pressing Save button
		$this->markTestIncomplete();
/*
		$name=$map['name'];
		$sysmapid=$map['sysmapid'];

		$this->chooseOkOnNextConfirmation();

		$sql1="select * from sysmaps where name='$name' order by sysmapid";
		$oldHashMap=$this->DBhash($sql1);
		$sql2="select * from sysmaps_elements where sysmapid=$sysmapid order by selementid";
		$oldHashElements=$this->DBhash($sql2);
		$sql3="select * from sysmaps_links where sysmapid=$sysmapid order by linkid";
		$oldHashLinks=$this->DBhash($sql3);
		$sql4="select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$oldHashLinkTriggers=$this->DBhash($sql4);

		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('sysmap_save');
		$this->wait();
		$this->getConfirmation();

		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok("$name");
		$this->ok('Configuration of network maps');

		$this->assertEquals($oldHashMap,$this->DBhash($sql1),"Chuck Norris: Map update changed data in table 'sysmaps'");
		$this->assertEquals($oldHashElements,$this->DBhash($sql2),"Chuck Norris: Map update changed data in table 'sysmaps_elements'");
		$this->assertEquals($oldHashLinks,$this->DBhash($sql3),"Chuck Norris: Map update changed data in table 'sysmaps_links'");
		$this->assertEquals($oldHashLinkTriggers,$this->DBhash($sql4),"Chuck Norris: Map update changed data in table 'sysmaps_link_triggers'");
*/
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_MassDelete($map)
	{
		$sysmapid=$map['sysmapid'];

		$this->DBsave_tables(array('sysmaps','sysmaps_elements','sysmaps_links','sysmaps_link_triggers'));

		$this->chooseOkOnNextConfirmation();

		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');
		$this->checkbox_select("maps[$sysmapid]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->assertTitle('Network maps');
		$this->ok('Network map deleted');

		$sql="select * from sysmaps where sysmapid=$sysmapid";
		$this->assertEquals(0,$this->DBcount($sql));
		$sql="select * from sysmaps_elements where sysmapid=$sysmapid";
		$this->assertEquals(0,$this->DBcount($sql));
		$sql="select * from sysmaps_links where sysmapid=$sysmapid";
		$this->assertEquals(0,$this->DBcount($sql));
		$sql="select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$this->assertEquals(0,$this->DBcount($sql));

		$this->DBsave_tables(array('sysmaps','sysmaps_elements','sysmaps_links','sysmaps_link_triggers'));
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_MassExport($map)
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaps_Create()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaps_Import()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
