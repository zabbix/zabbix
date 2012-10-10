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
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageAdministrationScripts extends CWebTest {
	// Returns all scripts
	public static function allScripts() {
		return DBdata('SELECT * FROM scripts');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_CheckLayout($script) {
		$this->login('scripts.php');
		$this->checkTitle('Configuration of scripts');

		$this->ok('Scripts');
		$this->ok('CONFIGURATION OF SCRIPTS');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name', 'Command', 'User group', 'Host group', 'Host access'));
		// Data
		$this->ok(array($script['name'], $script['command'], 'Read'));
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_SimpleUpdate($script) {
		$name = $script['name'];

		$sql = 'SELECT * FROM scripts WHERE name = '.zbx_dbstr($name).' ORDER BY scriptid';
		$oldHash = DBhash($sql);

		$this->login('scripts.php');
		$this->checkTitle('Configuration of scripts');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of scripts');
		$this->ok('Script updated');
		$this->ok($name);
		$this->ok('CONFIGURATION OF SCRIPTS');

		$this->assertEquals($oldHash, DBhash($sql), 'Chuck Norris: Non-change update should not change contents of the "scripts" table');
	}

	public function testPageAdministrationScripts_MassDeleteAll() {

		DBsave_tables('scripts');
		$this->chooseOkOnNextConfirmation();

		$this->login('scripts.php');
		$this->checkTitle('Configuration of scripts');
		$this->checkbox_select("all_scripts");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of scripts');
		$this->ok('Script deleted');

		$sql = 'SELECT * FROM scripts';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Not all scripts have been deleted from the "scripts" table');

		DBrestore_tables('scripts');
	}

	/**
	* @dataProvider allScripts
	*/
	public function testPageAdministrationScripts_MassDelete($script) {
		$scriptid = $script['scriptid'];

		DBsave_tables('scripts');
		$this->chooseOkOnNextConfirmation();

		$this->login('scripts.php');
		$this->checkTitle('Configuration of scripts');
		$this->checkbox_select("scripts[$scriptid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of scripts');
		$this->ok('Script deleted');

		$sql = 'SELECT * FROM scripts WHERE scriptid='.zbx_dbstr($scriptid).'';
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('scripts');
	}

	public function testPageAdministrationScripts_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
