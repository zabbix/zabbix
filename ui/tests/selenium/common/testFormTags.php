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
	public $new_name;

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
					'trigger_error_details' => 'Incorrect value for field "tag": cannot be empty.',
					'host_prototype_error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'trigger_error_details' => 'Tag "tag" with value "value" already exists.',
					'host_prototype_error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
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

		if ($object === 'trigger' || $object === 'trigger prototype') {
			$sql = 'SELECT * FROM triggers ORDER BY triggerid';
			$locator = 'name:triggersForm';
		}
		else {
			$sql = 'SELECT * FROM hosts ORDER BY hostid';
			$locator = ($object === 'host prototype') ? 'name:hostPrototypeForm' : 'name:'.$object.'sForm';
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open($this->link);
		$this->query('button:Create '.$object)->waitUntilClickable()->one()->click();
		$form = $this->query($locator)->waitUntilPresent()->asForm()->one();

		$fields = ($object === 'host' || $object === 'template')
			? [ucfirst($object).' name' => $data['name'], 'Groups' => 'Zabbix servers']
			: ['Name' => $data['name'], 'Expression' => $expression];

		if ($object === 'host prototype') {
			$data['name'] = $data['name'].' {#KEY}';
			$form->fill(['Host name' => $data['name']]);
			$form->selectTab('Groups');
			$form->fill(['Groups' => 'Zabbix servers']);
		}
		else {
			$form->fill($fields);
		}

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
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
					'trigger_error_details' => 'Incorrect value for field "tag": cannot be empty.',
					'host_prototype_error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'trigger_error_details' => 'Tag "action" with value "update" already exists.',
					'host_prototype_error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(action, update) already exists.'
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

		if ($object === 'trigger' || $object === 'trigger prototype') {
			$sql = 'SELECT * FROM triggers ORDER BY triggerid';
			$locator = 'name:triggersForm';
		}
		else {
			$sql = 'SELECT * FROM hosts ORDER BY hostid';
			$locator = ($object === 'host prototype') ? 'name:hostPrototypeForm' : 'name:'.$object.'sForm';
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($sql);
		}

		$data['name'] = $this->update_name;
		$this->page->login()->open($this->link);
		$this->query('link', $this->update_name)->waitUntilPresent()->one()->click();
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

			switch ($object) {
				case 'host':
				case 'template':
					$error_details = $data['error_details'];
					break;

				case 'trigger':
				case 'trigger prototype':
					$error_details = $data['trigger_error_details'];
					break;

				case 'host prototype':
					$error_details = $data['host_prototype_error_details'];
					break;
			}

			$this->assertMessage(TEST_BAD, $title, $error_details);
			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$title = ($action === 'add') ? ucfirst($object).' added' : ucfirst($object).' updated';
			$this->assertMessage(TEST_GOOD, $title);

			$success_sql = ($object === 'trigger' || $object === 'trigger prototype')
				? 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['name'])
				: 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name']);

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
		$new_name = $this->new_name.$action;

		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();

		switch ($object) {
			case 'trigger':
			case 'trigger prototype':
				$form = $this->query('name:triggersForm')->asForm()->waitUntilPresent()->one();
				$form->fill(['Name' => $new_name]);
				break;

			case 'host':
			case 'host prototype':
				$form_name = ($object === 'host prototype') ? 'name:hostPrototypeForm' : 'name:hostsForm';
				$form = $this->query($form_name)->asForm()->waitUntilPresent()->one();
				$form->fill(['Host name' => $new_name]);
				break;

			case 'template':
				$form = $this->query('name:templatesForm')->asForm()->waitUntilPresent()->one();
				$form->fill(['Template name' => $new_name]);
				break;
		}

		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		$this->query('button:'.$action)->one()->click();
		$form->submit();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, ucfirst($object).' added');

		// Check the results in DB.
		$sql_old_name = ($object === 'trigger' || $object === 'trigger prototype')
			? 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->clone_name)
			: 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_name);

		$this->assertEquals(1, CDBHelper::getCount($sql_old_name));

		$sql_new_name = ($object === 'trigger' || $object === 'trigger prototype')
			? 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($new_name)
			: 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name);

		$this->assertEquals(1, CDBHelper::getCount($sql_new_name));

		// Check created clone.
		$this->query('link', $new_name)->one()->click();
		$form->invalidate();

		switch ($object) {
			case 'host':
			case 'host prototype':
				$this->assertEquals($new_name, $form->getField('Host name')->getValue());
				break;

			case 'template':
				$this->assertEquals($new_name, $form->getField('Template name')->getValue());
				break;

			case 'trigger prototype':
			case 'trigger':
				$this->assertEquals($new_name, $form->getField('Name')->getValue());
				break;
		}

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
		$id = ($object === 'trigger' || $object === 'trigger prototype')
			? CDBHelper::getValue('SELECT triggerid FROM triggers WHERE description='.zbx_dbstr($data['name']))
			: CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['name']));

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
	}
}
