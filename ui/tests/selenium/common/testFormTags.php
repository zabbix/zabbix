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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * Base class for Tags function tests.
 *
 * @backup profiles
 */
class testFormTags extends CWebTest {

	const EDIT_BUTTON_PATH = 'xpath:.//button[@title="Edit"]';

	public $update_name;
	public $clone_name;
	public $remove_name;
	public $link;
	public $saved_link;
	public $host;
	public $template;

	/**
	 * Flag for problem tags in services.
	 */
	public $problem_tags = false;

	/**
	 * Tags table selector.
	 */
	protected $tags_table = 'class:tags-table';

	// Tags on host "Host for tags testing".
	const HOST_TAGS = [
		[
			'tag' => 'a:',
			'value' => 'a'
		],
		[
			'tag' => 'action',
			'value' => 'simple'
		],
		[
			'tag' => 'tag',
			'value' => 'HOST'
		],
		[
			'tag' => 'host tag without value',
			'value' => ''
		],
		[
			'tag' => 'common tag on host and element',
			'value' => 'common value'
		]
	];

	// Tags on template "Template for tags testing".
	const TEMPLATE_TAGS = [
		[
			'tag' => 'action',
			'value' => 'simple'
		],
		[
			'tag' => 'tag',
			'value' => 'TEMPLATE'
		],
		[
			'tag' => 'templateTag without value',
			'value' => ''
		],
		[
			'tag' => 'common tag on template and element',
			'value' => 'common value'
		]
	];

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
					'error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
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
	 * Check of creating different objects with tags.
	 *
	 * @param array    $data         data provider
	 * @param string   $object       host, template, trigger, item or prototypes
	 * @param string   $expression   trigger or trigger prototype expression
	 */
	public function checkTagsCreate($data, $object, $expression = null) {
		$sql = null;
		$old_hash = null;

		switch ($object) {
			case 'trigger':
			case 'trigger prototype':
				$sql = 'SELECT * FROM triggers ORDER BY triggerid';
				$fields = ['Name' => $data['name'], 'Expression' => $expression];
				break;

			case 'item':
			case 'item prototype':
				$sql = 'SELECT * FROM items ORDER BY itemid';
				$fields = ['Name' => $data['name'], 'Key' => 'itemtag_'.microtime(true).'[{#KEY}]', 'Type' => 'Zabbix trapper'];
				break;

			case 'web scenario':
				$sql = 'SELECT * FROM httptest ORDER BY httptestid';
				$fields = ['Name' => $data['name'], 'Key' => 'itemtag_'.microtime(true)];
				break;

			case 'service':
				$sql = 'SELECT * FROM services ORDER BY serviceid';
				$fields = ['Name' => $data['name']];
				break;

			case 'connector':
				$sql = 'SELECT * FROM connector ORDER BY connectorid';
				$fields = ['Name' => $data['name'], 'URL' => '{$URL}'];
				break;

			case 'host':
			case 'host prototype':
			case 'template':
				$sql = 'SELECT * FROM hosts ORDER BY hostid';
				$group_field = ($object === 'template') ? 'Template groups' : 'Host groups';
				$group_name = ($object === 'template') ? 'Templates' : 'Zabbix servers';
				$fields = [ucfirst($object).' name' => $data['name'], $group_field => $group_name];
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open($this->link);
		$this->query('button:Create '.$object)->waitUntilClickable()->one()->click();

		switch ($object) {
			case 'host prototype':
				$form = $this->query('name:hostPrototypeForm')->waitUntilPresent()->asForm(['normalized' => true])->one();
				$data['name'] = $data['name'].' {#KEY}';
				$form->fill(['Host name' => $data['name']]);
				$form->fill(['Host groups' => 'Zabbix servers']);
				break;

			case 'web scenario':
				$form = $this->query('name:webscenario_form')->waitUntilPresent()->asGridForm(['normalized' => true])->one();
				$form->fill(['Name' => $data['name']]);
				$form->selectTab('Steps');
				$form->getField('Steps')->query('button:Add')->waitUntilClickable()->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
				$overlay_form = $this->query('id:webscenario-step-form')->asForm()->one();
				$overlay_form->fill(['Name' => 'zabbix', 'id:url' => 'http://zabbix.com']);
				$overlay_form->submit();
				COverlayDialogElement::ensureNotPresent();
				break;

			case 'host':
			case 'template':
			case 'service':
			case 'connector':
			case 'trigger':
			case 'trigger prototype':
			case 'item':
			case 'item prototype':
				$form = COverlayDialogElement::find()->waitUntilReady()->asGridForm(['normalized' => true])->one()->waitUntilVisible();
				$form->fill($fields);
				break;
		}

		if (!$this->problem_tags && $object !== 'connector') {
			$form->selectTab('Tags');
		}
		$this->query($this->tags_table)->asMultifieldTable()->one()->fill($data['tags']);

		// Check screenshots of text area right after filling.
		if ($data['name'] === 'With tags' || $data['name'] === 'Long tag name and value') {
			$this->page->removeFocus();
			$this->page->updateViewport();
			$screenshot_area = $this->query($this->tags_table)->one();
			$screen_object = ($this->problem_tags) ? 'Service problem tags' : $object;
			$this->assertScreenshot($screenshot_area, $data['name'].' '.$screen_object);
		}

		$form->submit();
		$this->page->waitUntilReady();

		$this->checkResult($data, $object, $form, 'add', $sql, $old_hash);

		return $form;

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
					'error_details'=>'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(action, update) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => 'tag without value',
							'value' => ''
						]
					],
					'error_details' => 'Invalid parameter "/1/tags/3": value (tag, value)=(tag without value, ) already exists.'
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
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => '    trimmed tag2',
							'value' => 'new value        '
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
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
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
							'tag' => 'Тег',
							'value' => 'Значение'
						]
					]
				]
			]
		];
	}

	/**
	 * Check updating tags in different objects.
	 *
	 * @param array    $data     data provider
	 * @param string   $object   host, template, trigger, prototype, service etc.
	 */
	public function checkTagsUpdate($data, $object) {
		$sql = null;
		$old_hash = null;

		switch ($object) {
			case 'trigger':
			case 'trigger prototype':
				$sql = 'SELECT * FROM triggers ORDER BY triggerid';
				break;

			case 'item':
			case 'item prototype':
				$sql = 'SELECT * FROM items ORDER BY itemid';
				break;

			case 'web scenario':
				$sql = 'SELECT * FROM httptest ORDER BY httptestid';
				$locator = 'name:webscenario_form';
				break;

			case 'service':
				$sql = 'SELECT * FROM services ORDER BY serviceid';
				break;

			case 'connector':
				$sql = 'SELECT * FROM connector ORDER BY connectorid';
				break;

			case 'host':
			case 'host prototype':
			case 'template':
				$sql = 'SELECT * FROM hosts ORDER BY hostid';
				$locator = ($object === 'host prototype') ? 'name:hostPrototypeForm' : null;
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($sql);
		}

		$data['name'] = $this->update_name;
		$this->page->login()->open($this->link)->waitUntilReady();

		if ($object === 'service') {
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();
			$table->findRow('Name', $data['name'], true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
		}
		else {
			if ($object === 'template') {
				$this->query('button:Reset')->one()->click();
				$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
				$form->fill(['Name' => $this->update_name]);
				$this->query('button:Apply')->one()->waitUntilClickable()->click();
			}

			$this->query('link', $this->update_name)->waitUntilClickable()->one()->click();
		}

			$form = ($object === 'web scenario' || $object === 'host prototype')
					? $this->query($locator)->asForm()->waitUntilPresent()->one()
					: COverlayDialogElement::find()->waitUntilVisible()->asForm()->one();

		if (!$this->problem_tags && $object !== 'connector') {
			$form->selectTab('Tags');
		}

		$this->query($this->tags_table)->asMultifieldTable()->waitUntilPresent()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		$this->checkResult($data, $object, $form, 'update', $sql, $old_hash);
	}

	/**
	 * Check result after creating or updating object with tags.
	 *
	 * @param array     $data        data provider
	 * @param string    $object      host, template, trigger, item or prototype
	 * @param element   $form        object configuration form
	 * @param string    $action      create or update object
	 * @param string    $sql         selected table from db
	 * @param string    $old_hash    db hash before changes
	 */
	private function checkResult($data, $object, $form, $action, $sql = null, $old_hash = null) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {

			if ($object === 'service') {
				$title = null;
			}
			else {
				$title = ($action === 'add')
					? ($object === 'connector') ? 'Cannot create '.$object : 'Cannot add '.$object
					: 'Cannot update '.$object;
			}

			$this->assertMessage(TEST_BAD, $title, CTestArrayHelper::get($data, 'error_details'));

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));

			if (in_array($object, ['connector', 'template', 'trigger', 'trigger prototype', 'item', 'item prototype',
				'host', 'service'])) {
				COverlayDialogElement::find()->one()->close();
			}
		}
		else {
			switch ($object) {
				case 'host':
				case 'template':
				case 'host prototype':
					$success_sql = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name']);
					break;

				case 'trigger':
				case 'trigger prototype':
					$success_sql = 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($data['name']);
					break;

				case 'item':
				case 'item prototype':
					$success_sql = 'SELECT NULL FROM items WHERE name='.zbx_dbstr($data['name']);
					break;

				case 'web scenario':
					$success_sql = 'SELECT NULL FROM httptest WHERE name='.zbx_dbstr($data['name']);
					break;

				case 'service':
					$success_sql = 'SELECT NULL FROM services WHERE name='.zbx_dbstr($data['name']);
					break;

				case 'connector':
					$success_sql = 'SELECT NULL FROM connector WHERE name='.zbx_dbstr($data['name']);
					break;
			}

			$title = ($action === 'add')
				? ($object === 'service' || $object === 'connector') ? ucfirst($object).' created' : ucfirst($object).' added'
				: ucfirst($object).' updated';

			$this->assertMessage(TEST_GOOD, $title);

			// 2 elements for test case "InheritedHostAndTemplateTags"
			$count_elements = (strpos($data['name'], 'Inheritance') !== false) ? 2 : 1;
			$this->assertEquals($count_elements, CDBHelper::getCount($success_sql));

			// Check the results in form.
			$this->checkTagFields($data, $object, $form);

			if (!in_array($object, ['host', 'host prototype', 'web scenario'])) {
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	/**
	 * Test cloning of host, template, item, trigger or prototype with tags
	 *
	 * @param string   $object   host, template, item, trigger or prototype
	 */
	public function executeCloning($object) {
		$new_name = (strpos($object, 'prototype') !== false)
			? 'Tags - Clone '.$object.' {#KEY}'
			: '1Tags - Clone '.$object;

		$this->page->login()->open($this->link)->waitUntilReady();

		if ($object === 'service') {
			$table = $this->query('class:list-table')->asTable()->one();
			$table->findRow('Name', $this->clone_name)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
		}
		else {
			if ($object === 'host' || $object === 'template') {
				$this->query('button:Reset')->one()->click();
			}
			$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
		}

		switch ($object) {
			case 'trigger':
			case 'trigger prototype':
				$form = COverlayDialogElement::find()->asForm()->one();
				$form->fill(['Name' => $new_name]);
				$sql_old_name = 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM triggers WHERE description='.zbx_dbstr($new_name);
				break;

			case 'item':
			case 'item prototype':
				$form = COverlayDialogElement::find()->asForm()->one();
				$form->fill(['Name' => $new_name, 'Key' => 'newkey_'.microtime(true).'[{#KEY}]']);
				$sql_old_name = 'SELECT NULL FROM items WHERE name='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM items WHERE name='.zbx_dbstr($new_name);
				break;

			case 'host prototype':
				$form = $this->query('name:hostPrototypeForm')->asForm(['normalized' => true])->waitUntilPresent()->one();
				$form->fill(['Host name' => $new_name]);

				$sql_old_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name);
				break;

			case 'host':
			case 'discovered host':
				$form = $this->query('name:host-form')->asForm()->waitUntilPresent()->one();

				if ($object !== 'discovered host') {
					$form->fill(['Host name' => $new_name]);
				}

				$sql_old_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name);
				break;

			case 'template':
				$form = COverlayDialogElement::find()->asForm()->one();
				$form->fill(['Template name' => $new_name]);
				$sql_old_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name);
				break;

			case 'web scenario':
				$form = $this->query('name:webscenario_form')->asForm()->waitUntilPresent()->one();
				$form->fill(['Name' => $new_name]);
				$sql_old_name = 'SELECT NULL FROM httptest WHERE name='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM httptest WHERE name='.zbx_dbstr($new_name);
				break;

			case 'service':
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
				$form->fill(['Name' => $new_name]);
				$sql_old_name = 'SELECT NULL FROM services WHERE name='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM services WHERE name='.zbx_dbstr($new_name);
				break;

			case 'connector':
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
				$form->fill(['Name' => $new_name]);
				$sql_old_name = 'SELECT NULL FROM connector WHERE name='.zbx_dbstr($this->clone_name);
				$sql_new_name = 'SELECT NULL FROM connector WHERE name='.zbx_dbstr($new_name);
				break;

		}

		if (!$this->problem_tags && $object !== 'connector') {
			$form->selectTab('Tags');
		}
		$element = $this->query($this->tags_table)->asMultifieldTable()->waitUntilPresent()->one();
		$tags = $element->getValue();

		// Click Clone button.
		$this->query('button', 'Clone')->one()->click();
		$this->page->waitUntilReady();

		if ($object === 'discovered host') {
			$form->fill(['Host name' => $new_name]);
		}

		// Find form again for cloned host and click Add host.
		$form->invalidate();
		$form->submit();
		$this->page->waitUntilReady();

		if ($object === 'discovered host') {
			$this->assertMessage(TEST_GOOD, ('Host added'));
		}
		else {
			$this->assertMessage(TEST_GOOD, (
					($object === 'service' || $object === 'connector')
						? ucfirst($object).' created'
						: ucfirst($object).' added'
				)
			);
		}

		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount($sql_old_name));
		$this->assertEquals(1, CDBHelper::getCount($sql_new_name));

		// Check created clone.
		if ($object === 'service') {
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
			$table->findRow('Name',  $new_name)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
		}
		else {
			if ($object === 'template') {
				$this->query('button:Reset')->one()->click();
				$filter = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
				$filter->fill(['Name' => $new_name]);
				$this->query('button:Apply')->one()->waitUntilClickable()->click();
			}

			$this->query('link', $new_name)->one()->click();
		}
		$form->invalidate();

		switch ($object) {
			case 'host':
			case 'host prototype':
			case 'discovered host':
				$this->assertEquals($new_name, $form->getField('Host name')->getValue());
				break;

			case 'template':
				$this->assertEquals($new_name, $form->getField('Template name')->getValue());
				break;

			case 'trigger prototype':
			case 'trigger':
			case 'item prototype':
			case 'item':
			case 'web scenario':
			case 'connector':
			case 'service':
				$this->assertEquals($new_name, $form->getField('Name')->getValue());
				break;
		}

		if ($object !== 'connector') {
			$form->selectTab('Tags');
		}

		$element->checkValue($tags);

		if (!in_array($object, ['host prototype', 'web scenario'])) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Function for checking saved tag fields in form.
	 *
	 * @param array    $data     data provider
	 * @param string   $object   host, template, trigger, item or prototype
	 * @param string   $form     object configuration form
	 */
	private function checkTagFields($data, $object, $form) {
		switch ($object) {
			case 'item':
			case 'item prototype':
				$id = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr($data['name']));
				break;

			case 'web scenario':
				$id = CDBHelper::getValue('SELECT httptestid FROM httptest WHERE name='.zbx_dbstr($data['name']));
				break;

			case 'host':
			case 'host prototype':
			case 'discovered host':
			case 'template':
				$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['name']));
		}

		switch ($object) {
			case 'service':
				$this->page->open($this->link);
				$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
				$table->findRow('Name', $data['name'])->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
				$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
				break;

			case 'trigger':
			case 'trigger prototype':
			case 'connector':
			case 'item':
			case 'item prototype':
				$this->page->open($this->link);
				$table = $this->query($object === 'item' ? 'name:item_list' : 'class:list-table')->asTable()->one()
					->waitUntilReady();
				$table->query('link', $data['name'])->waitUntilClickable()->one()->click();
				$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
				break;

			case 'template':
				$this->page->open('zabbix.php?action=template.list&filter_name='.$data['name'].'&filter_set=1')
					->waitUntilReady();
				$this->query('link', $data['name'])->one()->click();
				$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
				break;

			case 'host':
			case 'host prototype':
			case 'web scenario':
				$this->page->open($this->saved_link.$id);
				break;
		}

		if ($object === 'host') {
			$form = $this->query('id:host-form')->waitUntilPresent()->asForm()->one();
		}

		if (!$this->problem_tags && $object !== 'connector') {
			$form->selectTab('Tags');
		}

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

		$this->query($this->tags_table)->asMultifieldTable()->one()->checkValue($expected);

		// Check screenshot of text area after saving.
		if ($data['name'] === 'With tags' || $data['name'] === 'Long tag name and value') {
			$this->page->removeFocus();
			$screenshot_area = $this->query($this->tags_table)->one();
			$screen_object = ($this->problem_tags) ? 'Service problem tags' : $object;
			$this->assertScreenshot($screenshot_area, $data['name'].' '.$screen_object);
		}
	}

	/**
	 * Test cloning of host or template with trigger, item, web scenario or prototype that have tags.
	 *
	 * @param string   $object   item, trigger, web scenario or prototype
	 * @param string   $parent   host or template
	 */
	public function executeCloningByParent($object, $parent) {
		$new_name = '1Tags - cloning of '.$parent.' with '.$object;
		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();

		// Get tags of object.
		switch ($object) {
			case 'trigger':
			case 'trigger prototype':
			case 'item':
			case 'item prototype':
				$form = COverlayDialogElement::find()->asForm()->one();
				break;

			case 'web scenario':
				$form_selector = 'id:webscenario-form';
				break;

			case 'host prototype':
				$form_selector = 'id:host-prototype-form';
				break;
		}

		if ($object === 'web scenario' || $object === 'host prototype') {
			$form = $this->query($form_selector)->asForm()->waitUntilPresent()->one();
		}

		$form->selectTab('Tags');
		$element = $this->query('class:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		if (in_array($object, ['connector', 'template', 'trigger', 'item', 'trigger prototype', 'item prototype',
				'host', 'service'])) {
			COverlayDialogElement::find()->one()->close();
		}

		// Navigate to host or template for cloning.
		$this->query('link', ($parent === 'Host') ? $this->host : $this->template)->waitUntilClickable()->one()->click();

		$host_modal = COverlayDialogElement::find()->one()->waitUntilReady();

		$host_modal->asForm()->fill([$parent.' name' => $new_name]);

		$host_modal->query('button:Clone')->one()->click();

		$this->query('xpath://div[@class="overlay-dialogue-footer" or contains(@class, "tfoot-buttons")]//button[text()="Add"]')
				->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $parent.' added');

		$this->page->open('zabbix.php?action='.(($parent === 'Host') ? 'host.list' : 'template.list'));
		$this->page->waitUntilReady();
		$this->query('button:Reset')->one()->click();
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$form->fill(['Name' => $new_name]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();

		switch ($object) {
			case 'trigger':
				$column = 'Triggers';
				break;

			case 'item':
				$column = 'Items';
				break;

			case 'web scenario':
				$column = 'Web';
				break;

			case 'host prototype':
			case 'item prototype':
			case 'trigger prototype':
				$column = 'Discovery';
				break;
		}

		$this->query('xpath://table[@class="list-table"]')->asTable()->one()
				->findRow('Name', $new_name)->getColumn($column)->query('link', $column)->one()->click();

		switch ($object) {
			case 'trigger':
			case 'item':
			case 'web scenario':
				$this->query('link', ucfirst($object).'s')->waitUntilClickable()->one()->click();
				$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
				break;

			case 'host prototype':
			case 'item prototype':
			case 'trigger prototype':
				if ($parent !== 'Host') {
					$this->query('link:Discovery rules')->waitUntilClickable()->one()->click();
				}

				$this->query('link', ucfirst($object).'s')->waitUntilClickable()->one()->click();
				$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
				break;
		}

		$new_form = (in_array($object, ['trigger', 'trigger prototype', 'item', 'item prototype']))
				? COverlayDialogElement::find()->one()->waitUntilReady()->asForm()
				: $this->query('xpath://main/form')->asForm()->waitUntilPresent()->one();

		$new_form->selectTab('Tags');
		$element->invalidate();
		$element->checkValue($tags);

		if (in_array($object, ['trigger', 'trigger prototype', 'item', 'item prototype'])) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Test copy of trigger or item.
	 *
	 * @param string   $object			item or trigger
	 * @param string   $target_type		target type
	 * @param string   $parent			host, host group or template name
	 */
	public function executeCopy($object, $target_type, $parent) {
		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();

		// Get tags of object and return to the list.
		$form = COverlayDialogElement::find()->asForm()->one();
		$form->selectTab('Tags');
		$element = $this->query('class:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();
		$this->query('button:Cancel')->one()->click();

		// Select object and copy to target.
		$table_name = ($object === 'item') ? 'item_list' : 'trigger_form';
		$table = $this->query('xpath://form[@name='.CXPathHelper::escapeQuotes($table_name).']/table')
				->asTable()->waitUntilReady()->one();
		$table->findRow('Name', $this->clone_name)->select();
		$this->query('button:Copy')->one()->click();
		$copy_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$copy_form->fill(['Target type' => $target_type.'s', 'Target' => $parent]);
		$copy_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, ucfirst($object).' copied');

		// Open host group, host or template and check object tags.
		if ($target_type !== 'Host group') {
			$this->page->open(($target_type === 'Host')
				? self::HOST_LIST_PAGE
				: 'zabbix.php?action=template.list')->waitUntilReady();

			$this->query('button:Reset')->one()->click();
			$filter = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
			$filter->fill(['Name' => $parent]);
			$this->query('button:Apply')->one()->waitUntilClickable()->click();
			$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $parent)
					->getColumn(ucfirst($object).'s')->query('link', ucfirst($object).'s')->one()->click();

			$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->selectTab('Tags');
			$element->checkValue($tags);
			COverlayDialogElement::find()->one()->close();
		}
		else {
			$filter_form = CFilterElement::find()->one()->getForm();
			$filter_form->fill(['Host groups' => $parent, 'Hosts' => '']);
			$result_form = $this->query('xpath://form[@name='.CXPathHelper::escapeQuotes($table_name).']')->one();
			$this->query('button:Apply')->one()->click();
			$this->page->waitUntilReady();
			$result_form->waitUntilReloaded();
			// Find row indices with the cloned entity name.
			$indices = $table->findRows(function ($row) {
				return $row->getColumn('Name')->getText() === $this->clone_name;
			});
			foreach (array_keys($indices->asArray()) as $index) {
				$table->getRow($index)->getColumn('Name')->query('tag:a')->waitUntilClickable()->one()->click();
				$form->invalidate();
				$form->selectTab('Tags');
				$element->checkValue($tags);
				$this->query('button:Cancel')->one()->click();
			}
			$this->query('button:Reset')->one()->click();
		}
	}

	public function getTagsInheritanceData() {
		return [
			[
				[
					'name' => 'Inheritance element',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'a',
							'value' => ':a'
						],
						[
							'tag' => 'common tag on host and element',
							'value' => 'common value'
						],
						[
							'tag' => 'common tag on template and element',
							'value' => 'common value'
						],
						[
							'tag' => 'InheritanceEmptyValue',
							'value' => ''
						],
						[
							'tag' => 'InheritanceTag',
							'value' => 'InheritanceValue'
						],
						[
							'tag' => '{$MACRO:A}',
							'value' => '{$MACRO:A}'
						],
						[
							'tag' => '{$MACRO}',
							'value' => '{$MACRO}'
						]
					]
				]
			]
		];
	}

	/**
	 * Check inherited tags from host or template.
	 *
	 * @param type $data			data provider
	 * @param type $object			trigger, item, web scenario or prototype
	 * @param string $parent		test on host or template
	 * @param type $expression		trigger or trigger prototype expression
	 */
	public function checkInheritedTags($data, $object, $parent, $expression = null) {
		// Change name for element due to sql count in checkResult function.
		$data['name'] = ($parent === 'Host') ? 'Inherited '.$object.' tags on '.$parent : 'Inheritance element on '.$parent;
		// Set host or template tags data.
		$parent_tags = ($parent === 'Host') ? self::HOST_TAGS : self::TEMPLATE_TAGS;

		// Create element with tags on host or template.
		$form = $this->checkTagsCreate($data, $object, $expression);

		// Remove index and action key in tags of element.
		unset($data['tags'][0]['action'], $data['tags'][0]['index']);

		// Open created element.
		$this->page->open($this->link);
		$this->query('link', $data['name'])->waitUntilClickable()->one()->click();
		$form->selectTab('Tags');
		$tags_table = $this->query($this->tags_table)->asMultifieldTable()->waitUntilVisible()->one();

		// Check all tags (inherited from host/template and own) on created element.
		if ($object === 'web scenario') {
			$field_name = 'scenario';
		}
		else {
			$field_name = (strpos($object, 'prototype') !== false) ? str_replace(' prototype', '', $object) : $object;
		}
		$form->fill(['id:show_inherited_tags' => 'Inherited and '.$field_name.' tags']);

		if ($object === 'web scenario') {
			$this->page->waitUntilReady();
		}
		else {
			COverlayDialogElement::find()->one()->waitUntilReady();
		}

		$tags_table->checkValue($this->prepareAllTags($data['tags'], $parent_tags));

		// Check disabled inherited tags from host or template on created element.
		$this->assertEquals($this->prepareInheritedTags($data['tags'], $parent_tags), $this->getInheritedTags());

		if (in_array($object, ['trigger', 'trigger prototype', 'item', 'item prototype'])) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Check inheritance of tags from host and template on inherited element from template.
	 *
	 * @param array    $data		data provider
	 * @param string   $object		trigger, item, web scenario or prototype
	 * @param string   $host_link	link to host
	 * @param string   $expression  trigger or trigger prototype expression
	 */
	public function checkInheritedElementTags($data, $object, $host_link, $expression = null) {
		// Create element tags on template.
		$form = $this->checkTagsCreate($data, $object, $expression);

		// Remove index and action key in tags of element.
		unset($data['tags'][0]['action'], $data['tags'][0]['index']);

		// Prepare tags that unique only for template (remove host tags from template tags).
		$host_tags = self::HOST_TAGS;
		$unique_template_tags = array_filter(self::TEMPLATE_TAGS, function ($tag) use ($host_tags) {
			foreach ($host_tags as $host_tag) {
				if ($host_tag == $tag) {
					return false;
				}
			}

			return true;
		});
		$unique_template_tags = array_values($unique_template_tags);

		// Open created element.
		$this->page->open($host_link);
		if (strpos($object, 'prototype') !== false) {
			$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();
			$table->findRow('Name', $this->template, true)->getColumn(ucfirst(str_replace(' prototype', '', $object)).'s')
					->query('tag:a')->one()->click();
		}
		$this->query('link', $data['name'])->waitUntilClickable()->one()->click();
		$form->selectTab('Tags');
		$tags_table = $this->query($this->tags_table)->asMultifieldTable()->waitUntilVisible()->one();

		// Check all tags (inherited from host and template and own) on created element.
		if ($object === 'web scenario') {
			$field_name = 'scenario';
		}
		else {
			$field_name = (strpos($object, 'prototype') !== false) ? str_replace(' prototype', '', $object) : $object;
		}
		$form->fill(['id:show_inherited_tags' => 'Inherited and '.$field_name.' tags']);

		if ($object === 'web scenario') {
			$this->page->waitUntilReady();
		}
		else {
			COverlayDialogElement::find()->one()->waitUntilReady();
		}

		$tags_table->checkValue($this->prepareAllTags($data['tags'], array_merge(self::HOST_TAGS, self::TEMPLATE_TAGS)));

		// Check empty column "Parent templates" except for inherited unique template tags.
		foreach ($tags_table->getRows() as $row) {
			$parent_template = $row->getColumn('Parent templates')->getText();
			$current_tag = [];
			$current_tag['tag'] = $row->getColumn('Name')->getText();
			$current_tag['value'] = $row->getColumn('Value')->getText();

			if (in_array($current_tag, $unique_template_tags)) {
				$this->assertEquals($this->template, $parent_template);
			}
			else {
				$this->assertEquals('', $parent_template);
			}
		}

		// Check disabled inherited tags from host and template on created element.
		$this->assertEquals($this->prepareInheritedTags($data['tags']), $this->getInheritedTags());

		if ($object === 'trigger' || $object === 'trigger prototype' || $object === 'item' || $object === 'item prototype') {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Get inherited tags from element page.
	 *
	 * @return array
	 */
	private function getInheritedTags() {
		$inherited_tags = [];

		$tags_table = $this->query($this->tags_table)->asMultifieldTable()->one();
		$headers = $tags_table->getHeadersText();
		// Find disabled rows of host and/or template tags by disabled Name field.
		$disabled_rows = $tags_table->findRows(function ($row) {
			return $row->getColumn('Name')->children()->one()->detect()->isEnabled() === false;
		});

		foreach ($disabled_rows as $row) {
			// Check other disabled fields.
			$this->assertFalse($row->getColumn('Value')->children()->one()->detect()->isEnabled());
			$this->assertFalse($row->getColumn('')->children()->one()->detect()->isEnabled());

			$values = [];
			// Get disabled row values.
			foreach ($tags_table->getRowControls($row, $headers) as $name => $control) {
				$values[$name] = $control->getValue();
			}
			$inherited_tags[] = $values;
		}

		return $inherited_tags;
	}

	/**
	 * Prepare all tags data (inherited form host and/or template and element tags).
	 *
	 * @param array $tags				element tags data
	 * @param array $parent_tags		host and/or template tags
	 *
	 * @return array
	 */
	private function prepareAllTags($tags, $parent_tags) {
		// Prepare all tags data (inherited form host and/or template, and element tags).
		$all_tags = array_merge($parent_tags, $tags);
		// Sort reference tags array by field "tag".
		usort($all_tags, function($a, $b) {
			return strcasecmp($a['tag'], $b['tag']);
		});
		// Remove duplicated tags and reindex the keys.
		return array_values(array_unique($all_tags, SORT_REGULAR));
	}

	/**
	 * Prepare only unique inherited tags form host and/or template and remove element tags from them.
	 *
	 * @param array $tags			element tags data
	 * @param array $parent_tags	host or template tags
	 *
	 * @return array
	 */
	private function prepareInheritedTags($tags, $parent_tags = false) {
		if (!$parent_tags) {
			$host_template_tags = array_merge(self::HOST_TAGS, self::TEMPLATE_TAGS);
			$parent_tags = array_unique($host_template_tags, SORT_REGULAR);
		}

		$inherited_tags = array_filter($parent_tags, function ($tag) use ($tags) {
			foreach ($tags as $element_tag) {
				if ($element_tag == $tag) {
					return false;
				}
			}

			return true;
		});

		usort($inherited_tags, function($a, $b) {
			return strcasecmp($a['tag'], $b['tag']);
		});

		return array_values($inherited_tags);
	}

	/**
	 * Check removing tags from different objects.
	 *
	 * @param string   $object   host, template, trigger, service etc.
	 */
	public function clearTags($object) {
		$tags = (!$this->problem_tags)
				? [['tag' => '', 'value' => '']]
				: [['tag' => '', 'operator' => 'Equals', 'value' => '']];

		$data = ['name' => $this->remove_name, 'tags' => $tags];
		$this->page->login()->open($this->link)->waitUntilReady();

		if ($object === 'service') {
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
			$table->findRow('Name', $data['name'], true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
		}
		else {
			if ($object === 'template') {
				$this->query('button:Reset')->one()->click();
				$filter = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
				$filter->fill(['Name' => $this->remove_name]);
				$this->query('button:Apply')->one()->waitUntilClickable()->click();
			}

			$this->query('link', $this->remove_name)->waitUntilClickable()->one()->hoverMouse()->click();
		}

		$locators = [
			'web scenario' => 'name:webscenario_form',
			'host prototype' => 'name:hostPrototypeForm'
		];

		$form = ($object === 'web scenario' || $object === 'host prototype')
				? $this->query($locators[$object])->asForm()->waitUntilPresent()->one()
				: COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		if (!$this->problem_tags && $object !== 'connector') {
			$form->selectTab('Tags');
		}

		$this->query($this->tags_table)->asMultifieldTable()->waitUntilPresent()->one()->clear();
		$form->submit();
		$this->page->waitUntilReady();

		$this->checkResult($data, $object, $form, 'update');
	}
}
