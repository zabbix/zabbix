<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class testPageMaps extends CWebTest {
	// Returns all maps
	public static function allMaps() {
		return DBdata('select * from sysmaps');
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_CheckLayout($map) {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');

		$this->zbxTestTextPresent('Maps');

		$this->zbxTestTextPresent('CONFIGURATION OF NETWORK MAPS');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');
		$this->zbxTestTextPresent(array('Name', 'Width', 'Height', 'Edit'));
		$this->zbxTestTextPresent(array($map['name'], $map['width'], $map['height']));
		$this->zbxTestDropdownHasOptions('action', array('Delete selected', 'Export selected'));
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleEdit($map) {
		$name = $map['name'];
		$sysmapid = $map['sysmapid'];

		$this->chooseOkOnNextConfirmation();

		$sqlMap = "select * from sysmaps where name='$name' order by sysmapid";
		$oldHashMap = DBhash($sqlMap);
		$sqlElements = "select * from sysmaps_elements where sysmapid=$sysmapid order by selementid";
		$oldHashElements = DBhash($sqlElements);
		$sqlLinks = "select * from sysmaps_links where sysmapid=$sysmapid order by linkid";
		$oldHashLinks = DBhash($sqlLinks);
		$sqlLinkTriggers = "SELECT slt.* FROM sysmaps_link_triggers slt, sysmaps_links sl WHERE slt.linkid = sl.linkid AND sl.sysmapid=$sysmapid ORDER BY slt.linktriggerid";
		$oldHashLinkTriggers = DBhash($sqlLinkTriggers);

		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestClickWait('link='.$name);

//		$this->waitForCondition("selenium.browserbot.getUserWindow().jQuery('img[name=sysmap]').attr('src') != 'images/general/tree/zero.gif'", 5000);

		$this->zbxTestClickWait('sysmap_update');

//		$this->waitForCondition("selenium.browserbot.getUserWindow().ZBX_SYSMAPS[0].map.saved == true", 3000);

		$txt = $this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('Configuration of network maps');

		$this->assertEquals($oldHashMap, DBhash($sqlMap), "Chuck Norris: Map update changed data in table 'sysmaps'");
		$this->assertEquals($oldHashElements, DBhash($sqlElements), "Chuck Norris: Map update changed data in table 'sysmaps_elements'");
		$this->assertEquals($oldHashLinks, DBhash($sqlLinks), "Chuck Norris: Map update changed data in table 'sysmaps_links'");
		$this->assertEquals($oldHashLinkTriggers, DBhash($sqlLinkTriggers), "Chuck Norris: Map update changed data in table 'sysmaps_link_triggers'");
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleUpdate($map) {
		$name = $map['name'];
		$sysmapid = $map['sysmapid'];

		$this->chooseOkOnNextConfirmation();

		$sqlMap = "select * from sysmaps where name='$name' order by sysmapid";
		$oldHashMap = DBhash($sqlMap);
		$sqlElements = "select * from sysmaps_elements where sysmapid=$sysmapid order by selementid";
		$oldHashElements = DBhash($sqlElements);
		$sqlLinks = "select * from sysmaps_links where sysmapid=$sysmapid order by linkid";
		$oldHashLinks = DBhash($sqlLinks);
		$sqlLinkTriggers = "select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$oldHashLinkTriggers = DBhash($sqlLinkTriggers);

		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->href_click("sysmaps.php?form=update&sysmapid=$sysmapid#form&sid=");
		$this->wait();
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestTextPresent("Network map updated");
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('Configuration of network maps');

		$this->assertEquals($oldHashMap, DBhash($sqlMap), "Chuck Norris: Map update changed data in table 'sysmaps'");
		$this->assertEquals($oldHashElements, DBhash($sqlElements), "Chuck Norris: Map update changed data in table 'sysmaps_elements'");
		$this->assertEquals($oldHashLinks, DBhash($sqlLinks), "Chuck Norris: Map update changed data in table 'sysmaps_links'");
		$this->assertEquals($oldHashLinkTriggers, DBhash($sqlLinkTriggers), "Chuck Norris: Map update changed data in table 'sysmaps_link_triggers'");
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_MassDelete($map) {
		$sysmapid = $map['sysmapid'];

		DBsave_tables('sysmaps');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestCheckboxSelect('maps['.$sysmapid.']');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestTextPresent('Network map deleted');

		$sql = "select * from sysmaps where sysmapid=$sysmapid";
		$this->assertEquals(0, DBcount($sql), 'Data from sysmaps table was not deleted');
		$sql = "select * from sysmaps_elements where sysmapid=$sysmapid";
		$this->assertEquals(0, DBcount($sql), 'Data from sysmaps_elements table was not deleted');
		$sql = "select * from sysmaps_links where sysmapid=$sysmapid";
		$this->assertEquals(0, DBcount($sql), 'Data from sysmaps_links table was not deleted');
		$sql = "select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$this->assertEquals(0, DBcount($sql), 'Data from sysmaps_link_triggers table was not deleted');
		$sql = "select * from screens_items where resourcetype=".SCREEN_RESOURCE_MAP." and resourceid=$sysmapid;";
		$this->assertEquals(0, DBcount($sql), 'Data from screens_items table was not deleted');

		DBrestore_tables('sysmaps');
	}

	public function testPageMaps_Create() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent('Map');
		$this->zbxTestClickWait('cancel');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestTextPresent('Configuration of network maps');
	}

}
