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

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testFormGraphPrototype extends CWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testFormDiscoveryRule';

	/**
	 * The name of the form test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRuleTemplate = 'testInheritanceDiscoveryRule';

	/**
	 * The name of the test discovery rule key created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryKey = 'discovery-rule-test';

	/**
	 * The name of the test item created in the test data set.
	 *
	 * @var string
	 */
	protected $itemSimple = 'testFormItem';

	/**
	 * The name of the test item created in the test data set.
	 *
	 * @var string
	 */
	protected $itemInheritance = 'itemInheritance';

	/**
	 * The name of the test item created in the test data set.
	 *
	 * @var string
	 */
	protected $itemDiscovery = 'itemDiscovery';

	/**
	 * The name of the test item key created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKeySimple = 'test-item-reuse';

	/**
	 * The name of the test item prototype within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'testFormItemReuse';

	/**
	 * The name of the test item prototype key within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $testFormItemReuse = 'item-prototype-reuse';

	/**
	 * The value of the yaxismin field to be created in the test data set.
	 *
	 * @var int
	 */
	protected $yaxismin = 100;

	/**
	 * The value of the yaxismax field to be created in the test data set.
	 *
	 * @var int
	 */
	protected $yaxismax = 500;


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormGraphPrototype_Setup() {
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
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
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
					'form' => 'testFormGraphPrototype1'
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
					'form' => 'testInheritanceGraphPrototype1'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'templatedHost' => 'Inheritance test template',
					'form' => 'testInheritanceGraphPrototype1'
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
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'graphtype' => 'Normal',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
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
	public function testFormGraphPrototype_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
			$discoveryRule = $this->discoveryRuleTemplate;
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
			if (!isset($data['templatedHost'])) {
				$discoveryRule = $this->discoveryRule;
				$hostid = 40001;
			}
			else {
				$discoveryRule = $this->discoveryRuleTemplate;
				$hostid = 30001;
			}
		}

		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$discoveryRule);
		$this->zbxTestClickWait('link=Graph prototypes');

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GRAPH PROTOTYPES', "Graph prototypes of ".$discoveryRule));

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF GRAPH PROTOTYPES');
		$this->assertElementPresent("//a[@id='tab_graphTab' and text()='Graph prototype']");

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
			$this->assertElementNotPresent('ymin_name');
			$this->assertElementNotPresent('yaxis_min');
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->assertElementNotPresent('ymax_name');
			$this->assertElementNotPresent('yaxis_max');
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

		$this->assertVisible('add_item');
		$this->assertAttribute("//input[@id='add_item']/@value", 'Add');

		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='add_item']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//input[@id='add_item']/@disabled");
		}

		if (!isset($data['form'])) {
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
		}

		$this->assertVisible('add_protoitem');
		$this->assertAttribute("//input[@id='add_protoitem']/@value", 'Add prototype');

		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='add_protoitem']/@disabled", 'disabled');
		}
		else {
			$this->assertElementNotPresent("//input[@id='add_protoitem']/@disabled");
		}

		if (!isset($data['form'])) {
			$this->zbxTestLaunchPopup('add_protoitem');
			if (isset($data['host'])) {
				sleep(1);
				$this->assertElementPresent("//span[text()='".$this->item."']");
				$this->zbxTestClick("//span[text()='".$this->item."']");
			}

			if (isset($data['template'])) {
				$this->zbxTestClick("//span[text()='".$this->itemDiscovery."']");
			}

			sleep(1);
			$this->selectWindow(null);
		}

		switch($ymin_type) {
			case 'Fixed':
				$this->assertVisible('yaxismin');
				$this->assertAttribute("//input[@id='yaxismin']/@maxlength", '255');
				$this->assertAttribute("//input[@id='yaxismin']/@size", '7');
//				$this->assertAttribute("//input[@id='yaxismin']/@value", '0');
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
				$this->assertElementPresent('yaxis_min_prototype');
				$this->assertAttribute("//input[@id='yaxis_min_prototype']/@value", 'Select prototype');
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
//				$this->assertAttribute("//input[@id='yaxismax']/@value", '100');
				break;
			case 'Calculated':
				$this->assertElementNotPresent('ymax_name');
				$this->assertElementNotPresent('yaxis_max');
				$this->assertNotVisible('yaxismax');
				break;
			case 'Item':
				$this->assertElementPresent('ymax_name');
				$this->assertElementPresent('yaxis_max');
				$this->assertAttribute("//input[@id='yaxis_max']/@value", 'Select');
				$this->assertElementPresent('yaxis_max_prototype');
				$this->assertAttribute("//input[@id='yaxis_max_prototype']/@value", 'Select prototype');
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

		$this->zbxTestClick('link=Preview');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='save']/@aria-disabled", 'true');
		}
		else {
			$this->assertAttribute("//input[@id='save']/@aria-disabled", 'false');
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
		return DBdata("select * from graphs where name LIKE 'testFormGraphPrototype%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormGraphPrototype_SimpleUpdate($data) {
		$sqlGraphs = "select * from graphs ORDER BY graphid";
		$oldHashGraphs = DBhash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&graphid='.$data['graphid'].'&parent_discoveryid=33800&hostid=40001');
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestTextPresent(array(
			'CONFIGURATION OF GRAPH PROTOTYPES',
			'Graph prototypes of '.$this->discoveryRule,
			'Graph prototype updated',
			$data['name']
		));

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphSimple',
					'hostCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphName',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphRemove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphNotRemove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal1',
					'graphtype' => 'Normal',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal2',
					'graphtype' => 'Normal',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Calculated',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal3',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal4',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal5',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked1',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked2',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked3',
					'graphtype' => 'Stacked',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked4',
					'graphtype' => 'Stacked',
					'ymax_type' => 'Item',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked5',
					'graphtype' => 'Stacked',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphPie',
					'graphtype' => 'Pie',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphExploded',
					'graphtype' => 'Exploded',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graphSomeRemove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'graphName' => 'graphSimple',
					'errors' => array(
						'ERROR: Cannot add graph',
						'Graph with name "graphSimple" already exists in graphs or graph prototypes'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'graphName' => 'graph!@#$%^&*()><>?:"|{},./;',
					'graphtype' => 'Exploded',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'graphName' => 'graphSaveCheck',
					'noItem' => true,
					'errors' => array(
						'ERROR: Cannot add graph',
						'Missing items for graph prototype "graphSaveCheck".'
					)
				)
			),
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
					'graphName' => 'graphRemoveAddItem',
					'removeItem' => true,
					'dbCheck' => true,
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'graphName' => 'graphStackedNoMinAxisItem',
					'graphtype' => 'Stacked',
					'noAxisItem' => true,
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'errors' => array(
						'ERROR: Cannot add graph',
						'No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'graphName' => 'graphStackedNoMaxAxisItem',
					'graphtype' => 'Stacked',
					'noAxisItem' => true,
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'errors' => array(
						'ERROR: Cannot add graph',
						'No permissions to referred object or it does not exist!'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'graphName' => 'graphStackedMore',
					'width' => 'name',
					'height' => 'name',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'yaxismin' => 'name',
					'ymax_type' => 'Fixed',
					'yaxismax' => 'name',
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
					'graphName' => 'graphStackedError',
					'width' => '65536',
					'height' => '-22',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "65536" for "Width" field: must be between 20 and 65535.',
						'Incorrect value "-22" for "Height" field: must be between 20 and 65535.'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testFormGraphPrototype_SimpleCreate($data) {
		$itemName = $this->item;
		$this->zbxTestLogin('graphs.php?parent_discoveryid=33800&form=Create+graph+prototype');

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF GRAPH PROTOTYPES');
		$this->assertElementPresent("//a[@id='tab_graphTab' and text()='Graph prototype']");

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

		if (!isset($data['noItem'])) {
			$this->zbxTestLaunchPopup('add_item');
			sleep(1);
			$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
			$this->zbxTestDropdownSelectWait('hostid', $this->host);
			$this->zbxTestClick('link='.$this->itemSimple);
			$this->selectWindow(null);
			sleep(1);

			$this->zbxTestLaunchPopup('add_protoitem');
			$this->zbxTestClick("//span[text()='"."$this->item"."']");
			$this->selectWindow(null);
			sleep(1);

			if (isset($data['removeItem'])) {
				$this->zbxTestClick('items_0_remove');
				sleep(1);
				$this->zbxTestClick('items_0_remove');
				sleep(1);

				$this->zbxTestLaunchPopup('add_item');
				sleep(1);
				$this->zbxTestDropdownSelectWait('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);
				$this->zbxTestClick('link='.$this->itemSimple);
				$this->selectWindow(null);
				sleep(1);

				$this->zbxTestLaunchPopup('add_protoitem');
				$this->zbxTestClick("//span[text()='"."$this->item"."']");
				$this->selectWindow(null);
				sleep(1);
			}
		}
		if (isset($data['width'])) {
			$this->input_type('width', $data['width']);
		}

		if (isset($data['height'])) {
			$this->input_type('height', $data['height']);
		}

		if (isset($data['graphName'])) {
			$graphName = $data['graphName'];
			$this->input_type('name', $graphName);
		}
		else {
			$graphName = null;
		}


		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {

			$ymin_type = $this->getSelectedLabel('ymin_type');
			$ymax_type = $this->getSelectedLabel('ymax_type');

			switch($ymin_type) {
				case 'Fixed':
					$this->input_type('yaxismin', isset($data['yaxismin']) ? $data['yaxismin'] : $this->yaxismin);
					break;
				case 'Item':
					if (!isset($data['noAxisItem'])) {
						$this->zbxTestLaunchPopup('yaxis_min_prototype', 'zbx_popup_item');
						$this->zbxTestClick("//span[text()='".$this->item."']");
						$this->selectWindow(null);
						sleep(1);
					}
					break;
				case 'Calculated':
					break;
			}

			switch($ymax_type) {
				case 'Fixed':
					$this->input_type('yaxismax', isset($data['yaxismax']) ? $data['yaxismax'] : $this->yaxismax);
					break;
				case 'Item':
					if (!isset($data['noAxisItem'])) {
						$this->zbxTestLaunchPopup('yaxis_max_prototype', 'zbx_popup_item');
						$this->zbxTestClick("//span[text()='".$this->item."']");
						$this->selectWindow(null);
						sleep(1);
					}
					break;
				case 'Calculated':
					break;
			}
		}


		$this->zbxTestClickWait('save');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestTextPresent('Graph prototype added');
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestTextPresent(array('CONFIGURATION OF GRAPH PROTOTYPES', "Graph prototypes of ".$this->discoveryRule));
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestTextPresent(array('CONFIGURATION OF GRAPH PROTOTYPES', 'Graph prototype'));
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Graph prototypes");

			$this->zbxTestClickWait("link=$graphName");

			$this->assertElementValue('name', $graphName);
			$this->assertElementPresent("//span[text()='".$this->host.": ".$this->itemSimple."']");
			$this->assertElementPresent("//span[text()='".$this->host.": ".$this->item."']");
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT name, graphid FROM graphs where name = '".$graphName."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $graphName);
			}
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT graphid FROM graphs where name = '".$graphName."'");
			while ($row = DBfetch($result)) {
				$graphid = $row['graphid'];
			}

			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Graph prototypes");

			$this->zbxTestCheckboxSelect("group_graphid_$graphid");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Graph prototypes deleted');
			$this->zbxTestTextNotPresent($this->template.": $graphName");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormGraphPrototype_Teardown() {
		DBrestore_tables('graphs');
	}
}
