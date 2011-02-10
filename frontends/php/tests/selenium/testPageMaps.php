<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

class testPageMaps extends CWebTest
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
		$name=$map['name'];
		$sysmapid=$map['sysmapid'];

		$this->chooseOkOnNextConfirmation();

		$sql1="select * from sysmaps where name='$name' order by sysmapid";
		$oldHashMap=DBhash($sql1);
		$sql2="select * from sysmaps_elements where sysmapid=$sysmapid order by selementid";
		$oldHashElements=DBhash($sql2);
		$sql3="select * from sysmaps_links where sysmapid=$sysmapid order by linkid";
		$oldHashLinks=DBhash($sql3);
		$sql4="select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$oldHashLinkTriggers=DBhash($sql4);

		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('sysmap_save');
// TODO There must be a better solution
		sleep(2);
		$this->getConfirmation();

		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok("$name");
		$this->ok('Configuration of network maps');

		$this->assertEquals($oldHashMap,DBhash($sql1),"Chuck Norris: Map update changed data in table 'sysmaps'");
		$this->assertEquals($oldHashElements,DBhash($sql2),"Chuck Norris: Map update changed data in table 'sysmaps_elements'");
		$this->assertEquals($oldHashLinks,DBhash($sql3),"Chuck Norris: Map update changed data in table 'sysmaps_links'");
		$this->assertEquals($oldHashLinkTriggers,DBhash($sql4),"Chuck Norris: Map update changed data in table 'sysmaps_link_triggers'");
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleUpdate($map)
	{
		$name=$map['name'];
		$sysmapid=$map['sysmapid'];

		$this->chooseOkOnNextConfirmation();

		$sql1="select * from sysmaps where name='$name' order by sysmapid";
		$oldHashMap=DBhash($sql1);
		$sql2="select * from sysmaps_elements where sysmapid=$sysmapid order by selementid";
		$oldHashElements=DBhash($sql2);
		$sql3="select * from sysmaps_links where sysmapid=$sysmapid order by linkid";
		$oldHashLinks=DBhash($sql3);
		$sql4="select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$oldHashLinkTriggers=DBhash($sql4);

		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');
		$this->href_click("sysmaps.php?form=update&sysmapid=$sysmapid#form&sid=");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok("Network map updated");
		$this->ok("$name");
		$this->ok('Configuration of network maps');

		$this->assertEquals($oldHashMap,DBhash($sql1),"Chuck Norris: Map update changed data in table 'sysmaps'");
		$this->assertEquals($oldHashElements,DBhash($sql2),"Chuck Norris: Map update changed data in table 'sysmaps_elements'");
		$this->assertEquals($oldHashLinks,DBhash($sql3),"Chuck Norris: Map update changed data in table 'sysmaps_links'");
		$this->assertEquals($oldHashLinkTriggers,DBhash($sql4),"Chuck Norris: Map update changed data in table 'sysmaps_link_triggers'");
	}

	public function testPageMaps_MassDeleteAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_MassDelete($map)
	{
		$sysmapid=$map['sysmapid'];

		DBsave_tables(array('sysmaps','sysmaps_elements','sysmaps_links','sysmaps_link_triggers','screens_items'));

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
		$this->assertEquals(0,DBcount($sql),'Data from sysmaps table was not deleted');
		$sql="select * from sysmaps_elements where sysmapid=$sysmapid";
		$this->assertEquals(0,DBcount($sql),'Data from sysmaps_elements table was not deleted');
		$sql="select * from sysmaps_links where sysmapid=$sysmapid";
		$this->assertEquals(0,DBcount($sql),'Data from sysmaps_links table was not deleted');
		$sql="select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$this->assertEquals(0,DBcount($sql),'Data from sysmaps_link_triggers table was not deleted');
		$sql="select * from screens_items where resourcetype=".SCREEN_RESOURCE_MAP." and resourceid=$sysmapid;";
		$this->assertEquals(0,DBcount($sql),'Data from screens_items table was not deleted');

		DBsave_tables(array('sysmaps','sysmaps_elements','sysmaps_links','sysmaps_link_triggers','screens_items'));
	}

	public function testPageMaps_MassExportAll()
	{
// TODO
		$this->markTestIncomplete();
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
		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');
		$this->button_click('form');
		$this->wait();
		$this->ok('Map');
		$this->button_click('cancel');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('Configuration of network maps');
	}

	public function testPageMaps_Import()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageMaps_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
