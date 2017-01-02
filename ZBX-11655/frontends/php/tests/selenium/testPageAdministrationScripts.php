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

class testPageAdministrationScripts extends CWebTest {

	private $sqlHashScripts = '';
	private $oldHashScripts = '';

	private function calculateHash($scriptid) {
		$this->sqlHashScripts = 'SELECT * FROM scripts WHERE scriptid='.$scriptid;
		$this->oldHashScripts = DBhash($this->sqlHashScripts);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashScripts, DBhash($this->sqlHashScripts));
	}

	public static function allScripts() {
		return DBdata('SELECT scriptid,name FROM scripts');
	}

	public function testPageAdministrationScripts_CheckLayout() {
		$this->zbxTestLogin('scripts.php');
		$this->zbxTestCheckTitle('Configuration of scripts');

		$this->zbxTestTextPresent('CONFIGURATION OF SCRIPTS');
		$this->zbxTestTextPresent('Scripts');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(
				array('Name', 'Type', 'Execute on', 'Commands', 'User group', 'Host group', 'Host access')
		);

		$dbResult = DBselect('SELECT name,command FROM scripts');

		while ($dbRow = DBfetch($dbResult)) {
			$this->zbxTestTextPresent(array($dbRow['name'], $dbRow['command']));
		}

		$this->zbxTestDropdownHasOptions('go', array('Delete selected'));
		$this->assertElementValue('goButton', 'Go (0)');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_SimpleUpdate($script) {
		$this->calculateHash($script['scriptid']);

		$this->zbxTestLogin('scripts.php');
		$this->zbxTestClickWait('link='.$script['name']);
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script updated');
		$this->zbxTestTextPresent($script['name']);

		$this->verifyHash();
	}

	public function testPageAdministrationScripts_MassDeleteAll() {
		DBsave_tables('scripts');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('scripts.php');
		$this->zbxTestCheckboxSelect('all_scripts');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script deleted');

		$this->assertEquals(0, DBcount('SELECT NULL FROM scripts'));

		DBrestore_tables('scripts');
	}

	public function testPageAdministrationScripts_backup() {
		DBsave_tables('scripts');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_MassDelete($script) {
		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('scripts.php');
		$this->zbxTestCheckboxSelect('scripts['.$script['scriptid'].']');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');
		$this->getConfirmation();
		$this->zbxTestCheckTitle('Configuration of scripts');
		$this->zbxTestTextPresent('Script deleted');

		$this->assertEquals(0, DBcount('SELECT NULL FROM scripts WHERE scriptid='.zbx_dbstr($script['scriptid'])));
	}

	public function testPageAdministrationScripts_restore() {
		DBsave_tables('scripts');
	}

}
