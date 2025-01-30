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
require_once dirname(__FILE__).'/../../../include/items.inc.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

define('LONG_KEY', substr(STRING_6000, 0, 2038).'[{#MACRO}]');

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @onBefore prepareTriggerPrototypeData
 *
 * @backup triggers
 */
class testFormTriggerPrototype extends CLegacyWebTest {
	protected static $long_key_prototype_string;
	protected static $long_key_ruleid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	const HOST = 'Simple form test host';
	const HOSTID = 40001;
	const DISCOVERY_RULE = 'testFormDiscoveryRule';
	const DISCOVERY_RULE_TEMPLATE = 'testInheritanceDiscoveryRule';
	const DISCOVERY_RULEID = 133800;
	const ITEM_KEY = 'item-prototype-reuse';

	public function prepareTriggerPrototypeData() {
		// Host with a long name for long trigger expression tests.
		$long_key_hostid = CDataHelper::call('host.create', [
			'host' => STRING_128,
			'groups' => [['groupid' => 6]]
		])['hostids'][0];

		// Item with a long key for long trigger expression tests.
		CDataHelper::call('item.create', [
			[
				'name' => 'test',
				'key_' => STRING_2048,
				'hostid' => $long_key_hostid,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		]);

		// LLD rule for long trigger expression tests.
		self::$long_key_ruleid = CDataHelper::call('discoveryrule.create', [
			'name' => 'LLD for trigger prototypes',
			'key_' => 'lld_key',
			'hostid' => $long_key_hostid,
			'type' => ITEM_TYPE_SIMPLE,
			'delay' => '1m'
		])['itemids'][0];

		// Item prototypes used by various tests.
		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Text item prototype {#KEY}',
				'key_' => 'text_prototype[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'hostid' => self::HOSTID,
				'ruleid' => self::DISCOVERY_RULEID
			],
			[
				'name' => 'test2',
				'key_' => LONG_KEY,
				'hostid' => $long_key_hostid,
				'ruleid' => self::$long_key_ruleid,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		]);

		// Trigger prototypes for long trigger expression tests.
		CDataHelper::call('triggerprototype.create', [
			[
				'description' => 'Trigger prototype with long expression for simple update',
				'expression' => 'last(/'.STRING_128.'/'.LONG_KEY.')=0'
			],
			[
				'description' => 'Trigger prototype with long expression for update',
				'expression' => 'last(/'.STRING_128.'/'.LONG_KEY.')>0'
			]
		]);
	}

	// Returns layout data
	public static function layout() {
		return [
			// #0.
			[
				['constructor' => 'open', 'host' => 'Simple form test host']
			],
			// #1.
			[
				['constructor' => 'open_close', 'host' => 'Simple form test host']
			],
			// #2.
			[
				['constructor' => 'open', 'severity' => 'Warning', 'host' => 'Simple form test host']
			],
			// #3.
			[
				['constructor' => 'open_close', 'severity' => 'Disaster', 'host' => 'Simple form test host']
			],
			// #4.
			[
				['severity' => 'Not classified', 'host' => 'Simple form test host']
			],
			// #5.
			[
				['severity' => 'Information', 'host' => 'Simple form test host']
			],
			// #6.
			[
				['severity' => 'Warning', 'host' => 'Simple form test host']
			],
			// #7.
			[
				['severity' => 'Average', 'host' => 'Simple form test host']
			],
			// #8.
			[
				['severity' => 'High', 'host' => 'Simple form test host']
			],
			// #9.
			[
				['severity' => 'Disaster', 'host' => 'Simple form test host']
			],
			// #10.
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open'
				]
			],
			// #11.
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open_close'
				]
			],
			// #12.
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1'
				]
			],
			// #13.
			[
				['constructor' => 'open', 'template' => 'Inheritance test template']
			],
			// #14.
			[
				['constructor' => 'open_close', 'template' => 'Inheritance test template']
			],
			// #15.
			[
				['constructor' => 'open', 'severity' => 'Warning', 'template' => 'Inheritance test template']
			],
			// #16.
			[
				[
					'constructor' => 'open_close',
					'severity' => 'Disaster',
					'template' => 'Inheritance test template'
				]
			],
			// #17.
			[
				['severity' => 'Not classified', 'template' => 'Inheritance test template']
			],
			// #18.
			[
				['severity' => 'Information', 'template' => 'Inheritance test template']
			],
			// #19.
			[
				['severity' => 'Warning', 'template' => 'Inheritance test template']
			],
			// #20.
			[
				['severity' => 'Average', 'template' => 'Inheritance test template']
			],
			// #21.
			[
				['severity' => 'High', 'template' => 'Inheritance test template']
			],
			// #22.
			[
				['severity' => 'Disaster', 'template' => 'Inheritance test template']
			],
			// #23.
			[
				['host' => 'Simple form test host', 'form' => 'testFormTriggerPrototype1']
			],
			// #24.
			[
				['template' => 'Inheritance test template', 'form' => 'testInheritanceTriggerPrototype1']
			],
			// #25.
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			// #26.
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			// #27.
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTriggerPrototype1',
					'constructor' => 'open'
				]
			],
			// #28.
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'constructor' => 'open'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormTriggerPrototype_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('zabbix.php?action=template.list');
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenDiscovery($data['template'], $form);
			$discoveryRule = self::DISCOVERY_RULE_TEMPLATE;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin(self::HOST_LIST_PAGE);
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenDiscovery($data['host'], $form);
			if (!isset($data['templatedHost'])) {
				$discoveryRule = self::DISCOVERY_RULE;
			}
			else {
				$discoveryRule = self::DISCOVERY_RULE_TEMPLATE;
			}
		}

		$this->zbxTestClickLinkTextWait($discoveryRule);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent($discoveryRule);

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestContentControlButtonClickTextWait('Create trigger prototype');
		}
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals(isset($data['form']) ? 'Trigger prototype' : 'New trigger prototype', $dialog->getTitle());
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestAssertElementPresentXpath("//a[@id='tab_triggersTab' and text()='Trigger prototype']");

		if (isset($data['constructor'])) {
			$dialog->query('button:Expression constructor')->waitUntilClickable()->one()->click();
			// Wait for expression constructor to open, textarea is disabled and its id has changed to 'expr_temp'.
			$dialog->query('id:expr_temp')->waitUntilVisible();

			if ($data['constructor'] === 'open_close') {
				$dialog->query('button:Close expression constructor')->waitUntilClickable()->one()->click();
				// Wait until expression constructor closes, textarea is enabled and its id has changed to 'expression'.
				$dialog->query('id:expression')->waitUntilVisible();
			}
		}

		$this->zbxTestTextPresent('Trigger prototype');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent triggers');
			if (isset($data['hostTemplate'])) {
				$this->zbxTestAssertElementPresentXpath("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent triggers');
		}

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleXpath("//input[@name='name']");
		$this->zbxTestAssertAttribute("//input[@name='name']", 'maxlength', 255);

		if (!(isset($data['constructor'])) || $data['constructor'] == 'open_close') {
			$this->zbxTestTextPresent(['Expression', 'Expression constructor']);
			$this->zbxTestAssertVisibleXpath("//textarea[@id='expression']");
			$this->zbxTestAssertAttribute("//textarea[@id='expression']", 'rows', 7);
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//textarea[@id='expression']", 'readonly');
			}

			$this->zbxTestAssertVisibleXpath("//button[@name='insert']");
			$this->zbxTestAssertElementText("//button[@name='insert']", 'Add');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//button[@name='insert']", 'disabled');
			}

			$this->zbxTestAssertElementNotPresentXpath("//li[@id='expression_row']//button[contains(@onclick, 'add_expression')]");
			$this->zbxTestAssertElementNotPresentId('insert_macro');
		}
		else {
			$this->zbxTestTextPresent('Expression');
			$this->zbxTestAssertVisibleId('expr_temp');
			$this->zbxTestAssertAttribute("//textarea[@id='expr_temp']", 'rows', 7);
			$this->zbxTestAssertAttribute("//textarea[@id='expr_temp']", 'readonly');
			$this->zbxTestTextPresent('Close expression constructor');
			$this->zbxTestAssertNotVisibleXpath('//input[@name="expression"]');

			if (!isset($data['form'])) {
				$this->zbxTestAssertVisibleXpath("//div[@id='expression-row']//button[@id='add_expression']");
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//div[@id='expression-row']//button[contains(@onclick, 'add_expression')]");
			}

			$this->zbxTestAssertVisibleXpath("//button[@name='insert']");
			$this->zbxTestAssertElementText("//button[@name='insert']", 'Edit');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertElementPresentXpath("//button[@name='insert'][@disabled]");
			}

			$this->zbxTestAssertVisibleId('insert-macro');
			$this->zbxTestAssertElementText("//button[@id='insert-macro']", 'Insert expression');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertElementPresentXpath("//button[@id='insert-macro'][@disabled]");
			}

			if (!isset($data['templatedHost'])) {
				$this->zbxTestTextPresent(['Target', 'Expression', 'Action', 'Info', 'Close expression constructor']);
			}
			else {
				$this->zbxTestTextPresent(['Expression', 'Info', 'Close expression constructor']);
			}
			$this->zbxTestTextPresent('Close expression constructor');
		}

		$this->zbxTestTextPresent(['OK event generation', 'PROBLEM event generation mode']);
		$this->zbxTestTextPresent(['Expression', 'Recovery expression', 'None']);
		$this->zbxTestTextPresent(['Single', 'Multiple']);
		if (!isset($data['templatedHost'])) {
			$this->assertTrue($this->zbxTestCheckboxSelected('type_0'));
		}

		$this->zbxTestTextPresent('Description');
		$this->zbxTestAssertVisibleId('description');
		$this->zbxTestAssertAttribute("//textarea[@id='description']", 'rows', 7);

		$form = $dialog->asForm();
		$entry_name = $form->getField('id:url_name');

		foreach (['placeholder' => 'Trigger URL', 'maxlength' => 64] as $attribute => $value) {
			$this->assertEquals($value, $entry_name->getAttribute($attribute));
		}

		// Check hintbox.
		$this->query('class:zi-help-filled-small')->one()->click();
		$hint = $this->query('xpath:.//div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent()->one();

		// Assert text.
		$this->assertEquals('Menu entry name is used as a label for the trigger URL in the event context menu.',
				$hint->getText()
		);

		// Press Escape key to close hintbox.
		$this->page->pressKey(WebDriverKeys::ESCAPE);
		$hint->waitUntilNotVisible();

		$this->zbxTestTextPresent('Menu entry URL');
		$this->zbxTestAssertVisibleId('url');
		$this->zbxTestAssertAttribute("//input[@id='url']", 'maxlength', 2048);

		$this->zbxTestAssertElementPresentId('priority_0');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_0'));
		$this->zbxTestAssertElementText("//*[@id='priority_0']/../label", 'Not classified');
		$this->zbxTestAssertElementPresentId('priority_1');
		$this->zbxTestAssertElementText("//*[@id='priority_1']/../label", 'Information');
		$this->zbxTestAssertElementPresentId('priority_2');
		$this->zbxTestAssertElementText("//*[@id='priority_2']/../label", 'Warning');
		$this->zbxTestAssertElementPresentId('priority_3');
		$this->zbxTestAssertElementText("//*[@id='priority_3']/../label", 'Average');
		$this->zbxTestAssertElementPresentId('priority_4');
		$this->zbxTestAssertElementText("//*[@id='priority_4']/../label", 'High');
		$this->zbxTestAssertElementPresentId('priority_5');
		$this->zbxTestAssertElementText("//*[@id='priority_5']/../label", 'Disaster');

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClickXpathWait("//*[@id='priority_0']/../label");
					break;
				case 'Information':
					$this->zbxTestClickXpathWait("//*[@id='priority_1']/../label");
					break;
				case 'Warning':
					$this->zbxTestClickXpathWait("//*[@id='priority_2']/../label");
					break;
				case 'Average':
					$this->zbxTestClickXpathWait("//*[@id='priority_3']/../label");
					break;
				case 'High':
					$this->zbxTestClickXpathWait("//*[@id='priority_4']/../label");
					break;
				case 'Disaster':
					$this->zbxTestClickXpathWait("//*[@id='priority_5']/../label");
					break;
			}
		}

		$this->zbxTestTextPresent('Create enabled');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestAssertAttribute("//input[@id='status']", 'type', 'checkbox');
		$dialog_footer = $dialog->getFooter();

		if (isset($data['form']) && !isset($data['templatedHost'])) {
			$this->assertEquals(4, $dialog_footer->query('button', ['Update', 'Clone', 'Delete', 'Cancel'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);
		}
		elseif (isset($data['templatedHost'])) {
			$this->assertEquals(3, $dialog_footer->query('button', ['Update', 'Clone', 'Cancel'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);
			$this->assertEquals(1, $dialog_footer->query('button:Delete')->all()
					->filter(CElementFilter::NOT_CLICKABLE)->count()
			);
			$this->assertTrue($this->zbxTestCheckboxSelected('recovery_mode_0'));
			$this->zbxTestAssertElementPresentXpath("//input[@id='recovery_mode_0'][@readonly]");
		}
		else {
			$this->assertEquals(2, $dialog_footer->query('button', ['Add', 'Cancel'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);
		}

		$this->zbxTestTabSwitch('Dependencies');
		$this->zbxTestTextPresent(['Dependencies', 'Name', 'Action']);

		if (!isset($data['template'])) {
			$this->zbxTestAssertElementText("//button[@id='add-dep-trigger']", 'Add');
			$this->zbxTestAssertElementText("//button[@id='add-dep-trigger-prototype']", 'Add prototype');
		}
		else {
			$this->zbxTestAssertElementText("//button[@id='add-dep-template-trigger']", 'Add');
			$this->zbxTestAssertElementText("//button[@id='add-dep-trigger-prototype']", 'Add prototype');
			$this->zbxTestAssertElementText("//button[@id='add-dep-host-trigger']", 'Add host trigger');
		}

		COverlayDialogElement::find()->one()->close();

	}

	// Returns update data
	public static function update() {
		return CDBHelper::getDataProvider('SELECT * FROM triggers t LEFT JOIN functions f ON f.triggerid=t.triggerid'.
				' WHERE f.itemid=\'23804\' AND t.description LIKE \'testFormTriggerPrototype%\''
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testFormTriggerPrototype_SimpleUpdate($data) {
		$description = $data['description'];

		$sqlTriggers = "select * from triggers ORDER BY triggerid";
		$oldHashTriggers = CDBHelper::getHash($sqlTriggers);

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$this->filterEntriesAndOpenDiscovery(self::HOST, $form);
		$this->zbxTestClickLinkTextWait(self::DISCOVERY_RULE);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');

		$this->zbxTestClickLinkTextWait($description);
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->query('button:Update')->one()->click();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype updated');
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent(self::DISCOVERY_RULE);
		$this->zbxTestTextPresent($description);
		$this->assertEquals($oldHashTriggers, CDBHelper::getHash($sqlTriggers));
	}

	public static function create() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "expression": cannot be empty.'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect value for field "expression": cannot be empty.'
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'expression' => '6 and 0 or 0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host}',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Invalid parameter "/1/expression": incorrect expression starting from "{Simple form test host}".'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_sysUptime',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #5.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '1234567890',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'a?aa+',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #7.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '}aa]a{',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #8.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '-aaa=%',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #9.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa,;:',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa><.',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa*&_',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa#@!',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '([)$^',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new'.
							' value, if this value is part of this expression. If time based functions are used in the'.
							' expression, it is recalculated every 30 seconds by a zabbix timer process. ',
					'url_name' => 'Trigger context menu name for trigger URL.',
					'url' => 'https://www.zabbix.com',
					'severity' => 'High',
					'status' => false
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_CheckUrl',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<5',
					'url_name' => 'MyTrigger: menu name',
					'url' => 'index.php'
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'xmlxpath function',
					'expression' => 'xmlxpath(last(/Simple form test host/text_prototype[{#KEY}],#123:now),'.
							' "/zabbix_export/version/text()","default")=0'
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'xmlxpath function min fields',
					'expression' => 'xmlxpath(last(/Simple form test host/text_prototype[{#KEY}]),"/export/version/text()")=3'
				]
			],
			// #18.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'xmlxpath function error',
					'expression' => 'xmlxpath(first(/Simple form test host/text_prototype[{#KEY}]),"/export/version/text()")=3',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => ['Invalid parameter "/1/expression": mandatory parameter is missing in function "first".']
				]
			],
			// #19.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'xmlxpath function wrong params',
					'expression' => 'xmlxpath(last(/Simple form test host/text_prototype[{#KEY}]))=0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => ['Invalid parameter "/1/expression": invalid number of parameters in function "xmlxpath".']
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'jsonpath function min fields',
					'expression' => 'jsonpath(last(/Simple form test host/text_prototype[{#KEY}]),"$.path")=0'
				]
			],
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'jsonpath function',
					'expression' => 'jsonpath(last(/Simple form test host/text_prototype[{#KEY}],#2:now-1h),"$.[0].last_name","new")=1'
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'jsonpath function error',
					'expression' => 'jsonpath(e(/Simple form test host/text_prototype[{#KEY}]),"$.path")=0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => ['Invalid parameter "/1/expression": incorrect usage of function "e".']
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'jsonpath function wrong params',
					'expression' => 'jsonpath(last(/Simple form test host/text_prototype[{#KEY}]))=0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => ['Invalid parameter "/1/expression": invalid number of parameters in function "jsonpath".']
				]
			],
			// #24.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'both jsonpath and xmlxpath functions',
					'expression' => 'jsonpath(last(/Simple form test host/text_prototype[{#KEY}]),"$path")=0'.
							' and xmlxpath(last(/Simple form test host/text_prototype[{#KEY}]),"/xpath/text()")=0'
				]
			],
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger_CheckWrongUrl',
					'expression' => 'last(/Simple form test host/someItem.uptime,#1)<0',
					'url' => 'javascript:alert(123);',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Invalid parameter "/1/url": unacceptable URL.'
					]
				]
			],
			// #26.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Simple form test host/someItem.uptime,#1)<0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect item key "someItem.uptime" provided for trigger expression on "Simple form test host".'
					]
				]
			],
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'somefunc(/Simple form test host/item-prototype-reuse,#1)<5',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Invalid parameter "/1/expression": unknown function "somefunc".'
					]
				]
			],
			// #28.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0 or {#MACRO}',
					'constructor' => [
						'text' => ['A or B', 'A', 'B'],
						'elements' => ['expr_0_61', 'expr_66_73']
					]
				]
			],
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Zabbix host/item-prototype-reuse,#1)<0 or 8 and 9',
					'constructor' => [
						'text' => ['A or (B and C)', 'Or', 'And', 'A', 'B', 'C'],
						'elements' => ['expr_0_43', 'expr_48_48', 'expr_54_54'],
						'elementError' => true,
						'element_count' => 2,
						'errors' => [
							'last(/Zabbix host/item-prototype-reuse,#1): Unknown host, no such host present in system'
						]
					]
				]
			],
			// #30.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Simple form test host/someItem.uptime,#1)<0 or 8 and 9 + last(/Simple form test host/test-item-reuse,#1)',
					'constructor' => [
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_48', 'expr_53_53', 'expr_59_109'],
						'elementError' => true,
						'element_count' => 2,
						'errors' => [
							'last(/Simple form test host/someItem.uptime,#1): Unknown host item, no such item in selected host'
						]
					]
				]
			],
			// #31.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'lasta(/Simple form test host/item-prototype-reuse,#1)<0 or 8 and 9 + last(/Simple form test host/test-item-reuse2,#1)',
					'constructor' => [
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_54', 'expr_59_59', 'expr_65_116'],
						'elementError' => true,
						'element_count' => 4,
						'errors' => [
							'lasta(/Simple form test host/item-prototype-reuse,#1): Incorrect function is used',
							'last(/Simple form test host/test-item-reuse2,#1): Unknown host item, no such item in selected host'
						]
					]
				]
			],
			// #32.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Simple form test host@/item-prototype-reuse,#1)<0',
					'constructor' => [
						'errors' => [
							'header' => 'Expression syntax error.',
							'details' => 'Cannot build expression tree: incorrect expression starting from'.
									' "last(/Simple form test host@/item-prototype-reuse,#1)<0".'
						]
					]
				]
			],
			// #33.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Simple form test host/system .uptime,#1)<0',
					'constructor' => [
						'errors' => [
							'header' => 'Expression syntax error.',
							'details' => 'Cannot build expression tree: incorrect expression starting from'.
									' "last(/Simple form test host/system .uptime,#1)<0".'
						]
					]
				]
			],
			// #34.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'lastA(/Simple form test host/item-prototype-reuse,#1)<0',
					'constructor' => [
						'errors' => [
							'header' => 'Expression syntax error.',
							'details' => 'Cannot build expression tree: incorrect expression starting from'.
									' "lastA(/Simple form test host/item-prototype-reuse,#1)<0".'
						]
					]
				]
			],
			// #35.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerSimple',
					'expression' => 'default',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			// #36.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerName',
					'expression' => 'default'
				]
			],
			// #37.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerRemove',
					'expression' => 'default',
					'formCheck' => true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			// #38.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'triggerName',
					'expression' => 'default',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Trigger prototype "triggerName" already exists on "Simple form test host".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTriggerPrototype_SimpleCreate($data) {

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$this->filterEntriesAndOpenDiscovery(self::HOST, $form);
		$this->zbxTestClickLinkTextWait(self::DISCOVERY_RULE);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create trigger prototype');
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_footer = $dialog->getFooter();

		if (isset($data['description'])) {
			$this->zbxTestInputTypeByXpath("//input[@name='name']", $data['description']);
			$description = $data['description'];
		}

		if (isset($data['expression'])) {
			switch ($data['expression']) {
				case 'default':
					$expression = 'last(/'.self::HOST.'/'.self::ITEM_KEY.'[{#KEY}],#1)=0';
					$this->zbxTestInputType('expression', $expression);
					break;
				default:
					$expression = $data['expression'];
					$this->zbxTestInputType('expression', $expression);
					break;
			}
		}

		if (isset($data['type'])) {
			$this->zbxTestCheckboxSelect('type_1');
		}

		if (isset($data['comments'])) {
			$this->zbxTestInputType('description', $data['comments']);
		}

		if (isset($data['url_name'])) {
			$this->zbxTestInputType('url_name', $data['url_name']);
		}

		if (isset($data['url'])) {
			$this->zbxTestInputType('url', $data['url']);
		}

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClickXpathWait("//*[@id='priority_0']/../label");
					break;
				case 'Information':
					$this->zbxTestClickXpathWait("//*[@id='priority_1']/../label");
					break;
				case 'Warning':
					$this->zbxTestClickXpathWait("//*[@id='priority_2']/../label");
					break;
				case 'Average':
					$this->zbxTestClickXpathWait("//*[@id='priority_3']/../label");
					break;
				case 'High':
					$this->zbxTestClickXpathWait("//*[@id='priority_4']/../label");
					break;
				case 'Disaster':
					$this->zbxTestClickXpathWait("//*[@id='priority_5']/../label");
					break;
			}
		}

		if (isset($data['status'])) {
			$this->zbxTestCheckboxSelect('status', false);
		}

		if (isset($data['constructor'])) {
			$this->zbxTestClickButtonText('Expression constructor');
			$constructor = $data['constructor'];
			if (isset($constructor['errors']) && !array_key_exists('elementError', $constructor)) {
				$this->assertMessage(TEST_BAD, $constructor['errors']['header'], $constructor['errors']['details']);
				COverlayDialogElement::find()->one()->close();
			}
			else {
				$this->query('xpath://*[@id="expression-table"]/div[1]')->waitUntilVisible()->one();
				$this->zbxTestAssertElementPresentXpath("//button[@name='test_expression']");
				$this->zbxTestAssertVisibleXpath("//div[@id='expression-row']//button[@id='and_expression']");
				$this->zbxTestAssertVisibleXpath("//div[@id='expression-row']//button[@id='or_expression']");
				$this->zbxTestAssertElementPresentXpath("//button[text()='Remove']");

				if (isset($constructor['text'])) {
					foreach ($constructor['text'] as $txt) {
						$this->zbxTestTextPresent($txt);
					}
				}

				if (isset($constructor['elements'])) {
					foreach ($constructor['elements'] as $elem) {
						$this->zbxTestAssertElementPresentId($elem);
					}
				}

				if (isset($constructor['elementError'])) {
					$count = CTestArrayHelper::get($constructor, 'element_count', 1);
					$this->assertEquals($count,
							$this->query('xpath://button['.CXPathHelper::fromClass('zi-i-negative').']')->all()->count()
					);
					$text = $this->query('xpath://tr[1]//button[@data-hintbox]')->one()
							->getAttribute('data-hintbox-contents');
					foreach ($constructor['errors'] as $error) {
						$this->assertStringContainsString($error, $text);
					}
				}
				else {
					$this->zbxTestAssertElementNotPresentXpath('//button['.CXPathHelper::fromClass('zi-i-negative').']');
				}

				COverlayDialogElement::find()->one()->close();
			}
		}

		if (!isset($data['constructor'])) {
			$dialog_footer->query('button:Add')->one()->click();
			switch ($data['expected']) {
				case TEST_GOOD:
					$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype added');
					$this->zbxTestCheckTitle('Configuration of trigger prototypes');
					$this->zbxTestAssertElementText("//tbody//a[text()='$description']", $description);
					$this->zbxTestAssertElementText("//a[text()='$description']/ancestor::tr/td[5]", $expression);
					$this->zbxTestTextPresent(self::DISCOVERY_RULE);
					break;
				case TEST_BAD:
					$this->assertMessage(TEST_BAD, $data['error_msg'], $data['errors']);
					$this->zbxTestCheckTitle('Configuration of trigger prototypes');
					$this->zbxTestAssertElementPresentXpath("//a[@id='tab_triggersTab' and text()='Trigger prototype']");
					foreach ($data['errors'] as $msg) {
						$msg = str_replace('<', '&lt;', $msg);
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(['Name', 'Expression', 'Description']);
					COverlayDialogElement::find()->one()->close();
					break;
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpen(self::HOST_LIST_PAGE);
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenDiscovery(self::HOST, $form);
			$this->zbxTestClickLinkTextWait(self::DISCOVERY_RULE);
			$this->zbxTestClickLinkTextWait('Trigger prototypes');

			$this->zbxTestClickLinkTextWait($description);
			$this->zbxTestAssertElementValue('expression', $expression);
			$getName = $this->zbxTestGetValue("//input[@name='name']");
			$this->assertEquals($getName, $description);
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT description FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['description'], $description);
			}
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT description, triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$triggerId = $row['triggerid'];
			}
			$this->zbxTestOpen(self::HOST_LIST_PAGE);
			// TODO: temporarily commented out due webdriver issue, alert is not displayed while leaving page during test execution
//			$this->zbxTestAcceptAlert();
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenDiscovery(self::HOST, $form);
			$this->zbxTestClickLinkTextWait(self::DISCOVERY_RULE);
			$this->zbxTestClickLinkTextWait('Trigger prototypes');
			$this->zbxTestCheckboxSelect("g_triggerid_$triggerId");
			$this->query('button:Delete')->one()->click();
			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype deleted');
			$this->assertEquals(0, CDBHelper::getCount("SELECT triggerid FROM triggers where description = '".$description."'"));
		}
	}

	public function getLongExpressionData() {
		return [
			// Create trigger prototype.
			[
				[
					'form_data' => [
						'Name' => 'Created trigger prototype',
						'Expression' => 'last(/'.STRING_128.'/'.STRING_2048.')=0 and last(/'.STRING_128.'/'.LONG_KEY.')=0'
					],
					'expected_db_expression' => '/^\{\d+\}=0 and \{\d+\}=0$/'
				]
			],
			// Simple update.
			[
				[
					'update' => true,
					'link_name' => 'Trigger prototype with long expression for simple update',
					'expected_db_expression' => '/^\{\d+\}=0$/'
				]
			],
			// Update.
			[
				[
					'update' => true,
					'link_name' => 'Trigger prototype with long expression for update',
					'form_data' => [
						'Name' => 'Updated trigger',
						'Expression' => 'last(/'.STRING_128.'/'.STRING_2048.')>0 and last(/'.STRING_128.'/'.LONG_KEY.')>0'
					],
					'expected_db_expression' => '/^\{\d+\}>0 and \{\d+\}>0$/'
				]
			]
		];
	}

	/**
	 * Test a special case where the host's name and item's key are very long.
	 * The trigger expression is saved using expression IDs instead of saving the whole text of the expression,
	 * so no issues due to length of host name or item key should be present regardless of their length.
	 *
	 * @dataProvider getLongExpressionData
	 */
	public function testFormTriggerPrototype_LongExpression($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&parent_discoveryid='.self::$long_key_ruleid);
		$this->page->waitUntilReady();

		// Open the correct form.
		$open_form_button = (CTestArrayHelper::get($data, 'update'))
			? 'link:'.$data['link_name']
			: 'button:Create trigger prototype';
		$this->page->query($open_form_button)->one()->click();

		// Fill form data and save.
		$dialog = COverlayDialogElement::find()->one();
		$dialog->asForm()->fill(CTestArrayHelper::get($data, 'form_data', []));
		$button = CTestArrayHelper::get($data, 'update', false) ? 'Update' : 'Add';
		$dialog->getFooter()->query('button', $button)->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Get the saved trigger's ID from UI.
		$link = (array_key_exists('form_data', $data)) ? $data['form_data']['Name'] : $data['link_name'];
		$linkElement = $this->page->query('link', $link)->one()->getAttribute('href');
		parse_str(parse_url($linkElement, PHP_URL_QUERY), $params);
		$triggerid = $params['triggerid'];

		// Get the newly saved trigger's expression, as it is saved in the DB.
		$db_expression = CDBHelper::getValue('SELECT expression FROM triggers WHERE triggerid = '.$triggerid);
		// Assert by regex that the expression is saved in DB similar to this: "{100253}=0 and {100253}=0".
		$this->assertEquals(1, preg_match($data['expected_db_expression'], $db_expression));
	}

	/**
	 * Function for filtering necessary hosts and opening their Web scenarios.
	 *
	 * @param string $name name of a host or template where triggers are opened
	 */
	private function filterEntriesAndOpenDiscovery($name, $form) {
		$this->query('button:Reset')->one()->click();
		$form->fill(['Name' => $name]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
				->getColumn('Discovery')->query('link:Discovery')->one()->click();
	}
}
