<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
class testInheritanceTriggerPrototype extends CWebTest {

	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	private $discoveryRuleId = 15011;	// 'testInheritanceDiscoveryRule'
	private $discoveryRule = 'testInheritanceDiscoveryRule';

	public function testInheritanceTriggerPrototype_backup() {
		DBsave_tables('triggers');
	}

	// Returns update data
	public static function update() {
		return DBdata(
			'SELECT DISTINCT t.triggerid,id.parent_itemid'.
			' FROM triggers t,functions f,item_discovery id'.
			' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=id.itemid'.
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM functions f,items i'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=15000'.	//	$this->templateid.
						' AND i.flags=2'.
					')'.
				' AND t.flags=2'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceTriggerPrototype_SimpleUpdate($data) {
		$sqlTriggers = 'SELECT * FROM triggers ORDER BY triggerid';
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('trigger_prototypes.php?form=update&triggerid='.$data['triggerid'].'&parent_discoveryid='.$data['parent_itemid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent('Trigger prototype updated');

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}


	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'testInheritanceTriggerPrototype5',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'testInheritanceTriggerPrototype1',
					'expression' => '{Inheritance test template:key-item-inheritance-test.last()}=0',
					'errors' => [
						'Cannot add trigger prototype',
						'Trigger prototype "testInheritanceTriggerPrototype1" must contain at least one item prototype.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceTriggerPrototype_SimpleCreate($data) {

		$this->zbxTestLogin('trigger_prototypes.php?form=Create+trigger+prototype&parent_discoveryid='.$this->discoveryRuleId);

		$this->zbxTestInputType('description', $data['description']);
		$this->zbxTestInputType('expression', $data['expression']);

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of trigger prototypes');
				$this->zbxTestCheckHeader('Trigger prototypes');
				$this->zbxTestTextPresent('Trigger prototype added');
				$this->zbxTestTextPresent($data['description']);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of trigger prototypes');
				$this->zbxTestCheckHeader('Trigger prototypes');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceTriggerPrototype_restore() {
		DBrestore_tables('triggers');
	}
}
