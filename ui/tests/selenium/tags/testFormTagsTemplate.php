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
class testFormTagsTemplate extends testFormTags {

	public $update_name = '2 template with tags for updating';
	public $clone_name = '1 template with tags for cloning';
	public $remove_name = '1 template for removing tags';
	public $link = 'zabbix.php?action=template.list';

	/**
	 * Test creating of Template with tags
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsTemplate_Create($data) {
		$this->checkTagsCreate($data, 'template');
	}

	/**
	 * Test update of Template with tags
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsTemplate_Update($data) {
		$this->checkTagsUpdate($data, 'template');
	}

	/**
	 * Test cloning of Template with tags.
	 */
	public function testFormTagsTemplate_Clone() {
		$this->executeCloning('template');
	}

	/**
	 * Test removing tags from Template.
	 */
	public function testFormTagsTemplate_RemoveTags() {
		$this->clearTags('template');
	}
}
