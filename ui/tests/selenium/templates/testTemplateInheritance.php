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

use Facebook\WebDriver\WebDriverBy;

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testTemplateInheritance extends CLegacyWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $templateName = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $hostName = 'Template inheritance test host';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function testTemplateInheritance_linkHost(){
		$sql = "select hostid from hosts where host='Linux by Zabbix agent';";
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->zbxTestCheckTitle('Configuration of hosts');
		$this->filterEntriesAndOpenObjects($this->hostName, 'Name', $this->hostName);
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$this->zbxTestClickButtonMultiselect('add_templates_');
		$this->zbxTestLaunchOverlayDialog('Templates');
		COverlayDialogElement::find()->all()->last()->setDataContext('Templates');
		$this->zbxTestClickLinkTextWait('Linux by Zabbix agent');
		$this->zbxTestTextPresent('Linux by Zabbix agent');
		$form->submit();

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$sql = 'select hosttemplateid from hosts_templates where templateid='.$hostid.' AND hostid=15001';
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public static function dataCreate() {
	// result, template, itemName, keyName, errorMsg
		return [
			[
				TEST_GOOD,
				'Inheritance test template',
				'Test LLD item1',
				'test-general-item',
				[]
			],
			// Duplicated item on Template inheritance test host
			[
				TEST_BAD,
				'Inheritance test template',
				'testInheritance',
				'key-item-inheritance',
				[
					'Cannot inherit item with key "key-item-inheritance" of template "Inheritance test template" to host '.
						'"Template inheritance test host", because an item with the same key is already inherited '.
						'from template "Inheritance test template 2".'
				]
			],
			// Item added to Template inheritance test host
			[
				TEST_GOOD,
				'Inheritance test template for unlink',
				'Test LLD item2',
				'test-additional-item',
				[]
			]
		];
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testTemplateInheritance_Create($result, $template, $itemName, $keyName, $errorMsgs) {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$template.'&filter_set=1');
		$this->zbxTestCheckHeader('Templates');

		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Items')->waitUntilVisible()->one()->click();
		$this->zbxTestContentControlButtonClickTextWait('Create item');
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		$this->zbxTestInputTypeWait('name', $itemName);
		$this->zbxTestInputType('key', $keyName);
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestDropdownSelect('value_type', 'Numeric (unsigned)');
		$this->zbxTestInputType('units', 'units');
		$this->zbxTestInputTypeOverwrite('delay', '33s');
		$this->zbxTestInputTypeOverwrite('history', '54d');
		$this->zbxTestInputTypeOverwrite('trends', '55d');
		$this->zbxTestInputType('description', 'description');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$dialog->getFooter()->query('button:Add')->one()->click();

		switch ($result) {
			case TEST_GOOD:
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot add item']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestCheckHeader('Items');
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestCheckHeader('Items');
				foreach ($errorMsgs as $msg) {
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add item');
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent('Host');
				$this->zbxTestTextPresent('Name');
				$this->zbxTestTextPresent('Key');
				break;
		}

		if ($result === TEST_GOOD) {
			// check that the inherited item matches the original
			$this->zbxTestOpen(self::HOST_LIST_PAGE);
			$this->filterEntriesAndOpenObjects($this->hostName, 'Items', 'Items');
			$this->zbxTestCheckHeader('Items');
			$this->zbxTestAssertElementText("//a[text()='".$itemName."']/parent::td", "$template: $itemName");
			$this->zbxTestClickLinkTextWait($itemName);
			$this->zbxTestAssertElementValue('name', $itemName);
			$this->zbxTestAssertElementValue('key', $keyName);
			$this->zbxTestDropdownAssertSelected('type', 'Simple check');
			$this->zbxTestDropdownAssertSelected('value_type', 'Numeric (unsigned)');
			$this->zbxTestAssertElementValue('units', 'units');
			$this->zbxTestAssertElementValue('delay', '33s');
			$this->zbxTestAssertElementValue('history', '54d');
			$this->zbxTestAssertElementValue('trends', '55d');
			$this->zbxTestAssertElementText('//*[@name="description"]', 'description');
			$this->zbxTestTextPresent('Parent items');
			$this->zbxTestTextPresent($template);
		}

		COverlayDialogElement::find()->one()->close();
	}

	public function testTemplateInheritance_unlinkHost(){
		$template = 'Inheritance test template for unlink';
		$sql = "select hostid from hosts where host='Inheritance test template for unlink';";
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Name', $this->hostName);
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		$table = $form->query('id:linked-templates')->asTable()->one()->waitUntilVisible();
		$table->findRow('Name', $template)
				->getColumn('Actions')->query('button:Unlink and clear')->one()->click();
		$this->assertFalse($table->findRow('Name', $template)->isValid());
		$form->submit();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host updated');

		$sql = 'select hosttemplateid from hosts_templates where templateid='.$hostid.'';
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * Creates a new trigger on the template and checks that the inherited trigger matches the original.
	 *
	 */
	public function testTemplateInheritance_CreateTrigger() {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$this->templateName.'&filter_set=1');

		// create a trigger
		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Triggers')->waitUntilVisible()->one()->click();
		$this->zbxTestContentControlButtonClickTextWait('Create trigger');
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestInputTypeWait('name', 'Test LLD trigger1');
		$this->zbxTestInputType('expression', 'last(/Inheritance test template/key-item-inheritance-test,#1)=0');
		$this->zbxTestCheckboxSelect('type_1');
		$this->zbxTestInputType('description', 'comments');
		$this->zbxTestInputType('url', 'zabbix.php');
		$this->zbxTestClickXpath("//label[@for='priority_2']");
		$this->zbxTestCheckboxSelect('status', false);

		$dialog->getFooter()->query('button:Add')->one()->click();
		$dialog->ensureNotPresent();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger added');

		// check that the inherited trigger matches the original
		$this->zbxTestOpen(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Triggers', 'Triggers');
		$this->zbxTestAssertElementText("//a[text()='Test LLD trigger1']/parent::td", "$this->templateName: Test LLD trigger1");
		$this->zbxTestClickLinkTextWait('Test LLD trigger1');
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestAssertElementValue('name', 'Test LLD trigger1');
		$this->zbxTestAssertElementValue('expression', 'last(/Template inheritance test host/key-item-inheritance-test,#1)=0');
		$this->assertTrue($this->zbxTestCheckboxSelected('recovery_mode_0'));
		$this->zbxTestAssertElementPresentXpath("//input[@id='recovery_mode_0'][@readonly]");
		$this->zbxTestAssertElementText('//*[@name="description"]', 'comments');
		$this->zbxTestAssertElementValue('url', 'zabbix.php');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_2'));
		$this->assertFalse($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestTextPresent('Parent triggers');
		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Creates a new graph on the template and checks that the inherited graph matches the original.
	 *
	 */
	public function testTemplateInheritance_CreateGraph() {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$this->templateName.'&filter_set=1');

		// create a graph
		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Graphs')->waitUntilVisible()->one()->click();
		$this->zbxTestContentControlButtonClickTextWait('Create graph');

		$this->zbxTestInputTypeWait('name', 'Test LLD graph1');
		$this->zbxTestInputType('width', '950');
		$this->zbxTestInputType('height', '250');
		$this->zbxTestDropdownSelect('graphtype', 'Normal');
		$this->zbxTestCheckboxSelect('show_legend', false);
		$this->assertFalse($this->zbxTestCheckboxSelected('show_legend'));
		$this->zbxTestCheckboxSelect('show_work_period', false);
		$this->zbxTestCheckboxSelect('show_triggers', false);
		$this->zbxTestCheckboxSelect('visible_percent_left');
		$this->zbxTestCheckboxSelect('visible_percent_right');
		$this->zbxTestInputType('percent_left', '4');
		$this->zbxTestInputType('percent_right', '5');
		$this->zbxTestDropdownSelect('ymin_type', 'Calculated');
		$this->zbxTestDropdownSelect('ymax_type', 'Calculated');

		$this->zbxTestClick('add_item');
		$this->zbxTestLaunchOverlayDialog('Items');
		$this->zbxTestClickLinkText('testInheritanceItem1');
		$this->zbxTestClickWait('add');
		$this->assertMessage(TEST_GOOD,'Graph added');

		// check that the inherited graph matches the original
		$this->zbxTestOpen(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Graphs', 'Graphs');
		$this->zbxTestAssertElementText("//a[text()='Test LLD graph1']/parent::td", "$this->templateName: Test LLD graph1");
		$this->zbxTestClickLinkTextWait('Test LLD graph1');

		$this->zbxTestAssertElementValue('name', 'Test LLD graph1');
		$this->zbxTestAssertElementValue('width', '950');
		$this->zbxTestAssertElementValue('height', '250');
		$this->zbxTestDropdownAssertSelected('graphtype', 'Normal');
		$this->assertFalse($this->zbxTestCheckboxSelected('show_legend'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_work_period'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_triggers'));
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_left'));
		$this->zbxTestAssertElementValue('percent_left', '4');
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_right'));
		$this->zbxTestAssertElementValue('percent_right', '5');
		$this->zbxTestDropdownAssertSelected('ymin_type', 'Calculated');
		$this->zbxTestDropdownAssertSelected('ymax_type', 'Calculated');
		$this->zbxTestTextPresent('Parent graphs');
		$this->zbxTestTextPresent($this->hostName.': testInheritanceItem1');
	}

	/**
	 * Creates a new LLD rule on the template and checks that the inherited LLD rule matches the original.
	 *
	 */
	public function testTemplateInheritance_CreateDiscovery() {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$this->templateName.'&filter_set=1');

		// create an LLD rule
		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Discovery')->waitUntilVisible()->one()->click();
		$this->zbxTestContentControlButtonClickTextWait('Create discovery rule');

		$this->zbxTestInputTypeWait('name', 'Test LLD');
		$this->zbxTestInputType('key', 'test-lld');
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestInputType('delay', '31s');
		$this->zbxTestInputType('lifetime', '32d');
		$this->zbxTestInputType('description', 'description');
		$this->zbxTestInputType('delay_flex_0_delay', '50s');
		$this->zbxTestInputType('delay_flex_0_period', '1-7,00:00-24:00');
		$this->zbxTestClickWait('interval_add');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$this->zbxTestClickWait('add');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'Discovery rule created');

		// check that the inherited rule matches the original
		$this->zbxTestOpen(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Discovery', 'Discovery');
		$this->zbxTestAssertElementText("//a[text()='Test LLD']/parent::td", "$this->templateName: Test LLD");
		$this->zbxTestClickLinkTextWait('Test LLD');

		$this->zbxTestAssertElementValue('name', 'Test LLD');
		$this->zbxTestAssertElementValue('key', 'test-lld');
		$this->zbxTestDropdownAssertSelected('type', 'Simple check');
		$this->zbxTestAssertElementValue('delay', '31s');
		$this->zbxTestAssertElementValue('lifetime', '32d');
		$this->zbxTestAssertElementValue('delay_flex_0_delay', '50s');
		$this->zbxTestAssertElementValue('delay_flex_0_period', '1-7,00:00-24:00');
		$this->zbxTestAssertElementText('//*[@name="description"]', 'description');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestTextPresent('Parent discovery rules');
		$this->zbxTestTextPresent($this->templateName);
	}

	/**
	 * Creates a new item prototype on the template and checks that the inherited item prototype matches
	 * the original.
	 *
	 */
	public function testTemplateInheritance_CreateItemPrototype() {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$this->templateName.'&filter_set=1');

		// create an item prototype
		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Discovery')->waitUntilVisible()->one()->click();
		$this->zbxTestClickLinkTextWait('testInheritanceDiscoveryRule');
		$this->zbxTestClickLinkTextWait('Item prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create item prototype');
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();

		$this->zbxTestInputTypeWait('name', 'Test LLD item');
		$this->zbxTestInputType('key', 'test-lld-item[{#KEY}]');
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestDropdownSelect('value_type', 'Numeric (unsigned)');
		$this->zbxTestInputType('units', 'units');
		$this->zbxTestInputType('delay', '33s');
		$this->zbxTestInputType('history', '54d');
		$this->zbxTestInputType('trends', '55d');
		$this->zbxTestInputType('description', 'description');
		$form->fill(['Value mapping' => 'Template value mapping']);
		$this->zbxTestCheckboxSelect('status', false);
		$this->zbxTestInputType('delay_flex_0_delay', '50s');
		$this->zbxTestInputType('delay_flex_0_period', '1-7,00:00-24:00');
		$form->getFieldContainer('Custom intervals')->query('button:Add')->waitUntilClickable()->one()->click();

		$dialog->getFooter()->query('button:Add')->one()->click();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item prototype added');
		$this->zbxTestTextPresent('Test LLD item');

		// check that the inherited item prototype matches the original
		$this->zbxTestOpen(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Discovery', 'Discovery');
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');
		$this->zbxTestClickLinkTextWait('testInheritanceDiscoveryRule');
		$this->zbxTestClickLinkTextWait('Item prototypes');
		$this->zbxTestAssertElementText("//a[text()='Test LLD item']/parent::td", "$this->templateName: Test LLD item");
		$this->zbxTestClickLinkTextWait('Test LLD item');
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();

		$this->zbxTestAssertElementValue('name', 'Test LLD item');
		$this->zbxTestAssertElementValue('key', 'test-lld-item[{#KEY}]');
		$this->zbxTestDropdownAssertSelected('type', 'Simple check');
		$this->zbxTestDropdownAssertSelected('value_type', 'Numeric (unsigned)');
		$this->zbxTestAssertElementValue('units', 'units');
		$this->zbxTestAssertElementValue('delay', '33s');
		$this->zbxTestAssertElementValue('history', '54d');
		$this->zbxTestAssertElementValue('trends', '55d');
		$this->zbxTestAssertElementValue('delay_flex_0_delay', '50s');
		$this->zbxTestAssertElementValue('delay_flex_0_period', '1-7,00:00-24:00');
		$overlay->asForm()->checkValue(['Value mapping' => 'Template value mapping']);
		$this->zbxTestAssertElementText('//*[@name="description"]', 'description');
		$this->zbxTestTextPresent('Parent items');
		$this->zbxTestTextPresent($this->templateName);

		$overlay->close();
	}

	/**
	 * Creates a new trigger prototype on the template and checks that the inherited trigger prototype matches
	 * the original.
	 *
	 */
	public function testTemplateInheritance_CreateTriggerPrototype() {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$this->templateName.'&filter_set=1');

		// create an trigger prototype
		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Discovery')->waitUntilVisible()
				->one()->click();
		$this->zbxTestClickLinkTextWait('testInheritanceDiscoveryRule');
		$this->zbxTestClickLinkTextWait('Trigger prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create trigger prototype');
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestInputTypeByXpath("//input[@name='name']", 'Test LLD trigger');
		$this->zbxTestInputType('expression', 'last(/Inheritance test template/item-discovery-prototype[{#KEY}],#1)=0');
		$this->zbxTestCheckboxSelect('type_1');
		$this->zbxTestInputType('description', 'comments');
		$this->zbxTestInputType('url', 'zabbix.php');
		$this->zbxTestClickXpath("//label[@for='priority_2']");
		$this->zbxTestCheckboxSelect('status', false);

		$dialog->getFooter()->query('button:Add')->one()->click();
		$dialog->ensureNotPresent();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'Trigger prototype added');
		$this->zbxTestTextPresent('Test LLD trigger');

		$sql = "SELECT triggerid FROM triggers WHERE description='Test LLD trigger' AND status='1' AND templateid IS NULL";
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Trigger prototype has not been added into Zabbix DB');

		$sql = "SELECT triggerid FROM triggers WHERE description='Test LLD trigger' AND status='1' AND templateid IS NOT NULL";
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Trigger prototype has not been added into Zabbix DB');

		// check that the inherited trigger prototype matches the original
		$this->zbxTestOpen(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Discovery', 'Discovery');
		$this->zbxTestClickLinkTextWait('testInheritanceDiscoveryRule');
		$this->zbxTestClickLinkTextWait('Trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestAssertElementText("//a[text()='Test LLD trigger']/parent::td", "$this->templateName: Test LLD trigger");
		$this->zbxTestClickLinkTextWait('Test LLD trigger');
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('name'));
		$getName = $this->zbxTestGetValue("//input[@name='name']");
		$this->assertEquals($getName, 'Test LLD trigger');
		$this->zbxTestAssertElementValue('expression', 'last(/Template inheritance test host/item-discovery-prototype[{#KEY}],#1)=0');
		$this->assertTrue($this->zbxTestCheckboxSelected('recovery_mode_0'));
		$this->zbxTestAssertElementPresentXpath("//input[@id='recovery_mode_0'][@readonly]");
		$this->zbxTestAssertElementText('//*[@name="description"]', 'comments');
		$this->zbxTestAssertElementValue('url', 'zabbix.php');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_2'));
		$this->assertFalse($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestTextPresent('Parent triggers');
		$this->zbxTestTextPresent($this->templateName);
		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Creates a new graph prototype on the template and checks that the inherited graph prototype matches the original.
	 *
	 */
	public function testTemplateInheritance_CreateGraphPrototype() {
		$this->zbxTestLogin('zabbix.php?action=template.list&filter_name='.$this->templateName.'&filter_set=1');

		// create a graph
		$this->query('class:list-table')->asTable()->one()->getRow(0)->query('link:Discovery')->waitUntilVisible()->one()->click();
		$this->zbxTestClickLinkTextWait('testInheritanceDiscoveryRule');
		$this->zbxTestClickLinkTextWait('Graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create graph prototype');

		$this->zbxTestInputTypeWait('name', 'Test LLD graph');
		$this->zbxTestInputTypeOverwrite('width', '950');
		$this->zbxTestInputTypeOverwrite('height', '250');
		if ($this->zbxTestGetValue("//input[@id='height']") != '250') {
				$this->zbxTestInputTypeOverwrite('height', '250');
		}
		$this->zbxTestDropdownSelect('graphtype', 'Normal');
		$this->zbxTestCheckboxSelect('show_legend', false);
		$this->assertFalse($this->zbxTestCheckboxSelected('show_legend'));
		$this->zbxTestCheckboxSelect('show_work_period', false);
		$this->assertFalse($this->zbxTestCheckboxSelected('show_work_period'));
		$this->zbxTestCheckboxSelect('show_triggers', false);
		$this->assertFalse($this->zbxTestCheckboxSelected('show_triggers'));
		$this->zbxTestCheckboxSelect('visible_percent_left');
		$this->zbxTestCheckboxSelect('visible_percent_right');
		$this->zbxTestInputType('percent_left', '4');
		$this->zbxTestInputType('percent_right', '5');
		$this->zbxTestDropdownSelect('ymin_type', 'Calculated');
		$this->zbxTestDropdownSelect('ymax_type', 'Calculated');

		$this->zbxTestClick('add_protoitem');
		$this->zbxTestLaunchOverlayDialog('Item prototypes');
		$this->zbxTestClickLinkText('itemDiscovery');
		$this->zbxTestTextPresent($this->templateName.': itemDiscovery');

		$this->zbxTestClick('add_item');
		$this->zbxTestLaunchOverlayDialog('Items');
		$this->zbxTestClickLinkText('testInheritanceItem1');
		$this->zbxTestTextPresent($this->templateName.': testInheritanceItem1');

		$this->zbxTestClickWait('add');
		$this->assertMessage(TEST_GOOD,'Graph prototype added');
		$this->zbxTestTextPresent('Test LLD graph');

		// check that the inherited graph matches the original
		$this->zbxTestOpen(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenObjects($this->hostName, 'Discovery', 'Discovery');
		$this->zbxTestClickLinkTextWait('testInheritanceDiscoveryRule');
		$this->zbxTestClickLinkTextWait('Graph prototypes');

		$this->zbxTestAssertElementText("//a[text()='Test LLD graph']/parent::td", "$this->templateName: Test LLD graph");
		$this->zbxTestClickLinkTextWait('Test LLD graph');

		$this->zbxTestAssertElementValue('name', 'Test LLD graph');
		$this->zbxTestAssertElementValue('width', '950');
		$this->zbxTestAssertElementValue('height', '250');
		$this->zbxTestDropdownAssertSelected('graphtype', 'Normal');
		$this->assertFalse($this->zbxTestCheckboxSelected('show_legend'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_work_period'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_triggers'));
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_left'));
		$this->zbxTestAssertElementValue('percent_left', '4');
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_right'));
		$this->zbxTestAssertElementValue('percent_right', '5');
		$this->zbxTestDropdownAssertSelected('ymin_type', 'Calculated');
		$this->zbxTestDropdownAssertSelected('ymax_type', 'Calculated');
		$this->zbxTestTextPresent($this->hostName.': itemDiscovery');
		$this->zbxTestTextPresent($this->hostName.': testInheritanceItem1');
		$this->zbxTestTextPresent('Parent graphs');
		$this->zbxTestTextPresent($this->templateName);
	}

	/**
	 * Function for filtering necessary hosts and opening their objects.
	 *
	 * @param string    $host	    name of a host where objects are opened
	 * @param string    $column     name of a column which is clicked for particular host
	 * @param string    $objects    objects of host: items, triggers, graphs, discovery rules or it can be host itself
	 */
	private function filterEntriesAndOpenObjects($host, $column, $objects) {
		$this->query('button:Reset')->one()->click();
		$filter = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$filter->fill(['Name' => $host]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();

		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $host)
				->getColumn($column)->query('link', $objects)->one()->click();
	}
}
