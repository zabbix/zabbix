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
 * @backup hosts
 */
class testFormTagsHost extends CWebTest {

	/**
	 * The name of the host for cloning in the test data set.
	 *
	 * @var string
	 */
	protected $clone_host = 'Host with tags for cloning';

	/**
	 * The name of the host for updating in the test data set.
	 *
	 * @var string
	 */
	protected $update_host = 'Host with tags for updating';

	public static function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'host_name' => 'Host with tags',
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
					'host_name' => 'Host with equal tag names',
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
					'host_name' => 'Host with equal tag values',
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
					'host_name' => 'Host with empty tag name',
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'value1'
						]
					],
					'error' => 'Cannot add host',
					'error_details' => 'Invalid parameter "/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_name' => 'Host with equal tags',
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
					'error' => 'Cannot add host',
					'error_details' => 'Invalid parameter "/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			]
		];
	}

	/**
	 * Test creating of host with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsHost_Create($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);

		$this->page->login()->open('hosts.php');
		$this->query('button:Create host')->waitUntilPresent()->one()->click();
		$form = $this->query('id:hosts-form')->waitUntilPresent()->asForm()->one();
		$form->fill([
			'Host name' => $data['host_name'],
			'Groups' => 'Zabbix servers'
		]);

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Host added', $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['host_name'])));
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
							'tag' => '',
							'value' => 'value1'
						]
					],
					'error' => 'Cannot update host',
					'error_details' => 'Invalid parameter "/tags/1/tag": cannot be empty.'
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
					'error' => 'Cannot update host',
					'error_details' => 'Invalid parameter "/tags/2": value (tag, value)=(action, update) already exists.'
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
	 * Test update of host with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsHost_Update($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);
		$data['host_name'] = $this->update_host;

		$this->page->login()->open('hosts.php');
		$this->query('link', $this->update_host)->waitUntilPresent()->one()->click();
		$form = $this->query('id:hosts-form')->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$this->query('id:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals('Host updated', $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->update_host)));
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

	public function testFormTagsHost_Clone() {
		$this->executeCloning('Clone');
	}

	public function testFormTagsHost_FullClone() {
		$this->executeCloning('Full clone');
	}

	/**
	 * Test cloning of host with tags
	 */
	private function executeCloning($action) {
		$new_name = 'Host with tags for cloning - '.$action;

		$this->page->login()->open('hosts.php');
		$this->query('link', $this->clone_host)->waitUntilPresent()->one()->click();
		$form = $this->query('id:hosts-form')->waitUntilPresent()->asForm()->one();
		$form->getField('Host name')->fill($new_name);

		$form->selectTab('Tags');
		$element = $this->query('id:tags-table')->asMultifieldTable()->one();
		$tags = $element->getValue();

		$this->query('button:'.$action)->one()->click();
		$form->submit();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Host added', $message->getTitle());
		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_host)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name)));

		// Check created clone.
		$this->query('link', $new_name)->one()->click();
		$form->invalidate();
		$name = $form->getField('Host name')->getValue();
		$this->assertEquals($new_name, $name);

		$form->selectTab('Tags');
		$element->checkValue($tags);
	}

	private function checkTagFields($data) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host_name']));
		$this->page->open('hosts.php?form=update&hostid='.$id.'&groupid=0');
		$form = $this->query('id:hosts-form')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Tags');

		$expected = $data['tags'];
		foreach ($expected as &$tag) {
			unset($tag['action'], $tag['index']);
		}
		unset($tag);

		$this->query('id:tags-table')->asMultifieldTable()->one()->checkValue($expected);
	}
}
