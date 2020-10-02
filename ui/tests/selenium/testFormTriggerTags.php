<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
					'fields' => [
						'Name' => 'Trigger with tags',
						'Expression' => '{Simple form test host:test-item-reuse.last()}=0',
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '!@#$%^&*()_+<>,.\/',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'tag' => 'tag1',
							'value' => 'value1'
						],
						[
							'tag' => 'tag2'
						],
						[
							'tag' => '{$MACRO:A}',
							'value' => '{$MACRO:A}'
						],
						[
							'tag' => '{$MACRO}',
							'value' => '{$MACRO}'],
						[
							'tag' => 'Таг',
							'value' => 'Значение'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Trigger with equal tag names',
						'Expression' => '{Simple form test host:test-item-reuse.last()}=0',
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag3',
							'value' => '3'
						],
						[
							'tag' => 'tag3',
							'value' => '4'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Trigger with equal tag values',
						'Expression' => '{Simple form test host:test-item-reuse.last()}=0',
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag4',
							'value' => '5'
						],
						[
							'tag' => 'tag5',
							'value' => '5'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Trigger with empty tag name',
						'Expression' => '{Simple form test host:test-item-reuse.last()}=0',
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'value1'
						]
					],
					'error'=>'Cannot add trigger',
					'error_details'=>'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Trigger with equal tags',
						'Expression' => '{Simple form test host:test-item-reuse.last()}=0',
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'tag',
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'value' => 'value'
						]
					],
					'error' => 'Cannot add trigger',
					'error_details' => 'Tag "tag" with value "value" already exists.'
				]
			]
		];
	}

	/**
	 * Test creating of trigger with tags.
	 *
	 * @dataProvider getCreateData
	 *
	 */
	public function testFormTriggerTags_Create($data) {
		$sql_triggers = "SELECT * FROM triggers ORDER BY triggerid";
		$old_hash = CDBHelper::getHash($sql_triggers);

		$this->page->login()->open('hosts.php?groupid=4');
		$this->query('link', $this->host)->waitUntilPresent()->one()->click();
		$this->query('link:Triggers')->waitUntilPresent()->one()->click();

		$this->query('button:Create trigger')->waitUntilPresent()->one()->click();
		$form = $this->query('name:triggersForm')->asForm()->one();

		$form->fill($data['fields']);

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']){
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Trigger added', $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['fields']['Name'])));
				// Check the results in form.
				$this->checkTagFields($data);
				break;
			case TEST_BAD:
				$this->assertTrue($message->isBad());
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
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '',
							'value' => 'value1'
						]
					],
					'error'=>'Cannot update trigger',
					'error_details'=>'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => 'action',
							'value' => 'update'
						]
					],
					'error' => 'Cannot update trigger',
					'error_details' => 'Tag "action" with value "update" already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '!@#$%^&*()_+<>,.\/',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => 'tag1',
							'value' => 'value1'
						],
						[
							'tag' => 'tag2'
						],
						[
							'tag' => '{$MACRO:A}',
							'value' => '{$MACRO:A}'
						],
						[
							'tag' => '{$MACRO}',
							'value' => '{$MACRO}'
						],
						[
							'tag' => 'Таг',
							'value' => 'Значение'
						]
					]
				]
			]
		];
	}

	/**
	 * Test update of trigger with tags.
	 *
	 * @dataProvider getUpdateData
	 *
	 */
	public function testFormTriggerTags_Update($data) {
		$sql_triggers = "SELECT * FROM triggers ORDER BY triggerid";
		$old_hash = CDBHelper::getHash($sql_triggers);
		$data['fields']['Name'] = 'Trigger with tags for updating';

		$this->page->login()->open('hosts.php?groupid=4');
		$this->query('link', $this->host)->waitUntilPresent()->one()->click();
		$this->query('link:Triggers')->waitUntilPresent()->one()->click();
		$this->query('link', $this->update_trigger)->waitUntilPresent()->one()->click();
		$form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']){
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Trigger updated', $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['fields']['Name'])));
				// Check the results in form.
				$this->checkTagFields($data);
				break;
			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertEquals($data['error'], $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_triggers));
				break;
		}
	}

	/**
	 * Test cloning of trigger with tags.
	 */
	public function testFormTriggerTags_Clone() {
		$new_name = 'Trigger with tags for cloning - Clone';

		$this->page->login()->open('hosts.php?groupid=4');
		$this->query('link', $this->host)->waitUntilPresent()->one()->click();
		$this->query('link:Triggers')->waitUntilPresent()->one()->click();
		$this->query('link', $this->clone_trigger)->waitUntilPresent()->one()->click();
		$form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();

		$form->getField('Name')->clear()->type($new_name);
		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		$this->query('button:Clone')->one()->click();
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Trigger added', $message->getTitle());
		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->clone_trigger)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM triggers WHERE description='.zbx_dbstr($new_name)));

		// Check created clone.
		$this->query('link', $new_name)->one()->click();
		$form->invalidate();
		$name = $form->getField('Name')->getValue();
		$this->assertEquals($new_name, $name);

		$form->selectTab('Tags');
		$element->checkValue($tags);
	}

	private function checkTagFields($data) {
		$id = CDBHelper::getValue('SELECT triggerid FROM triggers WHERE description='.zbx_dbstr($data['fields']['Name']));
		$this->page->open('triggers.php?form=update&triggerid='.$id.'&groupid=0');
		$form = $this->query('name:triggersForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Tags');

		$expected = $data['tags'];
		foreach ($expected as &$tag) {
			unset($tag['action'], $tag['index']);
		}
		unset($tag);

		$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($expected);
	}
}
