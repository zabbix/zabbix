<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../common/testFormHost.php';

/**
 * @dataSource DiscoveredHosts, Proxies, HostAvailabilityWidget
 *
 * @backup hosts
 *
 * @onBefore prepareUpdateData, prepareDatatableLayout
 */
class testFormHostFromConfiguration extends testFormHost {

	public $link = 'zabbix.php?action=host.list';

	public function prepareDatatableLayout() {
		// Change the width of datatable columns so that the whole host name would be visible.
		$layout = '{"columns":[{"id":"name","resized":true,"width":"33%"},{"id":"items","resized":true,"width":"5.5%"},'.
				'{"id":"triggers","resized":true,"width":"6.9%"},{"id":"graphs","resized":true,"width":"6.3%"},'.
				'{"id":"discovery","resized":true,"width":"7.6%"},{"id":"web","resized":true,"width":"5%"},'.
				'{"id":"interface","resized":true,"width":"9.6%"},{"id":"proxy","resized":true,"width":"5.6%"},'.
				'{"id":"templates","resized":true,"width":"7.8%"},{"id":"status","resized":true,"width":"6.1%"},'.
				'{"id":"availability","resized":true,"width":"12.3%"},{"id":"encryption","resized":true,"width":"11%"},'.
				'{"id":"info","resized":true,"width":"4.7%"},{"id":"tags","resized":true,"width":"7.7%"},{"id":"tagvalue"}],'.
				'"options":{}}';

		$this->updateDatatableLayout($layout, 'web.hosts.datatable');
	}

	public function testFormHostFromConfiguration_Layout() {
		$this->checkHostLayout();
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormHostFromConfiguration_Create($data) {
		$this->checkHostCreate($data);
	}

	/**
	 * @dataProvider getValidationUpdateData
	 */
	public function testFormHostFromConfiguration_ValidationUpdate($data) {
		$this->checkHostUpdate($data);
	}

	/**
	 * @backup hosts
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostFromConfiguration_Update($data) {
		$this->checkHostUpdate($data);
	}

	/**
	 * Update the host without any changes and check host and interfaces hashes.
	 */
	public function testFormHostFromConfiguration_SimpleUpdate() {
		$this->checkHostSimpleUpdate();
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormHostFromConfiguration_Clone($data) {
		$this->cloneHost($data);

		// Check that items cloned from original host.
		$this->assertItemsDBCount($data['fields']['Host name'], $data['items']);
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormHostFromConfiguration_Cancel($data) {
		$this->checkCancel($data);
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormHostFromConfiguration_Delete($data) {
		$this->checkDelete($data);
	}

	public function testFormHostFromConfiguration_DiscoveredHostLayout() {
		$this->checkDiscoveredHostLayout();
	}
}
