<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once(dirname(__FILE__) . '/../include/class.cwebtest.php');

class testPageAdministrationGeneralValuemap extends CWebTest {

	public static function allValuemaps(){
		return DBdata('select * from valuemaps');
	}

	/**
	* @dataProvider allValuemaps
	*/
	public function testPageAdministrationGeneralValuemap_CheckLayout($valuemap) {

		$this->login('config.php');
		$this->dropdown_select_wait('configDropDown', 'Value mapping');
		$this->assertTitle('Configuration of Zabbix');
		$this->ok('CONFIGURATION OF ZABBIX');
		$this->ok('Value mapping');
		$this->ok(array('Name', 'Value map'));
		$this->assertElementPresent('form');
		// checking that all valuemaps are present in the report
		$this->ok(array($valuemap['name']));

		// checking that in the "Value map" column are correct values
		// $sql1 = "SELECT m.value, m.newvalue FROM mappings m, valuemaps vm WHERE m.valuemapid=vm.valuemapid";
		$sqlMappings = 'SELECT m.value FROM mappings m,valuemaps vm WHERE m.valuemapid='.$valuemap['valuemapid'];
		$result1 = DBfetch(DBselect($sqlMappings));
		$value = $result1['value'];

		$sqlValuemaps = 'SELECT m.newvalue FROM mappings m,valuemaps vm WHERE m.valuemapid='.$valuemap['valuemapid'];
		$result2 = DBfetch(DBselect($sqlValuemaps));
		$new_value = $result2['newvalue'];

		$this->ok("$value ? $new_value");

	}

	/**
	* @dataProvider allValuemaps
	*/
	public function testPageAdministrationGeneralValuemap_SimpleUpdate($valuemap) {

		$sqlValuemaps = 'select * from valuemaps order by valuemapid';
		$oldHashValuemap=DBhash($sqlValuemaps);

		$sqlMappings = 'select * from mappings order by mappingid';
		$oldHashMappings=DBhash($sqlMappings);

		$this->login('config.php');
		$this->dropdown_select_wait('configDropDown', 'Value mapping');
		$this->ok('Value mapping');
		// checking that can click on each valuemap and then save it without any changes
		$this->click('link='.$valuemap['name']);
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->ok('Value map updated');

		$newHashValuemap = DBhash($sqlValuemaps);
		$this->assertEquals($oldHashValuemap, $newHashValuemap, "Chuck Norris: no-change valuemap update should not update data in table 'valuemaps'");

		$newHashMappings = DBhash($sqlMappings);
		$this->assertEquals($oldHashMappings, $newHashMappings, "Chuck Norris: no-change valuemap update should not update data in table 'mappings'");
	}
}
?>