<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class Maps {

	/**
	 * Create data for map related test.
	 *
	 * @return array
	 */
	public static function load() {
		$hostgroupid = CDataHelper::call('hostgroup.create', [['name' => 'Group with maps']])['groupids'][0];

		// Create host with item and trigger to use it in map further.
		$result = CDataHelper::createHosts([
			[
				'host' => 'Host for map with links',
				'groups' => ['groupid' => $hostgroupid],
				'items' => [
					[
						'name' => 'Trapper',
						'key_' => 'trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for map for form testing',
				'groups' => ['groupid' => 4], // Zabbix servers.
				'items' => [
					[
						'name' => 'Trap',
						'key_' => 'trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			// Hosts for Map Properties test.
			[
				'host' => 'Host_1',
				'groups' => ['groupid' => 4] // Zabbix servers.
			],
			[
				'host' => 'Host_2',
				'groups' => ['groupid' => 4] // Zabbix servers.
			]
		]);

		$triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for map with links',
				'expression' => 'last(/Host for map with links/trap)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Trigger for map for form testing',
				'expression' => 'last(/Host for map for form testing/trap)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			]
		]);
		$map_links_triggerid = $triggers['triggerids'][0];
		$map_form_triggerid = $triggers['triggerids'][1];

		$maps = CDataHelper::call('map.create', [
			[
				'name' => 'Map with links',
				'width' => 800,
				'height' => 600,
				'label_type' => MAP_LABEL_TYPE_LABEL,
				'selements' => [
					// Image (Crypto-router symbol small).
					[
						'selementid' => 1,
						'elementtype' => SYSMAP_ELEMENT_TYPE_IMAGE,
						'iconid_off' => 7,
						'label' => 'Test phone icon',
						'x' => 151,
						'y' => 101
					],
					// Map (Cloud symbol small).
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_MAP,
						'iconid_off' => 3,
						'label' => 'Map element (Local network)',
						'x' => 401,
						'y' => 101,
						'elements' => [['sysmapid' => 1]],
						'urls' => [['name' => 'Zabbix home', 'url' => 'http://www.zabbix.com']]
					],
					// Trigger (Crypto-router symbol big).
					[
						'selementid' => 3,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 15,
						'label' => 'Trigger element (CPU load)',
						'x' => 101,
						'y' => 301,
						'elements' => [['triggerid' => $map_links_triggerid]],
						'urls' => [['name' => 'blog.zabbix.com', 'url' => 'https://blog.zabbix.com/']]
					],
					// Host group (Cloud symbol big).
					[
						'selementid' => 4,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP,
						'iconid_off' => 1,
						'label' => 'Host group element (Linux servers)',
						'x' => 301,
						'y' => 351,
						'elements' => [['groupid' => 4]] // Zabbix servers.
					],
					// Host (Disk array symbol).
					[
						'selementid' => 5,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 19,
						'label' => 'Host element (Zabbix Server)',
						'x' => 501,
						'y' => 301,
						'elements' => [['hostid' => $result['hostids']['Host for map with links']]]
					]
				],
				'shapes' => [
					// Red header text box.
					[
						'type' => SYSMAP_SHAPE_TYPE_RECTANGLE,
						'x' => 408,
						'y' => 0,
						'width' => 233,
						'height' => 50,
						'text' => 'Map name: {MAP.NAME}',
						'font' => 10,
						'font_size' => 14,
						'font_color' => 'BB0000',
						'text_halign' => HALIGN_LEFT,
						'text_valign' => VALIGN_BOTTOM,
						'zindex' => 2
					],
					// Green dashed border rectangle.
					[
						'type' => SYSMAP_SHAPE_TYPE_RECTANGLE,
						'x' => 113,
						'y' => 82,
						'width' => 124,
						'height' => 86,
						'text' => '',
						'border_type' => SYSMAP_SHAPE_BORDER_TYPE_DASHED,
						'border_width' => 5,
						'border_color' => '009900',
						'zindex' => 1
					],
					// Pink ellipse.
					[
						'type' => SYSMAP_SHAPE_TYPE_ELLIPSE,
						'x' => 425,
						'y' => 257,
						'width' => 199,
						'height' => 135,
						'text' => '',
						'border_width' => 2,
						'background_color' => 'FFCCCC',
						'border_type' => SYSMAP_SHAPE_BORDER_TYPE_SOLID
					]
				],
				'links' => [
					[
						'selementid1' => 1,
						'selementid2' => 2,
						'drawtype' => 2,
						'color' => '00CC00',
						'label' => 'CPU load: {?last(/Zabbix Server/system.cpu.load[])}'
					],
					[
						'selementid1' => 1,
						'selementid2' => 3,
						'color' => '00CC00'
					],
					[
						'selementid1' => 4,
						'selementid2' => 3,
						'color' => '00CC00'
					],
					[
						'selementid1' => 5,
						'selementid2' => 4,
						'color' => '00CC00'
					],
					[
						'selementid1' => 2,
						'selementid2' => 5,
						'color' => '00CC00'
					],
					[
						'selementid1' => 2,
						'selementid2' => 3,
						'color' => '00CC00'
					],
					[
						'selementid1' => 1,
						'selementid2' => 4,
						'color' => '00CC00'
					],
					[
						'selementid1' => 5,
						'selementid2' => 1,
						'color' => '00CC00'
					]
				]
			],
			[
				'name' => 'Map for form testing',
				'width' => 500,
				'height' => 500,
				'selements' => [
					// Host 1.
					[
						'selementid' => 6,
						'elements' => [['hostid' => $result['hostids']['Host for map for form testing']]],
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 186,
						'x' => 139,
						'y' => 27
					],
					// Image.
					[
						'selementid' => 7,
						'elementtype' => SYSMAP_ELEMENT_TYPE_IMAGE,
						'iconid_off' => 6,
						'x' => 250,
						'y' => 350
					],
					// Trigger.
					[
						'selementid' => 8,
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
						'iconid_off' => 146,
						'elements' => [['triggerid' => $map_form_triggerid]],
						'x' => 350,
						'y' => 200
					]
				],
				'links' => [
					// Link between Host and Image.
					[
						'selementid1' => 6,
						'selementid2' => 7,
						'label' => 'Link label'
					]
				]
			],
			[
				'name' => 'Test map for Properties',
				'width' => 500,
				'height' => 500,
				'selements' => [
					// Host 1.
					[
						'selementid' => 9,
						'elements' => [['hostid' => $result['hostids']['Host_1']]],
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 186,
						'x' => 139,
						'y' => 27
					],
					// Host 2.
					[
						'selementid' => 10,
						'elements' => [['hostid' => $result['hostids']['Host_2']]],
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 151,
						'x' => 89,
						'y' => 377
					],
					// Image.
					[
						'selementid' => 11,
						'elementtype' => SYSMAP_ELEMENT_TYPE_IMAGE,
						'iconid_off' => 6,
						'x' => 250,
						'y' => 350
					]
				],
				'links' => [
					// Link between Host_1 and Host_2.
					[
						'selementid1' => 9,
						'selementid2' => 10,
						'label' => 'Link label'
					]
				]
			]
		]);

		$mapids = [
			'links_mapid' => $maps['sysmapids'][0],
			'form_test_mapid' => $maps['sysmapids'][1],
			'properties_mapid' => $maps['sysmapids'][2]
		];

		return $mapids;
	}
}
