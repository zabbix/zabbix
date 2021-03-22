<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

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

		$this->zbxTestLogin('triggers.php?form=update&triggerid='.$data['triggerid']);
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
					'expression' => '{Inheritance test template:test-inheritance-item1.last()}=0'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'testInheritanceTrigger1',
					'expression' => '{Inheritance test template:key-item-inheritance-test.last()}=0',
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
		$this->zbxTestLogin('triggers.php?filter_set=1&filter_hostids[0]='.$this->templateid);
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

	/**
	 * Test inheritance of trigger tags from template
	 */
	public function testInheritanceTrigger_Tags() {
		$inherited_trigger = 'testInheritanceTrigger1';

		// Go to Host form.
		$this->page->login()->open('hosts.php?form=update&hostid='.$this->hostid);
		$host_form = $this->query('name:hostsForm')->waitUntilPresent()->asForm()->one();
		// Fill tags on host.
		$host_form->selectTab('Tags');

		$host_tags = [
			['name'=>'host_tag', 'value'=>'host_tag_value']
		];

		$this->fillTags($host_tags, count($host_tags));
		$host_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Host updated');

		// Go to Template form.
		$this->page->login()->open('templates.php?groupid=1');
		$this->query('link', $this->template)->one()->waitUntilClickable()->click();

		$template_form = $this->query('name:templatesForm')->waitUntilPresent()->asForm()->one();
		// Fill tags on template.
		$template_form->selectTab('Tags');

		$template_tags = [
			['name'=>'tag', 'value'=>'value:'],
			['name'=>'tag:value', 'value'=>''],
			['name'=>'template', 'value'=>'template'],
			['name'=>'test', 'value'=>'inheritance']
		];
		$template_tags_count = count($template_tags);

		$this->fillTags($template_tags, $template_tags_count);
		$template_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Template updated');

		// Go to Trigger form on Template.
		$updated_templates_table = $this->query('class:list-table')->asTable()->one();
		$updated_templates_table->findRow('Name', $this->template)->getColumn('Triggers')->query('tag:a')->one()->click();
		$updated_templates_table->waitUntilReloaded();
		$updated_templates_table->findRow('Name', $inherited_trigger)->getColumn('Name')->query('tag:a')->one()->click();

		$trigger_form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();
		// Fill tags on trigger.
		$trigger_form->selectTab('Tags');

		$templated_trigger_tags = [
			['name'=>'tag1', 'value'=>'trigger'],
			['name'=>'tag2', 'value'=>'templated']
		];
		$templated_trigger_tags_count = count($templated_trigger_tags);

		$this->fillTags($templated_trigger_tags, $templated_trigger_tags_count);
		$trigger_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Trigger updated');

		// Check inherited trigger on host.
		// Go to host.
		$this->page->login()->open('triggers.php?filter_set=1&filter_hostids[0]='.$this->hostid);
		// Go to inherited trigger.
		$host_triggers_table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();
		$host_triggers_table->query('link', $inherited_trigger)->one()->click();
		// Check trigger name.
		$trigger_form->invalidate();
		$name = $trigger_form->getField('Name')->getValue();
		$this->assertEquals($name, $inherited_trigger);
		// Check tags.
		$trigger_form->selectTab('Tags');
		$trigger_tags_table = $this->query('id:tags-table')->asTable()->one();
		// Check trigger tags.
		$triggers_tags_slice_1 = $trigger_tags_table->getRows()->slice(0, -1); // Remove Add button from cycle.
		$this->checkTags($triggers_tags_slice_1, $templated_trigger_tags);

		// Click on inherited and trigger tags radio.
		$trigger_form->getField('id:show_inherited_tags')->fill('Inherited and trigger tags');

		// All expected trigger tags including host and template tags.
		$inherited_trigger_tags = [
			['name'=>'host_tag', 'value'=>'host_tag_value'],
			['name'=>'tag', 'value'=>'value:'],
			['name'=>'tag1', 'value'=>'trigger'],
			['name'=>'tag2', 'value'=>'templated'],
			['name'=>'tag:value', 'value'=>''],
			['name'=>'template', 'value'=>'template'],
			['name'=>'test', 'value'=>'inheritance']
		];

		$actual_triggers_tags = $trigger_tags_table->getRows()->slice(0, -1); // Remove Add button from cycle.
		$this->checkTags($actual_triggers_tags, $inherited_trigger_tags);
	}

	private function fillTags($array, $i) {
		$tags_table = $this->query('id:tags-table')->asTable()->one();
		$button = $tags_table ->query('button:Add')->one();
		$last = $i - 1;

		foreach ($array as $count => $tag){
			$row = $tags_table->getRows()->get($count);
			$row->getColumn('Name')->query('tag:textarea')->one()->fill($tag['name']);
			$row->getColumn('Value')->query('tag:textarea')->one()->fill($tag['value']);
			if ($count !== $last) {
				$button->click();
			}
		}
	}

	private function checkTags($slice, $array) {
		foreach ($slice as $i => $row) {
			$this->assertEquals($array[$i], [
				'name' => $row->getColumn('Name')->children()->one()->getValue(),
				'value' => $row->getColumn('Value')->children()->one()->getValue()
			]);
		}
	}
}
