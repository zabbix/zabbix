<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup triggers
 */
class testFormTriggerTags extends CWebTest {
	/**
	 * The name of the host for creating the trigger.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the trigger for cloning in the test data set.
	 *
	 * @var string
	 */
	protected $clone_trigger = 'Trigger with tags for cloning';

	/**
	 * The name of the trigger for updating in the test data set.
	 *
	 * @var string
	 */
	protected $update_trigger = 'Trigger with tags for updating';

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'trigger_name' => 'Trigger with tags',
					'expression' => '{Simple form test host:test-item-reuse.last()}=0',
					'tags' => [
						['name'=>'!@#$%^&*()_+<>,.\/', 'value'=>'!@#$%^&*()_+<>,.\/'],
						['name'=>'tag1', 'value'=>'value1'],
						['name'=>'tag2', 'value'=>''],
						['name'=>'{$MACRO:A}', 'value'=>'{$MACRO:A}'],
						['name'=>'{$MACRO}', 'value'=>'{$MACRO}'],
						['name'=>'Таг', 'value'=>'Значение']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'trigger_name' => 'Trigger with equal tag names',
					'expression' => '{Simple form test host:test-item-reuse.last()}=0',
					'tags' => [
						['name'=>'tag3', 'value'=>'3'],
						['name'=>'tag3', 'value'=>'4'],

					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'trigger_name' => 'Trigger with equal tag values',
					'expression' => '{Simple form test host:test-item-reuse.last()}=0',
					'tags' => [
						['name'=>'tag4', 'value'=>'5'],
						['name'=>'tag5', 'value'=>'5'],

					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'trigger_name' => 'Trigger with empty tag name',
					'expression' => '{Simple form test host:test-item-reuse.last()}=0',
					'tags' => [
						['name'=>'', 'value'=>'value1']
					],
					'error'=>'Cannot add trigger',
					'error_details'=>'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'trigger_name' => 'Trigger with equal tags',
					'expression' => '{Simple form test host:test-item-reuse.last()}=0',
					'tags' => [
						['name'=>'tag', 'value'=>'value'],
						['name'=>'tag', 'value'=>'value']
					],
					'error'=>'Cannot add trigger',
					'error_details'=>'Tag "tag" with value "value" already exists.'
				]
			]
		];
	}

	/**
	 * Test creating of trigger with tags
	 *
	 * @dataProvider getCreateData
	 *
	 */
	public function testFormTriggerTags_Create($data) {
		$sql_triggers = "SELECT * FROM triggers ORDER BY triggerid";
		$old_hash = CDBHelper::getHash($sql_triggers);

		$this->page->login()->open('hosts.php?groupid=4');
		$this->query('link:'.$this->host)->waitUntilPresent()->one()->click();
		$this->query('link:Triggers')->waitUntilPresent()->one()->click();

		$this->query('button:Create trigger')->waitUntilPresent()->one()->click();
		$form = $this->query('name:triggersForm')->asForm()->one();
		$form->getLabel('Name')->fill($data['trigger_name']);
		$form->getLabel('Expression')->fill($data['expression']);

		$form->selectTab('Tags');

		$tags_table = $this->query('id:tags-table')->asTable()->one();
		$button = $tags_table ->query('button:Add')->one();
		$last = count($data['tags']) - 1;

		foreach ($data['tags'] as $count => $tag){
			$row = $tags_table->getRows()->get($count);
			$row->getColumn('Name')->query('tag:input')->one()->fill($tag['name']);
			$row->getColumn('Value')->query('tag:input')->one()->fill($tag['value']);
			if ($count !== $last) {
				$button->click();
			}
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']){
			case TEST_GOOD:
				// Check if message is positive.
				$this->assertTrue($message->isGood());
				// Check message title.
				$this->assertEquals('Trigger added', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['trigger_name'])));
				// Check the results in form.
				$this->checkFormFields($data);
				break;
			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals($data['error'], $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_triggers));
				break;
		}
	}

	public static function getUpdateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'trigger_name' => 'Updated trigger with empty tag name',
					'tags' => [
						['name'=>'', 'value'=>'value1']
					],
					'error'=>'Cannot update trigger',
					'error_details'=>'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'trigger_name' => ' Updated trigger with equal tags',
					'tags' => [
						['name'=>'tag', 'value'=>'value'],
						['name'=>'tag', 'value'=>'value']
					],
					'error'=>'Cannot update trigger',
					'error_details'=>'Tag "tag" with value "value" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'trigger_name' => 'Updated trigger with tags',
					'tags' => [
						['name'=>'!@#$%^&*()_+<>,.\/', 'value'=>'!@#$%^&*()_+<>,.\/'],
						['name'=>'tag1', 'value'=>'value1'],
						['name'=>'tag2', 'value'=>''],
						['name'=>'{$MACRO:A}', 'value'=>'{$MACRO:A}'],
						['name'=>'{$MACRO}', 'value'=>'{$MACRO}'],
						['name'=>'Таг', 'value'=>'Значение']
					]
				]
			]
		];
	}

	/**
	 * Test update of trigger with tags
	 *
	 * @dataProvider getUpdateData
	 *
	 */
	public function testFormTriggerTags_Update($data) {
		$sql_triggers = "SELECT * FROM triggers ORDER BY triggerid";
		$old_hash = CDBHelper::getHash($sql_triggers);

		$this->page->login()->open('hosts.php?groupid=4');
		$this->query('link:'.$this->host)->waitUntilPresent()->one()->click();
		$this->query('link:Triggers')->waitUntilPresent()->one()->click();
		$this->query('link:'.$this->update_trigger)->waitUntilPresent()->one()->click();
		$form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();

		$form->getField('Name')->clear()->type($data['trigger_name']);

		$form->selectTab('Tags');
		$tags_table = $this->query('id:tags-table')->asTable()->one();

		$button = $tags_table ->query('button:Add')->one();
		$last = count($data['tags']) - 1;

		foreach ($data['tags'] as $count => $tag){
			$row = $tags_table->getRows()->get($count);
			$row->getColumn('Name')->query('tag:input')->one()->clear()->fill($tag['name']);
			$row->getColumn('Value')->query('tag:input')->one()->clear()->fill($tag['value']);
			if ($count !== $last) {
				$button->click();
			}
		}
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']){
			case TEST_GOOD:
				// Check if message is positive.
				$this->assertTrue($message->isGood());
				// Check message title.
				$this->assertEquals('Trigger updated', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->update_trigger)));
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['trigger_name'])));
				// Check the results in form.
				$this->checkFormFields($data);
				break;
			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals($data['error'], $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_triggers));
				break;
		}
	}

	/**
	 * Test cloning of trigger with tags
	 */
	public function testFormTriggerTags_Clone() {
		$this->page->login()->open('hosts.php?groupid=4');
		$this->query('link:'.$this->host)->waitUntilPresent()->one()->click();
		$this->query('link:Triggers')->waitUntilPresent()->one()->click();
		$this->query('link:'.$this->clone_trigger)->waitUntilPresent()->one()->click();
		$form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$tags_table = $this->query('id:tags-table')->asTable()->one();

		$tags = [];
		foreach ($tags_table->getRows()->slice(0, -1) as $row) {
			$tags[] = [
				'name' => $row->getColumn('Name')->children()->one()->getAttribute('value'),
				'value' => $row->getColumn('Value')->children()->one()->getAttribute('value')
			];
		}
		$form->selectTab('Trigger');
		$this->query('button:Clone')->one()->click();
		$new_name = 'Trigger with tags for cloning - Clone';
		$form->getField('Name')->clear()->type($new_name);

		$form->submit();
		$this->page->waitUntilReady();
		// Get global message.
		$message = CMessageElement::find()->one();
		// Check if message is positive.
		$this->assertTrue($message->isGood());
		// Check message title.
		$this->assertEquals('Trigger added', $message->getTitle());
		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->clone_trigger)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($new_name)));

		// Check created clone.
		$this->query('link:'.$new_name)->one()->click();
		$form = $this->query('name:triggersForm')->asForm()->one();
		$name = $form->getField('Name')->getAttribute('value');
		$this->assertEquals($name, $new_name);

		$form->selectTab('Tags');
		$tags_table = $this->query('id:tags-table')->asTable()->one();

		foreach ($tags_table->getRows()->slice(0, -1) as $i => $row) {
			$this->assertEquals($tags[$i], [
				'name' => $row->getColumn('Name')->children()->one()->getAttribute('value'),
				'value' => $row->getColumn('Value')->children()->one()->getAttribute('value')
			]);
		}
	}

	private function checkFormFields($data) {
		$id = CDBHelper::getValue('SELECT triggerid FROM triggers WHERE description='.zbx_dbstr($data['trigger_name']));
		$this->page->open('triggers.php?form=update&triggerid='.$id.'&groupid=0');
		$form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Tags');

		$tags_table = $this->query('id:tags-table')->asTable()->one();

		foreach ($data['tags'] as $i => $tag) {
			$row = $tags_table->getRows()->get($i);
			$tag_name = $row->getColumn('Name')->query('tag:input')->one()->getAttribute('value');
			$this->assertEquals($tag['name'], $tag_name);
			$tag_value = $row->getColumn('Value')->query('tag:input')->one()->getAttribute('value');
			$this->assertEquals($tag['value'], $tag_value);
		}
	}
}
