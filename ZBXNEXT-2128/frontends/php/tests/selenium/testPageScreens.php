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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageScreens extends CWebTest {

	public static function allScreens() {
		return DBdata('SELECT screenid,name FROM screens WHERE templateid IS NULL ORDER BY screenid');
	}

	public function testPageScreens_CheckLayout() {
		$screens = DBfetchArray(DBSelect('SELECT name FROM screens WHERE templateid IS NULL'));

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');

		$this->zbxTestTextPresent('CONFIGURATION OF SCREENS');
		$this->zbxTestTextPresent('Screens');
		$this->zbxTestTextPresent(sprintf('Displaying 1 to %1$s of %1$s found', count($screens)));
		// header
		$this->zbxTestTextPresent(['Name', 'Dimension (cols x rows)', 'Screen']);
		// data
		foreach ($screens as $screen) {
			$this->zbxTestTextPresent($screen['name']);
		}
		$this->zbxTestDropdownHasOptions('action', ['Export selected', 'Delete selected']);
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_SimpleEdit($screen) {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestClickWait('link='.$screen['name']);
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent($screen['name']);
		$this->zbxTestTextPresent('Change');
		$this->zbxTestTextPresent('CONFIGURATION OF SCREEN');
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_SimpleUpdate($screen) {
		DBsave_tables('screens');

		$sqlScreen = 'SELECT * FROM screens WHERE screenid='.$screen['screenid'];
		$oldHashScreen = DBhash($sqlScreen);
		$sqlScreenItems = 'SELECT * FROM screens_items WHERE screenid='.$screen['screenid'].' ORDER BY screenitemid';
		$oldHashScreenItems = DBhash($sqlScreenItems);

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestHrefClickWait('?form=update&screenid='.$screen['screenid']);

		$this->zbxTestTextPresent('CONFIGURATION OF SCREENS');
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
		DBsave_tables('screens');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestCheckboxSelect('screens['.$screen['screenid'].']');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent('Screen deleted');
		$this->zbxTestTextPresent('CONFIGURATION OF SCREENS');

		$sql = 'SELECT NULL FROM screens WHERE screenid='.$screen['screenid'];
		$this->assertEquals(0, DBcount($sql));
		$sql = 'SELECT NULL FROM screens_items WHERE screenid='.$screen['screenid'];
		$this->assertEquals(0, DBcount($sql));
		$sql = 'SELECT NULL FROM slides WHERE screenid='.$screen['screenid'];
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('screens');
	}

}
