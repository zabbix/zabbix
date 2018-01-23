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
class testInheritanceGraph extends CWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	// return list of graphs from a template
	public static function update() {
		return DBdata(
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
		$oldHashGraphs = DBhash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&graphid='.$data['graphid']);
		$this->zbxTestCheckTitle('Configuration of graphs');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph updated');

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
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
						'Graph with name "testInheritanceGraph4" already exists in graphs or graph prototypes.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceGraph_SimpleCreate($data) {
		$this->zbxTestLogin('graphs.php?form=Create+graph&hostid='.$this->templateid);

		$this->zbxTestInputType('name', $data['name']);
		$this->assertEquals($data['name'], $this->zbxTestGetValue("//input[@id='name']"));

		foreach ($data['addItems'] as $item) {
			$this->zbxTestClick('add_item');
			$this->zbxTestLaunchOverlayDialog('Items');
			$this->zbxTestClickLinkTextWait($item['itemName']);
			$this->zbxTestTextPresent($this->template.': '.$item['itemName']);
		}

		$this->zbxTestDoubleClickBeforeMessage('add', 'groupid');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestCheckHeader('Graphs');
				$this->zbxTestTextNotPresent('Cannot add graph');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph added');
				$this->zbxTestTextPresent($data['name']);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graphs');
				$this->zbxTestCheckHeader('Graphs');
				$this->zbxTestTextNotPresent('Graph added');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}
}
