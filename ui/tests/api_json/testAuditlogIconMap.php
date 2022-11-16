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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup icon_mapping
 */
class testAuditlogIconMap extends testAuditlogCommon {

	/**
	 * Existing Icon map ID.
	 */
	private const ICONMAPID = 1;

	public function testAuditlogIconMap_Create() {
		$create = $this->call('iconmap.create', [
			[
				'name' => 'icon_mapping',
				'default_iconid' => 5,
				'mappings' => [
					[
						'inventory_link' => 1,
						'expression' => 'created_mapping',
						'iconid' => 2
					]
				]
			]
		]);

		$resourceid = $create['result']['iconmapids'][0];
		$icon_map = CDBHelper::getRow('SELECT iconmappingid FROM icon_mapping WHERE iconmapid='.zbx_dbstr($resourceid));

		$created = json_encode([
			'iconmap.name' => ['add', 'icon_mapping'],
			'iconmap.default_iconid' => ['add', '5'],
			'iconmap.mappings['.$icon_map['iconmappingid'].']' => ['add'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].inventory_link' => ['add', '1'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].expression' => ['add', 'created_mapping'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].iconid' => ['add', '2'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].iconmappingid' => ['add', $icon_map['iconmappingid']],
			'iconmap.iconmapid' => ['add', $resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, $resourceid);
	}

	public function testAuditlogIconMap_Update() {
		$this->call('iconmap.update', [
			[
				'iconmapid' => self::ICONMAPID,
				'name' => 'updated_icon_mapping',
				'default_iconid' => 4,
				'mappings' => [
					[
						'inventory_link' => 2,
						'expression' => 'updated_created_mapping',
						'iconid' => 3
					]
				]
			]
		]);

		$icon_map = CDBHelper::getRow('SELECT iconmappingid FROM icon_mapping WHERE iconmapid='.zbx_dbstr(self::ICONMAPID));

		$updated = json_encode([
			'iconmap.mappings[1]' => ['delete'],
			'iconmap.mappings['.$icon_map['iconmappingid'].']' => ['add'],
			'iconmap.name' => ['update', 'updated_icon_mapping', 'API icon map'],
			'iconmap.default_iconid' => ['update', '4', '2'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].inventory_link' => ['add', '2'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].expression' => ['add', 'updated_created_mapping'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].iconid' => ['add', '3'],
			'iconmap.mappings['.$icon_map['iconmappingid'].'].iconmappingid' => ['add', $icon_map['iconmappingid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::ICONMAPID);
	}

	public function testAuditlogIconMap_Delete() {
		$this->call('iconmap.delete', [self::ICONMAPID]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'updated_icon_mapping', self::ICONMAPID);
	}
}
