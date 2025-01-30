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

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

/**
 * @backup triggers
 *
 * @onBefore prepareTriggerData
 */
class testFormTrigger extends CLegacyWebTest {
	const HOST = 'Host for Triggers test';

	protected static $long_key_hostid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function prepareTriggerData() {
		// Create host group for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for triggers test']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create hosts for various tests.
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => self::HOST,
				'groups' => [['groupid' => $groupid]]
			],
			[
				'host' => STRING_128,
				'groups' => [['groupid' => $groupid]]
			]
		]);

		$this->assertArrayHasKey('hostids', $hosts);
		$hostid = $hosts['hostids'][0];
		self::$long_key_hostid = $hosts['hostids'][1];

		// Create items.
		$items_data = [];
		$value_types = [
			'Float' => ITEM_VALUE_TYPE_FLOAT,
			'Character' => ITEM_VALUE_TYPE_STR,
			'Unsigned' => ITEM_VALUE_TYPE_UINT64,
			'Text' => ITEM_VALUE_TYPE_TEXT
		];

		foreach ($value_types as $name => $type) {
			$items_data[] = [
				'hostid' => $hostid,
				'name' => $name.' item',
				'key_' => $name,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $type
			];
		}

		// An additional item with a long key.
		$items_data[] = [
			'name' => 'test',
			'key_' => STRING_2048,
			'hostid' => self::$long_key_hostid,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_FLOAT
		];

		CDataHelper::call('item.create', $items_data);

		// Create triggers for various tests.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'testFormTrigger1',
				'expression' => 'last(/'.self::HOST.'/Float,#1)=0',
				'priority' => 0
			],
			[
				'description' => 'Trigger with long expression for simple update',
				'expression' => 'last(/'.STRING_128.'/'.STRING_2048.')=0'
			],
			[
				'description' => 'Trigger with long expression for update',
				'expression' => 'last(/'.STRING_128.'/'.STRING_2048.')>0'
			]
		]);
	}

	// Returns layout data
	public static function layout() {
		return [
			// #0.
			[
				['constructor' => 'open', 'host' => self::HOST
				]
			],
			// #1.
			[
				['constructor' => 'open_close', 'host' => self::HOST
				]
			],
			// #2.
			[
				['constructor' => 'open', 'severity' => 'Warning', 'host' => self::HOST
				]
			],
			// #3.
			[
				['constructor' => 'open_close', 'severity' => 'Disaster', 'host' => self::HOST
				]
			],
			// #4.
			[
				['severity' => 'Not classified', 'host' => self::HOST
				]
			],
			// #5.
			[
				['severity' => 'Information', 'host' => self::HOST
				]
			],
			// #6.
			[
				['severity' => 'Warning', 'host' => self::HOST
				]
			],
			// #7.
			[
				['severity' => 'Average', 'host' => self::HOST
				]
			],
			// #8.
			[
				['severity' => 'High', 'host' => self::HOST
				]
			],
			// #9.
			[
				['severity' => 'Disaster', 'host' => self::HOST
				]
			],
			// #10.
			[
				['constructor' => 'open', 'template' => 'Inheritance test template'
				]
			],
			// #11.
			[
				['constructor' => 'open_close', 'template' => 'Inheritance test template'
				]
			],
			// #12.
			[
				['constructor' => 'open', 'severity' => 'Warning', 'template' => 'Inheritance test template'
				]
			],
			// #13.
			[
				[
					'constructor' => 'open_close',
					'severity' => 'Disaster',
					'template' => 'Inheritance test template'
				]
			],
			// #14.
			[
				['severity' => 'Not classified', 'template' => 'Inheritance test template'
				]
			],
			// #15.
			[
				['severity' => 'Information', 'template' => 'Inheritance test template'
				]
			],
			// #16.
			[
				['severity' => 'Warning', 'template' => 'Inheritance test template'
				]
			],
			// #17.
			[
				['severity' => 'Average', 'template' => 'Inheritance test template'
				]
			],
			// #18.
			[
				['severity' => 'High', 'template' => 'Inheritance test template'
				]
			],
			// #19.
			[
				['severity' => 'Disaster', 'template' => 'Inheritance test template'
				]
			],
			// #20.
			[
				['host' => self::HOST, 'form' => 'testFormTrigger1'
				]
			],
			// #21.
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTrigger1'
				]
			],
			// #22.
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			// #23.
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger2',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			// #24.
			[
				[
					'host' => self::HOST,
					'form' => 'testFormTrigger1',
					'constructor' => 'open'
				]
			],
			// #25.
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTrigger1',
					'constructor' => 'open'
				]
			],
			// #26.
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger1',
					'templatedHost' => true,
					'constructor' => 'open'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormTrigger_CheckLayout($data) {
		if (isset($data['template'])) {
			$this->zbxTestLogin('zabbix.php?action=template.list');
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenTriggers($data['template'], $form);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin(self::HOST_LIST_PAGE);
			$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$this->filterEntriesAndOpenTriggers($data['host'], $form);
		}

		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestContentControlButtonClickTextWait('Create trigger');
		}
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->assertEquals(isset($data['form']) ? 'Trigger' : 'New trigger', $dialog->getTitle());

		if (isset($data['constructor'])) {
			switch ($data['constructor']) {
				case 'open':
					$form->query('button:Expression constructor')->waitUntilClickable()->one()->click();
					$form->query('id:expression')->waitUntilNotVisible();
					break;
				case 'open_close':
					$form->query('button:Expression constructor')->waitUntilClickable()->one()->click();
					$form->query('id:expression')->waitUntilNotVisible();
					$form->query('button:Close expression constructor')->waitUntilClickable()->one()->click();
					$form->query('id:expression')->waitUntilVisible();
					break;
			}
		}

		$this->assertEquals('Trigger', $form->getSelectedTab());

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
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 255);

		if (!isset($data['constructor']) || $data['constructor'] == 'open_close') {
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
			$this->assertEquals(0, $dialog->query('button', ['id:add_expression', 'Edit', 'id:insert-macro'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);
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
			elseif (isset($data['templatedHost'])) {
				$this->assertEquals(0, $dialog->query('button', ['And', 'Or', 'Replace', 'Edit', 'Insert expression'])
						->all()->filter(CElementFilter::CLICKABLE)->count()
				);
			}
			else {
				$this->assertFalse($this->query("xpath://div[@id='expression-row']//button[@id='add_expression']")
						->one()->isDisplayed()
				);
				$this->assertEquals(2, $dialog->query('button', ['Edit', 'Insert expression'])
						->all()->filter(CElementFilter::CLICKABLE)->count()
				);
				$this->assertEquals(0, $dialog->query('button', ['And', 'Or', 'Replace'])->all()
						->filter(CElementFilter::CLICKABLE)->count()
				);
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

		$entry_name = $dialog->asForm()->getField('Menu entry name');

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

		$this->zbxTestTextPresent('Enabled');
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
			$this->assertFalse($dialog_footer->query('button:Delete')->one()->isClickable());
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
		}
		else {
			$this->zbxTestAssertElementText("//button[@id='add-dep-template-trigger']", 'Add');
			$this->zbxTestAssertElementText("//button[@id='add-dep-host-trigger']", 'Add host trigger');
		}

		COverlayDialogElement::find()->one()->close();
	}

	public function testFormTrigger_SimpleUpdate() {
		$sqlTriggers = 'select * from triggers order by triggerid';
		$sqlFunctions = 'select * from functions order by functionid';

		$oldHashTriggers = CDBHelper::getHash($sqlTriggers);
		$oldHashFunctions = CDBHelper::getHash($sqlFunctions);

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$this->filterEntriesAndOpenTriggers(self::HOST, $form);
		$this->zbxTestClickLinkTextWait('testFormTrigger1');
		COverlayDialogElement::find()->waitUntilReady()->one()->query('button:Update')->one()->click();
		COverlayDialogElement::ensureNotPresent();
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger updated');
		$this->zbxTestTextPresent('testFormTrigger1');
		$this->zbxTestCheckHeader('Triggers');

		$this->assertEquals($oldHashTriggers, CDBHelper::getHash($sqlTriggers));
		$this->assertEquals($oldHashFunctions, CDBHelper::getHash($sqlFunctions));
	}

	// Returns create data
	public static function create() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'error_msg' => 'Cannot add trigger',
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
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect value for field "expression": cannot be empty.'
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'expression' => '6 & 0 | 0',
					'error_msg' => 'Cannot add trigger',
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
					'expression' => '6 and 0 or 0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": trigger expression must contain at least one /host/key reference.'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{self::HOST}',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": incorrect expression starting from "{self::HOST}".'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_simple',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'HTML_symbols&#8704;&forall;&#8734;&ne;&sup;&Eta;&#937;&#958;&pi;&#8194;&mdash;&#8364;&loz;',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #7.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'ASCII_characters&#33;&#40;&#51;&#101;&#10;&#25;',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #8.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_allFields',
					'type' => true,
					'comments' => 'MyTrigger_allFields -Description textbox for comments',
					'url' => 'http://MyTrigger_allFields.com',
					'severity' => 'Disaster',
					'status' => false,
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #9.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '1234567890',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '0',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'a?aa+',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '}aa]a{',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '-aaa=%',
					'expression' => 'last(/'.self::HOST.'/Unsigned,#1)<0',
					'formCheck' => true
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa,;:',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa><.',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa*&_',
					'expression' => 'last(/'.self::HOST.'/Unsigned,#1)<0',
					'formCheck' => true
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa#@!',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'description' => '([)$^',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<0',
					'formCheck' => true
				]
			],
			// #19.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new'.
							' value, if this value is part of this expression. If time based functions are used in the'.
							' expression, it is recalculated every 30 seconds by a zabbix timer process.',
					'url_name' => 'Trigger context menu name for trigger URL.',
					'url' => 'https://www.zabbix.com',
					'severity' => 'High',
					'status' => false
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_CheckURL',
					'expression' => 'last(/'.self::HOST.'/Float,#1)<4',
					'url_name' => 'MyTrigger: menu name',
					'url' => 'triggers.php'
				]
			],
			// #21.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger_CheckUrl',
					'expression' => 'last(/'.self::HOST.'/Unsigned,#1)<5',
					'url' => 'javascript:alert(123);',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/url": unacceptable URL.'
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Zabbix host/Unsigned,#1)<0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect trigger expression. Host "Zabbix host" does not exist or you have no access to this host.'
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/'.self::HOST.'/someItem.uptime,#1)<0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect item key "someItem.uptime" provided for trigger expression on '.
								CXPathHelper::escapeQuotes(self::HOST).'.'
					]
				]
			],
			// #24.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'somefunc(/'.self::HOST.'/Float,#1)<0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": unknown function "somefunc".'
					]
				]
			],
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/'.self::HOST.'/Float,#1) or {#MACRO}',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": incorrect expression starting from "{#MACRO}".'
					]
				]
			],
			// #26.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/'.self::HOST.'/Float,#1) or {#MACRO}',
					'constructor' => [
						'text' => ['A or B', 'A', 'B'],
						'elements' => ['expr_0_37', 'expr_42_49']
					]
				]
			],
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/Zabbix host/Float,#1)<0 or 8 and 9',
					'constructor' => [
						'text' => ['A or (B and C)', 'Or', 'And', 'A', 'B', 'C'],
						'elements' => ['expr_0_28', 'expr_33_33', 'expr_39_39'],
						'elementError' => true,
						'element_count' => 2,
						'errors' => [
							'last(/Zabbix host/Float,#1): Unknown host, no such host present in system'
						]
					]
				]
			],
			// #28.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/'.self::HOST.'/someItem,#1)<0 or 8 and 9 + last(/'.self::HOST.'/Float,#1)',
					'constructor' => [
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_42', 'expr_47_47', 'expr_53_94'],
						'elementError' => true,
						'element_count' => 2,
						'errors' => [
							'last(/'.self::HOST.'/someItem,#1): Unknown host item, no such item in selected host'
						]
					]
				]
			],
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'lasta(/'.self::HOST.'/Float,#1)<0 or 8 and 9 + last(/'.self::HOST.'/Float2,#1)',
					'constructor' => [
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_40', 'expr_45_45', 'expr_51_93'],
						'elementError' => true,
						'element_count' => 4,
						'errors' => [
							'lasta(/'.self::HOST.'/Float,#1): Incorrect function is used',
							'last(/'.self::HOST.'/Float2,#1): Unknown host item, no such item in selected host'
						]
					]
				]
			],
			// #30.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/'.self::HOST.'@/Float,#1)<0',
					'constructor' => [
						'errors' => [
							'header' => 'Expression syntax error.',
							'details' => 'Cannot build expression tree: incorrect expression starting from'.
									' "last(/'.self::HOST.'@/Float,#1)<0".'
						]
					]
				]
			],
			// #31.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'last(/'.self::HOST.'/system .uptime,#1)<0',
					'constructor' => [
						'errors' => [
							'header' => 'Expression syntax error.',
							'details' => 'Cannot build expression tree: incorrect expression starting from '.
									'"last(/'.self::HOST.'/system .uptime,#1)<0".'
						]
					]
				]
			],
			// #32.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => 'lastA(/'.self::HOST.'/Float,#1)<0',
					'constructor' => [
						'errors' => [
							'header' => 'Expression syntax error.',
							'details' => 'Cannot build expression tree: incorrect expression starting from '.
									'"lastA(/'.self::HOST.'/Float,#1)<0".'
						]
					]
				]
			],
			// #33.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_rate_good',
					'expression' => 'rate(/'.self::HOST.'/Unsigned,2m:now-1h)>0.5'
				]
			],
			// #34.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger_rate_bad_second_par',
					'expression' => 'rate(/'.self::HOST.'/Float,test)>0.5',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						"Invalid parameter \"/1/expression\": incorrect expression starting from ".
								"\"rate(/".self::HOST."/Float,test)>0.5\"."
					]
				]
			],
			// #35.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger_rate_no_slash',
					'expression' => 'rate('.self::HOST.'/Float,1h)>0.5',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						"Invalid parameter \"/1/expression\": incorrect expression starting from ".
								"\"rate(".self::HOST."/Float,1h)>0.5\"."
					]
				]
			],
			// #36.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger_rate_bad_key',
					'expression' => 'rate(/'.self::HOST.'/test,1h)>0.5',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect item key "test" provided for trigger expression on '.
								CXPathHelper::escapeQuotes(self::HOST)
					]
				]
			],
			// #37.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'jsonpath Trigger all fields',
					'expression' => 'jsonpath(last(/'.self::HOST.'/Text,#10:now),"$.[0].last_name","LastName")="Penddreth"',
					'formCheck' => true
				]
			],
			// #38.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'jsonpath Trigger min',
					'expression' => 'jsonpath(last(/'.self::HOST.'/Text),"$.last_name")<>"Test"'
				]
			],
			// #39.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Trigger wrong json function',
					'expression' => 'jsonpath(max(/'.self::HOST.'/Character,#1:now-5m),"$.[0].last_name","last_name")="Test"',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect item value type "Character" provided for trigger function "max".'
					]
				]
			],
			// #40.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Missing json parameters',
					'expression' => 'jsonpath(last(/'.self::HOST.'/Text,#1:now-5m))="Test"',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": invalid number of parameters in function "jsonpath".'
					]
				]
			],
			// #41.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Wrong json parameters',
					'expression' => 'jsonpath(last(/'.self::HOST.'/Text,20),"$.[0].last_name")="Test"',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": invalid second parameter in function "last".'
					]
				]
			],
			// #42.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Incorrect json expression',
					'expression' => 'jsonpath(last(/'.self::HOST.'/Character,#5-now),"$.[0].last_name","last")<"Test"',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": incorrect expression starting from "jsonpath(last(/'.
								self::HOST.'/Character,#5-now),"$.[0].last_name","last")<"Test"".'
					]
				]
			],
			// #43.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'xml xpath Trigger all fields',
					'expression' => 'xmlxpath(last(/'.self::HOST.'/Text,#4:now-1m),"/zabbix_export/version/text()",5.0)=7.0',
					'formCheck' => true
				]
			],
			// #44.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'xml xpath Trigger min fields',
					'expression' => 'xmlxpath(last(/'.self::HOST.'/Character),"/zabbix_export/version")=1'
				]
			],
			// #45.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Trigger wrong xml function',
					'expression' => 'xmlxpath(min(/'.self::HOST.'/Text,#4:now-1m),"/zabbix_export/version/text()",5.0)=7.0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect item value type "Text" provided for trigger function "min".'
					]
				]
			],
			// #46.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Missing xml parameters',
					'expression' => 'xmlxpath(last(/'.self::HOST.'/Text,#1:now-5m))="Test"',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": invalid number of parameters in function "xmlxpath".'
					]
				]
			],
			// #47.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Wrong xml parameters',
					'expression' => 'xmlxpath(last(/'.self::HOST.'/Text,4),5.0)=7.0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": invalid second parameter in function "last".'
					]
				]
			],
			// #48.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'json and xmlpath expression',
					'expression' => 'jsonpath(last(/testPageHistory_CheckLayout/character[item_testpagehistory_checklayout]),'.
							'"$.[0].last_name")="Test" or xmlxpath(last(/'.self::HOST.'/Text),"/zabbix_export/version/text()")="test"'
				]
			],
			// #49.
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'Double json expression',
					'expression' => 'jsonpath(last(/testPageHistory_CheckLayout/character[item_testpagehistory_checklayout]),'.
							'"$.[0].last_name")="Test" and jsonpath(last(/'.self::HOST.'/Text),"$.test.last")=4'
				]
			],
			// #50.
			[
				[
					'expected' => TEST_BAD,
					'description' => 'Incorrect json expression',
					'expression' => 'xmlxpath(last(/'.self::HOST.'/Text,#3-now),"/zabbix_export/version/text()",5.0)=7.0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Invalid parameter "/1/expression": incorrect expression starting from "xmlxpath(last(/'.
								self::HOST.'/Text,#3-now),"/zabbix_export/version/text()",5.0)=7.0".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTrigger_SimpleCreate($data) {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$this->filterEntriesAndOpenTriggers(self::HOST, $form);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestContentControlButtonClickTextWait('Create trigger');
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->assertEquals('New trigger', $dialog->getTitle());

		if (isset($data['description'])) {
			$this->zbxTestInputTypeWait('name', $data['description']);
		}
		$description = $this->zbxTestGetValue("//input[@id='name']");

		if (isset($data['expression'])) {
			$this->zbxTestInputType('expression', $data['expression']);
		}
		$expression = $this->zbxTestGetValue("//textarea[@id='expression']");

		if (isset($data['type'])) {
			$this->zbxTestClickXpathWait("//label[@for='type_1']");
			$type = 'checked';
		}
		else {
			$type = 'unchecked';
		}

		if (isset($data['comments'])) {
			$this->zbxTestInputType('description', $data['comments']);
		}
		$comments = $this->zbxTestGetValue("//textarea[@id='description']");

		if (isset($data['url_name'])) {
			$this->zbxTestInputType('url_name', $data['url_name']);
		}
		$url_name = $this->zbxTestGetValue("//input[@id='url_name']");

		if (isset($data['url'])) {
			$this->zbxTestInputType('url', $data['url']);
		}
		$url = $this->zbxTestGetValue("//input[@id='url']");

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
			$severity = $data['severity'];
		}
		else {
			$severity = 'Not classified';
		}

		if (isset($data['status'])) {
			$this->zbxTestCheckboxSelect('status', false);
			$status = 'unchecked';
		}
		else {
			$status = 'checked';
		}

		if (isset($data['constructor'])) {
			$this->zbxTestClickButtonText('Expression constructor');

			$constructor = $data['constructor'];
			if (isset($constructor['errors']) && !array_key_exists('elementError', $constructor)) {
				$this->assertMessage(TEST_BAD, $constructor['errors']['header'], $constructor['errors']['details']);
				COverlayDialogElement::find()->one()->close();
			}
			else {
				$this->zbxTestAssertVisibleXpath("//div[@id='expression-constructor-buttons']//button[@id='and_expression']");
				$this->zbxTestAssertVisibleXpath("//div[@id='expression-constructor-buttons']//button[@id='or_expression']");
				$this->zbxTestAssertVisibleXpath("//div[@id='expression-constructor-buttons']//button[@id='replace_expression']");

				if (isset($constructor['text'])) {
					foreach ($constructor['text'] as $txt) {
						$this->query('xpath://div[@id="expression-table"]/div[1]')->waitUntilVisible()->one();
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
					$text = $this->query("xpath://tr[1]//button[@data-hintbox]")->one()
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
			$dialog->getFooter()->query('button:Add')->one()->click();
			$this->page->waitUntilReady();
			switch ($data['expected']) {
				case TEST_GOOD:
					$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger added');
					$this->zbxTestCheckTitle('Configuration of triggers');
					$this->zbxTestAssertElementText("//tbody//a[text()='$description']", $description);
					$this->zbxTestAssertElementText("//a[text()='$description']/ancestor::tr/td[6]", $expression);
					break;
				case TEST_BAD:
					$this->assertMessage(TEST_BAD, $data['error_msg'], $data['errors']);
					$this->zbxTestTextPresent('Name');
					$this->zbxTestTextPresent('Expression');
					$this->zbxTestTextPresent('Description');
					COverlayDialogElement::find()->one()->close();
					break;
			}

			if (isset($data['formCheck'])) {
				$this->zbxTestClickLinkTextWait($description);
				$this->zbxTestAssertElementValue('name', $description);
				$this->zbxTestAssertElementValue('expression', $expression);

				if ($type == 'checked') {
					$this->assertTrue($this->zbxTestCheckboxSelected('type_1'));
				}
				else {
					$this->assertTrue($this->zbxTestCheckboxSelected('type_0'));
				}

				$this->zbxTestAssertElementValue('description', $comments);

				$this->zbxTestAssertElementValue('url_name', $url_name);

				$this->zbxTestAssertElementValue('url', $url);

				switch ($severity) {
					case 'Not classified':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_0'));
						break;
					case 'Information':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_1'));
						break;
					case 'Warning':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_2'));
						break;
					case 'Average':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_3'));
						break;
					case 'High':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_4'));
						break;
					case 'Disaster':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_5'));
						break;
				}

				if ($status == 'checked') {
					$this->assertTrue($this->zbxTestCheckboxSelected('status'));
				}
				else {
					$this->assertFalse($this->zbxTestCheckboxSelected('status'));
				}

				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	public function getLongExpressionData() {
		return [
			// Create trigger.
			[
				[
					'form_data' => [
						'Name' => 'Created trigger',
						'Expression' => 'last(/'.STRING_128.'/'.STRING_2048.')=0 and last(/'.STRING_128.'/'.STRING_2048.')=0'
					],
					'expected_db_expression' => '/^\{\d+\}=0 and \{\d+\}=0$/'
				]
			],
			// Simple update.
			[
				[
					'update' => true,
					'link_name' => 'Trigger with long expression for simple update',
					'expected_db_expression' => '/^\{\d+\}=0$/'
				]
			],
			// Update.
			[
				[
					'update' => true,
					'link_name' => 'Trigger with long expression for update',
					'form_data' => [
						'Name' => 'Updated trigger',
						'Expression' => 'last(/'.STRING_128.'/'.STRING_2048.')>0 and last(/'.STRING_128.'/'.STRING_2048.')>0'
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
	public function testFormTrigger_LongExpression($data) {
		$this->page->login()->open('zabbix.php?action=trigger.list&filter_set=1&context=host&filter_hostids[0]='.
				self::$long_key_hostid
		);
		$this->page->waitUntilReady();

		// Open the correct form.
		$open_form_button = (CTestArrayHelper::get($data, 'update'))
			? 'link:'.$data['link_name']
			: 'button:Create trigger';
		$this->page->query($open_form_button)->one()->click();

		// Fill form data and save.
		$dialog = COverlayDialogElement::find()->one();
		$dialog->asForm()->fill(CTestArrayHelper::get($data, 'form_data', []));
		$button = CTestArrayHelper::get($data, 'update') ? 'Update' : 'Add';
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
	private function filterEntriesAndOpenTriggers($name, $form) {
		$table = $this->query('xpath://table[@class="list-table"]')->asTable()->one();
		$this->query('button:Reset')->one()->click();
		$form->fill(['Name' => $name]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$table->waitUntilReloaded();
		$table->findRow('Name', $name)->getColumn('Triggers')->query('link:Triggers')->one()->click();
	}
}
