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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup triggers
 */
class testInheritanceTrigger extends CLegacyWebTest {

	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	// return list of triggers from a template
	public static function update() {
		return CDBHelper::getDataProvider(
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
		$oldHashTriggers = CDBHelper::getHash($sqlTriggers);

		$this->zbxTestLogin('triggers.php?form=update&triggerid='.$data['triggerid'].'&context=host');
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger updated');

		$this->assertEquals($oldHashTriggers, CDBHelper::getHash($sqlTriggers));
	}

	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'testInheritanceTrigger',
					'expression' => 'last(/Inheritance test template/test-inheritance-item1)=0'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'testInheritanceTrigger1',
					'expression' => 'last(/Inheritance test template/key-item-inheritance-test)=0',
					'errors' => [
						'Trigger "testInheritanceTrigger1" already exists on "Inheritance test template".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceTrigger_SimpleCreate($data) {
		$this->zbxTestLogin('triggers.php?filter_set=1&context=template&filter_hostids[0]='.$this->templateid);
		$this->zbxTestContentControlButtonClickTextWait('Create trigger');

		$this->zbxTestInputType('description', $data['description']);
		$this->zbxTestInputType('expression', $data['expression']);

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestCheckHeader('Triggers');
				$this->zbxTestTextPresent('Trigger added');
				$this->zbxTestTextPresent($data['description']);
				break;
			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestCheckHeader('Triggers');
				$this->zbxTestTextPresent('Cannot add trigger');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}
}
