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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Base class for Tags function tests.
 */
class testFormTags extends CWebTest {

	public $update_name;
	public $clone_name;
	public $link;
	public $saved_link;

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

	public function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'With tags',
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
					'name' => 'With equal tag names',
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
					'name' => 'With equal tag values',
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
					'name' => 'With empty tag name',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'value1'
						]
					],
					'error_details' => 'Invalid parameter "/tags/1/tag": cannot be empty.',
					'trigger_error_details'=>'Incorrect value for field "tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'With equal tags',
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
					'error_details' => 'Invalid parameter "/tags/2": value (tag, value)=(tag, value) already exists.',
					'trigger_error_details' => 'Tag "tag" with value "value" already exists.'
				]
			]
		];
	}

	/**
	 * Check create host, template, trigger or prototype with tags.
	 *
	 * @param arary    $data         data provider
	 * @param string   $object       host, template, trigger or prototype
	 * @param string   $expression   trigger or trigger prototype expression
	 */
	public function checkTagsCreate($data, $object, $expression = null) {
		$sql = ($object === 'host' || $object === 'template')
			? 'SELECT * FROM hosts ORDER BY hostid'
			: 'SELECT * FROM triggers ORDER BY triggerid';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open($this->link);
		$this->query('button:Create '.$object)->waitUntilPresent()->one()->click();

		$locator = ($object === 'host' || $object === 'template') ? 'id:'.$object.'sForm' : 'name:triggersForm' ;
		$form = $this->query($locator)->waitUntilPresent()->asForm()->one();

		$fields = ($object === 'host' || $object === 'template')
			? [ucfirst($object).' name' => $data['name'], 'Groups' => 'Zabbix servers']
			: ['Name' => $data['name'], 'Expression' => $expression];

		$form->fill($fields);

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, ucfirst($object).' added');

				$success_sql = ($object === 'host' || $object === 'template')
					? 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name'])
					: 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['name']);

				$this->assertEquals(1, CDBHelper::getCount($success_sql));
				// Check the results in form.
				$this->checkTagFields($data, $object, $form);
				break;
			case TEST_BAD:
				$error_details = ($object === 'host' || $object === 'template')
					? $data['error_details']
					: $data['trigger_error_details'];

				$this->assertMessage(TEST_BAD, 'Cannot add '.$object, $error_details);
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql));
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
					'error_details' => 'Invalid parameter "/tags/1/tag": cannot be empty.',
					'trigger_error_details'=>'Incorrect value for field "tag": cannot be empty.'
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
					'error_details' => 'Invalid parameter "/tags/2": value (tag, value)=(action, update) already exists.',
					'trigger_error_details' => 'Tag "action" with value "update" already exists.'
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
	 * Check update tags in host, template, trigger or prototype.
	 *
	 * @param arary    $data     data provider
	 * @param string   $object   host, template, trigger or prototype
	 */
	public function checkTagsUpdate($data, $object) {
		$sql = ($object === 'host' || $object === 'template')
			? 'SELECT * FROM hosts ORDER BY hostid'
			: 'SELECT * FROM triggers ORDER BY triggerid';
		$old_hash = CDBHelper::getHash($sql);

		$data['name'] = $this->update_name;

		$this->page->login()->open($this->link);
		$this->query('link', $this->update_name)->waitUntilPresent()->one()->click();

		$locator = ($object === 'host' || $object === 'template') ? 'id:'.$object.'sForm' : 'name:triggersForm' ;
		$form = $this->query($locator)->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, ucfirst($object).' updated');

				$success_sql = ($object === 'host' || $object === 'template')
					? 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->update_name)
					: 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['name']);

				$this->assertEquals(1, CDBHelper::getCount($success_sql));
				// Check the results in form.
				$this->checkTagFields($data, $object, $form);
				break;
			case TEST_BAD:
				$error_details = ($object === 'host' || $object === 'template')
					? $data['error_details']
					: $data['trigger_error_details'];

				$this->assertMessage(TEST_BAD, 'Cannot update '.$object, $error_details);
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql));
				break;
		}
	}

	/**
	 * Test cloning of host, template, trigger or trigger prototype with tags
	 *
	 * @param string   $object   host, template, trigger or prototype
	 * @param string   $action   clone or full clone
	 */
	public function executeCloning($object, $action) {
		$new_name = $object.$action;

		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilPresent()->one()->click();

		$locator = ($object === 'host' || $object === 'template') ? 'id:'.$object.'sForm' : 'name:triggersForm' ;
		$form = $this->query($locator)->waitUntilPresent()->asForm()->one();

		$fields = ($object === 'host' || $object === 'template')
			? [ucfirst($object).' name' => $new_name]
			: ['Name' => $new_name];

		$form->fill($fields);

		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		$this->query('button:'.$action)->one()->click();
		$form->submit();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, ucfirst($object).' added');

		// Check the results in DB.
		$sql_old_name = ($object === 'host' || $object === 'template')
			? 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_name)
			: 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->clone_name);

		$this->assertEquals(1, CDBHelper::getCount($sql_old_name));

		$sql_new_name = ($object === 'host' || $object === 'template')
			? 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name)
			: 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($new_name);

		$this->assertEquals(1, CDBHelper::getCount($sql_new_name));

		// Check created clone.
		$this->query('link', $new_name)->one()->click();
		$form->invalidate();

		$name = ($object === 'host' || $object === 'template')
			? ucfirst($object).' name'
			: 'Name';

		$this->assertEquals($new_name, $form->getField($name)->getValue());

		$form->selectTab('Tags');
		$element->checkValue($tags);
	}

	/**
	 * Function for checking saved tag fields in form.
	 *
	 * @param arary    $data     data provider
	 * @param string   $object   host, template, trigger or prototype
	 * @param string   $form     object configuration form
	 */
	private function checkTagFields($data, $object, $form) {
		$id = ($object === 'host' || $object === 'template')
			? CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['name']))
			: CDBHelper::getValue('SELECT triggerid FROM triggers WHERE description='.zbx_dbstr($data['name']));

		$this->page->open($this->saved_link.$id);
		$form->selectTab('Tags');

		$expected = $data['tags'];
		foreach ($expected as &$tag) {
			unset($tag['action'], $tag['index']);
		}
		unset($tag);

		$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($expected);
	}
}
