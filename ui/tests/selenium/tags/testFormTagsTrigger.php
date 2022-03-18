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
 * @dataSource EntitiesTags
 * @onBefore prepareCopyData
 * @backup triggers
 */
class testFormTagsTrigger extends testFormTags {

	public $update_name = 'Trigger with tags for updating';
	public $clone_name = 'Trigger with tags for cloning';
	public $remove_name = 'Trigger for tags removing';
	public $link;
	public $saved_link = 'triggers.php?form=update&context=host&triggerid=';
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Trigger with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsTrigger_Create($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$expression = 'last(/Host for tags testing/trap.host)=0';
		$this->checkTagsCreate($data, 'trigger', $expression);
	}

	/**
	 * Test update of Trigger with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsTrigger_Update($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->checkTagsUpdate($data, 'trigger');
	}

	/**
	 * Test cloning of Trigger with tags.
	 */
	public function testFormTagsTrigger_Clone() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->executeCloning('trigger', 'Clone');
	}

	/**
	 * Create item with a certain key to test trigger copy.
	 */
	public static function prepareCopyData() {
		CDataHelper::call('item.create', [
			[
				'hostid' => '99015', // Empty host
				'name' => 'Item to test trigger copy',
				'key_' => 'tags.clone',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => '99014', // Empty template
				'name' => 'Item to test trigger copy',
				'key_' => 'tags.clone',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => '99017', // "Host with item and without graph 1" on host group "Group to copy graph"
				'name' => 'Item to test trigger copy',
				'key_' => 'tags.clone',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => '99018', // "Host with item and without graph 1" on host group "Group to copy graph"
				'name' => 'Item to test trigger copy',
				'key_' => 'tags.clone',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);
	}

	/**
	 * Test host trigger copy to host.
	 */
	public function testFormTagsTrigger_CopyToHost() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->executeCopy('trigger', 'Host', 'Empty host');
	}

	/**
	 * Test host trigger copy to host group.
	 */
	public function testFormTagsTrigger_CopyToHostGroup() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->executeCopy('trigger', 'Host group', 'Group to copy graph');
	}

	/**
	 * Test host trigger copy to template.
	 */
	public function testFormTagsTrigger_CopyToTemplate() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->executeCopy('trigger', 'Template', 'Empty template');
	}

	/**
	 * Test host full cloning with Trigger.
	 */
	public function testFormTagsTrigger_HostFullClone() {
		$this->host = 'Host with tags for cloning';
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->executeFullCloning('trigger', 'Host');
	}

	/**
	 * Test template full cloning with Trigger.
	 */
	public function testFormTagsTrigger_TemplateFullClone() {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$this->link = 'triggers.php?filter_set=1&filter_hostids%5B0%5D='.$templateid.'&context=template';
		$this->clone_name = 'Template trigger with tags for full cloning';
		$this->executeFullCloning('trigger', 'Template');
	}

	/**
	 * Test tags inheritance from host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsTrigger_InheritedHostTags($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$expression = 'last(/Host for tags testing/trap.host)=0';
		$this->checkInheritedTags($data, 'trigger', 'Host', $expression);
	}

	/**
	 * Test tags inheritance from template.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	// TODO: uncomment after fix ZBX-19485
//	public function testFormTagsTrigger_InheritedTemplateTags($data) {
//		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
//		$this->link = 'triggers.php?filter_set=1&filter_hostids[0]='.$templateid.'&context=template';
//		$expression = 'last(/Template for tags testing/trap.template)=0';
//		$this->checkInheritedTags($data, 'trigger', 'Template', $expression);
//	}

	/**
	 * Test tags of inherited trigger from template on host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsTrigger_InheritedElementTags($data) {
		$expression = 'last(/Template for tags testing/trap.template)=0';
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'triggers.php?filter_set=1&context=template&filter_hostids[0]='.$templateid;
		$host_link = 'triggers.php?filter_set=1&context=host&filter_hostids[0]='.$hostid;

		$this->checkInheritedElementTags($data, 'trigger', $host_link, $expression);
	}

	/**
	 * Test removing tags from Trigger.
	 */
	public function testFormTagsTrigger_RemoveTags() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'triggers.php?filter_set=1&context=host&filter_hostids%5B0%5D='.$hostid;
		$this->clearTags('trigger');
	}
}
