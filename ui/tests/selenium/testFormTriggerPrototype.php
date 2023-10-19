<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup triggers
 */
class testFormTriggerPrototype extends CLegacyWebTest {

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

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the form test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testFormDiscoveryRule';

	/**
	 * The name of the form test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRuleTemplate = 'testInheritanceDiscoveryRule';

	/**
	 * The name of the test discovery rule key created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryKey = 'discovery-rule-form';

	/**
	 * The name of the test item prototype within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'testFormItemReuse';

	/**
	 * The name of the test item prototype key within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKey = 'item-prototype-reuse';

	// Returns layout data
	public static function layout() {
		return [
			[
				['constructor' => 'open', 'host' => 'Simple form test host']
			],
			[
				['constructor' => 'open_close', 'host' => 'Simple form test host']
			],
			[
				['constructor' => 'open', 'severity' => 'Warning', 'host' => 'Simple form test host']
			],
			[
				['constructor' => 'open_close', 'severity' => 'Disaster', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Not classified', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Information', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Warning', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Average', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'High', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Disaster', 'host' => 'Simple form test host']
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open_close'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1'
				]
			],
			[
				['constructor' => 'open', 'template' => 'Inheritance test template']
			],
			[
				['constructor' => 'open_close', 'template' => 'Inheritance test template']
			],
			[
				['constructor' => 'open', 'severity' => 'Warning', 'template' => 'Inheritance test template']
			],
			[
				[
					'constructor' => 'open_close',
					'severity' => 'Disaster',
					'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Not classified', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Information', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Warning', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Average', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'High', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Disaster', 'template' => 'Inheritance test template']
			],
			[
				['host' => 'Simple form test host', 'form' => 'testFormTriggerPrototype1']
			],
			[
				['template' => 'Inheritance test template', 'form' => 'testInheritanceTriggerPrototype1']
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTriggerPrototype1',
					'constructor' => 'open'
				]
			],
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
			$discoveryRule = $this->discoveryRuleTemplate;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin(self::HOST_LIST_PAGE);
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenDiscovery($data['host'], $form);
			if (!isset($data['templatedHost'])) {
				$discoveryRule = $this->discoveryRule;
			}
			else {
				$discoveryRule = $this->discoveryRuleTemplate;
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
			$this->zbxTestClickButtonText('Expression constructor');
			// Wait for expression constructor to open, textarea is disabled and its id has changed to 'expr_temp'.
			$dialog->query('id:expr_temp')->waitUntilVisible();

			if ($data['constructor'] === 'open_close') {
				$this->zbxTestClickButtonText('Close expression constructor');
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
		$hint = $this->query('xpath:.//div[@class="overlay-dialogue"]')->waitUntilPresent()->one();

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
			$this->assertEquals(4, $dialog_footer->query('button',['Update', 'Clone', 'Delete', 'Cancel'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);
		}
		elseif (isset($data['templatedHost'])) {
			$this->assertEquals(3, $dialog_footer->query('button',['Update', 'Clone', 'Cancel'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);
			$this->assertEquals(1, $dialog_footer->query('button:Delete')->all()
					->filter(CElementFilter::NOT_CLICKABLE)->count()
			);
			$this->assertTrue($this->zbxTestCheckboxSelected('recovery_mode_0'));
			$this->zbxTestAssertElementPresentXpath("//input[@id='recovery_mode_0'][@disabled]");
		}
		else {
			$this->assertEquals(2, $dialog_footer->query('button',['Add', 'Cancel'])->all()
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
		return CDBHelper::getDataProvider("select * from triggers t left join functions f on f.triggerid=t.triggerid where f.itemid='23804' and t.description LIKE 'testFormTriggerPrototype%'");
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
		$this->filterEntriesAndOpenDiscovery($this->host, $form);
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');

		$this->zbxTestClickLinkTextWait($description);
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->query('button:Update')->one()->click();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype updated');
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent($this->discoveryRule);
		$this->zbxTestTextPresent($description);
		$this->assertEquals($oldHashTriggers, CDBHelper::getHash($sqlTriggers));
	}

	public static function create() {
		return [
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
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_sysUptime',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '1234567890',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'a?aa+',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '}aa]a{',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '-aaa=%',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa,;:',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa><.',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa*&_',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa#@!',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '([)$^',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<0'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new value, if this value is part of this expression. If time based functions are used in the expression, it is recalculated every 30 seconds by a zabbix timer process. ',
					'url_name' => 'Trigger context menu name for trigger URL.',
					'url' => 'https://www.zabbix.com',
					'severity' => 'High',
					'status' => false
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_CheckUrl',
					'expression' => 'last(/Simple form test host/item-prototype-reuse[{#KEY}],#1)<5',
					'url_name' => 'MyTrigger: menu name',
					'url' => 'index.php'
				]
			],
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
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerSimple',
					'expression' => 'default',
					'formCheck' =>true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerName',
					'expression' => 'default'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerRemove',
					'expression' => 'default',
					'formCheck' =>true,
					'dbCheck' => true,
					'remove' => true
				]
			],
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
		$this->filterEntriesAndOpenDiscovery($this->host, $form);
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
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
					$expression = 'last(/'.$this->host.'/'.$this->itemKey.'[{#KEY}],#1)=0';
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
					foreach($constructor['text'] as $txt) {
						$this->zbxTestTextPresent($txt);
					}
				}

				if (isset($constructor['elements'])) {
					foreach($constructor['elements'] as $elem) {
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
					$this->zbxTestTextPresent($this->discoveryRule);
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
			$this->filterEntriesAndOpenDiscovery($this->host, $form);
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
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
			$this->zbxTestAcceptAlert();
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenDiscovery($this->host, $form);
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
			$this->zbxTestClickLinkTextWait('Trigger prototypes');
			$this->zbxTestCheckboxSelect("g_triggerid_$triggerId");
			$this->query('button:Delete')->one()->click();
			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype deleted');
			$this->assertEquals(0, CDBHelper::getCount("SELECT triggerid FROM triggers where description = '".$description."'"));
		}
	}

	/**
	* Function for filtering necessary hosts and opening their Web scenarios.
	*
	* @param string    $name    name of a host or template where triggers are opened
	*/
	private function filterEntriesAndOpenDiscovery($name, $form) {
		$this->query('button:Reset')->one()->click();
		$form->fill(['Name' => $name]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
				->getColumn('Discovery')->query('link:Discovery')->one()->click();
	}
}
