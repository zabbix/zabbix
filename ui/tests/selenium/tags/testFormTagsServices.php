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
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource EntitiesTags
 *
 * @backup services
 */
class testFormTagsServices extends testFormTags {

	public $update_name = 'Service with tags for updating';
	public $clone_name = 'Service with tags for cloning';
	public $remove_name = 'Service for removing tags';
	public $link = 'zabbix.php?action=service.list.edit';

	/**
	 * Test creating of Service with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsServices_Create($data) {
		$this->checkTagsCreate($data, 'service');
	}

	/**
	 * Test update of Service with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsServices_Update($data) {
		$this->checkTagsUpdate($data, 'service');
	}

	/**
	 * Test cloning of Service with tags.
	 */
	public function testFormTagsServices_Clone() {
		$this->executeCloning('service');
	}

	/**
	 * Test removing tags from Service.
	 */
	public function testFormTagsServices_RemoveTags() {
		$this->clearTags('service');
	}
}
