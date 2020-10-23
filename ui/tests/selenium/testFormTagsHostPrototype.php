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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup hosts
 */
class testFormTagsHostPrototype extends CWebTest {

	const DICROVERY_RULE_ID = 90001;			// "Discovery rule 1" on host "Host for host prototype tests"

	/**
	 * The name of the host for cloning in the test data set.
	 *
	 * @var string
	 */
	protected $clone_prototype = '{#HOST} prototype with tags for cloning';

	/**
	 * The name of the host for updating in the test data set.
	 *
	 * @var string
	 */
	protected $update_prototype = '{#HOST} prototype with tags for updating';

	/**
	 * Attach FormParametersBehavior and CMessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'host_name' => 'Host with tags {#KEY}',
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
					'host_name' => 'Host with equal tag names {#KEY}',
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
					'host_name' => 'Host with equal tag values {#KEY}',
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
					'host_name' => 'Host with empty tag name {#KEY}',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'value1'
						]
					],
					'error' => 'Cannot add host prototype',
					'error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_name' => 'Host with equal tags {#KEY}',
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
					'error' => 'Cannot add host prototype',
					'error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			]
		];
	}

	/**
	 * Test creating of host prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsHostPrototype_Create($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);

		$this->page->login()->open('host_prototypes.php?parent_discoveryid='.self::DICROVERY_RULE_ID);
		$this->query('button:Create host prototype')->waitUntilPresent()->one()->click();
		$form = $this->query('id:host-prototype-form')->waitUntilPresent()->asForm()->one();
		$form->fill(['Host name' => $data['host_name']]);

		$form->selectTab('Groups');
		$form->fill(['Groups' => 'Zabbix servers']);

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, 'Host prototype added');
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['host_name'])));
				// Check the results in form.
				$this->checkTagFields($data);
				break;
			case TEST_BAD:
				$this->assertMessage(TEST_BAD, $data['error'], $data['error_details']);
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
							'tag' => '',
							'value' => 'value1'
						]
					],
					'error' => 'Cannot update host prototype',
					'error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
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
					'error' => 'Cannot update host prototype',
					'error_details' => 'Invalid parameter "/1/tags/2": value (tag, value)=(action, update) already exists.'
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
	 * Test update of host prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsHostPrototype_Update($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);
		$data['host_name'] = $this->update_prototype;

		$this->page->login()->open('host_prototypes.php?parent_discoveryid='.self::DICROVERY_RULE_ID);
		$this->query('link', $this->update_prototype)->waitUntilPresent()->one()->click();
		$form = $this->query('id:host-prototype-form')->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertMessage(TEST_GOOD, 'Host prototype updated');
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->update_prototype)));
				// Check the results in form.
				$this->checkTagFields($data);
				break;
			case TEST_BAD:
				$this->assertMessage(TEST_BAD, $data['error'], $data['error_details']);
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hosts));
				break;
		}
	}

	/**
	 * Test cloning of host prototype with tags
	 */
	public function testFormTagsHostPrototype_Clone() {
		$new_name = 'Cloned {#HOST} prototype with tags for cloning';

		$this->page->login()->open('host_prototypes.php?parent_discoveryid='.self::DICROVERY_RULE_ID);
		$this->query('link', $this->clone_prototype)->waitUntilPresent()->one()->click();
		$form = $this->query('id:host-prototype-form')->waitUntilPresent()->asForm()->one();
		$form->getField('Host name')->fill($new_name);

		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		$this->query('button:Clone')->one()->click();
		$form->submit();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Host prototype added', $message->getTitle());
		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_prototype)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name)));

		// Check created clone.
		$this->query('link', $new_name)->one()->click();
		$form->invalidate();
		$this->assertEquals($new_name, $form->getField('Host name')->getValue());

		$form->selectTab('Tags');
		$element->checkValue($tags);
	}

	private function checkTagFields($data) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host_name']));
		$this->page->open('host_prototypes.php?form=update&parent_discoveryid='.self::DICROVERY_RULE_ID.'&hostid='.$id);
		$form = $this->query('id:host-prototype-form')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Tags');

		$expected = $data['tags'];
		foreach ($expected as &$tag) {
			unset($tag['action'], $tag['index']);
		}
		unset($tag);

		$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($expected);
	}
}

