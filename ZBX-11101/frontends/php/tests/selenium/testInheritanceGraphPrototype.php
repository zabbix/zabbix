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
class testInheritanceGraphPrototype extends CWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	private $discoveryRuleId = 15011;	// 'testInheritanceDiscoveryRule'
	private $discoveryRule = 'testInheritanceDiscoveryRule';

	public function testInheritanceGraphPrototype_backup() {
		DBsave_tables('graphs');
	}

	public static function update() {
		return DBdata(
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
		$oldHashGraphs = DBhash($sqlGraphs);

		$this->zbxTestLogin('graphs.php?form=update&graphid='.$data['graphid'].'&parent_discoveryid='.$data['parent_itemid']);
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototype updated');

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
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
						'Cannot add graph prototype',
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
		DBexecute("UPDATE config SET server_check_interval = 0 WHERE configid = 1");
		$this->zbxTestLogin('graphs.php?form=Create+graph+prototype&parent_discoveryid='.$this->discoveryRuleId);

		$this->zbxTestInputTypeWait('name', $data['name']);

		if (isset($data['addItemPrototypes'])) {
			foreach ($data['addItemPrototypes'] as $item) {
				$this->zbxTestClickWait('add_protoitem');
				$this->zbxTestSwitchToNewWindow();
				$this->zbxTestClickLinkTextWait($item['itemName']);
				$this->zbxTestWaitWindowClose();
				$this->zbxTestTextPresent($this->template.': '.$item['itemName']);
			}
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestCheckHeader('Graph prototypes');
				$this->zbxTestTextPresent('Graph prototype added');
				$this->zbxTestTextPresent($data['name']);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestCheckHeader('Graph prototypes');
				$this->zbxTestTextPresent($data['errors']);
				$this->zbxTestTextNotPresent('Graph prototype added');
				break;
		}

		DBexecute("UPDATE config SET server_check_interval = 10 WHERE configid = 1");
	}

	public function testInheritanceGraphPrototype_restore() {
		DBrestore_tables('graphs');
	}
}
