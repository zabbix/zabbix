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
							'value' => '{$MACRO}'
						],
						[
							'tag' => 'Таг',
							'value' => 'Значение'
						]
					]
				]
			],
			[
				[
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
					'trigger_error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'trigger_error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			],
			[
				[
					'name' => 'With trailing spaces',
					'trim' => true,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '    trimmed tag    ',
							'value' => '   trimmed value    '
						]
					]
				]
			],
			[
				[
					'name' => 'Long tag name and value',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'Long tag name. Long tag name. Long tag name. Long tag name. Long tag name.'
									.' Long tag name. Long tag name. Long tag name.',
							'value' => 'Long tag value. Long tag value. Long tag value. Long tag value. Long tag value.'
									.' Long tag value. Long tag value. Long tag value. Long tag value.'
						]
					]
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
		$sql = null;
		$old_hash = null;
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$sql = ($object === 'host' || $object === 'template')
				? 'SELECT * FROM hosts ORDER BY hostid'
				: 'SELECT * FROM triggers ORDER BY triggerid';
			$old_hash = CDBHelper::getHash($sql);
		}

		if ($object === 'host' || $object === 'template') {
			$locator = 'id:'.$object.'sForm';
			$fields = [ucfirst($object).' name' => $data['name'], 'Groups' => 'Zabbix servers'];
		}
		else {
			$locator = 'name:triggersForm';
			$fields = ['Name' => $data['name'], 'Expression' => $expression];
		}

		$this->page->login()->open($this->link);
		$this->query('button:Create '.$object)->waitUntilClickable()->one()->click();
		$form = $this->query($locator)->asForm()->waitUntilPresent()->one();
		$form->fill($fields);

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);

		// Check screenshots of text area right after filling.
		if ($data['name'] === 'With tags' || $data['name'] === 'Long tag name and value') {
			$this->page->removeFocus();
			$screenshot_area = $this->query('id:tags-table')->one();
			$this->assertScreenshot($screenshot_area, $data['name']);
		}

		$form->submit();
		$this->page->waitUntilReady();

		$this->checkResult($data, $object, $form, 'add', $sql, $old_hash);
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
					'trigger_error_details'=>'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'trigger_error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(action, update) already exists.'
				]
			],
			[
				[
					'trim' => true,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'new tag       ',
							'value' => '   trimmed value    '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'tag' => '    trimmed tag    ',
							'value' => '        new value'
						]
					]
				]
			],
			[
				[
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
		$sql = null;
		$old_hash = null;

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$sql = ($object === 'host' || $object === 'template')
				? 'SELECT * FROM hosts ORDER BY hostid'
				: 'SELECT * FROM triggers ORDER BY triggerid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$data['name'] = $this->update_name;

		$this->page->login()->open($this->link);
		$this->query('link', $this->update_name)->waitUntilClickable()->one()->click();

		$locator = ($object === 'host' || $object === 'template') ? 'id:'.$object.'sForm' : 'name:triggersForm';
		$form = $this->query($locator)->asForm()->waitUntilPresent()->one();

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		$this->checkResult($data, $object, $form, 'update', $sql, $old_hash);
	}

	/**
	 * Check result after creating or updating object with tags.
	 *
	 * @param arary     $data        data provider
	 * @param string    $object      host, template, trigger or prototype
	 * @param element   $form        object configuration form
	 * @param string    $action      create or update object
	 * @param string    $sql         selected table from db
	 * @param string    $old_hash    db hash before changes
	 */
	private function checkResult($data, $object, $form, $action, $sql = null, $old_hash = null) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$title = ($action === 'add') ? 'Cannot add '.$object : 'Cannot update '.$object;

			$error_details = ($object === 'host' || $object === 'template')
					? $data['error_details']
					: $data['trigger_error_details'];

			$this->assertMessage(TEST_BAD, $title, $error_details);
			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$title = ($action === 'add') ? ucfirst($object).' added' : ucfirst($object).' updated';

			$this->assertMessage(TEST_GOOD, $title);

			$success_sql = ($object === 'host' || $object === 'template')
				? 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name'])
				: 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['name']);
			$this->assertEquals(1, CDBHelper::getCount($success_sql));

			// Check the results in form.
			$this->checkTagFields($data, $object, $form);
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
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();

		if ($object === 'host' || $object === 'template') {
			$locator = 'id:'.$object.'sForm';
			$fields = [ucfirst($object).' name' => $new_name];
			$sql_old_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_name);
			$sql_new_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name);
			$name = ucfirst($object).' name';
		}
		else {
			$locator = 'name:triggersForm';
			$fields = ['Name' => $new_name];
			$sql_old_name = 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->clone_name);
			$sql_new_name = 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($new_name);
			$name = 'Name';
		}

		$form = $this->query($locator)->asForm()->waitUntilPresent()->one();
		$form->fill($fields);
		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();
		$this->query('button:'.$action)->one()->click();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, ucfirst($object).' added');

		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount($sql_old_name));
		$this->assertEquals(1, CDBHelper::getCount($sql_new_name));

		// Check created clone.
		$this->query('link', $new_name)->one()->click();
		$form->invalidate();
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

			if (CTestArrayHelper::get($data, 'trim', false) === false) {
				continue;
			}

			// Remove trailing spaces from tag and value.
			foreach ($expected as $i => &$options) {
				foreach (['tag', 'value'] as $parameter) {
					if (array_key_exists($parameter, $options)) {
						$options[$parameter] = trim($options[$parameter]);
					}
				}
			}
			unset($options);
		}
		unset($tag);

		$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($expected);

		// Check screenshot of text area after saving.
		if ($data['name'] === 'With tags' || $data['name'] === 'Long tag name and value') {
			$this->page->removeFocus();
			$screenshot_area = $this->query('id:tags-table')->one();
			$this->assertScreenshot($screenshot_area, $data['name']);
		}
	}
}
