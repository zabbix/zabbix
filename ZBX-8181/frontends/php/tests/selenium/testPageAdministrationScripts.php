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

class testPageAdministrationScripts extends CWebTest {
	// Returns all scripts
	public static function allScripts() {
		return DBdata('SELECT scriptid,name,command FROM scripts');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_CheckLayout($script) {
		$this->zbxTestLogin('scripts.php');
		$this->checkTitle('Configuration of scripts');
		$this->zbxTestTextPresent('CONFIGURATION OF SCRIPTS');
		$this->zbxTestTextPresent('Scripts');
		$this->zbxTestTextPresent('Displaying');

		// header
		$this->zbxTestTextPresent(
				array('Name', 'Type', 'Execute on', 'Commands', 'User group', 'Host group', 'Host access')
		);

		// data
		$this->zbxTestTextPresent(array($script['name'], $script['command']));
		$this->zbxTestDropdownSelect('go', 'Delete selected');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_SimpleUpdate($script) {
		$sql = 'SELECT * FROM scripts WHERE name='.zbx_dbstr($script['name']).' ORDER BY scriptid';
		$oldHash = DBhash($sql);

		$this->zbxTestLogin('scripts.php');

		$this->zbxTestClickWait('link='.$script['name']);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script updated');
		$this->zbxTestTextPresent('CONFIGURATION OF SCRIPTS');

		$this->assertEquals($oldHash, DBhash($sql),
				'Chuck Norris: Non-change update should not change contents of the "scripts" table');
	}

	public function testPageAdministrationScripts_MassDeleteAll() {
		DBsave_tables('scripts');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('scripts.php');

		$this->zbxTestCheckboxSelect('all_scripts');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script deleted');

		$sql = 'SELECT * FROM scripts';
		$this->assertEquals(0, DBcount($sql),
				'Chuck Norris: Not all scripts have been deleted from the "scripts" table');

		DBrestore_tables('scripts');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_MassDelete($script) {
		DBsave_tables('scripts');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('scripts.php');

		$this->zbxTestCheckboxSelect('scripts['.$script['scriptid'].']');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script deleted');

		$sql = 'SELECT * FROM scripts WHERE scriptid='.zbx_dbstr($script['scriptid']);
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('scripts');
	}

	public function testPageAdministrationScripts_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
