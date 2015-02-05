<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestTextPresent('Graph prototype updated');

		$this->assertEquals($oldHashGraphs, DBhash($sqlGraphs));
	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceGraphPrototype5',
					'addItemPrototypes' => array(
						array('itemName' => 'testInheritanceItemPrototype1'),
						array('itemName' => 'testInheritanceItemPrototype2'),
						array('itemName' => 'testInheritanceItemPrototype3'),
						array('itemName' => 'testInheritanceItemPrototype4')
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceGraphPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('graphs.php?form=Create+graph+prototype&parent_discoveryid='.$this->discoveryRuleId);

		$this->input_type('name', $data['name']);

		if (isset($data['addItemPrototypes'])) {
			foreach ($data['addItemPrototypes'] as $item) {
				$this->zbxTestLaunchPopup('add_protoitem');
				$this->zbxTestClick("//span[text()='".$item['itemName']."']");
				sleep(1);
				$this->selectWindow();
			}
		}

		$this->zbxTestClickWait('save');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestTextPresent('CONFIGURATION OF GRAPH PROTOTYPES');
				$this->zbxTestTextPresent('Graph prototype added');
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of graph prototypes');
				$this->zbxTestTextPresent('CONFIGURATION OF GRAPH PROTOTYPES');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testInheritanceGraphPrototype_restore() {
		DBrestore_tables('graphs');
	}
}
