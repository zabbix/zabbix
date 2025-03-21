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
 * @backup triggers
 */
class testInheritanceTriggerPrototype extends CLegacyWebTest {

	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	private $discoveryRuleId = 15011;	// 'testInheritanceDiscoveryRule'
	private $discoveryRule = 'testInheritanceDiscoveryRule';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	// Returns update data
	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT DISTINCT t.description,id.parent_itemid'.
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
		$oldHashTriggers = CDBHelper::getHash($sqlTriggers);

		$this->zbxTestLogin('zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='.$data['parent_itemid']);
		$this->zbxTestClickLinkTextWait($data['description']);
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->query('button:Update')->one()->click();
		COverlayDialogElement::ensureNotPresent();
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent('Trigger prototype updated');

		$this->assertEquals($oldHashTriggers, CDBHelper::getHash($sqlTriggers));
	}


	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'testInheritanceTriggerPrototype5',
					'expression' => 'last(/Inheritance test template/item-discovery-prototype[{#KEY}])<0'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'testInheritanceTriggerPrototype1',
					'expression' => 'last(/Inheritance test template/key-item-inheritance-test)=0',
					'title' => 'Cannot add trigger prototype',
					'errors' => 'Trigger prototype "testInheritanceTriggerPrototype1" must contain at least one item prototype.'
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceTriggerPrototype_SimpleCreate($data) {

		$this->zbxTestLogin('zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='.
				$this->discoveryRuleId);
		$this->zbxTestContentControlButtonClickTextWait('Create trigger prototype');
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestInputTypeByXpath("//input[@name='name']", $data['description']);
		$this->zbxTestInputType('expression', $data['expression']);
		$dialog->getFooter()->query('button:Add')->one()->click();

		switch ($data['expected']) {
			case TEST_GOOD:
				$dialog->ensureNotPresent();
				$this->zbxTestCheckTitle('Configuration of trigger prototypes');
				$this->zbxTestCheckHeader('Trigger prototypes');
				$this->zbxTestTextPresent('Trigger prototype added');
				$this->zbxTestTextPresent($data['description']);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of trigger prototypes');
				$this->zbxTestCheckHeader('Trigger prototypes');
				$this->assertMessage(TEST_BAD, $data['title'], $data['errors']);
				break;
		}
	}
}
