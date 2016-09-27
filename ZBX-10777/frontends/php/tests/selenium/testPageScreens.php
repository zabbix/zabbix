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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageScreens extends CWebTest {

	public static function allScreens() {
		return DBdata('SELECT screenid,name FROM screens WHERE templateid IS NULL ORDER BY screenid');
	}

	public function testPageScreens_CheckLayout() {
		$screens = DBfetchArray(DBSelect('SELECT name FROM screens WHERE templateid IS NULL'));

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');

		$this->zbxTestCheckHeader('Screens');
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(sprintf('Displaying %1$s of %1$s found', count($screens)));
		$this->zbxTestDropdownAssertSelected('config', 'Screens');

		$this->zbxTestTextPresent(['Name', 'Dimension (cols x rows)', 'Actions']);

		foreach ($screens as $screen) {
			$this->zbxTestTextPresent($screen['name']);
		}
		$this->zbxTestTextPresent(['Export', 'Delete']);
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_SimpleEdit($screen) {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestClickLinkText($screen['name']);
		$this->zbxTestCheckTitle('Custom screens [refreshed every 30 sec.]');
		$this->zbxTestTextPresent($screen['name']);
		$this->zbxTestTextPresent('Edit screen');
		$this->zbxTestCheckHeader('Screens');
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_SimpleUpdate($screen) {
		$sqlScreen = 'SELECT * FROM screens WHERE screenid='.$screen['screenid'];
		$oldHashScreen = DBhash($sqlScreen);
		$sqlScreenItems = 'SELECT * FROM screens_items WHERE screenid='.$screen['screenid'].' ORDER BY screenitemid';
		$oldHashScreenItems = DBhash($sqlScreenItems);

		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestHrefClickWait('?form=update&screenid='.$screen['screenid']);

		$this->zbxTestCheckHeader('Screens');
		$this->zbxTestTextPresent(['Screen','Sharing']);
		$this->zbxTestTextPresent(['Owner', 'Name', 'Columns', 'Rows']);

		$this->zbxTestClickWait('update');

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent('Screen updated');

		$this->assertEquals($oldHashScreen, DBhash($sqlScreen));
		$this->assertEquals($oldHashScreenItems, DBhash($sqlScreenItems));
	}

	public function testPageScreens_Create() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestClickWait('form');

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent(['Owner', 'Name', 'Columns', 'Rows']);

		$this->zbxTestClickWait('cancel');

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextNotPresent(['Owner', 'Columns', 'Rows']);
	}

	public function testPageScreens_backup() {
		DBsave_tables('screens');
	}

	/**
	* @dataProvider allScreens
	*/
	public function testPageScreens_MassDelete($screen) {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestCheckboxSelect('screens_'.$screen['screenid']);
		$this->zbxTestClickButton('screen.massdelete');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of screens');
		$this->zbxTestTextPresent('Screen deleted');
		$this->zbxTestCheckHeader('Screens');

		$sql = 'SELECT NULL FROM screens WHERE screenid='.$screen['screenid'];
		$this->assertEquals(0, DBcount($sql));
		$sql = 'SELECT NULL FROM screens_items WHERE screenid='.$screen['screenid'];
		$this->assertEquals(0, DBcount($sql));
		$sql = 'SELECT NULL FROM slides WHERE screenid='.$screen['screenid'];
		$this->assertEquals(0, DBcount($sql));
	}

	public function testPageScreens_restore() {
		DBrestore_tables('screens');
	}
}
