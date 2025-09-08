<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../../include/CLegacyWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup graphs
 */
class testInheritanceGraph extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	// return list of graphs from a template
	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT g.graphid'.
			' FROM graphs g'.
			' WHERE EXISTS ('.
				'SELECT NULL'.
				' FROM graphs_items gi,items i'.
				' WHERE g.graphid=gi.graphid'.
					' AND gi.itemid=i.itemid'.
					' AND i.hostid=15000'.	//	$this->templateid.
					' AND i.flags=0'.
				')'.
				' AND g.flags=0'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceGraph_SimpleUpdate($data) {
		$sqlGraphs = 'SELECT * FROM graphs ORDER BY graphid';
		$oldHashGraphs = CDBHelper::getHash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&context=host&graphid='.$data['graphid']);
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph updated');

		$this->assertEquals($oldHashGraphs, CDBHelper::getHash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceGraph5',
					'addItems' => [
						['itemName' => 'testInheritanceItem1'],
						['itemName' => 'testInheritanceItem2'],
						['itemName' => 'testInheritanceItem3'],
						['itemName' => 'testInheritanceItem4']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceGraph4',
					'addItems' => [
						['itemName' => 'testInheritanceItem1']
					],
					'error_msg' => 'Cannot add graph',
					'errors'=> [
						'Graph "testInheritanceGraph4" already exists on the template "Inheritance test template".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceGraph_SimpleCreate($data) {
		$this->zbxTestLogin('graphs.php?form=Create+graph&context=template&hostid='.$this->templateid);

		$this->zbxTestInputType('name', $data['name']);
		$this->assertEquals($data['name'], $this->zbxTestGetValue("//input[@id='name']"));

		foreach ($data['addItems'] as $item) {
			$this->zbxTestClick('add_item');
			$this->zbxTestLaunchOverlayDialog('Items');
			$this->zbxTestClickLinkTextWait($item['itemName']);
			$this->zbxTestTextPresent($this->template.': '.$item['itemName']);
		}
		$this->query('id:add')->one()->click();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, 'Graph added');
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestCheckHeader('Graphs');
				$this->zbxTestTextNotPresent('Cannot add graph');
				$filter = $this->query('name:zbx_filter')->asForm()->one();
				$filter->getField('Templates')->clear()->fill($this->template);
				$filter->submit();
				$this->query('link', $data['name'])->one()->waitUntilVisible();
				break;

			case TEST_BAD:
				$this->assertMessage(TEST_BAD, $data['error_msg']);
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestCheckHeader('Graphs');
				$this->zbxTestTextNotPresent('Graph added');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}
}
