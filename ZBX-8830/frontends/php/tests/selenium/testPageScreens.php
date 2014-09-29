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

class testPageScreens extends CWebTest {
	// Returns all screens
	public static function allScreens() {
		return DBdata("select * from screens where templateid is NULL order by screenid");
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_CheckLayout($screen) {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');

		$this->zbxTestTextPresent('CONFIGURATION OF SCREENS');
		$this->zbxTestTextPresent('Screens');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');
		// Header
		$this->zbxTestTextPresent(array('Name', 'Dimension (cols x rows)', 'Screen'));
		// Data
		$this->zbxTestTextPresent(array($screen['name']));
		$this->zbxTestDropdownHasOptions('action', array('Export selected', 'Delete selected'));
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_SimpleEdit($screen) {
		$screenid = $screen['screenid'];
		$name = $screen['name'];

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('Change');
		$this->zbxTestTextPresent('CONFIGURATION OF SCREEN');
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_SimpleUpdate($screen) {
		$screenid = $screen['screenid'];
		$name = $screen['name'];

		$sqlScreen = "select * from screens where screenid=$screenid order by screenid";
		$oldHashScreen = DBhash($sqlScreen);
		$sqlScreenItems = "select * from screens_items where screenid=$screenid order by screenitemid";
		$oldHashScreenItems = DBhash($sqlScreenItems);

		DBsave_tables('screens');

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->href_click("?form=update&screenid=$screenid&sid=");
		$this->wait();

		$this->zbxTestTextPresent('CONFIGURATION OF SCREENS');
		// $this->zbxTestTextPresent($name);
		$this->zbxTestTextPresent('Screen');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Columns');
		$this->zbxTestTextPresent('Rows');

		$this->zbxTestClickWait('update');

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent('Screen updated');

		$this->assertEquals($oldHashScreen, DBhash($sqlScreen));
		$this->assertEquals($oldHashScreenItems, DBhash($sqlScreenItems));

		DBrestore_tables('screens');
	}

	public function testPageScreens_Create() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestClickWait('form');

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent('Screens');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Columns');
		$this->zbxTestTextPresent('Rows');

		$this->zbxTestClickWait('cancel');

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextNotPresent('Columns');
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_MassDelete($screen) {
		$screenid = $screen['screenid'];
		$name = $screen['name'];

		$this->chooseOkOnNextConfirmation();

		DBsave_tables('screens');

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestCheckboxSelect('screens['.$screenid.']');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent('Screen deleted');
		$this->zbxTestTextPresent('CONFIGURATION OF SCREENS');

		$sql = "select * from screens where screenid=$screenid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from screens_items where screenid=$screenid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from slides where screenid=$screenid";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('screens');
	}

}
