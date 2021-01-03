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
 * @backup triggers
 */
class testFormTagsTriggerPrototype extends testFormTags {

	public $update_name = 'Trigger prototype with tags for updating';
	public $clone_name = 'Trigger prototype with tags for cloning';
	public $link = 'trigger_prototypes.php?parent_discoveryid=33800';
	public $saved_link = 'trigger_prototypes.php?form=update&parent_discoveryid=33800&triggerid=';

	/**
	 * Test creating of Trigger prototype with tags.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormTagsTriggerPrototype_Create($data) {
		$expression = '{Simple form test host:item-prototype-form1.last()}=0';
		$this->checkTagsCreate($data, 'trigger prototype', $expression);
	}

	/**
	 * Test update of Trigger prototype with tags.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormTagsTriggerPrototype_Update($data) {
		$this->checkTagsUpdate($data, 'trigger prototype');
	}

	/**
	 * Test cloning of Trigger prototype with tags.
	 */
	public function testFormTagsTriggerPrototype_Clone() {
		$this->executeCloning('trigger prototype', 'Clone');
	}
}
