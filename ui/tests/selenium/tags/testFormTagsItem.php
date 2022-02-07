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
 * @backup items
 */
class testFormTagsItem extends testFormTags {
	public $update_name = 'Item with tags for updating';
	public $clone_name = 'Item with tags for cloning';
	public $remove_name = 'Item for tags removing';
	public $link;
	public $saved_link = 'items.php?form=update&context=host&itemid=';
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Item with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsItem_Create($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->checkTagsCreate($data, 'item');
	}

	/**
	 * Test update of Item with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsItem_Update($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->checkTagsUpdate($data, 'item');
	}

	/**
	 * Test cloning of Item with tags.
	 */
	public function testFormTagsItem_Clone() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeCloning('item', 'Clone');
	}

	/**
	 * Test host full cloning with Item.
	 */
	public function testFormTagsItem_HostFullClone() {
		$this->host = 'Host with tags for cloning';
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeFullCloning('item', 'Host');
	}

	/**
	 * Test template full cloning with Item.
	 */
	public function testFormTagsItem_TemplateFullClone() {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$templateid.'&context=template';
		$this->clone_name = 'Template item with tags for full cloning';
		$this->executeFullCloning('item', 'Template');
	}

	/**
	 * Test host item copy to host.
	 */
	public function testFormTagsItem_CopyToHost() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeCopy('item', 'Host', 'Empty host');
	}

	/**
	 * Test host item copy to host group.
	 */
	public function testFormTagsItem_CopyToHostGroup() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeCopy('item', 'Host group', 'Group to copy graph');
	}

	/**
	 * Test host item copy to template.
	 */
	public function testFormTagsItem_CopyToTemplate() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeCopy('item', 'Template', 'Empty template');
	}

	/**
	 * Test tags inheritance from host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItem_InheritedHostTags($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->checkInheritedTags($data, 'item', 'Host');
	}

	/**
	 * Test tags inheritance from template.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItem_InheritedTemplateTags($data) {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$templateid.'&context=template';
		$this->checkInheritedTags($data, 'item', 'Template');
	}

	/**
	 * Test tags of inherited item from template on host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItem_InheritedElementTags($data) {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'items.php?filter_set=1&context=template&filter_hostids[0]='.$templateid;
		$host_link = 'items.php?filter_set=1&context=host&filter_hostids[0]='.$hostid;

		$this->checkInheritedElementTags($data, 'item', $host_link);
	}

	/**
	 * Test removing tags from Item.
	 */
	public function testFormTagsItem_RemoveTags() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->clearTags('item');
	}
}
