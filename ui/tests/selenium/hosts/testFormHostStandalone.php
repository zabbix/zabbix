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

require_once dirname(__FILE__).'/../common/testFormHost.php';

/**
 * @backup hosts
 *
 * @onBefore prepareUpdateData
 */
class testFormHostStandalone extends testFormHost {

	const STANDALONE = true;

	public $link = 'zabbix.php?action=host.edit&hostid=';
	public $create_link = 'zabbix.php?action=host.edit';

	public function testFormHostStandalone_Layout() {
		$this->checkHostLayout($this->link, self::STANDALONE);
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostStandalone_Create($data) {
		$this->checkHostCreate($data, $this->create_link, self::STANDALONE);
	}

	/**
	 * @dataProvider getValidationUpdateData
	 */
	public function testFormHostStandalone_ValidationUpdate($data) {
		$this->checkHostUpdate($data, $this->link, self::STANDALONE);
	}

	/**
	 * @backup hosts
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostStandalone_Update($data) {
		$this->checkHostUpdate($data, $this->link, self::STANDALONE);
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function testFormHostStandalone_SimpleUpdate() {
		$this->checkHostSimpleUpdate($this->link, self::STANDALONE);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostStandalone_Clone($data) {
		$this->cloneHost($data, $this->link, 'Clone', self::STANDALONE);

		// Check that items aren't cloned from original host.
		$this->assertItemsDBCount($data['host_fields']['Host name'], 0);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostStandalone_FullClone($data) {
		$this->cloneHost($data, $this->link, 'Full clone', self::STANDALONE);

		// Check that items cloned from original host.
		$this->assertItemsDBCount($data['host_fields']['Host name'], 3);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostStandalone_Cancel($data) {
		$this->checkCancel($data, $this->link, $this->create_link, self::STANDALONE);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostStandalone_Delete($data) {
		$this->checkDelete($data, $this->link, self::STANDALONE);
	}
}
