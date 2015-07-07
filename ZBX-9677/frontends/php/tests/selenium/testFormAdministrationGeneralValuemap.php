<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testFormAdministrationGeneralValuemap extends CWebTest {
		private $valuemap1 = '1valuemap1';
		private $valuemap2 = '2valuemap2';
		private $valuemapWithMultipleMappings = '1valuemapWithMultipleMappings1';

	public function testFormAdministrationGeneralValuemap_Layout() {

		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Value mapping');
		$this->checkTitle('Configuration of value mapping');
		$this->zbxTestTextPresent('CONFIGURATION OF VALUE MAPPING');
		$this->zbxTestTextPresent('Value mapping');
		$this->assertElementPresent('form');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent('Name');
		$this->assertElementPresent('mapname');
		$this->assertAttribute("//input[@id='mapname']/@maxlength", '64');
		$this->assertAttribute("//input[@id='mapname']/@size", '40');

		$this->zbxTestTextPresent(array('Mappings', 'Value', 'Mapped to'));
		$this->assertElementPresent('mappings[0][value]');
		$this->assertAttribute("//table[@id='mappingsTable']//input[@name='mappings[0][value]']/@maxlength", 64);
		$this->assertAttribute("//table[@id='mappingsTable']//input[@name='mappings[0][value]']/@size", 20);

		$this->assertElementPresent('mappings[0][newvalue]');
		$this->assertAttribute("//table[@id='mappingsTable']//input[@name='mappings[0][newvalue]']/@maxlength", 64);
		$this->assertAttribute("//table[@id='mappingsTable']//input[@name='mappings[0][newvalue]']/@size", 30);

		$this->assertElementPresent('addMapping');

	}

	public static function dataCreate() {

		return array(
			array('1valuemap1', '1', 'one'),
			array('2valuemap2', '2', 'two')
		);
	}

	public static function dataUpdate() {

		return array(
			array('1valuemap1', '1valuemap_updated'),
			array('2valuemap2', '2valuemap_updated')
		);
	}

	/**
	* @dataProvider dataCreate
	*/
	public function testFormAdministrationGeneralValuemap_AddValueMap($mapname, $value, $newvalue) {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent(array('Name', 'Mappings', 'Value', 'Mapped to'));

		$this->assertElementPresent('addMapping');
		$this->assertElementPresent('save');
		$this->assertElementPresent('cancel');

		$this->input_type('mapname', $mapname);

		$this->input_type('mappings[0][value]', $value);
		$this->input_type('mappings[0][newvalue]', $newvalue);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Value map added');
		$this->zbxTestTextPresent('CONFIGURATION OF VALUE MAPPING');
		$this->zbxTestTextPresent('Value mapping');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Value map');

		// checking that valuemap with such name has been created in the DB
		$sqlValuemap = 'SELECT * FROM valuemaps WHERE name ='.zbx_dbstr($mapname);
		$this->assertEquals(1, DBcount($sqlValuemap), 'Chuck Norris: Value map with such name has not been created in the DB');
		$valuemap = DBfetch(DBselect($sqlValuemap));

		// checking that mappings for this valuemap has been created in the DB
		$sqlMappingid = 'SELECT mappingid FROM mappings WHERE valuemapid=\''.$valuemap['valuemapid'].'\'';
		$result2 = DBcount($sqlMappingid);

		$sqlMappings = 'SELECT count(mappingid) FROM mappings WHERE valuemapid=\''.$valuemap['valuemapid'].'\'';
		$mappings_amount = DBcount($sqlMappings);
		$this->assertEquals($result2, $mappings_amount, 'Chuck Norris: Incorrect amount of mappings for this value map"');

	}

	public function testFormAdministrationGeneralValuemap_AddValueMapWithMultipleMappings() {

		$value1 = '1';
		$newvalue1 = 'one';
		$value2 = '2';
		$newvalue2 = 'two';
		$value3 = '3';
		$newvalue3 = 'three';

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent(array('Name', 'Mappings', 'Value', 'Mapped to'));

		$this->input_type('mapname', $this->valuemapWithMultipleMappings);
		$this->input_type('mappings[0][value]', $value1);
		$this->input_type('mappings[0][newvalue]', $newvalue1);
		$this->zbxTestClick('addMapping');
		$this->input_type('mappings[1][value]', $value2);
		$this->input_type('mappings[1][newvalue]', $newvalue2);
		$this->zbxTestClick('addMapping');

		$this->input_type('mappings[2][value]', $value3);
		$this->input_type('mappings[2][newvalue]', $newvalue3);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Value map added');
		$this->zbxTestTextPresent('CONFIGURATION OF VALUE MAPPING');
		$this->zbxTestTextPresent('Value mapping');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Value map');
	}

	/**
	* @dataProvider dataUpdate
	*/
	public function testFormAdministrationGeneralValuemap_UpdateValueMap($oldVmName, $newVmName) {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('link='.$oldVmName);
		$this->input_type("mapname", $newVmName);
		$this->zbxTestClickWait('save');

		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$newVmName.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value map name has not been updated in the DB');
	}

	public function testFormAdministrationGeneralValuemap_IncorrectValueMap() {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent(array('Name', 'Mappings', 'Value', 'Mapped to'));

		$this->input_type('mapname', 'incorrect_valuemap');
		// trying to create already existing valuemap
		$this->input_type('mapname', $this->valuemapWithMultipleMappings);
		$this->zbxTestClick('addMapping');
		$this->input_type('mappings[0][value]', 6);
		$this->input_type('mappings[0][newvalue]', 'six');
		$this->zbxTestClick('addMapping');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Cannot add value map', 'Value map', 'already exists.'));
	}

	/**
	* @dataProvider dataUpdate
	*/
	public function testFormAdministrationGeneralValuemap_DeleteValueMap($oldVmName, $newVmName) {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('link='.$newVmName);
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Value map deleted');

		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$newVmName.'\'';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Value map with such name has not been deleted from the DB');

	}

	public function testFormAdministrationGeneralValuemap_CancelDeleteValueMap() {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('link='.$this->valuemapWithMultipleMappings);
		$this->zbxTestClick('cancel');

		// checking that valuemap was not deleted after clicking on the "Cancel" button in the confirm dialog box
		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$this->valuemapWithMultipleMappings.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value map with such name has been deleted from the DB');
	}

	public function testFormAdministrationGeneralValuemap_DeleteRemainingValueMaps() {

		// finally deleting remaining value maps
		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickWait('link='.$this->valuemapWithMultipleMappings);
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClick('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->zbxTestTextPresent('Value map deleted');

		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$this->valuemapWithMultipleMappings.'\'';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Value map with such name has not been deleted from the DB');

	}
}
