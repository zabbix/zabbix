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
			'SELECT DISTINCT g.graphid,id.lldruleid'.
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

		$this->zbxTestLogin('zabbix.php?action=popup&popup=graph.prototype.edit&context=host&parent_discoveryid='.
				$data['lldruleid'].'&graphid='.$data['graphid']);
		$this->zbxTestCheckTitle('Graph prototype edit');

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button', 'Update')->waitUntilClickable()->one()->click();
		$dialog->ensureNotPresent();

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
					'errors'=> 'Graph prototype "testInheritanceGraphPrototype4" already exists on the LLD rule with'.
						' key "inheritance-discovery-rule" of the template "Inheritance test template".'
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceGraphPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('zabbix.php?action=popup&popup=graph.prototype.edit&context=template&parent_discoveryid='.
				$this->discoveryRuleId
		);
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		$this->zbxTestInputTypeWait('name', $data['name']);

		if (isset($data['addItemPrototypes'])) {
			foreach ($data['addItemPrototypes'] as $item) {
				$this->zbxTestClick('add_item_prototype');
				$this->zbxTestLaunchOverlayDialog('Item prototypes');
				$this->zbxTestClickLinkTextWait($item['itemName']);
				$this->zbxTestTextPresent($this->template.': '.$item['itemName']);
			}
			$dialog->getFooter()->query('button:Add')->waitUntilClickable()->one()->click();
		}

		switch ($data['expected']) {
			case TEST_GOOD:
				$dialog->ensureNotPresent();
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestCheckHeader('Graph prototypes');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Graph prototype added');
				$this->zbxTestTextPresent($data['name']);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Graph prototype edit');
				$message = CMessageElement::find()->waitUntilVisible()->one();
				$this->assertTrue($message->isBad());
				$this->assertEquals('Cannot add graph prototype', $message->getTitle());
				$this->assertTrue($message->hasLine($data['errors']));
				$this->zbxTestTextNotPresent('Graph prototype added');
				break;
		}
	}
}
