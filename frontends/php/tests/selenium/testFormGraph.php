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
		return array(
			array(
				array(
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Pie',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Exploded',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'form' => 'testFormGraph1'
				)
			),
			array(
				array(
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceGraph1'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'templatedHost' => 'Inheritance test template',
					'form' => 'testInheritanceGraph1'
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Pie',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Exploded',
					'template' => 'Inheritance test template'
				)
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormGraph_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
			if (isset($data['templatedHost'])) {
				$hostid = 30001;
			}
			else {
				$hostid = 40001;
			}
		}

		$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");

		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestTextPresent('CONFIGURATION OF GRAPHS');

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestTextPresent('CONFIGURATION OF GRAPHS');
		$this->zbxTestTextPresent('Graph');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent graphs');
			if (isset($data['hostTemplate'])) {
				$this->assertElementPresent("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent graphs');
		}

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", '255');
		$this->assertAttribute("//input[@id='name']/@size", '50');
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');
		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='name']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//input[@id='name']/@disabled");
		}

		$this->zbxTestTextPresent('Width');
		$this->assertVisible('width');
		$this->assertAttribute("//input[@id='width']/@maxlength", '5');
		$this->assertAttribute("//input[@id='width']/@size", '5');
		$this->assertAttribute("//input[@id='width']/@value", '900');
		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='width']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//input[@id='width']/@disabled");
		}

		$this->zbxTestTextPresent('Height');
		$this->assertVisible('height');
		$this->assertAttribute("//input[@id='height']/@maxlength", '5');
		$this->assertAttribute("//input[@id='height']/@size", '5');
		$this->assertAttribute("//input[@id='height']/@value", '200');
		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='height']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//input[@id='height']/@disabled");
		}

		$this->zbxTestTextPresent('Graph type');
		$this->assertVisible('graphtype');
		$this->zbxTestDropdownHasOptions('graphtype', array(
			'Normal',
			'Stacked',
			'Pie',
			'Exploded'
		));
		if (!isset($data['form'])) {
			$this->assertAttribute("//*[@id='graphtype']/option[text()='Normal']/@selected", 'selected');
		}

		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//select[@id='graphtype']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//select[@id='graphtype']/@disabled");
		}

		if (isset($data['graphtype'])) {
			$this->zbxTestDropdownSelectWait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->getSelectedLabel('graphtype');

		if (isset($data['ymin_type'])) {
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->zbxTestDropdownSelectWait('ymax_type', $data['ymax_type']);
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$ymin_type = $this->getSelectedLabel('ymin_type');
			$ymax_type = $this->getSelectedLabel('ymax_type');
		}
		else {
			$ymin_type = null;
			$ymax_type = null;
		}

		$this->zbxTestTextPresent('Show legend');
		$this->assertVisible('show_legend');
		if (!isset($data['form'])) {
			$this->assertAttribute("//*[@id='show_legend']/@checked", 'checked');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show working time');
			$this->assertVisible('show_work_period');

			if (!isset($data['form'])) {
				$this->assertAttribute("//*[@id='show_work_period']/@checked", 'checked');
			}

			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//*[@id='show_work_period']/@disabled", 'disabled');
			}
			else {
				$this->assertElementNotPresent("//*[@id='show_work_period']/@disabled");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Show working time');
			$this->assertElementNotPresent('show_work_period');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Show triggers');
			$this->assertVisible('show_triggers');
			if (!isset($data['form'])) {
				$this->assertAttribute("//*[@id='show_triggers']/@checked", 'checked');
			}
			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//*[@id='show_triggers']/@disabled", 'disabled');
			}
			else {
				$this->assertElementNotPresent("//*[@id='show_triggers']/@disabled");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Show triggers');
			$this->assertElementNotPresent('show_triggers');
		}

		if ($graphtype == 'Normal') {
			$this->zbxTestTextPresent('Percentile line (left)');
			$this->assertVisible('visible_percent_left');
			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//input[@id='visible_percent_left']/@disabled", 'disabled');
			}
			$this->zbxTestTextPresent('Percentile line (right)');
			$this->assertVisible('visible_percent_right');
			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//input[@id='visible_percent_right']/@disabled", 'disabled');
			}
		}
		else {
			$this->zbxTestTextNotPresent('Percentile line (left)');
			$this->assertElementNotPresent('visible_percent_left');

			$this->zbxTestTextNotPresent('Percentile line (right)');
			$this->assertElementNotPresent('visible_percent_right');
		}

		if ($graphtype == 'Pie' || $graphtype == 'Exploded') {
			$this->zbxTestTextPresent('3D view');
			$this->assertVisible('show_3d');
			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//input[@id='show_3d']/@disabled", 'disabled');
			}
			else {
				$this->assertElementNotPresent("//input[@id='show_3d']/@disabled");
			}
		}
		else {
			$this->zbxTestTextNotPresent('3D view');
			$this->assertElementNotPresent('show_3d');
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {
			$this->zbxTestTextPresent('Y axis MIN value');
			$this->assertVisible('ymin_type');
			$this->zbxTestDropdownHasOptions('ymin_type', array(
				'Calculated',
				'Fixed',
				'Item'
			));

			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//select[@id='ymin_type']/@disabled", 'disabled');
			}
			else {
				$this->assertElementNotPresent("//select[@id='ymin_type']/@disabled");
			}

			if (!isset($data['form'])) {
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
			}

			$this->zbxTestTextPresent('Y axis MAX value');
			$this->assertVisible('ymax_type');
			$this->zbxTestDropdownHasOptions('ymax_type', array(
				'Calculated',
				'Fixed',
				'Item'
			));

			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//select[@id='ymax_type']/@disabled", 'disabled');
			}
			else {
				$this->assertElementNotPresent("//select[@id='ymax_type']/@disabled");
			}

			if (!isset($data['form'])) {
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
		}
		else {
			$this->zbxTestTextNotPresent('Y axis MIN value');
			$this->assertElementNotPresent('ymin_type');

			$this->zbxTestTextNotPresent('Y axis MAX value');
			$this->assertElementNotPresent('ymax_type');
		}

		if (!isset($data['form'])) {
			// add general item
			$this->zbxTestLaunchPopup('add_item');

			if (isset($data['host'])) {
				sleep(1);
				$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);

				$this->assertElementPresent("//a[text()='".$this->itemSimple."']");
				$this->zbxTestClick('link='.$this->itemSimple);
			}

			if (isset($data['template'])) {
				$this->zbxTestClick('link='.$this->itemInheritance);
			}

			sleep(1);
			$this->selectWindow(null);

			switch($ymin_type) {
				case 'Fixed':
					$this->assertVisible('yaxismin');
					$this->assertAttribute("//input[@id='yaxismin']/@maxlength", '255');
					$this->assertAttribute("//input[@id='yaxismin']/@size", '7');
					$this->assertAttribute("//input[@id='yaxismin']/@value", '0.0000');

					$this->assertElementNotPresent('ymin_name');
					$this->assertElementNotPresent('yaxis_min');
					break;
				case 'Calculated':
					$this->assertElementNotPresent('ymin_name');
					$this->assertElementNotPresent('yaxis_min');
					$this->assertNotVisible('yaxismin');
					break;
				case 'Item':
					$this->assertElementPresent('ymin_name');
					$this->assertElementPresent('yaxis_min');
					$this->assertAttribute("//input[@id='yaxis_min']/@value", 'Select');

					$this->assertNotVisible('yaxismin');
					break;
				default:
					$this->zbxTestTextNotPresent('Add graph items first');
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
					$this->assertAttribute("//input[@id='yaxismax']/@value", '100.0000');

					$this->assertElementNotPresent('ymax_name');
					$this->assertElementNotPresent('yaxis_max');
					break;
				case 'Calculated':
					$this->assertElementNotPresent('ymax_name');
					$this->assertElementNotPresent('yaxis_max');
					$this->assertNotVisible('yaxismax');
					break;
				case 'Item':
					$this->zbxTestDropdownSelectWait('ymax_type', 'Calculated');
					$this->zbxTestDropdownSelectWait('ymax_type', 'Item');
					$this->assertElementPresent('ymax_name');
					$this->assertElementPresent('yaxis_max');
					$this->assertAttribute("//input[@id='yaxis_max']/@value", 'Select');

					$this->assertNotVisible('yaxismax');
					break;
				default:
					$this->zbxTestTextNotPresent('Add graph items first');
					$this->assertElementNotPresent('ymax_name');
					$this->assertElementNotPresent('yaxis_max');
					$this->assertElementNotPresent('yaxismax');
					break;
			}

			switch ($graphtype) {
				case 'Normal':
					$this->zbxTestTextPresent(array('Items', 'Name', 'Function', 'Draw style', 'Y axis side', 'Colour', 'Action'));
					break;
				case 'Stacked':
					$this->zbxTestTextPresent(array('Items', 'Name', 'Function', 'Y axis side', 'Colour', 'Action'));
					break;
				case 'Pie':
				case 'Exploded':
					$this->zbxTestTextPresent(array('Items', 'Name', 'Type', 'Function', 'Colour', 'Action'));
					break;
			}
		}

		$this->assertVisible('add_item');
		$this->assertAttribute("//input[@id='add_item']/@value", 'Add');

		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='add_item']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//input[@id='add_item']/@disabled");
		}

		$this->zbxTestClick('link=Preview');

		$this->assertVisible('update');
		$this->assertAttribute("//input[@id='update']/@value", 'Update');

		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='update']/@aria-disabled", 'true');
		}
		else {
			$this->assertAttribute("//input[@id='update']/@aria-disabled", 'false');
		}

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');

		if (isset($data['form'])) {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');
		}
		else {
			$this->assertElementNotPresent('clone');
		}

		if (isset($data['form'])) {
			$this->assertVisible('delete');
			$this->assertAttribute("//input[@id='delete']/@value", 'Delete');

			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//input[@id='delete']/@aria-disabled", 'true');
			}
			else {
				$this->assertAttribute("//input[@id='delete']/@aria-disabled", 'false');
			}
		}
		else {
			$this->assertElementNotPresent('delete');
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
		$this->zbxTestTextPresent(array(
			'Graph updated',
			$data['name'],
			'GRAPHS'
		));

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_BAD,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphSaveCheck',
					'addItems' => array(
						array('itemName' => 'testFormItem', 'remove' => true),
						array('itemName' => 'testFormItem')
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'graphSaveCheck',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'errors' => array(
						'ERROR: Cannot add graph',
						'Graph with name "graphSaveCheck" already exists in graphs or graph prototypes.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'graphSaveCheck',
					'errors' => array(
						'ERROR: Cannot add graph',
						'Missing items for graph "graphSaveCheck".'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphNormal1',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'ymax_name' => 'testFormItem',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphNormal2',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'ymin_type' => 'Item',
					'ymin_name' => 'testFormItem',
					'ymax_type' => 'Item',
					'ymax_name' => 'testFormItem',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphNormal3',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'ymax_name' => 'testFormItem',
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphNormal4',
					'graphtype' => 'Normal',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphStacked1',
					'graphtype' => 'Stacked',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphStacked2',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed' ,
					'addItems' => array(
						array('itemName' => 'testFormItem')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphStacked3',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymin_name' => 'testFormItem',
					'ymax_type' => 'Fixed',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'errors' => array(
						'ERROR: Cannot add graph',
						'No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'width' => 'name',
					'height' => 'name',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'yaxismin' => 'name',
					'ymax_type' => 'Fixed',
					'yaxismax' => 'name',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "0" for "Width" field: must be between 20 and 65535.',
						'Incorrect value "0" for "Height" field: must be between 20 and 65535.',
						'Value "name" of "yaxismin" has incorrect decimal format.',
						'Value "name" of "yaxismax" has incorrect decimal format.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'graphStacked',
					'width' => '65536',
					'height' => '-22',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "65536" for "Width" field: must be between 20 and 65535.',
						'Incorrect value "-22" for "Height" field: must be between 20 and 65535.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphPie',
					'graphtype' => 'Pie',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graphExploded',
					'graphtype' => 'Exploded',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'graph!@#$%^&*()><>?:"|{},./;',
					'graphtype' => 'Exploded',
					'addItems' => array(
						array('itemName' => 'testFormItem')
					),
					'dbCheck' => true,
					'formCheck' => true
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testFormGraph_SimpleCreate($data) {
		$this->zbxTestLogin('graphs.php?hostid=40001&form=Create+graph');
		$this->zbxTestCheckTitle('Configuration of graphs');

		if (isset($data['name'])) {
			$this->input_type('name', $data['name']);
		}
		$name = $this->getValue('name');

		if (isset($data['graphtype'])) {
			$this->zbxTestDropdownSelectWait('graphtype', $data['graphtype']);
		}
		$graphtype = $this->getSelectedLabel('graphtype');

		if (isset($data['addItems'])) {
			foreach($data['addItems'] as $item) {
				$this->zbxTestLaunchPopup('add_item');
				$link = $item['itemName'];

				$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);

				$this->assertElementPresent("//a[text()='".$link."']");
				$this->zbxTestClick("link=$link");
				sleep(1);
				$this->selectWindow(null);
				if(isset($item['remove'])) {
				$this->zbxTestClick('items_0_remove');
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
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
			sleep(1);
		}

		if (isset($data['ymax_type'])) {
			$this->zbxTestDropdownSelectWait('ymax_type', $data['ymax_type']);
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
			$this->zbxTestLaunchPopup('yaxis_min' , 'zbx_popup_item');

			$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
			$this->zbxTestDropdownSelectWait('hostid', $this->host);

			$this->assertElementPresent("//a[text()='".$this->itemSimple."']");
			$this->zbxTestClick('link='.$this->itemSimple);
			sleep(1);
			$this->selectWindow(null);
			$ymin_name = $data['ymin_name'];
			$ymin_nameValue = $this->getValue('ymin_name');
			$this->assertEquals($ymin_nameValue, $this->host.": $ymin_name");
		}

		if (isset($data['ymax_name'])) {
			$this->zbxTestLaunchPopup('yaxis_max', 'zbx_popup_item');

			$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
			$this->zbxTestDropdownSelectWait('hostid', $this->host);

			$this->assertElementPresent("//a[text()='".$this->itemSimple."']");
			$this->zbxTestClick('link='.$this->itemSimple);
			sleep(1);
			$this->selectWindow(null);
			$ymax_name = $data['ymax_name'];
			$ymax_nameValue = $this->getValue('ymax_name');
			$this->assertEquals($ymax_nameValue, $this->host.": $ymax_name");
		}

		$this->zbxTestClickWait('add');
		$expected = $data['expected'];
		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestTextPresent('Graph added');
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestTextPresent('CONFIGURATION OF GRAPHS');
				break;
			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestTextPresent('CONFIGURATION OF GRAPHS');
				foreach ($data['errors'] as $msg) {
				$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent(array('Name', 'Width', 'Height'));
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
			$this->zbxTestClickWait("link=$name");
			$this->assertAttribute("//input[@id='name']/@value", $name);
			$this->assertAttribute("//*[@id='graphtype']/option[text()='$graphtype']/@selected", 'selected');
			$this->assertAttribute("//input[@id='width']/@value", $width);
			$this->assertAttribute("//input[@id='height']/@value", $height);
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormGraph_Teardown() {
		DBrestore_tables('graphs');
	}
}
