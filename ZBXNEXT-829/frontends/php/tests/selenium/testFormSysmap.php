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

class testFormSysmap extends CWebTest {

	public $mapName = "Test map 1";

	/**
	 * Returns all possible map name variants
	 * used as a data provider
	 * @static
	 * @return array
	 */
	public static function possibleMapNames() {
		return array(
			//string map name
			//bool   operation with this name should be successful
			array('My map name', true),
			array('Another_map_name', true),
			array('1 starts with a number', true),
			array('!@#$%^&*()', true),
			array('0', true),

			array('', false), // map with empty name
			array('0123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789', false),
		);
	}


	public function testFormSysmapOpen() {
		$this->login('sysmaps.php');
		$this->checkTitle('Network maps');
	}


	/**
	 * Test creation of map
	 * @dataProvider possibleMapNames
	 */
	public function testFormSysmapCreate($mapName, $successExpected) {

		DBsave_tables('sysmaps');

		$this->login('sysmaps.php');
		$this->button_click('form');
		$this->wait();
		$this->input_type('name', $mapName);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Network maps');
		if ($successExpected) {
			$this->ok('Network map added');
			$this->ok($mapName);

		}
		else {
			$this->ok('ERROR');
		}

		DBrestore_tables('sysmaps');
	}


	public function testFormSysmapSimpleUpdate() {
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Network maps');
		$this->ok('Network map updated');
		$this->ok($this->mapName);
	}


	/**
	 * Test map update
	 * @dataProvider possibleMapNames
	 */
	public function testFormSysmapUpdateMapName($mapName, $successExpected) {
		// Update Map
		DBsave_tables('sysmaps');

		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();

		$this->input_type('name', $mapName);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Network maps');

		if ($successExpected) {
			$this->ok('Network map updated');
			$this->ok($mapName);
		}
		else {
			$this->ok('ERROR');
		}

		DBrestore_tables('sysmaps');
	}


	public function testFormSysmapDelete() {

		DBsave_tables('sysmaps');

		$this->chooseOkOnNextConfirmation();
		// Delete Map
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->checkTitle('Network maps');
		$this->ok('Network map deleted');

		DBrestore_tables('sysmaps');
	}


	public function testFormSysmapCloneMap() {

		$mapName = 'Cloned map';

		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('clone');
		$this->input_type('name', $mapName);
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Network maps');
		$this->ok('Network map added');
		$this->ok($mapName);
		return $mapName;

	}


	/**
	 * @depends testFormSysmapCloneMap
	 */
	public function testFormSysmapDeleteClonedMap($mapName = 'Cloned map') {
		// Delete Map if it was created
		$this->open('sysmaps.php');
		$this->chooseOkOnNextConfirmation();
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->checkTitle('Network maps');
		$this->ok('Network map deleted');
	}

}
?>
