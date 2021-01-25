<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormTags.php';

/**
 * @backup hosts
 */
class testFormTagsHostPrototype extends testFormTags {

	public $update_name = '{#HOST} prototype with tags for updating';
	public $clone_name = '{#HOST} prototype with tags for cloning';
	public $link = 'host_prototypes.php?parent_discoveryid=90001';
	public $saved_link = 'host_prototypes.php?form=update&parent_discoveryid=90001&hostid=';
	public $new_name = 'Cloned Host prototype {#KEY}';

	/**
	 * Test creating of Host prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsHostPrototype_Create($data) {
		$this->checkTagsCreate($data, 'host prototype');
	}

	/**
	 * Test update of Host prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsHostPrototype_Update($data) {
		$this->checkTagsUpdate($data, 'host prototype');
	}

	/**
	 * Test cloning of Host prototype with tags.
	 */
	public function testFormTagsHostPrototype_Clone() {
		$this->executeCloning('host prototype', 'Clone');
	}
}
