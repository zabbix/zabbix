<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 *
 * @backup graphs
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
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'graphtype' => 'Normal',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
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
					'form' => 'testFormGraphPrototype1'
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
					'form' => 'testInheritanceGraphPrototype1'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'templatedHost' => 'Inheritance test template',
					'form' => 'testInheritanceGraphPrototype1'
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
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Normal',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'graphtype' => 'Normal',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
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
	public function testFormGraphPrototype_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickLinkTextWait($data['template']);
			$discoveryRule = $this->discoveryRuleTemplate;
			$hostid = 30000;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickLinkTextWait($data['host']);
			if (!isset($data['templatedHost'])) {
				$discoveryRule = $this->discoveryRule;
				$hostid = 40001;
			}
			else {
				$discoveryRule = $this->discoveryRuleTemplate;
				$hostid = 30001;
			}
		}

		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($discoveryRule);
		$this->zbxTestClickLinkTextWait('Graph prototypes');

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestTextPresent(['Graph prototypes', $discoveryRule]);

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestClickButtonText('Create graph prototype');
		}

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		$this->zbxTestAssertElementPresentXpath("//a[@id='tab_graphTab' and text()='Graph prototype']");

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
			$this->zbxTestAssertElementNotPresentId('ymin_name');
			$this->zbxTestAssertElementNotPresentId('yaxis_min');
			$this->zbxTestDropdownSelectWait('ymin_type', $data['ymin_type']);
		}

		if (isset($data['ymax_type'])) {
			$this->zbxTestAssertElementNotPresentId('ymax_name');
			$this->zbxTestAssertElementNotPresentId('yaxis_max');
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
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//input[@id='visible_percent_left']", 'disabled');
				$this->zbxTestAssertAttribute("//input[@id='visible_percent_right']", 'disabled');
			}
			$this->zbxTestTextPresent('Percentile line (right)');
			$this->zbxTestAssertElementPresentId('visible_percent_right');
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
			$this->zbxTestAssertElementPresentId('ymax_type');
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

		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertElementNotPresentId('add_item');
			$this->zbxTestAssertElementNotPresentId('add_protoitem');
		}
		else {
			$this->zbxTestAssertVisibleId('add_item');
			$this->zbxTestAssertElementText("//button[@id='add_item']", 'Add');
			$this->zbxTestAssertVisibleId('add_protoitem');
			$this->zbxTestAssertElementText("//button[@id='add_protoitem']", 'Add prototype');
		}

		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertElementNotPresentId('add_item');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//button[@id='add_item'][@disabled]");
		}

		if (!isset($data['form'])) {
			$this->zbxTestClick('add_item');
			$this->zbxTestLaunchOverlayDialog('Items');

			if (isset($data['host'])) {
				$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);

				$this->zbxTestClickLinkText($this->itemSimple);
			}

			if (isset($data['template'])) {
				$this->zbxTestClickLinkText($this->itemInheritance);
			}
		}

		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertElementNotPresentId('add_protoitem');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//button[@id='add_protoitem'][@disabled]");
		}

		if (!isset($data['form'])) {
			$this->zbxTestClick('add_protoitem');
			$this->zbxTestLaunchOverlayDialog('Item prototypes');

			if (isset($data['host'])) {
				$this->zbxTestClickLinkText($this->item);
			}

			if (isset($data['template'])) {
				$this->zbxTestClickLinkText($this->itemDiscovery);
			}
		}

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
				$this->zbxTestAssertElementPresentId('yaxis_min_prototype');
				$this->zbxTestAssertElementText("//button[@id='yaxis_min_prototype']", 'Select prototype');
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
				$this->zbxTestAssertElementPresentId('ymax_name');
				$this->zbxTestAssertElementPresentId('yaxis_max');
				$this->zbxTestAssertElementText("//button[@id='yaxis_max']", 'Select');
				$this->zbxTestAssertElementPresentId('yaxis_max_prototype');
				$this->zbxTestAssertElementText("//button[@id='yaxis_max_prototype']", 'Select prototype');
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

		$this->zbxTestTabSwitch('Preview');

		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//button[@id='update']", 'disabled');
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//button[@id='update'][@disabled]");
		}

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertElementText("//button[@id='cancel']", 'Cancel');

		if (isset($data['form'])) {
			$this->zbxTestAssertVisibleId('clone');
			$this->zbxTestAssertElementValue('clone', 'Clone');
			$this->zbxTestAssertVisibleId('update');
			$this->zbxTestAssertElementValue('update', 'Update');
			$this->zbxTestAssertVisibleId('delete');
			$this->zbxTestAssertElementValue('delete', 'Delete');

			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//button[@id='delete']", 'disabled');
			}
			else {

				$this->zbxTestAssertElementNotPresentXpath("//button[@id='delete'][@disabled]");
			}
		}
		else {
			$this->zbxTestAssertVisibleId('add');
			$this->zbxTestAssertElementValue('add', 'Add');
			$this->zbxTestAssertElementNotPresentId('update');
			$this->zbxTestAssertElementNotPresentId('delete');
			$this->zbxTestAssertElementNotPresentId('clone');
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
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototype updated');
		$this->zbxTestTextPresent([
			$this->discoveryRule,
			'Graph prototype updated',
			$data['name']
		]);

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphSimple',
					'hostCheck' => true,
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphName',
					'hostCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphRemove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphNotRemove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal1',
					'graphtype' => 'Normal',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal2',
					'graphtype' => 'Normal',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Calculated',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal3',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal4',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphNormal5',
					'ymin_type' => 'Item',
					'ymax_type' => 'Item',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked1',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked2',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked3',
					'graphtype' => 'Stacked',
					'ymax_type' => 'Fixed',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked4',
					'graphtype' => 'Stacked',
					'ymax_type' => 'Item',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphStacked5',
					'graphtype' => 'Stacked',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphPie',
					'graphtype' => 'Pie',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphExploded',
					'graphtype' => 'Exploded',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graphSomeRemove',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'graphName' => 'graphSimple',
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'Graph with name "graphSimple" already exists in graphs or graph prototypes'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'graphName' => 'graph!@#$%^&*()><>?:"|{},./;',
					'graphtype' => 'Exploded',
					'formCheck' => true,
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'graphName' => 'graphSaveCheck',
					'noItem' => true,
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'Missing items for graph prototype "graphSaveCheck".'
					]
				]
			],
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
					'graphName' => 'graphRemoveAddItem',
					'removeItem' => true,
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'graphName' => 'graphStackedNoMinAxisItem',
					'graphtype' => 'Stacked',
					'noAxisItem' => true,
					'ymin_type' => 'Item',
					'ymax_type' => 'Fixed',
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'No permissions to referred object or it does not exist!'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'graphName' => 'graphStackedNoMaxAxisItem',
					'graphtype' => 'Stacked',
					'noAxisItem' => true,
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Item',
					'error-msg' => 'Cannot add graph',
					'errors' => [
						'No permissions to referred object or it does not exist!'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'graphName' => 'graphStackedMore',
					'width' => '0',
					'height' => '0',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'yaxismin' => 'name',
					'ymax_type' => 'Fixed',
					'yaxismax' => 'name',
					'error-msg' => 'age received incorrect data',
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
					'graphName' => 'graphStackedError',
					'width' => '65536',
					'height' => '-22',
					'graphtype' => 'Stacked',
					'ymin_type' => 'Fixed',
					'ymax_type' => 'Fixed',
					'error-msg' => 'age received incorrect data',
					'errors' => [
						'Incorrect value "65536" for "Width" field: must be between 20 and 65535.',
						'Incorrect value "-22" for "Height" field: must be between 20 and 65535.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormGraphPrototype_SimpleCreate($data) {
		$itemName = $this->item;
		$this->zbxTestLogin('graphs.php?parent_discoveryid=33800&form=Create+graph+prototype');

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		$this->zbxTestAssertElementPresentXpath("//a[@id='tab_graphTab' and text()='Graph prototype']");

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

		if (!isset($data['noItem'])) {
			$this->zbxTestClick('add_protoitem');
			$this->zbxTestLaunchOverlayDialog('Item prototypes');

			$this->zbxTestClickLinkText($this->item);

			$this->zbxTestClick('add_item');
			$this->zbxTestLaunchOverlayDialog('Items');

			$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
			$this->zbxTestDropdownSelectWait('hostid', $this->host);
			$this->zbxTestClickLinkText($this->itemSimple);

			if (isset($data['removeItem'])) {
				$this->zbxTestClickWait('items_0_remove');
				$this->zbxTestTextNotPresent($this->item);

				$this->zbxTestClickWait('items_0_remove');
				$this->zbxTestTextNotPresent($this->itemSimple);

				$this->zbxTestClick('add_item');
				$this->zbxTestLaunchOverlayDialog('Items');
				$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
				$this->zbxTestDropdownSelectWait('hostid', $this->host);
				$this->zbxTestClickLinkText($this->itemSimple);

				$this->zbxTestClick('add_protoitem');
				$this->zbxTestLaunchOverlayDialog('Item prototypes');
				$this->zbxTestClickLinkText($this->item);
			}
		}
		if (isset($data['width'])) {
			$this->zbxTestInputTypeOverwrite('width', $data['width']);
		}

		if (isset($data['height'])) {
			$this->zbxTestInputTypeOverwrite('height', $data['height']);
		}

		if (isset($data['graphName'])) {
			$graphName = $data['graphName'];
			$this->zbxTestInputType('name', $graphName);
		}
		else {
			$graphName = null;
		}

		if ($graphtype == 'Normal' || $graphtype == 'Stacked') {

			$ymin_type = $this->zbxTestGetSelectedLabel('ymin_type');
			$ymax_type = $this->zbxTestGetSelectedLabel('ymax_type');

			switch($ymin_type) {
				case 'Fixed':
					$this->zbxTestInputType('yaxismin', isset($data['yaxismin']) ? $data['yaxismin'] : $this->yaxismin);
					break;
				case 'Item':
					if (!isset($data['noAxisItem'])) {
						$this->zbxTestClick('yaxis_min_prototype');
						$this->zbxTestLaunchOverlayDialog('Item prototypes');
						$this->zbxTestClickLinkText($this->item);
					}
					break;
				case 'Calculated':
					break;
			}

			switch($ymax_type) {
				case 'Fixed':
					$this->zbxTestInputType('yaxismax', isset($data['yaxismax']) ? $data['yaxismax'] : $this->yaxismax);
					break;
				case 'Item':
					if (!isset($data['noAxisItem'])) {
						$this->zbxTestClick('yaxis_max_prototype');
						$this->zbxTestLaunchOverlayDialog('Item prototypes');
						$this->zbxTestClickLinkText($this->item);
					}
					break;
				case 'Calculated':
					break;
			}
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototype added');
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestTextPresent(['Graph prototypes', $this->discoveryRule]);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error-msg']);
				$this->zbxTestCheckHeader('Graph prototypes');
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpen('hosts.php');
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Discovery rules');
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
			$this->zbxTestClickLinkTextWait('Graph prototypes');

			$this->zbxTestClickLinkTextWait($graphName);

			$this->zbxTestAssertElementValue('name', $graphName);
			$this->zbxTestAssertElementPresentXpath("//span[text()='".$this->host.": ".$this->itemSimple."']");
			$this->zbxTestAssertElementPresentXpath("//span[text()='".$this->host.": ".$this->item."']");
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

			$this->zbxTestOpen('hosts.php');
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Discovery rules');
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
			$this->zbxTestClickLinkTextWait('Graph prototypes');

			$this->zbxTestCheckboxSelect('group_graphid_'.$graphid);
			$this->zbxTestClickButton('graph.massdelete');

			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototypes deleted');
			$this->zbxTestTextNotPresent($this->template.": $graphName");
		}
	}
}
