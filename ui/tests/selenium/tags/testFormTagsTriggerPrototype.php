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
 * @backup triggers
 */
class testFormTagsTriggerPrototype extends testFormTags {

	public $update_name = 'Trigger prototype with tags for updating';
	public $clone_name = 'Trigger prototype with tags for cloning';
	public $remove_name = 'Trigger prototype for removing tags';
	public $link;
	public $saved_link;
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Trigger prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsTriggerPrototype_Create($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'trigger_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&triggerid=';
		$expression = 'last(/Host for tags testing/itemprototype_trap[{#KEY}])=0';
		$this->checkTagsCreate($data, 'trigger prototype', $expression);
	}

	/**
	 * Test update of Trigger prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsTriggerPrototype_Update($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'trigger_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&triggerid=';
		$this->checkTagsUpdate($data, 'trigger prototype');
	}

	/**
	 * Test cloning of Trigger prototype with tags.
	 */
	public function testFormTagsTriggerPrototype_Clone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host with tags for cloning:trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeCloning('trigger prototype', 'Clone');
	}

	/**
	 * Test host full cloning with Trigger prototype.
	 */
	public function testFormTagsTriggerPrototype_HostFullClone() {
		$this->host = 'Host with tags for cloning';
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeFullCloning('trigger prototype', 'Host');
	}

	/**
	 * Test template full cloning with Trigger prototype.
	 */
	public function testFormTagsTriggerPrototype_TemplateFullClone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->clone_name = 'Template trigger prototype with tags for full cloning';
		$this->executeFullCloning('trigger prototype', 'Template');
	}

	/**
	 * Test tags inheritance from host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsTriggerPrototype_InheritedHostTags($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'trigger_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&triggerid=';
		$expression = 'last(/Host for tags testing/itemprototype_trap[{#KEY}])=0';
		$this->checkInheritedTags($data, 'trigger prototype', 'Host', $expression);
	}

	/**
	 * Test tags inheritance from template.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	// TODO: uncomment after fix ZBX-19485
//	public function testFormTagsTriggerPrototype_InheritedTemplateTags($data) {
//		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
//		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
//		$this->saved_link = 'trigger_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&triggerid=';
//		$expression = 'last(/Template for tags testing/template.itemprototype_trap[{#KEY}])=0';
//		$this->checkInheritedTags($data, 'trigger prototype', 'Template', $expression);
//	}

	/**
	 * Test tags of inherited trigger prototype from template on host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsTriggerPrototype_InheritedElementTags($data) {
		$expression = 'last(/Template for tags testing/template.itemprototype_trap[{#KEY}])=0';
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->saved_link = 'trigger_prototypes.php?form=update&context=template&parent_discoveryid='.$discoveryruleid.'&triggerid=';
		$host_link = 'host_discovery.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';

		$this->checkInheritedElementTags($data, 'trigger prototype', $host_link, $expression);
	}

	/**
	 * Test removing tags from Trigger prototype.
	 */
	public function testFormTagsTriggerPrototype_RemoveTags() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'trigger_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'trigger_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&triggerid=';
		$this->clearTags('trigger prototype');
	}
}
