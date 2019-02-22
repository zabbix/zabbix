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
 * @backup hosts
 */
class testFormHostTags extends CWebTest {

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
					'host_name' => 'Host with equal tag names',
					'tags' => [
						['name'=>'tag3', 'value'=>'3'],
						['name'=>'tag3', 'value'=>'4'],

					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'host_name' => 'Host with equal tag values',
					'tags' => [
						['name'=>'tag4', 'value'=>'5'],
						['name'=>'tag5', 'value'=>'5'],

					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_name' => 'Host with empty tag name',
					'tags' => [
						['name'=>'', 'value'=>'value1']
					],
					'error'=>'Cannot add host',
					'error_details'=>'Invalid parameter "/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_name' => 'Host with equal tags',
					'tags' => [
						['name'=>'tag', 'value'=>'value'],
						['name'=>'tag', 'value'=>'value']
					],
					'error'=>'Cannot add host',
					'error_details'=>'Invalid parameter "/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			]
		];
	}

	/**
	 * Test creating of host with tags
	 *
	 * @dataProvider getCreateData
	 *
	 */
	public function testFormHostTags_Create($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);

		$this->page->login()->open('hosts.php');
		$this->query('button:Create host')->waitUntilPresent()->one()->click();
		$form = $this->query('id:hostForm')->waitUntilPresent()->asForm()->one();
		$form->getLabel('Host name')->fill($data['host_name']);
		$groups = $form->getField('Groups')->asMultiselect()->select('Zabbix servers');
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
				$this->assertEquals('Host added', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['host_name'])));
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
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hosts));
				break;
		}
	}

	public static function getUpdateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'host_name' => 'Updated Host with empty tag name',
					'tags' => [
						['name'=>'', 'value'=>'value1']
					],
					'error'=>'Cannot update host',
					'error_details'=>'Invalid parameter "/tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'host_name' => ' Updated Host with equal tags',
					'tags' => [
						['name'=>'tag', 'value'=>'value'],
						['name'=>'tag', 'value'=>'value']
					],
					'error'=>'Cannot update host',
					'error_details'=>'Invalid parameter "/tags/2": value (tag, value)=(tag, value) already exists.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'host_name' => 'Updated Host with tags',
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
	 * Test update of host with tags
	 *
	 * @dataProvider getUpdateData
	 *
	 */
	public function testFormHostTags_Update($data) {
		$sql_hosts = "SELECT * FROM hosts ORDER BY hostid";
		$old_hash = CDBHelper::getHash($sql_hosts);

		$this->page->login()->open('hosts.php');
		$this->query('link:'.$this->update_host)->waitUntilPresent()->one()->click();
		$form = $this->query('id:hostForm')->waitUntilPresent()->asForm()->one();

		$form->getField('Host name')->clear()->type($data['host_name']);

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
				$this->assertEquals('Host updated', $message->getTitle());
				// Check the results in DB.
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->update_host)));
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['host_name'])));
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
				$this->assertEquals($old_hash, CDBHelper::getHash($sql_hosts));
				break;
		}
	}

	public function testFormHostTags_Clone() {
		$this->executeCloning('Clone');
	}

	public function testFormHostTags_FullClone() {
		$this->executeCloning('Full clone');
	}

	/**
	 * Test cloning of host with tags
	 */
	private function executeCloning($action) {
		$this->page->login()->open('hosts.php');
		$this->query('link:'.$this->clone_host)->waitUntilPresent()->one()->click();
		$form = $this->query('id:hostForm')->waitUntilPresent()->asForm()->one();

		$form->selectTab('Tags');
		$tags_table = $this->query('id:tags-table')->asTable()->one();

		$tags = [];
		foreach ($tags_table->getRows()->slice(0, -1) as $row) {
			$tags[] = [
				'name' => $row->getColumn('Name')->children()->one()->getAttribute('value'),
				'value' => $row->getColumn('Value')->children()->one()->getAttribute('value')
			];
		}
		$form->selectTab('Host');

		$this->query('button:'.$action)->one()->click();

		$new_name = 'Host with tags for cloning - '.$action;
		$form->getField('Host name')->clear()->type($new_name);

		$form->submit();
		$this->page->waitUntilReady();
		// Get global message.
		$message = CMessageElement::find()->one();
		// Check if message is positive.
		$this->assertTrue($message->isGood());
		// Check message title.
		$this->assertEquals('Host added', $message->getTitle());
		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($this->clone_host)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($new_name)));

		// Check created clone.
		$this->query('link:'.$new_name)->one()->click();
		$form = $this->query('id:hostForm')->asForm()->one();
		$name = $form->getField('Host name')->getAttribute('value');
		$this->assertEquals($name, $new_name);

		$form->selectTab('Tags');
		$tags_table = $this->query('id:tags-table')->asTable()->one();

		foreach ($tags_table->getRows()->slice(0, -1) as $i => $row) { // Slice rows to cut off Add button.
			$this->assertEquals($tags[$i], [
				'name' => $row->getColumn('Name')->children()->one()->getAttribute('value'),
				'value' => $row->getColumn('Value')->children()->one()->getAttribute('value')
			]);
		}
	}

	private function checkFormFields($data) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host_name']));
		$this->page->open('hosts.php?form=update&hostid='.$id.'&groupid=0');
		$form = $this->query('id:hostForm')->waitUntilPresent()->asForm()->one();
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
