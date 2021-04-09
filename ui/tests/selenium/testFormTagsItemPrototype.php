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
class testFormTagsItemPrototype extends testFormTags {

	public $update_name = 'Item prototype with tags for updating';
	public $clone_name = 'Item prototype with tags for cloning';
	public $link = 'disc_prototypes.php?parent_discoveryid=133800&context=host';
	public $saved_link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid=133800&itemid=';
	public $new_name = 'Cloned Item prototype {#KEY}';

	/**
	 * Test creating of Item prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsItemPrototype_Create($data) {
		$this->checkTagsCreate($data, 'item prototype');
	}

	/**
	 * Test update of Item prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsItemPrototype_Update($data) {
		$this->checkTagsUpdate($data, 'item prototype');
	}

	/**
	 * Test cloning of Item prototype with tags.
	 */
	public function testFormTagsItemPrototype_Clone() {
		$this->executeCloning('item prototype', 'Clone');
	}
}
