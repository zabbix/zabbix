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
class testFormTagsItemPrototype extends testFormTags {

	public $update_name = 'Item prototype with tags for updating: {#KEY}';
	public $clone_name = 'Item prototype with tags for cloning: {#KEY}';
	public $remove_name = 'Item prototype for removig tags: {#KEY}';
	public $link;
	public $saved_link;
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Item prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsItemPrototype_Create($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&itemid=';
		$this->checkTagsCreate($data, 'item prototype');
	}

	/**
	 * Test update of Item prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsItemPrototype_Update($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&itemid=';
		$this->checkTagsUpdate($data, 'item prototype');
	}

	/**
	 * Test cloning of Item prototype with tags.
	 */
	public function testFormTagsItemPrototype_Clone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host with tags for cloning:trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeCloning('item prototype', 'Clone');
	}

	/**
	 * Test host full cloning with Item prototype.
	 */
	public function testFormTagsItemPrototype_HostFullClone() {
		$this->host = 'Host with tags for cloning';
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeFullCloning('item prototype', 'Host');
	}

	/**
	 * Test template full cloning with Item prototype.
	 */
	public function testFormTagsItemPrototype_TemplateFullClone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->clone_name = 'Template item prototype with tags for full cloning: {#KEY}';
		$this->executeFullCloning('item prototype', 'Template');
	}

	/**
	 * Test tags inheritance from host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItemPrototype_InheritedHostTags($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&itemid=';
		$this->checkInheritedTags($data, 'item prototype', 'Host');
	}

	/**
	 * Test tags inheritance from template.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsItemPrototype_InheritedTemplateTags($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->saved_link = 'disc_prototypes.php?form=update&context=template&parent_discoveryid='.$discoveryruleid.'&itemid=';
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
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->saved_link = 'disc_prototypes.php?form=update&context=template&parent_discoveryid='.$discoveryruleid.'&itemid=';
		$host_link = 'host_discovery.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';

		$this->checkInheritedElementTags($data, 'item prototype', $host_link);
	}

	/**
	 * Test removing tags from Item prototype.
	 */
	public function testFormTagsItemPrototype_RemoveTags() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'disc_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&itemid=';
		$this->clearTags('item prototype');
	}
}
