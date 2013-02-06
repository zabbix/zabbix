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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

define('GRAPH_GOOD', 0);
define('GRAPH_BAD', 1);

class testFormItem extends CWebTest {

	// returns all possible item types
	public static function itemTypes() {
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
	 * @dataProvider itemTypes
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
				break;
			case 'Calculated':
			case 'Item':
				$this->assertNotVisible('yaxismin');
				break;
			default:
				$this->assertElementNotPresent('yaxismin');
				break;
		}

		switch($ymax_type) {
			case 'Fixed':
				$this->assertVisible('yaxismax');
				$this->assertAttribute("//input[@id='yaxismax']/@maxlength", '255');
				$this->assertAttribute("//input[@id='yaxismax']/@size", '7');
				$this->assertAttribute("//input[@id='yaxismax']/@value", '100.00');
				break;
			case 'Calculated':
			case 'Item':
				$this->assertNotVisible('yaxismax');
				break;
			default:
				$this->assertElementNotPresent('yaxismax');
				break;
		}

		if ($ymin_type == 'Item' || $ymax_type == 'Item') {
			$this->ok('Add graph items first');
		}
		else {
			$this->nok('Add graph items first');
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

	/**
	 * Restore the original tables.
	 */
	public function testFormGraph_teardown() {
		DBrestore_tables('graphs');
	}
}
