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

	const MONITORING = true;
	const STANDALONE = false;

	public $link = 'zabbix.php?action=host.view';
	public $create_link = null;

	public function testFormHostMonitoring_Layout() {
		$this->checkHostLayout($this->link, self::STANDALONE, self::MONITORING);
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostMonitoring_Create($data) {
		$this->checkHostCreate($data, $this->link, self::STANDALONE, self::MONITORING);
	}

	/**
	 * @dataProvider getValidationUpdateData
	 */
	public function testFormHostMonitoring_ValidationUpdate($data) {
		$this->checkHostUpdate($data, $this->link, self::STANDALONE, self::MONITORING);
	}

	/**
	 * @backup hosts
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostMonitoring_Update($data) {
		$this->checkHostUpdate($data, $this->link, self::STANDALONE, self::MONITORING);
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function testFormHostMonitoring_SimpleUpdate() {
		$this->checkHostSimpleUpdate($this->link, self::STANDALONE, self::MONITORING);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostMonitoring_Clone($data) {
		$full_clone = false;
		$this->cloneHost($data, $this->link, $full_clone, self::STANDALONE, self::MONITORING);

		// Check that items aren't cloned from original host.
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host_fields']['Host name']));
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM items WHERE hostid='.$hostid));
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostMonitoring_FullClone($data) {
		$full_clone = true;
		$this->cloneHost($data, $this->link, $full_clone, self::STANDALONE, self::MONITORING);

		// Check that items cloned from original host.
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($data['host_fields']['Host name']));
		$this->assertEquals(3, CDBHelper::getCount('SELECT null FROM items WHERE hostid='.$hostid));
	}

	/**
	 * @dataProvider getСancelData
	 */
	public function testFormHostMonitoring_Cancel($data) {
		$this->checkCancel($data, $this->link, $this->create_link, self::STANDALONE, self::MONITORING);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostMonitoring_Delete($data) {
		$this->checkDelete($data, $this->link, self::STANDALONE, self::MONITORING);
	}
}

