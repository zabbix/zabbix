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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup icon_mapping, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditIconMap extends testPageReportsAuditValues {
	
	/**
	 * Id of icon map.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "iconmap.default_iconid: 5".
			"\niconmap.iconmapid: 1".
			"\niconmap.mappings[1]: Added".
			"\niconmap.mappings[1].expression: created_mapping".
			"\niconmap.mappings[1].iconid: 2".
			"\niconmap.mappings[1].iconmappingid: 1".
			"\niconmap.mappings[1].inventory_link: 1".
			"\niconmap.name: icon_mapping";

	public $updated = "iconmap.default_iconid: 5 => 4".
			"\niconmap.mappings[1]: Deleted".
			"\niconmap.mappings[2]: Added".
			"\niconmap.mappings[2].expression: updated_created_mapping".
			"\niconmap.mappings[2].iconid: 3".
			"\niconmap.mappings[2].iconmappingid: 2".
			"\niconmap.mappings[2].inventory_link: 2".
			"\niconmap.name: icon_mapping => updated_icon_mapping";

	public $deleted = 'Description: updated_icon_mapping';

	public $resource_name = 'Icon mapping';

	public function prepareCreateData() {
		$ids = CDataHelper::call('iconmap.create', [
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
		$this->assertArrayHasKey('iconmapids', $ids);
		self::$ids = $ids['iconmapids'][0];
	}
	
	/**
	 * Check audit of created icon mapping.
	 */
	public function testAuditIconMap_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}
	
	/**
	 * Check audit of updated icon mapping.
	 */
	public function testAuditIconMap_Update() {
		CDataHelper::call('iconmap.update', [
			[
				'iconmapid' => self::$ids,
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

		$this->checkAuditValues(self::$ids, 'Update');
	}
	
	/**
	 * Check audit of deleted icon mapping.
	 */
	public function testAuditMediaType_Delete() {
		CDataHelper::call('iconmap.delete', [self::$ids]);
		$this->checkAuditValues(self::$ids, 'Delete');
	}
}