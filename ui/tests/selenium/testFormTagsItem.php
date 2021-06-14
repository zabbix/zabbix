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
 * @backup items
 */
class testFormTagsItem extends testFormTags {

	public $update_name = 'Item with tags for updating';
	public $clone_name = 'Item with tags for cloning';
	public $link = 'items.php?filter_set=1&filter_hostids%5B0%5D=99109&context=host';
	public $saved_link = 'items.php?form=update&context=host&itemid=';
	public $new_name = 'Cloned Item';

	/**
	 * Test creating of Item with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsItem_Create($data) {
		$this->checkTagsCreate($data, 'item');
	}

	/**
	 * Test update of Item with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsItem_Update($data) {
		$this->checkTagsUpdate($data, 'item');
	}

	/**
	 * Test cloning of Item prototype with tags.
	 */
	public function testFormTagsItem_Clone() {
		$this->executeCloning('item', 'Clone');
	}
}
