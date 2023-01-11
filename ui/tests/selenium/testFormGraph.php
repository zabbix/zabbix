<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup graphs
 */
class testFormGraph extends CLegacyWebTest {

	/**
	 * The name of the host for the testing of the create function created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the host group that the above host belongs to.
	 *
	 * @var string
	 */
	protected $hostGroup = 'Zabbix servers';

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
			$this->query('button:Reset')->one()->click();
			$this->zbxTestClickLinkTextWait($data['template']);
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->query('button:Reset')->one()->click();
			$this->zbxTestClickLinkTextWait($data['host']);
			if (isset($data['templatedHost'])) {
				$hostid = 30001;
			}
			else {
				$hostid = 40001;
			}
		}

		$this->zbxTestClickXpathWait("//ul[contains(@class, 'object-group')]//a[text()='Graphs']");

		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestCheckHeader('Graphs');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestContentControlButtonClickTextWait('Create graph');
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
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 128);
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
			$this->zbxTestAssertAttribute("//z-select[@id='graphtype']", 'disabled');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//z-select[@id='graphtype'][@disabled]");
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
		$this->zbxTestAssertElementPresentId('show_legend');
		if (!isset($data['form'])) {
			$this->assertTrue($this->zbxTestCheckboxSelected('show_legend'));
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show working time');
			$this->zbxTestAssertElementPresentId('show_work_period');

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
			$this->zbxTestAssertElementPresentId('show_triggers');
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
			$this->zbxTestAssertElementPresentId('visible_percent_left');
			$this->zbxTestTextPresent('Percentile line (right)');
			$this->zbxTestAssertElementPresentId('visible_percent_right');
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
			$this->zbxTestAssertElementPresentId('show_3d');
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
			$this->zbxTestAssertElementPresentId('ymin_type');
			$this->zbxTestDropdownHasOptions('ymin_type', [
				'Calculated',
				'Fixed',
				'Item'
			]);

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//z-select[@id='ymin_type']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//z-select[@id='ymin_type'][@disabled]");
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
			$this->zbxTestAssertElementPresentId('ymax_type');
			$this->zbxTestDropdownHasOptions('ymax_type', [
				'Calculated',
				'Fixed',
				'Item'
			]);

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//z-select[@id='ymax_type']", 'disabled');
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//z-select[@id='ymax_type'][@disabled]");
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
			$this->zbxTestClick('add_item');
			$this->zbxTestLaunchOverlayDialog('Items');

			if (isset($data['host'])) {
				$host = COverlayDialogElement::find()->one()->query('class:multiselect-control')->asMultiselect()->one();
				$host->fill($this->host);
				COverlayDialogElement::find()->one()->waitUntilReady();
				$this->zbxTestClickLinkText($this->itemSimple);
			}

			if (isset($data['template'])) {
				$this->zbxTestClickLinkText($this->itemInheritance);
			}

			switch($ymin_type) {
				case 'Fixed':
					$this->zbxTestAssertVisibleId('yaxismin');
					$this->zbxTestAssertElementValue('yaxismin', 0);

					$this->zbxTestAssertElementNotPresentId('ymin_itemid');
					break;
				case 'Calculated':
					$this->zbxTestAssertElementNotPresentId('ymin_itemid');
					$this->zbxTestAssertNotVisibleId('yaxismin');
					break;
				case 'Item':
					$this->zbxTestAssertElementPresentId('ymin_itemid');
					$this->zbxTestAssertElementText("//div[@id='ymin_itemid']//following-sibling::div/button", 'Select');

					$this->zbxTestAssertNotVisibleId('yaxismin');
					break;
				default:
					$this->zbxTestTextNotPresent('Add graph items first');
					$this->zbxTestAssertElementNotPresentId('ymin_itemid');
					$this->zbxTestAssertElementNotPresentId('yaxismin');
					break;
			}

			switch($ymax_type) {
				case 'Fixed':
					$this->zbxTestAssertVisibleId('yaxismax');
					$this->zbxTestAssertElementValue('yaxismax', 100);

					$this->zbxTestAssertElementNotPresentId('ymax_itemid');
					break;
				case 'Calculated':
					$this->zbxTestAssertElementNotPresentId('ymax_itemid');
					$this->zbxTestAssertNotVisibleId('yaxismax');
					break;
				case 'Item':
					$this->zbxTestDropdownSelectWait('ymax_type', 'Calculated');
					$this->zbxTestDropdownSelectWait('ymax_type', 'Item');
					$this->zbxTestAssertElementPresentId('ymax_itemid');
					$this->zbxTestAssertElementText("//div[@id='ymax_itemid']//following-sibling::div/button", 'Select');

					$this->zbxTestAssertNotVisibleId('yaxismax');
					break;
				default:
					$this->zbxTestTextNotPresent('Add graph items first');
					$this->zbxTestAssertElementNotPresentId('ymax_itemid');
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
		return CDBHelper::getDataProvider(
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
		$oldHashGraphs = CDBHelper::getHash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&graphid='.$data['graphid'].'&hostid=40001');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph updated');
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Hosts')->clear()->fill('Simple form test host');
		$filter->submit();
		$this->zbxTestTextPresent([
			$data['name'],
			'Graphs'
		]);

		$this->assertEquals($oldHashGraphs, CDBHelper::getHash($sqlGraphs));
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
					'error-msg' => 'Page received incorrect data',
					'errors' => [
						'Field "ymin_itemid" is mandatory.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'width' => '0',
					'height' => '0',
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
						'Field "yaxismin" is not correct: a number is expected',
						'Field "yaxismax" is not correct: a number is expected'
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
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
			$this->zbxTestAssertElementValue('name', $data['name']);
		}
		$name = $this->zbxTestGetValue("//input[@id='name']");

		if (isset($data['graphtype'])) {
			$this->zbxTestDropdownSelectWait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->zbxTestGetSelectedLabel('graphtype');

		if (isset($data['addItems'])) {
			foreach($data['addItems'] as $item) {
				$this->zbxTestClick('add_item');
				$this->zbxTestLaunchOverlayDialog('Items');
				$link = $item['itemName'];
				$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
				$host = $overlay->query('class:multiselect-control')->asMultiselect()->one();
				$host->fill([
					'values' => $this->host,
					'context' => $this->hostGroup
				]);
				$this->zbxTestClickLinkTextWait($link);

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
			$this->zbxTestClickXpath('//div[@id="ymin_itemid"]//following-sibling::div/button');
			$this->zbxTestLaunchOverlayDialog('Items');
			$host = COverlayDialogElement::find()->one()->query('class:multiselect-control')->asMultiselect()->one();
			$host->fill([
				'values' => $this->host,
				'context' => $this->hostGroup
			]);
			$this->zbxTestClickLinkTextWait($this->itemSimple);

			$ymin_name = $data['ymin_name'];
			$ymin_nameValue = $this->zbxTestGetText('//div[@id="ymin_itemid"]');
			$this->assertEquals($ymin_nameValue, $this->host.": $ymin_name");
		}

		if (isset($data['ymax_name'])) {
			$this->zbxTestClickXpath('//div[@id="ymax_itemid"]//following-sibling::div/button');
			$this->zbxTestLaunchOverlayDialog('Items');
			$host = COverlayDialogElement::find()->one()->query('class:multiselect-control')->asMultiselect()->one();
			$host->fill([
				'values' => $this->host,
				'context' => $this->hostGroup
			]);
			$this->zbxTestClickLinkTextWait($this->itemSimple);

			$ymax_name = $data['ymax_name'];
			$ymax_nameValue = $this->zbxTestGetText('//div[@id="ymax_itemid"]');
			$this->assertEquals($this->host.": $ymax_name", $ymax_nameValue);
		}

		$this->zbxTestClickWait('add');
		$expected = $data['expected'];
		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot add graph']);
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
			$filter = $this->query('name:zbx_filter')->asForm()->one();
			$filter->getField('Hosts')->fill([
				'values' => $this->host,
				'context' => $this->hostGroup
			]);
			$filter->submit();

			$this->zbxTestClickLinkTextWait($name);
			$this->zbxTestAssertElementValue('name', $name);
			$this->zbxTestDropdownAssertSelected('graphtype', $graphtype);
			$this->zbxTestAssertElementValue('width', $width);
			$this->zbxTestAssertElementValue('height', $height);
		}
	}
}
