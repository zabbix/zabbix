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
	public $host;
	public $template;

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
					'expected' => TEST_BAD,
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'tag' => 'tag without value',
							'value' => ''
						]
					],
					'error_details' => 'Invalid parameter "/tags/3": value (tag, value)=(tag without value, ) already exists.',
					'trigger_error_details' => 'Invalid parameter "/1/tags/3": value (tag, value)=(tag without value, ) already exists.'
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
	 * Check update tags in host, template, trigger or prototype.
	 *
	 * @param array    $data     data provider
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
		$this->query('id:tags-table')->asMultifieldTable()->waitUntilPresent()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		$this->checkResult($data, $object, $form, 'update', $sql, $old_hash);
	}

	/**
	 * Check result after creating or updating object with tags.
	 *
	 * @param array     $data        data provider
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
			// 2 elements for test case "InheritedHostAndTemplateTags"
			$count_elements = (strpos($data['name'], 'Inheritance') !== false) ? 2 : 1;
			$this->assertEquals($count_elements, CDBHelper::getCount($success_sql));

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
		$new_name = (strpos($object, 'prototype') !== false) ? 'Tags - '.$action.' '.$object.' {#KEY}' : '1Tags - '.$action.' '.$object;

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
		$this->query('button', $action)->one()->click();
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

	/**
	 * Test full cloning of host or template with trigger or prototype that have tags.
	 *
	 * @param string   $object   trigger or trigger prototype
	 * @param string   $parent   host or template
	 */
	public function executeFullCloning($object, $parent) {
		$new_name = '1Tags - full cloning of '.$parent.' with '.$object;
		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();

		// Get tags of object.
		$form = $this->query('xpath://main/form')->asForm()->waitUntilPresent()->one();
		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		// Navigate to host or template for full cloning.
		$name = ($parent === 'Host') ? $this->host : $this->template;
		$this->query('link', $name)->waitUntilClickable()->one()->click();
		$form->invalidate();
		$form->fill([$parent.' name' => $new_name]);
		$this->query('button:Full clone')->one()->click();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $parent.' added');

		// Open cloned host/template.
		$this->query('link', $new_name)->one()->click();

		switch ($object) {
			case 'trigger':
				$this->query('link', ucfirst($object).'s')->waitUntilClickable()->one()->click();
				$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
				break;

			case 'trigger prototype':
				$this->query('link:Discovery rules')->waitUntilClickable()->one()->click();
				$this->query('link', ucfirst($object).'s')->waitUntilClickable()->one()->click();
				$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
				break;
		}

		$form->invalidate();
		$form->selectTab('Tags');
		$element->checkValue($tags);
	}

	/**
	 * Test copy of trigger or item.
	 *
	 * @param string   $target_type		target type
	 * @param string   $parent			host, host group or template name
	 */
	public function executeTriggerCopy($target_type, $parent) {
		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();

		// Get tags of object and return to the list.
		$form = $this->query('xpath://main/form')->asForm()->waitUntilPresent()->one();
		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();
		$this->query('button:Cancel')->one()->click();

		// Select object and copy to target.
		$table = $this->query('xpath://form[@name="triggersForm"]/table')->asTable()->waitUntilReady()->one();
		$table->findRow('Name', $this->clone_name)->select();
		$this->query('button:Copy')->one()->click();
		$copy_form = $this->query('name:elements_form')->asForm()->waitUntilPresent()->one();
		$copy_form->fill(['Target type' => $target_type.'s', 'Target' => $parent]);
		$copy_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Trigger copied');

		// Open host group, host or template and check object tags.
		if ($target_type !== 'Host group') {
			$this->page->open(($target_type === 'Host') ? 'hosts.php' : 'templates.php')->waitUntilReady();
			$this->query('link', $parent)->waitUntilClickable()->one()->click();
			$this->query('link', 'Triggers')->waitUntilClickable()->one()->click();
			$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->selectTab('Tags');
			$element->checkValue($tags);
		}
		else {
			$filter_form = $this->query('name:zbx_filter')->asForm()->one();
			$filter_form->fill(['Host groups' => $parent, 'Hosts' => '']);
			$result_form = $this->query('xpath://form[@name="triggersForm"]')->one();
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
	 * @param type $object			trigger, item or prototype
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
		$tags_table = $this->query('id:tags-table')->asMultifieldTable()->waitUntilVisible()->one();

		// Check all tags (inherited from host/template and own) on created element.
		$field_name = (strpos($object, 'prototype') !== false) ? str_replace(' prototype', '', $object) : $object;

		$form->fill(['id:show_inherited_tags' => 'Inherited and '.$field_name.' tags']);
		$this->page->waitUntilReady();
		$tags_table->checkValue($this->prepareAllTags($data['tags'], $parent_tags));

		// Check disabled inherited tags from host or template on created element.
		$this->assertEquals($this->prepareInheritedTags($data['tags'], $parent_tags), $this->getInheritedTags());
	}

	/**
	 * Check inheritance of tags from host and template on inherited element from template.
	 *
	 * @param array    $data		data provider
	 * @param string   $object		trigger, item or prototype
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
		$tags_table = $this->query('id:tags-table')->asMultifieldTable()->waitUntilVisible()->one();

		// Check all tags (inherited from host and template and own) on created element.
		$field_name = (strpos($object, 'prototype') !== false) ? str_replace(' prototype', '', $object) : $object;
		$form->fill(['id:show_inherited_tags' => 'Inherited and '.$field_name.' tags']);
		$this->page->waitUntilReady();
		$tags_table->checkValue($this->prepareAllTags($data['tags'], array_merge(self::HOST_TAGS, self::TEMPLATE_TAGS)));

		// Check empty column "Parent templates" except for inhereted unique template tags.
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
	}

	/**
	 * Get inherited tags from element page.
	 *
	 * @return array
	 */
	private function getInheritedTags() {
		$inherited_tags = [];

		$tags_table = $this->query('id:tags-table')->asMultifieldTable()->one();
		$headers = $tags_table->getHeadersText();
		// Find disabled rows of host and/or template tags by disabled Name field.
		$disabled_rows = $tags_table->findRows(function ($row) {
			return $row->getColumn('Name')->children()->one()->detect()->isEnabled() === false;
		});

		foreach ($disabled_rows as $row) {
			// Check other disabled fields.
			$this->assertFalse($row->getColumn('Value')->children()->one()->detect()->isEnabled());
			$this->assertFalse($row->getColumn('Action')->children()->one()->detect()->isEnabled());

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
}
