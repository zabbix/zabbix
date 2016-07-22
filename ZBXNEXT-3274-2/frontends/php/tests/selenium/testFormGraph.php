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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class testFormGraph extends CWebTest {

	/**
	 * The name of the host for the testing of the create function created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the host item for the testing of the layout of the graphs created in the test data set.
	 *
	 * @var string
	 */
	protected $itemSimple = 'testFormItem';

	/**
	 * The name of the inheritance item for the testing of the layout of the graphs created in the test data set.
	 *
	 * @var string
	 */
	protected $itemInheritance = 'itemInheritance';

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormGraph_Setup() {
		DBsave_tables('graphs');
	}


	// Returns layout data
	public static function layout() {
		return [
			[
				[
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Normal',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Pie',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Exploded',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormGraph1'
				]
			],
			[
				[
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceGraph1'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'templatedHost' => 'Inheritance test template',
					'form' => 'testInheritanceGraph1'
				]
			],
			[
				[
					'graphtype' => 'Normal',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Pie',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Exploded',
					'template' => 'Inheritance test template'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormGraph_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickLinkTextWait($data['template']);
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickLinkTextWait($data['host']);
			if (isset($data['templatedHost'])) {
				$hostid = 30001;
			}
			else {
				$hostid = 40001;
			}
		}

		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Graphs']");

		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestCheckHeader('Graphs');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestTextPresent('Graphs');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent graphs');
			if (isset($data['hostTemplate'])) {
				$this->zbxTestAssertElementPresentXpath("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent graphs');
		}

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'size', 20);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'autofocus');
		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//input[@id='name']", 'readonly');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//input[@id='name'][readonly]");
		}

		$this->zbxTestTextPresent('Width');
		$this->zbxTestAssertVisibleId('width');
		$this->zbxTestAssertAttribute("//input[@id='width']", 'maxlength', 5);
		$this->zbxTestAssertAttribute("//input[@id='width']", 'size', 20);
		$this->zbxTestAssertElementValue('width', 900);
		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//input[@id='width']", 'readonly');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//input[@id='width'][readonly]");
		}

		$this->zbxTestTextPresent('Height');
		$this->zbxTestAssertVisibleId('height');
		$this->zbxTestAssertAttribute("//input[@id='height']", 'maxlength', 5);
		$this->zbxTestAssertAttribute("//input[@id='height']", 'size', 20);
		$this->zbxTestAssertElementValue('height', 200);
		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//input[@id='height']", 'readonly');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//input[@id='height'][readonly]");
		}

		$this->zbxTestTextPresent('Graph type');
		$this->zbxTestAssertVisibleId('graphtype');
		$this->zbxTestDropdownHasOptions('graphtype', [
			'Normal',
			'Stacked',
			'Pie',
			'Exploded'
		]);
		if (!isset($data['form'])) {
			$this->zbxTestDropdownAssertSelected('graphtype', 'Normal');
		}

		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//select[@id='graphtype']", 'disabled');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//select[@id='graphtype'][@disabled]");
		}

		if (isset($data['graphtype'])) {
			$this->zbxTestDropdownSelectWait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->zbxTestGetSelectedLabel('graphtype');

		if (isset($data['ymin_type'])) {
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->zbxTestDropdownSelectWait('ymax_type', $data['ymax_type']);
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$ymin_type = $this->zbxTestGetSelectedLabel('ymin_type');
			$ymax_type = $this->zbxTestGetSelectedLabel('ymax_type');
		}
		else {
			$ymin_type = null;
			$ymax_type = null;
		}

		$this->zbxTestTextPresent('Show legend');
		$this->zbxTestAssertVisibleId('show_legend');
		if (!isset($data['form'])) {
			$this->assertTrue($this->zbxTestCheckboxSelected('show_legend'));
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show working time');
			$this->zbxTestAssertVisibleId('show_work_period');

			if (!isset($data['form'])) {
				$this->assertTrue($this->zbxTestCheckboxSelected('show_work_period'));
			}

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//*[@id='show_work_period']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//*[@id='show_work_period'][@disabled]");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Show working time');
			$this->zbxTestAssertElementNotPresentId('show_work_period');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show triggers');
			$this->zbxTestAssertVisibleId('show_triggers');
			if (!isset($data['form'])) {
				$this->assertTrue($this->zbxTestCheckboxSelected('show_triggers'));
			}
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//*[@id='show_triggers']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//*[@id='show_triggers'][@disabled]");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Show triggers');
			$this->zbxTestAssertElementNotPresentId('show_triggers');
		}

		if ($graphtype == 'Normal') {
			$this->zbxTestTextPresent('Percentile line (left)');
			$this->zbxTestAssertVisibleId('visible_percent_left');
			$this->zbxTestTextPresent('Percentile line (right)');
			$this->zbxTestAssertVisibleId('visible_percent_right');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//input[@id='visible_percent_left']", 'disabled');
				$this->zbxTestAssertAttribute("//input[@id='visible_percent_right']", 'disabled');
			}
		}
		else {
			$this->zbxTestTextNotPresent('Percentile line (left)');
			$this->zbxTestAssertElementNotPresentId('visible_percent_left');

			$this->zbxTestTextNotPresent('Percentile line (right)');
			$this->zbxTestAssertElementNotPresentId('visible_percent_right');
		}

		if ($graphtype == 'Pie' || $graphtype == 'Exploded') {
			$this->zbxTestTextPresent('3D view');
			$this->zbxTestAssertVisibleId('show_3d');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//input[@id='show_3d']/@disabled", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//input[@id='show_3d'][@disabled]");
			}
		}
		else {
			$this->zbxTestTextNotPresent('3D view');
			$this->zbxTestAssertElementNotPresentId('show_3d');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Y axis MIN value');
			$this->zbxTestAssertVisibleId('ymin_type');
			$this->zbxTestDropdownHasOptions('ymin_type', [
				'Calculated',
				'Fixed',
				'Item'
			]);

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//select[@id='ymin_type']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//select[@id='ymin_type'][@disabled]");
			}

			if (!isset($data['form'])) {
				switch ($ymin_type) {
					case 'Calculated':
					case 'Fixed':
					case 'Item':
						$this->zbxTestDropdownAssertSelected('ymin_type', $ymin_type);
						break;
				}
			}

			$this->zbxTestTextPresent('Y axis MAX value');
			$this->zbxTestAssertVisibleId('ymax_type');
			$this->zbxTestDropdownHasOptions('ymax_type', [
				'Calculated',
				'Fixed',
				'Item'
			]);

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//select[@id='ymax_type']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//select[@id='ymax_type'][@disabled]");
			}

			if (!isset($data['form'])) {
				switch ($ymax_type) {
					case 'Calculated':
					case 'Fixed':
					case 'Item':
						$this->zbxTestDropdownAssertSelected('ymax_type', $ymax_type);
						break;
				}
			}
		}
		else {
			$this->zbxTestTextNotPresent('Y axis MIN value');
			$this->zbxTestAssertElementNotPresentId('ymin_type');

			$this->zbxTestTextNotPresent('Y axis MAX value');
			$this->zbxTestAssertElementNotPresentId('ymax_type');
		}

		if (!isset($data['form'])) {
			// add general item
			$this->zbxTestLaunchPopup('add_item');

			if (isset($data['host'])) {
				$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);

				$this->zbxTestAssertElementPresentXpath("//a[text()='".$this->itemSimple."']");
				$this->zbxTestClickLinkTextWait($this->itemSimple);
			}

			if (isset($data['template'])) {
				$this->zbxTestClickLinkTextWait($this->itemInheritance);
			}

			$this->webDriver->switchTo()->window('');

			switch($ymin_type) {
				case 'Fixed':
					$this->zbxTestAssertVisibleId('yaxismin');
					$this->zbxTestAssertAttribute("//input[@id='yaxismin']", 'maxlength', 255);
					$this->zbxTestAssertAttribute("//input[@id='yaxismin']", 'size', 20);
					$this->zbxTestAssertElementValue('yaxismin', 0);

					$this->zbxTestAssertElementNotPresentId('ymin_name');
					$this->zbxTestAssertElementNotPresentId('yaxis_min');
					break;
				case 'Calculated':
					$this->zbxTestAssertElementNotPresentId('ymin_name');
					$this->zbxTestAssertElementNotPresentId('yaxis_min');
					$this->zbxTestAssertNotVisibleId('yaxismin');
					break;
				case 'Item':
					$this->zbxTestAssertElementPresentId('ymin_name');
					$this->zbxTestAssertElementPresentId('yaxis_min');
					$this->zbxTestAssertElementText("//button[@id='yaxis_min']", 'Select');

					$this->zbxTestAssertNotVisibleId('yaxismin');
					break;
				default:
					$this->zbxTestTextNotPresent('Add graph items first');
					$this->zbxTestAssertElementNotPresentId('ymin_name');
					$this->zbxTestAssertElementNotPresentId('yaxis_min');
					$this->zbxTestAssertElementNotPresentId('yaxismin');
					break;
			}

			switch($ymax_type) {
				case 'Fixed':
					$this->zbxTestAssertVisibleId('yaxismax');
					$this->zbxTestAssertAttribute("//input[@id='yaxismax']", 'maxlength', 255);
					$this->zbxTestAssertAttribute("//input[@id='yaxismax']", 'size', 20);
					$this->zbxTestAssertElementValue('yaxismax', 100);

					$this->zbxTestAssertElementNotPresentId('ymax_name');
					$this->zbxTestAssertElementNotPresentId('yaxis_max');
					break;
				case 'Calculated':
					$this->zbxTestAssertElementNotPresentId('ymax_name');
					$this->zbxTestAssertElementNotPresentId('yaxis_max');
					$this->zbxTestAssertNotVisibleId('yaxismax');
					break;
				case 'Item':
					$this->zbxTestDropdownSelectWait('ymax_type', 'Calculated');
					$this->zbxTestDropdownSelectWait('ymax_type', 'Item');
					$this->zbxTestAssertElementPresentId('ymax_name');
					$this->zbxTestAssertElementPresentId('yaxis_max');
					$this->zbxTestAssertElementText("//button[@id='yaxis_max']", 'Select');

					$this->zbxTestAssertNotVisibleId('yaxismax');
					break;
				default:
					$this->zbxTestTextNotPresent('Add graph items first');
					$this->zbxTestAssertElementNotPresentId('ymax_name');
					$this->zbxTestAssertElementNotPresentId('yaxis_max');
					$this->zbxTestAssertElementNotPresentId('yaxismax');
					break;
			}

			switch ($graphtype) {
				case 'Normal':
					$this->zbxTestTextPresent(['Items', 'Name', 'Function', 'Draw style', 'Y axis side', 'Colour', 'Action']);
					break;
				case 'Stacked':
					$this->zbxTestTextPresent(['Items', 'Name', 'Function', 'Y axis side', 'Colour', 'Action']);
					break;
				case 'Pie':
				case 'Exploded':
					$this->zbxTestTextPresent(['Items', 'Name', 'Type', 'Function', 'Colour', 'Action']);
					break;
			}
		}

		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertElementNotPresentId('add_item');
		}
		else {
			$this->zbxTestAssertVisibleId('add_item');
			$this->zbxTestAssertElementText("//button[@id='add_item']", 'Add');
			$this->zbxTestAssertElementNotPresentXpath("//button[@id='add_item'][@disabled]");
		}

		$this->zbxTestTabSwitch('Preview');

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertElementText("//button[@id='cancel']", 'Cancel');

		if (isset($data['form'])) {
			$this->zbxTestAssertVisibleId('update');
			$this->zbxTestAssertElementValue('update', 'Update');
			$this->zbxTestAssertVisibleId('clone');
			$this->zbxTestAssertElementValue('clone', 'Clone');
			$this->zbxTestAssertVisibleId('delete');
			$this->zbxTestAssertElementValue('delete', 'Delete');

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//button[@id='update']", 'disabled');
				$this->zbxTestAssertAttribute("//button[@id='delete']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//button[@id='update'][@disabled]");
				$this->zbxTestAssertElementNotPresentXpath("//button[@id='delete'][@disabled]");
			}
		}
		else {
			$this->zbxTestAssertVisibleId('add');
			$this->zbxTestAssertElementValue('add', 'Add');
			$this->zbxTestAssertElementNotPresentId('clone');
			$this->zbxTestAssertElementNotPresentId('update');
			$this->zbxTestAssertElementNotPresentId('delete');
		}
	}

	// Returns update data
	public static function update() {
		return DBdata(
			'SELECT * FROM graphs g'.
			' LEFT JOIN graphs_items gi'.
				' ON gi.graphid=g.graphid'.
			' WHERE g.graphid BETWEEN 300000 AND 300010'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testFormGraph_SimpleUpdate($data) {
		$sqlGraphs = 'SELECT * FROM graphs ORDER BY graphid';
		$oldHashGraphs = DBhash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&graphid='.$data['graphid'].'&hostid=40001');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph updated');
		$this->zbxTestTextPresent([
			$data['name'],
			'Graphs'
		]);

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'error-msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphSaveCheck',
					'addItems' => [
						['itemName' => 'testFormItem', 'remove' => true],
						['itemName' => 'testFormItem']
					],
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testFormGraph1',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'Graph with name "testFormGraph1" already exists in graphs or graph prototypes.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'graphSaveCheck',
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'Missing items for graph "graphSaveCheck".'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphNormal1',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'ymax_name' => 'testFormItem',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphNormal2',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'ymin_type' => 'Item',
					'ymin_name' => 'testFormItem',
					'ymax_type' => 'Item',
					'ymax_name' => 'testFormItem',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphNormal3',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'ymax_name' => 'testFormItem',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphNormal4',
					'graphtype' => 'Normal',
					'addItems' => [
						['itemName' => 'testFormItem']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphStacked1',
					'graphtype' => 'Stacked',
					'addItems' => [
						['itemName' => 'testFormItem']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphStacked2',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'addItems' => [
						['itemName' => 'testFormItem']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphStacked3',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymin_name' => 'testFormItem',
					'ymax_type' => 'Fixed',
					'addItems' => [
						['itemName' => 'testFormItem']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'No permissions to referred object or it does not exist!'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'width' => 'name',
					'height' => 'name',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'yaxismin' => 'name',
					'ymax_type' => 'Fixed',
					'yaxismax' => 'name',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'error-msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "0" for "Width" field: must be between 20 and 65535.',
						'Incorrect value "0" for "Height" field: must be between 20 and 65535.',
						'Value "name" of "yaxismin" has incorrect decimal format.',
						'Value "name" of "yaxismax" has incorrect decimal format.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'width' => '65536',
					'height' => '-22',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'error-msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "65536" for "Width" field: must be between 20 and 65535.',
						'Incorrect value "-22" for "Height" field: must be between 20 and 65535.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphPie',
					'graphtype' => 'Pie',
					'addItems' => [
						['itemName' => 'testFormItem']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graphExploded',
					'graphtype' => 'Exploded',
					'addItems' => [
						['itemName' => 'testFormItem']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'graph!@#$%^&*()><>?:"|{},./;',
					'graphtype' => 'Exploded',
					'addItems' => [
						['itemName' => 'testFormItem']
					],
					'dbCheck' => true,
					'formCheck' => true
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormGraph_SimpleCreate($data) {
		$this->zbxTestLogin('graphs.php?hostid=40001&form=Create+graph');
		$this->zbxTestCheckTitle('Configuration of graphs');

		if (isset($data['name'])) {
			$this->zbxTestInputType('name', $data['name']);
		}
		$name = $this->zbxTestGetValue("//input[@id='name']");

		if (isset($data['graphtype'])) {
			$this->zbxTestDropdownSelectWait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->zbxTestGetSelectedLabel('graphtype');

		if (isset($data['addItems'])) {
			foreach($data['addItems'] as $item) {
				$this->zbxTestLaunchPopup('add_item');
				$link = $item['itemName'];

				$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);

				$this->zbxTestAssertElementPresentXpath("//a[text()='".$link."']");
				$this->zbxTestClickLinkTextWait($link);

				$this->zbxTestWaitWindowClose();
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('items_0_name'));
				$this->zbxTestTextPresent($this->host . ': ' . $link);

				if(isset($item['remove'])) {
					$this->zbxTestClickWait('items_0_remove');
					$this->zbxTestTextNotPresent($link);
				}
			}
		}

		if (isset($data['width'])) {
			$this->zbxTestInputTypeOverwrite('width', $data['width']);
		}
		$width = $this->zbxTestGetValue("//input[@id='width']");

		if (isset($data['height'])) {
			$this->zbxTestInputTypeOverwrite('height', $data['height']);
		}
		$height = $this->zbxTestGetValue("//input[@id='height']");

		if (isset($data['ymin_type'])) {
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->zbxTestDropdownSelectWait('ymax_type', $data['ymax_type']);
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$ymin_type = $this->zbxTestGetSelectedLabel('ymin_type');
			$ymax_type = $this->zbxTestGetSelectedLabel('ymax_type');
		}
		else {
			$ymin_type = null;
			$ymax_type = null;
		}

		if (isset($data['yaxismin'])) {
			$this->zbxTestInputType('yaxismin' ,$data['yaxismin']);
			$yaxismin = $this->zbxTestGetValue("//input[@id='yaxismin']");
		}
		elseif ($ymin_type == 'Fixed') {
			$yaxismin = $this->zbxTestGetValue("//input[@id='yaxismin']");
		}
		else {
			$yaxismin = null;
		}

		if (isset($data['yaxismax'])) {
			$this->zbxTestInputType('yaxismax' ,$data['yaxismax']);
			$yaxismin = $this->zbxTestGetValue("//input[@id='yaxismax']");
		}
		elseif ($ymax_type == 'Fixed') {
			$yaxismax = $this->zbxTestGetValue("//input[@id='yaxismax']");
		}
		else {
			$yaxismax = null;
		}

		if (isset($data['ymin_name'])) {
			$this->zbxTestLaunchPopup('yaxis_min' , 'zbx_popup_item');

			$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
			$this->zbxTestDropdownSelectWait('hostid', $this->host);

			$this->zbxTestAssertElementPresentXpath("//a[text()='".$this->itemSimple."']");
			$this->zbxTestClickLinkTextWait($this->itemSimple);

			$this->webDriver->switchTo()->window('');
			$ymin_name = $data['ymin_name'];
			$ymin_nameValue = $this->zbxTestGetValue("//input[@id='ymin_name']");
			$this->assertEquals($ymin_nameValue, $this->host.": $ymin_name");
		}

		if (isset($data['ymax_name'])) {
			$this->zbxTestLaunchPopup('yaxis_max', 'zbx_popup_item');

			$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
			$this->zbxTestDropdownSelectWait('hostid', $this->host);

			$this->zbxTestAssertElementPresentXpath("//a[text()='".$this->itemSimple."']");
			$this->zbxTestClickLinkTextWait($this->itemSimple);

			$this->webDriver->switchTo()->window('');
			$ymax_name = $data['ymax_name'];
			$ymax_nameValue = $this->zbxTestGetValue("//input[@id='ymax_name']");
			$this->assertEquals($ymax_nameValue, $this->host.": $ymax_name");
		}

		$this->zbxTestClickWait('add');
		$expected = $data['expected'];
		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph added');
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestCheckHeader('Graphs');
				break;
			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error-msg']);
				$this->zbxTestCheckHeader('Graphs');
				foreach ($data['errors'] as $msg) {
				$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent(['Name', 'Width', 'Height']);
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
			$this->zbxTestClickLinkTextWait($name);
			$this->zbxTestAssertElementValue('name', $name);
			$this->zbxTestDropdownAssertSelected('graphtype', $graphtype);
			$this->zbxTestAssertElementValue('width', $width);
			$this->zbxTestAssertElementValue('height', $height);
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormGraph_Teardown() {
		DBrestore_tables('graphs');
	}
}
