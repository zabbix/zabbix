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
class testFormTagsHost extends testFormTags {

	public $update_name = 'Host with tags for updating';
	public $clone_name = 'Host with tags for cloning';
	public $remove_name = 'Host for removing tags';
	public $link = 'zabbix.php?action=host.list';
	public $saved_link = 'zabbix.php?action=popup&popup=host.edit&hostid=';

	/**
	 * Test creating of Host with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsHost_Create($data) {
		$this->checkTagsCreate($data, 'host');
	}

	/**
	 * Test update of Host with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsHost_Update($data) {
		$this->checkTagsUpdate($data, 'host');
	}

	/**
	 * Test cloning of Host with tags.
	 */
	public function testFormTagsHost_Clone() {
		$this->executeCloning('host');
	}

	/**
	 * Test removing tags from Host.
	 */
	public function testFormTagsHost_RemoveTags() {
		$this->clearTags('host');
	}
}
