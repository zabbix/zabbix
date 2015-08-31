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
class testInheritanceTrigger extends CWebTest {

	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	public function testInheritanceTrigger_Setup() {
		DBsave_tables('triggers');
	}

	// return list of triggers from a template
	public static function update() {
		return DBdata(
			'SELECT t.triggerid'.
			' FROM triggers t'.
			' WHERE EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=15000'.	//	$this->templateid.
					' AND i.flags=0'.
				')'.
				' AND t.flags=0'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceTrigger_SimpleUpdate($data) {
		$sqlTriggers = 'SELECT * FROM triggers ORDER BY triggerid';
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('triggers.php?form=update&triggerid='.$data['triggerid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestTextPresent('Trigger updated');

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'testInheritanceTrigger',
					'expression' => '{Inheritance test template:test-inheritance-item1.last()}=0'
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceTrigger_SimpleCreate($data) {
		$this->zbxTestLogin('triggers.php?form=Create+trigger&hostid='.$this->templateid);

		$this->input_type('description', $data['description']);
		$this->input_type('expression', $data['expression']);

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
				$this->zbxTestTextPresent('Trigger added');
				break;
			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
				$this->zbxTestTextPresent('ERROR: Cannot add trigger');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testInheritanceTrigger_restore() {
		DBrestore_tables('triggers');
	}
}
