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
class testFormHostMonitoring extends testFormHost {

	public $monitoring = true;
	public $link = 'zabbix.php?action=host.view';

	public function testFormHostMonitoring_Layout() {
		$this->checkHostLayout($this->link);
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostMonitoring_Create($data) {
		$this->checkHostCreate($data, $this->link);
	}

	/**
	 * @dataProvider getValidationUpdateData
	 */
	public function testFormHostMonitoring_ValidationUpdate($data) {
		$this->checkHostUpdate($data, $this->link);
	}

	/**
	 * @backup hosts
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostMonitoring_Update($data) {
		$this->checkHostUpdate($data, $this->link);
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function testFormHostMonitoring_SimpleUpdate() {
		$this->checkHostSimpleUpdate($this->link, self::MONITORING);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostMonitoring_Clone($data) {
		$this->cloneHost($data, $this->link, 'Clone', self::MONITORING);

		// Check that items aren't cloned from original host.
		$this->assertItemsDBCount($data['host_fields']['Host name'], 0);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostMonitoring_FullClone($data) {
		$this->cloneHost($data, $this->link, 'Full clone', self::MONITORING);

		// Check that items cloned from original host.
		$this->assertItemsDBCount($data['host_fields']['Host name'], 3);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostMonitoring_Cancel($data) {
		$this->checkCancel($data, $this->link, null, self::MONITORING);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostMonitoring_Delete($data) {
		$this->checkDelete($data, $this->link, self::MONITORING);
	}
}

