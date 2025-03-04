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


require_once dirname(__FILE__).'/../common/testFormTags.php';

/**
 * @dataSource EntitiesTags
 * @backup items
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
 */
class testFormTagsItemPrototype extends testFormTags {

	public $update_name = 'Item prototype with tags for updating: {#KEY}';
	public $clone_name = 'Item prototype with tags for cloning: {#KEY}';
	public $remove_name = 'Item prototype for removing tags: {#KEY}';
	public $link;
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Item prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsItemPrototype_Create($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->checkTagsCreate($data, 'item prototype');
	}

	/**
	 * Test update of Item prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsItemPrototype_Update($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->checkTagsUpdate($data, 'item prototype');
	}

	/**
	 * Test cloning of Item prototype with tags.
	 */
	public function testFormTagsItemPrototype_Clone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host with tags for cloning:trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeCloning('item prototype');
	}

	/**
	 * Test host cloning with Item prototype.
	 */
	public function testFormTagsItemPrototype_HostClone() {
		$this->host = 'Host with tags for cloning';
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeCloningByParent('item prototype', 'Host');
	}

	/**
	 * Test template cloning with Item prototype.
	 */
	public function testFormTagsItemPrototype_TemplateClone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->clone_name = 'Template item prototype with tags for cloning: {#KEY}';
		$this->executeCloningByParent('item prototype', 'Template');
	}

	/**
	 * Test tags inheritance from host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItemPrototype_InheritedHostTags($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->checkInheritedTags($data, 'item prototype', 'Host');
	}

	/**
	 * Test tags inheritance from template.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItemPrototype_InheritedTemplateTags($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->checkInheritedTags($data, 'item prototype', 'Template');
	}

	/**
	 * Test tags of inherited item prototype from template on host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItemPrototype_InheritedElementTags($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=template';
		$host_link = 'host_discovery.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';

		$this->checkInheritedElementTags($data, 'item prototype', $host_link);
	}

	/**
	 * Test removing tags from Item prototype.
	 */
	public function testFormTagsItemPrototype_RemoveTags() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->clearTags('item prototype');
	}
}
