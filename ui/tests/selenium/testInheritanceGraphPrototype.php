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

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup graphs
 */
class testInheritanceGraphPrototype extends CLegacyWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	private $discoveryRuleId = 15011;	// 'testInheritanceDiscoveryRule'
	private $discoveryRule = 'testInheritanceDiscoveryRule';

	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT DISTINCT g.graphid,id.parent_itemid'.
			' FROM graphs g,graphs_items gi,item_discovery id'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=id.itemid'.
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND i.hostid=15000'.	//	$this->templateid.
						' AND i.flags=2'.
					')'.
				' AND g.flags=2'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceGraphPrototype_SimpleUpdate($data) {
		$sqlGraphs = 'SELECT * FROM graphs ORDER BY graphid';
		$oldHashGraphs = CDBHelper::getHash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&context=host&graphid='.$data['graphid'].'&parent_discoveryid='.
				$data['parent_itemid']);
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototype updated');

		$this->assertEquals($oldHashGraphs, CDBHelper::getHash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceGraphPrototype5',
					'addItemPrototypes' => [
						['itemName' => 'testInheritanceItemPrototype1'],
						['itemName' => 'testInheritanceItemPrototype2'],
						['itemName' => 'testInheritanceItemPrototype3'],
						['itemName' => 'testInheritanceItemPrototype4']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceGraphPrototype4',
					'addItemPrototypes' => [
						['itemName' => 'testInheritanceItemPrototype1']
					],
					'errors'=> [
						'Graph with name "testInheritanceGraphPrototype4" already exists in graphs or graph prototypes.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceGraphPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('graphs.php?form=Create+graph+prototype&context=template&parent_discoveryid='.$this->discoveryRuleId);

		$this->zbxTestInputTypeWait('name', $data['name']);

		if (isset($data['addItemPrototypes'])) {
			foreach ($data['addItemPrototypes'] as $item) {
				$this->zbxTestClick('add_protoitem');
				$this->zbxTestLaunchOverlayDialog('Item prototypes');
				$this->zbxTestClickLinkTextWait($item['itemName']);
				$this->zbxTestTextPresent($this->template.': '.$item['itemName']);
			}
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestCheckHeader('Graph prototypes');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototype added');
				$this->zbxTestTextPresent($data['name']);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestCheckHeader('Graph prototypes');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add graph prototype');
				$this->zbxTestTextPresent($data['errors']);
				$this->zbxTestTextNotPresent('Graph prototype added');
				break;
		}

	}
}
