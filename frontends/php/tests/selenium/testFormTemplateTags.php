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
require_once dirname(__FILE__).'/traits/TagTrait.php';

/**
 * @backup hosts
 */
class testFormTemplateTags extends CWebTest {

	use TagTrait;

	/**
	 * The name of the template for cloning in the test data set.
	 *
	 * @var string
	 */
	protected $clone_template = 'Template with tags for cloning';

	/**
	 * The name of the template for updating in the test data set.
	 *
	 * @var string
	 */
	protected $update_template = 'Template with tags for updating';

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
							'Template name' => 'Template with tags',
							'Groups' => 'Zabbix servers'
						],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '!@#$%^&*()_+<>,.\/',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'name' => 'tag1',
							'value' => 'value1'
						],
						[
							'name' => 'tag2'
						],
						[
							'name' => '{$MACRO:A}',
							'value' => '{$MACRO:A}'
						],
						[
							'name' => '{$MACRO}',
							'value' => '{$MACRO}'],
						[
							'name' => 'Таг',
							'value' => 'Значение'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
							'Template name' => 'Template with equal tag names',
							'Groups' => 'Zabbix servers'
						],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => 'tag3',
							'value' => '3'
						],
						[
							'name' => 'tag3',
							'value' => '4'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
							'Template name' => 'Template with equal tag values',
							'Groups' => 'Zabbix servers'
						],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => 'tag4',
							'value' => '5'
						],
						[
							'name' => 'tag5',
							'value' => '5'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
							'Template name' => 'Template with empty tag name',
							'Groups' => 'Zabbix servers'
						],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'value1'
						]
					],
					'error'=>'Cannot add template',
					'error_details'=>'Invalid parameter "/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
							'Template name' => 'Template with equal tags',
							'Groups' => 'Zabbix servers'
						],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => 'tag',
							'value' => 'value'
						],
						[
							'name' => 'tag',
							'value' => 'value'
						]
					],
					'error'=>'Cannot add template',
					'error_details'=>'Invalid parameter "/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			]
		];
	}

	/**
	 * Test creating of Template with tags
	 *
	 * @dataProvider getCreateData
	 *
	 */
	public function testFormTemplateTags_Create($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);

		$this->page->login()->open('templates.php');
		$this->query('button:Create template')->waitUntilPresent()->one()->click();
		$form = $this->query('name:templatesForm')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->selectTab('Tags');
		$this->fillTags($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']){
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Template added', $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['fields']['Template name'])));
				// Check the results in form.
				$this->checkTagFields($data);
				break;
			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertEquals($data['error'], $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hosts));
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
							'name' => '',
							'value' => 'value1'
						]
					],
					'error'=>'Cannot update template',
					'error_details'=>'Invalid parameter "/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => 'action', 'value' => 'update'
						]
					],
					'error'=>'Cannot update template',
					'error_details'=>'Invalid parameter "/tags/2": value (tag, value)=(action, update) already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '!@#$%^&*()_+<>,.\/',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => 'tag1',
							'value' => 'value1'
						],
						[
							'name' => 'tag2'
						],
						[
							'name' => '{$MACRO:A}',
							'value' => '{$MACRO:A}'
						],
						[
							'name' => '{$MACRO}',
							'value' => '{$MACRO}'
						],
						[
							'name' => 'Таг',
							'value' => 'Значение'
						]
					]
				]
			]
		];
	}

	/**
	 * Test update of template with tags
	 *
	 * @dataProvider getUpdateData
	 *
	 */
	public function testFormTemplateTags_Update($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);
		$data['fields']['Template name'] = $this->update_template;

		$this->page->login()->open('templates.php');
		$this->query('link:'.$this->update_template)->waitUntilPresent()->one()->click();
		$form = $this->query('name:templatesForm')->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$this->fillTags($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']){
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Template updated', $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['fields']['Template name'])));
				// Check the results in form.
				$this->checkTagFields($data);
				break;
			case TEST_BAD:
				// Check if message is negative.
				$this->assertTrue($message->isBad());
				// Check message title.
				$this->assertEquals($data['error'], $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hosts));
				break;
		}
	}

	public function testFormTemplateTags_Clone() {
		$this->executeCloning('Clone');
	}

	public function testFormTemplateTags_FullClone() {
		$this->executeCloning('Full clone');
	}

	/**
	 * Test cloning of template with tags
	 */
	private function executeCloning($action) {
		$new_name = 'Template with tags for cloning - '.$action;

		$this->page->login()->open('templates.php?groupid=4');
		$this->query('link:'.$this->clone_template)->waitUntilPresent()->one()->click();
		$form = $this->query('name:templatesForm')->waitUntilPresent()->asForm()->one();
		$form->getField('Template name')->fill($new_name);

		$form->selectTab('Tags');
		$tags = $this->getTags();

		$this->query('button:'.$action)->one()->click();

		$form->submit();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Template added', $message->getTitle());
		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_template)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name)));

		// Check created clone.
		$this->query('link:'.$new_name)->one()->click();
		$form->invalidate();
		$name = $form->getField('Template name')->getValue();
		$this->assertEquals($new_name, $name);

		$form->selectTab('Tags');
		$this->assertTags($tags);
	}

	private function checkTagFields($data) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['fields']['Template name']));
		$this->page->open('templates.php?form=update&templateid='.$id.'&groupid=4');
		$form = $this->query('name:templatesForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Tags');
		$this->assertTags($data['tags']);
	}
}
