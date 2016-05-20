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

class testFormMap extends CWebTest {
	/**
	 * Possible combinations of grid settings
	 * @return array
	 */
	public function possibleGridOptions() {
		return [
			// grid size, show grid, auto align
			['20x20', 1, 1],
			['40x40', 1, 1],
			['50x50', 1, 1],
			['75x75', 1, 1],
			['100x100', 1, 1],

			['20x20', 0, 1],
			['40x40', 0, 1],
			['50x50', 0, 1],
			['75x75', 0, 1],
			['100x100', 0, 1],

			['20x20', 0, 0],
			['40x40', 0, 0],
			['50x50', 0, 0],
			['75x75', 0, 0],
			['100x100', 0, 0],

			['20x20', 1, 0],
			['40x40', 1, 0],
			['50x50', 1, 0],
			['75x75', 1, 0],
			['100x100', 1, 0]
		];
	}


	/**
	 * Test setting of grid options for map
	 *
	 * @dataProvider possibleGridOptions
	 */
	public function testFormMap_UpdateGridOptions($gridSize, $showGrid, $autoAlign) {

		$map_name = 'Test map 1';

		// getting map options from DB as they are at the beginning of the test
		$db_result = DBSelect("SELECT * FROM sysmaps WHERE name = '$map_name'");
		$db_map = DBfetch($db_result);
		$this->assertTrue(isset($db_map['sysmapid']), 'Chuck Norris: Could not fetch map with name "'.$map_name.'" from DB. Here is what I got: '.print_r($db_map, true));

		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestClickWait('link='.$map_name);

		// checking if appropriate value for grid size is selected
		$this->assertTrue(
			$this->getSelectedValue('gridsize') == $db_map['grid_size'],
			'Chuck Norris: I was expecting to see '.$db_map['grid_size'].' selected, but saw '.$this->getSelectedValue('gridsize')
		);

		// grid should be shown by default
		if ($db_map['grid_show'] == SYSMAP_GRID_SHOW_ON) {
			$this->zbxTestTextPresent('Shown');
		}
		else {
			$this->zbxTestTextPresent('Hidden');
		}

		// auto align should be on by default
		if ($db_map['grid_align'] == SYSMAP_GRID_ALIGN_ON) {
			$this->zbxTestTextPresent('On');
		}
		else {
			$this->zbxTestTextPresent('Off');
		}

		// selecting new grid size
		$this->zbxTestDropdownSelect('gridsize', $gridSize);
		sleep(1);

		// changing other two options if they are not already set as needed
		if (($db_map['grid_show'] == SYSMAP_GRID_SHOW_ON && $showGrid == 0) || ($db_map['grid_show'] == SYSMAP_GRID_SHOW_OFF && $showGrid == 1)) {
			$this->zbxTestClick('gridshow');
		}
		if (($db_map['grid_align'] == SYSMAP_GRID_ALIGN_ON && $autoAlign == 0) || ($db_map['grid_align'] == SYSMAP_GRID_ALIGN_OFF && $autoAlign == 1)) {
			$this->zbxTestClick('gridautoalign');
		}

		// clicking "Update"
		$this->chooseOkOnNextConfirmation();
		$this->zbxTestClickWait('update');
		$this->getConfirmation();

		// checking if DB was updated as expected
		$db_result = DBSelect("SELECT * FROM sysmaps WHERE name = '$map_name'");
		$db_map = DBfetch($db_result);
		$this->assertTrue(isset($db_map['sysmapid']), 'Chuck Norris: Could not fetch map with name "'.$map_name.'" from DB, after it was updated');

		$this->assertTrue(
			$db_map['grid_size'] == substr($gridSize, 0, strpos($gridSize, 'x'))
			&& $db_map['grid_show'] == $showGrid
			&& $db_map['grid_align'] == $autoAlign,
			'Chuck Norris: Database was not updated as expected. Here is what I got: '.print_r($db_map, true).'. Now compare it with data set provided.'
		);

		// returning to the map
		$this->zbxTestClickWait('link='.$map_name);

		// checking if all options remain as they were set
		$this->assertTrue(
			$this->getSelectedValue('gridsize') == substr($gridSize, 0, strpos($gridSize, 'x')),
			'Chuck Norris: When returning to map after update, I was expecting to see '.substr($gridSize, 0, strpos($gridSize, 'x')).' selected, but saw '.$this->getSelectedValue('gridsize')
		);

		if ($showGrid) {
			$this->zbxTestTextPresent('Shown');
		}
		else {
			$this->zbxTestTextPresent('Hidden');
		}

		if ($autoAlign) {
			$this->zbxTestTextPresent('On');
		}
		else {
			$this->zbxTestTextPresent('Off');
		}

		// if we got until here, everything works as expected
	}

	/**
	 * Testing regression of ZBX-6840
	 */
	public function testFormMap_testZBX6840() {
		$this->zbxTestLogin('maps.php');
		$this->zbxTestDropdownSelectWait('sysmapid', 'testZBX6840');
	}
}
