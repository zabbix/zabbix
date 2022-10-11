<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testFormTags.php';

/**
 * @dataSource DiscoveredHosts
 * @backup hosts
 */
class testFormTagsDiscoveredHost extends testFormTags {

	public $update_name = 'Discovered host from prototype 1';
	public $clone_name = 'Discovered host from prototype 11';
	public $remove_name = 'Discovered host from prototype 1';
	public $link = 'zabbix.php?action=host.list';
	public $saved_link = 'zabbix.php?action=host.edit&hostid=';

	/**
	 * Test update of Discovered Host with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsDiscoveredHost_Update($data) {
		$this->checkTagsUpdate($data, 'host');
	}

	public static function getInheritedUpdateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'tags' => [
						[
							'tag' => '',
							'value' => 'value1'
						]
					],
					'error_details'=>'Invalid parameter "/1/tags/1/tag": cannot be empty.'
				]
			],
			// No errors for the same added tags, but the duplicate tag is not saved.
			[
				[
					'tags' => [
						[
							'tag' => 'discovered',
							'value' => 'true'
						],
						[
							'tag' => 'discovered without tag value',
							'value' => ''
						]
					]
				]
			],
			[
				[
					'tags' => [
						[
							'tag' => 'discovered',
							'value' => 'true'
						],
						[
							'tag' => 'discovered without tag value',
							'value' => ''
						],
						[
							'tag' => 'discovered without tag value',
							'value' => 'true'
						]
					]
				]
			]
		];
	}

	/**
	 * Test update of Discovered Host with inherited tags.
	 *
	 * @dataProvider getInheritedUpdateData
	 */
	public function testFormTagsDiscoveredHost_InheritedUpdate($data) {
		$this->update_name = $this->clone_name;
		$this->checkTagsUpdate($data, 'host');
	}

	/**
	 * Test cloning of Discovered Host with tags.
	 */
	public function testFormTagsDiscoveredHost_Clone() {
		$this->executeCloning('discovered host', 'Clone');
	}

	/**
	 * Test full cloning of Discovered Host with tags.
	 */
	public function testFormTagsDiscoveredHost_FullClone() {
		$this->executeCloning('discovered host', 'Full clone');
	}

	/**
	 * Test removing tags from Discovered Host.
	 */
	public function testFormTagsDiscoveredHost_RemoveTags() {
		$this->clearTags('host');
	}

	/**
	 * Test of Discovered Host inherited tag.
	 */
	public function testFormTagsDiscoveredHost_InheritedTagLayout() {
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->clone_name).' AND flags=4');
		$all_tags = CDBHelper::getAll('SELECT tag, value FROM host_tag WHERE hostid='.$hostid.' ORDER BY tag, value');
		$inerited_tags = CDBHelper::getAll('SELECT tag, value FROM host_tag WHERE automatic=1 AND hostid='.
				$hostid.' ORDER BY tag, value');
		$this->page->login()->open($this->link);
		$this->query('link', $this->clone_name)->waitUntilClickable()->one()->click();
		$form = COverlayDialogElement::find()->waitUntilVisible()->asForm()->one();
		$form->selectTab('Tags');
		$tags_table = $this->query('class:tags-table')->asMultifieldTable()->one();
		$tags_table->checkValue($all_tags);

		foreach ($inerited_tags as $tag) {
			$row = $tags_table->findRow('Name', $tag['tag']);
			// Inherited tags are disabled and don't contain a remove button.
			$this->assertFalse($row->query('button:Remove')->one(false)->isValid());
			$this->assertFalse($row->getColumn('Name')->children()->one()->isEnabled());
			$this->assertFalse($row->getColumn('Value')->children()->one()->isEnabled());
			$this->assertEquals('(created by host discovery)', $row->getColumn('')->getText());
		}
	}
}
