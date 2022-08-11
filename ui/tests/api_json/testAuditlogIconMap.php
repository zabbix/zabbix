<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup icon_mapping, ids
 */
class testAuditlogIconMap extends CAPITest {

	protected static $resourceid;

	public function testAuditlogIconMap_Create() {
		$created = "{\"iconmap.name\":[\"add\",\"icon_mapping\"],\"iconmap.default_iconid\":[\"add\",\"5\"],".
				"\"iconmap.mappings[8]\":[\"add\"],\"iconmap.mappings[8].inventory_link\":[\"add\",\"1\"],".
				"\"iconmap.mappings[8].expression\":[\"add\",\"created_mapping\"],\"iconmap.mappings[8].iconid".
				"\":[\"add\",\"2\"],\"iconmap.mappings[8].iconmappingid\":[\"add\",\"8\"],\"iconmap.iconmapid\":[\"add\",\"8\"]}";

		$create = $this->call('iconmap.create', [
			[
				'name' => 'icon_mapping',
				'default_iconid' => '5',
				'mappings' => [
					[
						'inventory_link' => 1,
						'expression' => 'created_mapping',
						'iconid' => '2'
					]
				]
			]
		]);

		self::$resourceid = $create['result']['iconmapids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogIconMap_Update() {
		$updated = "{\"iconmap.mappings[8]\":[\"delete\"],\"iconmap.mappings[9]\":[\"add\"],\"iconmap.name\":[\"update".
				"\",\"updated_icon_mapping\",\"icon_mapping\"],\"iconmap.default_iconid\":[\"update\",\"4\",\"5".
				"\"],\"iconmap.mappings[9].inventory_link\":[\"add\",\"2\"],\"iconmap.mappings[9].expression".
				"\":[\"add\",\"updated_created_mapping\"],\"iconmap.mappings[9].iconid\":[\"add\",\"3\"],".
				"\"iconmap.mappings[9].iconmappingid\":[\"add\",\"9\"]}";

		$this->call('iconmap.update', [
			[
				'iconmapid' => self::$resourceid,
				'name' => 'updated_icon_mapping',
				'default_iconid' => '4',
				'mappings' => [
					[
						'inventory_link' => 2,
						'expression' => 'updated_created_mapping',
						'iconid' => '3'
					]
				]
			]
		]);

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogIconMap_Delete() {
		$this->call('iconmap.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'updated_icon_mapping');
	}

	private function sendGetRequest($output, $action, $result) {
		$get = $this->call('auditlog.get', [
			'output' => [$output],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => self::$resourceid,
				'action' => $action
			]
		]);

		$this->assertEquals($result, $get['result'][0][$output]);
	}
}
