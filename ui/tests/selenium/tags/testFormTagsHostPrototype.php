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

require_once __DIR__.'/../common/testFormTags.php';

/**
 * @dataSource EntitiesTags
 * @backup hosts
 */
class testFormTagsHostPrototype extends testFormTags {

	public $update_name = '{#HOST} prototype with tags for updating';
	public $clone_name = '{#HOST} prototype with tags for cloning';
	public $remove_name = '{#HOST} prototype with for removing tags';
	public $link;
	public $saved_link;
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Host prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsHostPrototype_Create($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&hostid=';
		$this->checkTagsCreate($data, 'host prototype');
	}

	/**
	 * Test update of Host prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsHostPrototype_Update($data) {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&hostid=';
		$this->checkTagsUpdate($data, 'host prototype');
	}

	/**
	 * Test cloning of Host prototype with tags.
	 */
	public function testFormTagsHostPrototype_Clone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host with tags for cloning:trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeCloning('host prototype');
	}

	/**
	 * Test host cloning with Host prototype.
	 */
	public function testFormTagsHostPrototype_HostClone() {
		$this->host = 'Host with tags for cloning';
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->host.':trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->executeCloningByParent('host prototype', 'Host');
	}

	/**
	 * Test template cloning with Host prototype.
	 */
	public function testFormTagsHostPrototype_TemplateClone() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->clone_name = '{#TEMPLATE} prototype with tags for cloning';
		$this->executeCloningByParent('host prototype', 'Template');
	}

	/**
	 * Test tags of inherited host prototype from template on host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsHostPrototype_InheritedElementTags($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.'.$this->template.':template_trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=template';
		$this->saved_link = 'host_prototypes.php?form=update&context=template&parent_discoveryid='.$discoveryruleid.'&hostid=';
		$host_link = 'host_discovery.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';

		$form = $this->checkTagsCreate($data, 'host prototype');
		// Remove index and action key in tags of element.
		unset($data['tags'][0]['action'], $data['tags'][0]['index']);

		// Check created element tags.
		$this->page->open($host_link);
		$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();
		$table->findRow('Name', $this->template, true)->getColumn('Hosts')->children()->one()->click();
		$this->query('link', $data['name'].' {#KEY}')->waitUntilPresent()->one()->click();
		$form->selectTab('Tags');
		$tags_table = $this->query('class:tags-table')->asMultifieldTable()->waitUntilVisible()->one();
		$tags_table->checkValue($data['tags']);

		// Check disabled fields.
		foreach ($tags_table->getRows() as $row) {
			foreach (['Name', 'Value', ''] as $field) {
				$this->assertFalse($row->getColumn($field)->children()->one()->detect()->isEnabled());
			}
		}
	}

	/**
	 * Test removing tags from Host prototype.
	 */
	public function testFormTagsHostPrototype_RemoveTags() {
		$discoveryruleid = CDataHelper::get('EntitiesTags.discoveryruleids.Host for tags testing:trap_discovery');
		$this->link = 'host_prototypes.php?parent_discoveryid='.$discoveryruleid.'&context=host';
		$this->saved_link = 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$discoveryruleid.'&hostid=';
		$this->clearTags('host prototype');
	}
}
