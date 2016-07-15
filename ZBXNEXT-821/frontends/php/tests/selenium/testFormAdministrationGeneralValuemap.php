<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		private $valuemapWithMultipleMappings = '1valuemapWithMultipleMappings1';

	public function testFormAdministrationGeneralValuemap_Layout() {

		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Value mapping');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", "maxlength", 64);
		$this->zbxTestAssertAttribute("//input[@id='name']", "size", 20);

		$this->zbxTestTextPresent(['Mappings', 'Value', 'Mapped to']);
		$this->zbxTestAssertElementPresentId('mappings_0_value');
		$this->zbxTestAssertAttribute("//table[@id='mappings_table']//input[@name='mappings[0][value]']", "maxlength", 64);
		$this->zbxTestAssertAttribute("//table[@id='mappings_table']//input[@name='mappings[0][value]']", "size", 20);

		$this->zbxTestAssertElementPresentId('mappings_0_newvalue');
		$this->zbxTestAssertAttribute("//table[@id='mappings_table']//input[@name='mappings[0][newvalue]']", "maxlength", 64);
		$this->zbxTestAssertAttribute("//table[@id='mappings_table']//input[@name='mappings[0][newvalue]']", "size", 20);

		$this->zbxTestAssertElementPresentId('mapping_add');

	}

	public static function dataCreate() {

		return [
			['1valuemap1', '1', 'one'],
			['2valuemap2', '2', 'two']
		];
	}

	public static function dataUpdate() {

		return [
			['1valuemap1', '1valuemap_updated'],
			['2valuemap2', '2valuemap_updated']
		];
	}

	/**
	* @dataProvider dataCreate
	*/
	public function testFormAdministrationGeneralValuemap_AddValueMap($mapname, $value, $newvalue) {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent(['Name', 'Mappings', 'Value', 'Mapped to']);

		$this->zbxTestAssertElementPresentId('mapping_add');
		$this->zbxTestAssertElementPresentId('add');
		$this->zbxTestAssertElementPresentId('cancel');

		$this->zbxTestInputType('name', $mapname);

		$this->zbxTestInputType('mappings_0_value', $value);
		$this->zbxTestInputType('mappings_0_newvalue', $newvalue);

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Value map added');
		$this->zbxTestCheckHeader('Value mapping');
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
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent(['Name', 'Mappings', 'Value', 'Mapped to']);

		$this->zbxTestInputType('name', $this->valuemapWithMultipleMappings);
		$this->zbxTestInputType('mappings_0_value', $value1);
		$this->zbxTestInputType('mappings_0_newvalue', $newvalue1);
		$this->zbxTestClick('mapping_add');
		$this->zbxTestInputType('mappings_1_value', $value2);
		$this->zbxTestInputType('mappings_1_newvalue', $newvalue2);
		$this->zbxTestClick('mapping_add');

		$this->zbxTestInputType('mappings_2_value', $value3);
		$this->zbxTestInputType('mappings_2_newvalue', $newvalue3);

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Value map added');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Value map');
	}

	/**
	* @dataProvider dataUpdate
	*/
	public function testFormAdministrationGeneralValuemap_UpdateValueMap($oldVmName, $newVmName) {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestClickLinkText($oldVmName);
		$this->zbxTestInputType("name", $newVmName);
		$this->zbxTestClickWait('update');

		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$newVmName.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value map name has not been updated in the DB');
	}

	public function testFormAdministrationGeneralValuemap_IncorrectValueMap() {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestClickWait('form');
		$this->zbxTestTextPresent(['Name', 'Mappings', 'Value', 'Mapped to']);

		$this->zbxTestInputType('name', 'incorrect_valuemap');
		// trying to create already existing valuemap
		$this->zbxTestInputType('name', $this->valuemapWithMultipleMappings);
		$this->zbxTestInputType('mappings_0_value', 6);
		$this->zbxTestInputType('mappings_0_newvalue', 'six');
		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent(['Cannot add value map', 'Value map "'.$this->valuemapWithMultipleMappings.'" already exists.']);
	}

	/**
	* @dataProvider dataUpdate
	*/
	public function testFormAdministrationGeneralValuemap_DeleteValueMap($oldVmName, $newVmName) {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestClickLinkText($newVmName);
		$this->zbxTestClick('delete');
		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Value map deleted');

		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$newVmName.'\'';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Value map with such name has not been deleted from the DB');

	}

	public function testFormAdministrationGeneralValuemap_CancelDeleteValueMap() {

		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestClickLinkText($this->valuemapWithMultipleMappings);
		$this->zbxTestClick('cancel');

		// checking that valuemap was not deleted after clicking on the "Cancel" button in the confirm dialog box
		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$this->valuemapWithMultipleMappings.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value map with such name has been deleted from the DB');
	}

	public function testFormAdministrationGeneralValuemap_DeleteRemainingValueMaps() {

		// finally deleting remaining value maps
		$this->zbxTestLogin('adm.valuemapping.php');
		$this->zbxTestCheckTitle('Configuration of value mapping');
		$this->zbxTestCheckHeader('Value mapping');
		$this->zbxTestClickLinkText($this->valuemapWithMultipleMappings);
		$this->zbxTestClick('delete');
		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Value map deleted');

		$sql = 'SELECT * FROM valuemaps WHERE name=\''.$this->valuemapWithMultipleMappings.'\'';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Value map with such name has not been deleted from the DB');

	}
}
