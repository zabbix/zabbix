<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

define('GRAPH_GOOD', 0);
define('GRAPH_BAD', 1);

class testFormItem extends CWebTest {

	// returns all possible item types
	public static function graphTypes() {
		return array(
			array(
				array(
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
				)
			),
			array(
				array(
					'graphtype' => 'Pie',
				)
			),
			array(
				array(
					'graphtype' => 'Exploded',
				)
			)
		);
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormGraph_setup() {
		DBsave_tables('graphs');
	}

	/**
	 * @dataProvider graphTypes
	 */
	public function testFormGraph_CheckLayout($data) {

		$this->login('graphs.php');
		$this->checkTitle('Configuration of graphs');
		$this->ok('CONFIGURATION OF GRAPHS');

		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of graphs');

		$this->ok('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');
		$this->assertAttribute("//input[@id='name']/@size", '50');
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

		$this->ok('Width');
		$this->assertVisible('width');
		$this->assertAttribute("//input[@id='width']/@maxlength", '5');
		$this->assertAttribute("//input[@id='width']/@size", '5');
		$this->assertAttribute("//input[@id='width']/@value", '900');

		$this->ok('Height');
		$this->assertVisible('height');
		$this->assertAttribute("//input[@id='height']/@maxlength", '5');
		$this->assertAttribute("//input[@id='height']/@size", '5');
		$this->assertAttribute("//input[@id='height']/@value", '200');

		$this->ok('Graph type');
		$this->assertVisible('graphtype');
		$this->zbxDropdownHasOptions('graphtype', array(
			'Normal',
			'Stacked',
			'Pie',
			'Exploded'
		));
		$this->assertAttribute("//*[@id='graphtype']/option[text()='Normal']/@selected", 'selected');

		if (isset($data['graphtype'])) {
			$this->dropdown_select_wait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->getSelectedLabel('graphtype');

		if (isset($data['ymin_type'])) {
			$this->dropdown_select_wait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->dropdown_select_wait('ymax_type', $data['ymax_type']);
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$ymin_type = $this->getSelectedLabel('ymin_type');
			$ymax_type = $this->getSelectedLabel('ymax_type');
		}
		else {
			$ymin_type = null;
			$ymax_type = null;
		}

		$this->ok('Show legend');
		$this->assertVisible('legend');
		$this->assertAttribute("//*[@id='legend']/@checked", 'checked');

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->ok('Show working time');
			$this->assertVisible('showworkperiod');
			$this->assertAttribute("//*[@id='showworkperiod']/@checked", 'checked');
		}
		else {
			$this->nok('Show working time');
			$this->assertElementNotPresent('showworkperiod');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->ok('Show triggers');
			$this->assertVisible('showtriggers');
			$this->assertAttribute("//*[@id='showtriggers']/@checked", 'checked');
		}
		else {
			$this->nok('Show triggers');
			$this->assertElementNotPresent('showtriggers');
		}

		if ($graphtype == 'Normal') {
			$this->ok('Percentile line (left)');
			$this->assertVisible('visible_percent_left');

			$this->ok('Percentile line (right)');
			$this->assertVisible('visible_percent_right');
		}
		else {
			$this->nok('Percentile line (left)');
			$this->assertElementNotPresent('visible_percent_left');

			$this->nok('Percentile line (right)');
			$this->assertElementNotPresent('visible_percent_right');
		}

		if ($graphtype == 'Pie' || $graphtype == 'Exploded') {
			$this->ok('3D view');
			$this->assertVisible('graph3d');
		}
		else {
			$this->nok('3D view');
			$this->assertElementNotPresent('graph3d');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->ok('Y axis MIN value');
			$this->assertVisible('ymin_type');
			$this->zbxDropdownHasOptions('ymin_type', array(
				'Calculated',
				'Fixed',
				'Item'
			));
			switch ($ymin_type) {
				case 'Calculated':
					$this->assertAttribute("//*[@id='ymin_type']/option[text()='$ymin_type']/@selected", 'selected');
					break;
				case 'Fixed':
					$this->assertAttribute("//*[@id='ymin_type']/option[text()='$ymin_type']/@selected", 'selected');
					break;
				case 'Item':
					$this->assertAttribute("//*[@id='ymin_type']/option[text()='$ymin_type']/@selected", 'selected');
					break;
			}

			$this->ok('Y axis MAX value');
			$this->assertVisible('ymax_type');
			$this->zbxDropdownHasOptions('ymax_type', array(
				'Calculated',
				'Fixed',
				'Item'
			));
			switch ($ymax_type) {
				case 'Calculated':
					$this->assertAttribute("//*[@id='ymax_type']/option[text()='$ymax_type']/@selected", 'selected');
					break;
				case 'Fixed':
					$this->assertAttribute("//*[@id='ymax_type']/option[text()='$ymax_type']/@selected", 'selected');
					break;
				case 'Item':
					$this->assertAttribute("//*[@id='ymax_type']/option[text()='$ymax_type']/@selected", 'selected');
					break;
			}
		}
		else {
			$this->nok('Y axis MIN value');
			$this->assertElementNotPresent('ymin_type');

			$this->nok('Y axis MAX value');
			$this->assertElementNotPresent('ymax_type');
		}

		switch($ymin_type) {
			case 'Fixed':
				$this->assertVisible('yaxismin');
				$this->assertAttribute("//input[@id='yaxismin']/@maxlength", '255');
				$this->assertAttribute("//input[@id='yaxismin']/@size", '7');
				$this->assertAttribute("//input[@id='yaxismin']/@value", '0.00');

				$this->assertElementNotPresent('ymin_name');
				$this->assertElementNotPresent('yaxis_min');
				break;
			case 'Calculated':
				$this->assertElementNotPresent('ymin_name');
				$this->assertElementNotPresent('yaxis_min');
				$this->assertNotVisible('yaxismin');
				break;
			case 'Item':
				$this->zbxLaunchPopup('add_item');
				$this->button_click("link=Host name");
				sleep(1);
				$this->selectWindow(null);

				$this->dropdown_select_wait('ymin_type', 'Calculated');
				$this->dropdown_select_wait('ymin_type', 'Item');
				$this->assertElementPresent('ymin_name');
				$this->assertElementPresent('yaxis_min');
				$this->assertAttribute("//input[@id='yaxis_min']/@value", 'Select');

				$this->assertNotVisible('yaxismin');
				break;
			default:
				$this->nok('Add graph items first');
				$this->assertElementNotPresent('ymin_name');
				$this->assertElementNotPresent('yaxis_min');
				$this->assertElementNotPresent('yaxismin');
				break;
		}

		switch($ymax_type) {
			case 'Fixed':
				$this->assertVisible('yaxismax');
				$this->assertAttribute("//input[@id='yaxismax']/@maxlength", '255');
				$this->assertAttribute("//input[@id='yaxismax']/@size", '7');
				$this->assertAttribute("//input[@id='yaxismax']/@value", '100.00');

				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				break;
			case 'Calculated':
				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				$this->assertNotVisible('yaxismax');
				break;
			case 'Item':
				$this->zbxLaunchPopup('add_item');
				$this->button_click("link=Host name");
				sleep(1);
				$this->selectWindow(null);

				$this->dropdown_select_wait('ymax_type', 'Calculated');
				$this->dropdown_select_wait('ymax_type', 'Item');
				$this->assertElementPresent('ymax_name');
				$this->assertElementPresent('yaxis_max');
				$this->assertAttribute("//input[@id='yaxis_max']/@value", 'Select');

				$this->assertNotVisible('yaxismax');
				break;
			default:
				$this->nok('Add graph items first');
				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				$this->assertElementNotPresent('yaxismax');
				break;
		}

		switch ($graphtype) {
			case 'Normal':
				$this->ok(array('Items', 'Name', 'Function', 'Draw style', 'Y axis side', 'Colour', 'Action'));
				break;
			case 'Stacked':
				$this->ok(array('Items', 'Name', 'Function', 'Y axis side', 'Colour', 'Action'));
				break;
			case 'Pie':
			case 'Exploded':
				$this->ok(array('Items', 'Name', 'Type', 'Function', 'Colour', 'Action'));
				break;
		}

		$this->assertVisible('add_item');
		$this->assertAttribute("//input[@id='add_item']/@value", 'Add');

		$this->button_click('link=Preview');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');
	}

	// Returns all possible item data
	public static function dataCreate() {
		return array(
			array(
				array(
					'expected' => GRAPH_BAD,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphSaveCheck',
					'addItems' => array(
						array('itemName' => 'CPU nice time', 'remove' => true),
						array('itemName' => 'Host name')
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => GRAPH_BAD,
					'name' => 'graphSaveCheck',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'errors' => array(
						'ERROR: Cannot add graph',
						'Graph with name "graphSaveCheck" already exists in graphs or graph prototypes.'
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_BAD,
					'name' => 'graphSaveCheck',
					'errors' => array(
						'ERROR: Cannot add graph',
						'Missing items for graph "graphSaveCheck".'
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphNormal1',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'ymax_name' => 'Agent ping',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphNormal2',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'ymin_type' => 'Item',
					'ymin_name' => 'Agent ping',
					'ymax_type' => 'Item',
					'ymax_name' => 'Agent ping',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphNormal3',
					'graphtype' => 'Normal',
					'addItems' => array(
						array('itemName' => 'Host name')
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphStacked1',
					'graphtype' => 'Stacked',
					'addItems' => array(
						array('itemName' => 'Host name')
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphStacked2',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'addItems' => array(
						array('itemName' => 'Host name')
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphStacked3',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymin_name' => 'Agent ping',
					'ymax_type' => 'Fixed',
					'addItems' => array(
						array('itemName' => 'Host name')
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_BAD,
					'name' => 'graphStacked',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'errors' => array(
						'ERROR: Cannot add graph',
						'Incorrect item for axis value.'
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_BAD,
					'name' => 'graphStacked',
					'width' => 'name',
					'height' => 'name',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'yaxismin' => 'name',
					'ymax_type' => 'Fixed',
					'yaxismax' => 'name',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Width (min:20, max:65535)": must be between 20 and 65535.',
						'Warning. Incorrect value for field "Height (min:20, max:65535)": must be between 20 and 65535.',
						'Warning. Field "yaxismin" is not decimal number.',
						'Warning. Field "yaxismin" is not decimal number.'
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_BAD,
					'name' => 'graphStacked',
					'width' => '65536',
					'height' => '-22',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Width (min:20, max:65535)": must be between 20 and 65535.',
						'Warning. Incorrect value for field "Height (min:20, max:65535)": must be between 20 and 65535.'
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphPie',
					'graphtype' => 'Pie',
					'addItems' => array(
						array('itemName' => 'Host name')
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graphExploded',
					'graphtype' => 'Exploded',
					'addItems' => array(
						array('itemName' => 'Host name')
					)
				)
			),
			array(
				array(
					'expected' => GRAPH_GOOD,
					'name' => 'graph!@#$%^&*()><>?:"|{},./;',
					'graphtype' => 'Exploded',
					'addItems' => array(
						array('itemName' => 'Host name')
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormGraph_Create($data) {

		$this->login('graphs.php');
		$this->checkTitle('Configuration of graphs');
		$this->ok('CONFIGURATION OF GRAPHS');

		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of graphs');

		if (isset($data['name'])) {
			$this->input_type('name', $data['name']);
		}
		$name = $this->getValue('name');

		if (isset($data['graphtype'])) {
			$this->dropdown_select_wait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->getSelectedLabel('graphtype');

		if (isset($data['addItems'])) {
			foreach($data['addItems'] as $item) {
				$this->zbxLaunchPopup('add_item');
				$link = $item['itemName'];
				$this->button_click("link=$link");
				sleep(1);
				$this->selectWindow(null);
				if(isset($item['remove'])) {
				$this->button_click('items_0_remove');
				}
			}
		}

		if (isset($data['width'])) {
			$this->input_type('width', $data['width']);
		}
		$width = $this->getValue('width');

		if (isset($data['height'])) {
			$this->input_type('height', $data['height']);
		}
		$height = $this->getValue('height');

		if (isset($data['ymin_type'])) {
			$this->dropdown_select_wait('ymin_type', $data['ymin_type']);
			sleep(1);
		}

		if (isset($data['ymax_type'])) {
			$this->dropdown_select_wait('ymax_type', $data['ymax_type']);
			sleep(1);
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$ymin_type = $this->getSelectedLabel('ymin_type');
			$ymax_type = $this->getSelectedLabel('ymax_type');
		}
		else {
			$ymin_type = null;
			$ymax_type = null;
		}

		if (isset($data['yaxismin'])) {
			$this->input_type('yaxismin' ,$data['yaxismin']);
			$yaxismin = $this->getValue('yaxismin');
		}
		elseif ($ymin_type == 'Fixed') {
			$yaxismin = $this->getValue('yaxismin');
		}
		else {
			$yaxismin = null;
		}

		if (isset($data['yaxismax'])) {
			$this->input_type('yaxismax' ,$data['yaxismax']);
			$yaxismin = $this->getValue('yaxismax');
		}
		elseif ($ymax_type == 'Fixed') {
			$yaxismax = $this->getValue('yaxismax');
		}
		else {
			$yaxismax = null;
		}

		if (isset($data['ymin_name'])) {
			$this->zbxLaunchPopup('yaxis_min' , 'zbx_popup_item');
			$this->dropdown_select_wait('groupid', 'Zabbix servers');
			$this->dropdown_select_wait('hostid', 'ЗАББИКС Сервер');
			$this->button_click('link=Agent ping');
			$this->selectWindow(null);
		}

		if (isset($data['ymax_name'])) {
			$this->zbxLaunchPopup('yaxis_max', 'zbx_popup_item');
			$this->dropdown_select_wait('groupid', 'Zabbix servers');
			$this->dropdown_select_wait('hostid', 'ЗАББИКС Сервер');
			$this->button_click('link=Agent ping');
			$this->selectWindow(null);
		}


		$this->button_click('save');
		$this->wait();
		$expected = $data['expected'];
		switch ($expected) {
			case GRAPH_GOOD:
				$this->ok('Graph added');
				$this->checkTitle('Configuration of graphs');
				$this->ok('CONFIGURATION OF GRAPHS');
				break;
			case GRAPH_BAD:
				$this->checkTitle('Configuration of graphs');
				$this->ok('CONFIGURATION OF GRAPHS');
				foreach ($data['errors'] as $msg) {
				$this->ok($msg);
				}
				$this->ok(array('Name', 'Width', 'Height'));
				break;
			}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT name, width, height FROM graphs where name like '".$name."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $name);
				$this->assertEquals($row['width'], $width);
				$this->assertEquals($row['height'], $height);
			}
		}

		if (isset($data['formCheck'])) {
			$this->button_click("link=$name");
			$this->wait();
			$this->assertAttribute("//input[@id='name']/@value", $name);
			$this->assertAttribute("//*[@id='graphtype']/option[text()='$graphtype']/@selected", 'selected');
			$this->assertAttribute("//input[@id='width']/@value", $width);
			$this->assertAttribute("//input[@id='height']/@value", $height);
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormGraph_teardown() {
		DBrestore_tables('graphs');
	}
}
