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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource EntitiesTags
 * @backup httptest
 */
class testFormTagsWeb extends testFormTags {

	public $update_name = 'Web scenario with tags for updating';
	public $clone_name = 'Web scenario with tags for cloning';
	public $remove_name = 'Web scenario for removing tags';
	public $link;
	public $saved_link;
	public $host = 'Host for tags testing';
	public $template = 'Template for tags testing';

	/**
	 * Test creating of Web scenario with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsWeb_Create($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->saved_link = 'httpconf.php?form=update&hostid='.$hostid.'&context=host&httptestid=';
		$this->checkTagsCreate($data, 'web scenario');
	}

	/**
	 * Test update of web scenario with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsWeb_Update($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->saved_link = 'httpconf.php?form=update&hostid='.$hostid.'&context=host&httptestid=';
		$this->checkTagsUpdate($data, 'web scenario');
	}

	/**
	 * Test cloning of Web scenario with tags.
	 */
	public function testFormTagsWeb_Clone() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.Host with tags for cloning');
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeCloning('web scenario');
	}

	/**
	 * Test host cloning with Web scenario.
	 */
	public function testFormTagsWeb_HostClone() {
		$this->host = 'Host with tags for cloning';
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->executeCloningByParent('web scenario', 'Host');
	}

	/**
	 * Test template cloning with Item.
	 */
	public function testFormTagsWeb_TemplateClone() {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$templateid.'&context=template';
		$this->clone_name = 'Template web scenario with tags for cloning';
		$this->executeCloningByParent('web scenario', 'Template');
	}

	/**
	 * Test tags inheritance from host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsWeb_InheritedHostTags($data) {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->saved_link = 'httpconf.php?form=update&hostid='.$hostid.'&context=host&httptestid=';
		$this->checkInheritedTags($data, 'web scenario', 'Host');
	}

	/**
	 * Test tags inheritance from template.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsWeb_InheritedTemplateTags($data) {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$templateid.'&context=template';
		$this->saved_link = 'httpconf.php?form=update&hostid='.$templateid.'&context=template&httptestid=';
		$this->checkInheritedTags($data, 'web scenario', 'Template');
	}

	/**
	 * Test tags of inherited web scenario from template on host.
	 *
	 * @dataProvider getTagsInheritanceData
	 */
	public function testFormTagsWeb_InheritedElementTags($data) {
		$templateid = CDataHelper::get('EntitiesTags.templateids.'.$this->template);
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'httpconf.php?filter_set=1&context=template&filter_hostids[0]='.$templateid;
		$this->saved_link = 'httpconf.php?form=update&hostid='.$templateid.'&context=template&httptestid=';
		$host_link = 'httpconf.php?filter_set=1&context=host&filter_hostids[0]='.$hostid;

		$this->checkInheritedElementTags($data, 'web scenario', $host_link);
	}

	/**
	 * Test removing tags from Web scenario.
	 */
	public function testFormTagsWeb_RemoveTags() {
		$hostid = CDataHelper::get('EntitiesTags.hostids.'.$this->host);
		$this->link = 'httpconf.php?filter_set=1&filter_hostids[0]='.$hostid.'&context=host';
		$this->saved_link = 'httpconf.php?form=update&hostid='.$hostid.'&context=host&httptestid=';
		$this->clearTags('web scenario');
	}
}
