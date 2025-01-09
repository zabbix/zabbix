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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

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
			'SELECT t.description'.
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

		$this->zbxTestLogin('zabbix.php?action=trigger.list&context=host&filter_rst=1&filter_hostids[0]='.$this->hostid);
		$this->zbxTestClickLinkTextWait($data['description']);

		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->query('button:Update')->one()->click();
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
					'title' => 'Cannot add trigger',
					'errors' => 'Trigger "testInheritanceTrigger1" already exists on "Inheritance test template".'
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceTrigger_SimpleCreate($data) {
		$this->zbxTestLogin('zabbix.php?action=trigger.list&context=template&filter_rst=1&filter_hostids[0]='.$this->templateid);
		$this->zbxTestContentControlButtonClickTextWait('Create trigger');
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestInputType('name', $data['description']);
		$this->zbxTestInputType('expression', $data['expression']);
		$dialog->getFooter()->query('button:Add')->one()->click();

		switch ($data['expected']) {
			case TEST_GOOD:
				$dialog->ensureNotPresent();
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestCheckHeader('Triggers');
				$this->zbxTestTextPresent('Trigger added');
				$this->zbxTestTextPresent($data['description']);
				break;
			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestCheckHeader('Triggers');
				$this->assertMessage(TEST_BAD, $data['title'], $data['errors']);
				break;
		}
	}
}
