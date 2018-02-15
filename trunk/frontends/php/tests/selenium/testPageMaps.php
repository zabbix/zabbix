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

class testPageMaps extends CWebTest {
	public $mapName = 'Local network';
	public $mapWidth = 680;
	public $mapHeight = 200;

	public static function allMaps() {
		return DBdata('select * from sysmaps');
	}

	public function testPageMaps_CheckLayout() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestCheckHeader('Maps');
		$this->zbxTestCheckFatalErrors();

		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Width']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Height']");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Actions')]");

		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->mapName);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[3]", $this->mapWidth);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[4]", $this->mapHeight);

		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Export'][@disabled]");
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleUpdateConstructor($map) {
		$name = $map['name'];
		$sysmapid = $map['sysmapid'];

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
		$this->zbxTestClickLinkText($name);

		$this->zbxTestClickWait('edit');
		$this->zbxTestCheckHeader('Network maps');
		$this->zbxTestClickWait('sysmap_update');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestTextPresent($name);
		$this->zbxTestCheckHeader('Maps');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($oldHashMap, DBhash($sqlMap));
		$this->assertEquals($oldHashElements, DBhash($sqlElements));
		$this->assertEquals($oldHashLinks, DBhash($sqlLinks));
		$this->assertEquals($oldHashLinkTriggers, DBhash($sqlLinkTriggers));
	}

	/**
	* @dataProvider allMaps
	*/
	public function testPageMaps_SimpleUpdateProperties($map) {
		$name = $map['name'];
		$sysmapid = $map['sysmapid'];

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
		$this->zbxTestClickXpathWait("//a[text()='".$name."']/../..//a[text()='Properties']");
		$this->zbxTestCheckHeader('Network maps');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Network map updated');
		$this->zbxTestTextPresent($name);
		$this->zbxTestTextPresent('Configuration of network maps');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($oldHashMap, DBhash($sqlMap));
		$this->assertEquals($oldHashElements, DBhash($sqlElements));
		$this->assertEquals($oldHashLinks, DBhash($sqlLinks));
		$this->assertEquals($oldHashLinkTriggers, DBhash($sqlLinkTriggers));
	}

	/**
	 * @dataProvider allMaps
	 * @backup sysmaps
	 */
	public function testPageMaps_MassDelete($map) {
		$sysmapid = $map['sysmapid'];

		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestCheckboxSelect('maps_'.$sysmapid);
		$this->zbxTestClickButton('map.massdelete');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good','Network map deleted');
		$this->zbxTestCheckFatalErrors();

		$sql = "select * from sysmaps where sysmapid=$sysmapid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from sysmaps_elements where sysmapid=$sysmapid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from sysmaps_links where sysmapid=$sysmapid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from sysmaps_link_triggers where linkid in (select linkid from sysmaps_links where sysmapid=$sysmapid) order by linktriggerid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from screens_items where resourcetype=".SCREEN_RESOURCE_MAP." and resourceid=$sysmapid;";
		$this->assertEquals(0, DBcount($sql));
	}

	public function testPageMaps_CreateCancel() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Network maps');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestClickWait('cancel');
		$this->zbxTestCheckTitle('Configuration of network maps');
		$this->zbxTestCheckFatalErrors();
	}

	public function testPageMaps_FilterByName() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestInputTypeOverwrite('filter_name', $this->mapName);
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->mapName);
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestTextPresent('Displaying 1 of 1 found');
		$this->zbxTestCheckFatalErrors();
	}

	public function testPageMaps_FilterNone() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestInputTypeOverwrite('filter_name', '1928379128ksdhksdjfh');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');
		$this->zbxTestInputTypeOverwrite('filter_name', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');
		$this->zbxTestCheckFatalErrors();
	}

	public function testPageMaps_FilterReset() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestCheckFatalErrors();
	}
}
