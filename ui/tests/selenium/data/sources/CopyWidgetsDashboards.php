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

class CopyWidgetsDashboards {

	/**
	 * Create data for Copy widgets test.
	 *
	 * !!! Please, add new widgets to "Dashboard for Copying widgets _2" if necessary.
	 * If "Dashboard for Copying widgets _2" is run out of space, create new dashboard with this exact name:
	 * "Dashboard for Copying widgets _3", etc.
	 * Second page on new dashboard for paste widgets should be named "Test_page".
	 *
	 * @return array
	 */
	public static function load() {
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host with widgets items',
				'groups' => ['groupid' => 4], // Zabbix servers.
				'items' => [
					[
						'name' => 'Widget item',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with map for copy widgets',
				'groups' => ['groupid' => CDataHelper::call('hostgroup.create',
						[['name' => 'Group with maps for copy']])['groupids'][0]
				]
			]
		]);
		$itemid = $hosts['itemids']['Host with widgets items:key[1]'];
		$hostid = $hosts['hostids']['Host with map for copy widgets'];

		$maps = CDataHelper::call('map.create', [
			[
				'name' => 'Map for widget copies',
				'width' => 500,
				'height' => 500,
				'selements' => [
					[
						'elements' => [['hostid' => $hostid]],
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 186
					]
				]
			]
		]);
		$mapid = $maps['sysmapids'][0];

		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template for copy widgets',
				'groups' => ['groupid' => 1], // Templates.
				'items' => [
					[
						'name' => 'Templates widget item',
						'key_' => 'templ_key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'LLD rule for graph prototype widget',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		$templateid = $templates['templateids']['Template for copy widgets'];
		$template_itemid = $templates['itemids']['Template for copy widgets:templ_key[1]'];
		$discoveryruleid =  $templates['discoveryruleids']['Template for copy widgets:drule'];

		$item_protototypes = CDataHelper::call('itemprototype.create', [
			[
				'hostid' => $templateid,
				'ruleid' => $discoveryruleid,
				'name' => 'Template item prototype {#KEY}',
				'key_' => 'trap[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$item_prototypeid = $item_protototypes['itemids'][0];

		$graph_protototypes = CDataHelper::call('graphprototype.create', [
			[
				'name' => 'Template graph prototype {#KEY}',
				'width' => 600,
				'height' => 300,
				'gitems' => [['itemid' => $item_prototypeid, 'color' => '3333FF']]
			]
		]);
		$graph_prototypeid = $graph_protototypes['graphids'][0];

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Copying widgets _1',
				'display_period' => 30,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'Page 1',
						'widgets' => [
							[
								'name' => 'Test copy Action log',
								'type' => 'actionlog',
								'x' => 0,
								'y' => 0,
								'width' => 17,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '10'
									],
									[
										'type' => 0,
										'name' => 'show_lines',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sort_triggers',
										'value' => 7
									]
								]
							],
							[
								'name' => 'Test copy Clock',
								'type' => 'clock',
								'x' => 0,
								'y' => 8,
								'width' => 17,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '60'
									],
									[
										'type' => 0,
										'name' => 'time_type',
										'value' => 2
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => 42229
									]
								]
							],
							[
								'name' => 'Test copy Top items',
								'type' => 'topitems',
								'x' => 54,
								'y' => 4,
								'width' => 18,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'problems',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'layout',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'application',
										'value' => '3'
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50011
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 50012
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.items.0',
										'value' => 'Test_item'
									]
								]
							],
							[
								'name' => 'Test copy classic Graph',
								'type' => 'graph',
								'x' => 17,
								'y' => 0,
								'width' => 24,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '30'
									],
									[
										'type' => 0,
										'name' => 'dynamic',
										'value' => 10
									],
									[
										'type' => 0,
										'name' => 'show_legend',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => 99088
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'ABCDE'
									]
								]
							],
							[
								'name' => 'Test copy Favorite graphs',
								'type' => 'favgraphs',
								'x' => 24,
								'y' => 7,
								'width' => 17,
								'height' => 1,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '30'
									]
								]
							],
							[
								'name' => 'Test copy Favorite maps',
								'type' => 'favmaps',
								'x' => 24,
								'y' => 6,
								'width' => 17,
								'height' => 1,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '600'
									]
								]
							],
							[
								'name' => 'Test copy Discovery status',
								'type' => 'discovery',
								'x' => 41,
								'y' => 4,
								'width' => 13,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '900'
									]
								]
							],
							[
								'name' => 'Test copy Graph prototype',
								'type' => 'graphprototype',
								'x' => 41,
								'y' => 0,
								'width' => 17,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '0',
										'name' => 'columns',
										'value' => 3
									],
									[
										'type' => '0',
										'name' => 'rows',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									],
									[
										'type' => '0',
										'name' => 'rf_rate',
										'value' => '30'
									],
									[
										'type' => '0',
										'name' => 'show_legend',
										'value' => 0
									],
									[
										'type' => '7',
										'name' => 'graphid',
										'value' => 600000
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'BCDEF'
									]
								]
							],
							[
								'name' => 'Test copy Host availability',
								'type' => 'hostavail',
								'x' => 0,
								'y' => 4,
								'width' => 24,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'interface_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'interface_type',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'interface_type',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'layout',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'maintenance',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '60'
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50013
									]
								]
							],
							[
								'name' => 'Test copy Map',
								'type' => 'map',
								'x' => 58,
								'y' => 0,
								'width' => 14,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 8,
										'name' => 'sysmapid',
										'value' => $mapid
									]
								]
							],
							[
								'name' => 'Test copy Map from tree',
								'type' => 'map',
								'x' => 17,
								'y' => 8,
								'width' => 9,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '120'
									],
									[
										'type' => 1,
										'name' => 'sysmapid._reference',
										'value' => 'STZDI._mapid'
									]
								]
							],
							[
								'name' => 'Test copy Map navigation tree',
								'type' => 'navtree',
								'x' => 24,
								'y' => 4,
								'width' => 17,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'navtree.2.order',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '60'
									],
									[
										'type' => 0,
										'name' => 'show_unavailable',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'navtree.1.name',
										'value' => 'Map with icon mapping'
									],
									[
										'type' => 1,
										'name' => 'navtree.2.name',
										'value' => 'Public map with image'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'STZDI'
									],
									[
										'type' => 8,
										'name' => 'navtree.1.sysmapid',
										'value' => 6
									],
									[
										'type' => 8,
										'name' => 'navtree.2.sysmapid',
										'value' => 10
									]
								]
							],
							[
								'name' => 'Test copy Problems',
								'type' => 'problems',
								'x' => 56,
								'y' => 8,
								'width' => 16,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '900'
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'show_lines',
										'value' => 12
									],
									[
										'type' => 0,
										'name' => 'show_opdata',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'show_tags',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sort_triggers',
										'value' => 15
									],
									[
										'type' => 0,
										'name' => 'show_timeline',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'tag_name_format',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tags.1.operator',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'acknowledgement_status',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test2'
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => '2'
									],
									[
										'type' => 1,
										'name' => 'tags.1.value',
										'value' => '33'
									],
									[
										'type' => 1,
										'name' => 'tag_priority',
										'value' => '1,2'
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'tag2'
									],
									[
										'type' => 1,
										'name' => 'tags.1.tag',
										'value' => 'tagg33'
									],
									[
										'type' => 2,
										'name' => 'exclude_groupids',
										'value' => 50014
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50005
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99026
									]
								]
							],
							[
								'name' => 'Test copy Problems by severity',
								'type' => 'problemsbysv',
								'x' => 26,
								'y' => 8,
								'width' => 30,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'ext_ack',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'layout',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '30'
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'show_opdata',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show_timeline',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'show_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test problem'
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'tag5'
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => '5'
									],
									[
										'type' => 2,
										'name' => 'exclude_groupids',
										'value' => 50008
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50011
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99012
									]
								]
							]
						]
					],
					[
						'name' => 'Test_page'
					]
				]
			],
			[
				'name' => 'Dashboard for Copying widgets _2',
				'display_period' => 30,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'Page 1',
						'widgets' => [
							[
								'name' => 'Test copy System information',
								'type' => 'systeminfo',
								'x' => 13,
								'y' => 0,
								'width' => 14,
								'height' => 6,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '30'
									]
								]
							],
							[
								'name' => 'Test copy Trigger overview',
								'type' => 'trigover',
								'x' => 0,
								'y' => 0,
								'width' => 13,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '120'
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'layout ',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'application',
										'value' => 'Inventory'
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50011
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99012
									]
								]
							],
							[
								'name' => 'Test copy Problems 2',
								'type' => 'problems',
								'x' => 0,
								'y' => 6,
								'width' => 16,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '60'
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show_lines',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'show_opdata',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'show_tags',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'show_timeline',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sort_triggers',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'tag_name_format',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'tags.1.operator',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'acknowledgement_status',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test4'
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => '3'
									],
									[
										'type' => 1,
										'name' => 'tags.1.value',
										'value' => '44'
									],
									[
										'type' => 1,
										'name' => 'tag_priority',
										'value' => 'test5, test6'
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'tag3'
									],
									[
										'type' => 1,
										'name' => 'tags.1.tag',
										'value' => 'tag44'
									],
									[
										'type' => 2,
										'name' => 'exclude_groupids',
										'value' => 50014
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50006
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99015
									]
								]
							],
							[
								'name' => 'Test copy URL',
								'type' => 'url',
								'x' => 0,
								'y' => 3,
								'width' => 13,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '120'
									],
									[
										'type' => 1,
										'name' => 'url',
										'value' => 'https://www.zabbix.com/integrations'
									]
								]
							],
							[
								'name' => 'Test copy item history',
								'type' => 'itemhistory',
								'x' => 38,
								'y' => 0,
								'width' => 18,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => '0'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'style',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Item 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => $itemid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.history',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => 12
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sortorder',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_timestamp',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_column_header',
										'value' => 1
									]
								]
							],
							[
								'name' => 'Test copy Problem hosts',
								'type' => 'problemhosts',
								'x' => 27,
								'y' => 3,
								'width' => 17,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'ext_ack',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'hide_empty_groups',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '30'
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'Test'
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'Tag1'
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => '1'
									],
									[
										'type' => 2,
										'name' => 'exclude_groupids',
										'value' => 50014
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50011
									],
									[
										'type' => 4,
										'name' => 'itemids',
										'value' => 42230
									]
								]
							],
							[
								'name' => 'Test copy Web monitoring',
								'type' => 'web',
								'x' => 27,
								'y' => 0,
								'width' => 11,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'maintenance',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '120'
									],
									[
										'type' => 2,
										'name' => 'exclude_groupids',
										'value' => 50008
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50016
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99133
									]
								]
							],
							[
								'name' => 'Test copy Problems 3',
								'type' => 'problems',
								'x' => 56,
								'y' => 0,
								'width' => 16,
								'height' => 6,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '60'
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'severities',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'show',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show_lines',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'show_opdata',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'show_tags',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sort_triggers',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tag_name_format',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'acknowledgement_status',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test5'
									],
									[
										'type' => 1,
										'name' => 'tag_priority',
										'value' => 'test7, test8'
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'tag9'
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => '9'
									],
									[
										'type' => 2,
										'name' => 'exclude_groupids',
										'value' => 50014
									],
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 50006
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99015
									]
								]
							],
							[
								'name' => 'Test copy Graph prototype 2',
								'type' => 'graphprototype',
								'x' => 16,
								'y' => 6,
								'width' => 14,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'show_legend',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns',
										'value' => 20
									],
									[
										'type' => 0,
										'name' => 'rows',
										'value' => 5
									],
									[
										'type' => 1,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '600'
									],
									[
										'type' => 7,
										'name' => 'graphid',
										'value' => 600000
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'CDEFG'
									]
								]
							],
							[
								'name' => 'Test copy item value',
								'type' => 'item',
								'x' => 16,
								'y' => 10,
								'width' => 14,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => 42230
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns',
										'value' => 20
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 60
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'adv_conf',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_h_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_v_pos',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_h_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_v_pos',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_size',
										'value' => 16
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_size',
										'value' => 34
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units',
										'value' => 'some'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'bg_color',
										'value' => 'E1E1E1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'updown_color',
										'value' => 'FFB300'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'up_color',
										'value' => 'CE93D8'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'down_color',
										'value' => '29B6F6'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_v_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show.0',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show.1',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show.2',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show.3',
										'value' => 4
									],
									// Sparkline fields.
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show.4',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sparkline.width',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sparkline.fill',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.color',
										'value' => 'FF0000'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.to',
										'value' => 'now-12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sparkline.history',
										'value' => 2
									]
								]
							],
							[
								'name' => 'Geomap widget for copying',
								'type' => 'geomap',
								'x' => 0,
								'y' => 9,
								'width' => 16,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 2,
										'name' => 'groupids',
										'value' => 4
									],
									[
										'type' => 3,
										'name' => 'hostids',
										'value' => 99136
									],
									[
										'type' => 0,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'tag'
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => 'value'
									],
									[
										'type' => 1,
										'name' => 'tags.1.tag',
										'value' => 'tag2'
									],
									[
										'type' => 0,
										'name' => 'tags.1.operator',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'tags.1.value',
										'value' => 'value2'
									]
								]
							],
							[
								'name' => 'Test copy Top hosts',
								'type' => 'tophosts',
								'x' => 44,
								'y' => 3,
								'width' => 12,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids',
										'value' => 50011
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids',
										'value' => 50012
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.tag',
										'value' => 'tag_name'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.value',
										'value' => 'tag_value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Item name'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.data',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item',
										'value' => '3_item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.time_period.from',
										'value' => 'now-1h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.time_period.to',
										'value' => 'now'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.min',
										'value' => '10'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.max',
										'value' =>  '50'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.display',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.history',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.base_color',
										'value' => 'FF0000'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columnsthresholds.0.color.0',
										'value' => 'FF465C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columnsthresholds.0.threshold.0',
										'value' => '100'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Host name'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.data',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.aggregate_function',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.base_color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.2.name',
										'value' => 'Text name'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.2.data',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.3.item',
										'value' => '3_item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.2.aggregate_function',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.2.base_color',
										'value' => '00BFFF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.2.text',
										'value' => 'text_here'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'column',
										'value' => 0
									],
									// Sparkline fields.
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.3.name',
										'value' => 'Text name'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.3.data',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.3.display',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.3.sparkline.width',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.3.sparkline.fill',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.3.sparkline.color',
										'value' => 'FF0000'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.3.sparkline.time_period.from',
										'value' => 'now-2h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.3.sparkline.time_period.to',
										'value' => 'now-30m'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => ' columns.3.sparkline.history',
										'value' => 1
									]
								]
							],
							[
								'type' => 'gauge',
								'name' => 'Gauge for copying',
								'x' => 30,
								'y' => 10,
								'width' => 20,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => '99142'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'min',
										'value' => '10'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'max',
										'value' => '350'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'angle',
										'value' => '270'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'description',
										'value' => 'Test description'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_size',
										'value' => 16
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_v_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'desc_color',
										'value' => 'FDD835'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_places',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size',
										'value' => 31
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'value_color',
										'value' => 'FF6F00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_arc_size',
										'value' => 22
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'value_arc_color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'empty_color',
										'value' => '00FF00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'bg_color',
										'value' => 'FFECB3'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units',
										'value' => 'bytes'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_size',
										'value' => 26
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units_color',
										'value' => '42A5F5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'needle_show',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'needle_color',
										'value' => '9FA8DA'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'scale_decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'scale_size',
										'value' => 12
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.0.color',
										'value' => '26A69A'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.0.threshold',
										'value' => '123'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'th_show_labels',
										'value' => '1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'th_show_arc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'th_arc_size',
										'value' => 56
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'toptriggers',
								'name' => 'Top triggers for copying',
								'x' => 30,
								'y' => 6,
								'width' => 20,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 10
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids',
										'value' => 10084
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'problem',
										'value' => 'test top triggers'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'severities',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => 25
									],
									[
										'type' =>ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.tag',
										'value' => 'top trigger tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.value',
										'value' => 'top trigger tag value1'
									],
									[
										'type' =>ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.1.tag',
										'value' => 'top trigger tag2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.1.operator',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.1.value',
										'value' => 'top trigger tag value2'
									]
								]
							],
							[
								'type' => 'piechart',
								'name' => 'Pie chart for copying',
								'x' => 50,
								'y' => 10,
								'width' => 13,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'test'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'test'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => 'FF465C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.data_set_label',
										'value' => 'DATA SET LABEL 🍪'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'draw_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'width',
										'value' => 30
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'space',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'merge',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'merge_percent',
										'value' => 10
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'merge_color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'total_show',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size',
										'value' => 25
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_places',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_show',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units',
										'value' => '🍪'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'value_color',
										'value' => '78909C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => 'now-2h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => 'now-1h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend_aggregation',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend_lines',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend_columns',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'ds.1.itemids.0',
										'value' => '99142'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type.0',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color.0',
										'value' => '0EC9AC'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.dataset_type',
										'value' => 0
									]
								]
							],
							[
								'type' => 'hostnavigator',
								'name' => 'Host navigator for copying',
								'x' => 63,
								'y' => 10,
								'width' => 9,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 60
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => 4 // Zabbix servers.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hosts.0',
										'value' => 'test'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'status',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'host_tags_evaltype',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.0.tag',
										'value' => 'host tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'host_tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.0.value',
										'value' => 'host tag value1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.1.tag',
										'value' => 'host tag2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'host_tags.1.operator',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.1.value',
										'value' => 'host tag value2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'severities.0',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'maintenance',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_problems',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'group_by.0.attribute',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'group_by.0.tag_name',
										'value' => 'host tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => 50
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'AZIBQ'
									]
								]
							],
							[
								'type' => 'itemnavigator',
								'name' => 'Item navigator for copying',
								'x' => 50,
								'y' => 6,
								'width' => 22,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 60
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => 4 // Zabbix servers.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hosts.0',
										'value' => 10084 // ЗАББИКС Сервер.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'host_tags_evaltype',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.0.tag',
										'value' => 'host tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'host_tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.0.value',
										'value' => 'host tag value1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.1.tag',
										'value' => 'host tag2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'host_tags.1.operator',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'host_tags.1.value',
										'value' => 'host tag value2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'trap item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'item_tags_evaltype',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'item_tags.0.tag',
										'value' => 'item tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'item_tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'item_tags.0.value',
										'value' => 'item tag1 value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'state',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_problems',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'group_by.0.attribute',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'group_by.0.tag_name',
										'value' => 'item tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => 77
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'AZIBO'
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb for copying',
								'x' => 9,
								'y' => 17,
								'width' => 8,
								'height' => 5,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 2,
										'name' => 'groupids.0',
										'value' => 4
									],
									[
										'type' => 3,
										'name' => 'hostids.0',
										'value' => 10084
									],
									[
										'type' => 1,
										'name' => 'host_tags.0.tag',
										'value' => 'tag1'
									],
									[
										'type' => 0,
										'name' => 'host_tags.0.operator',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'host_tags.0.value',
										'value' => 'val1'
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Linux: Available memory'
									],
									[
										'type' => 1,
										'name' => 'item_tags.0.tag',
										'value' => 'tag2'
									],
									[
										'type' => 0,
										'name' => 'item_tags.0.operator',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'item_tags.0.value',
										'value' => 'val2'
									],
									[
										'type' => 0,
										'name' => 'maintenance',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'primary_label',
										'value' => 'TEXT'
									],
									[
										'type' => 0,
										'name' => 'primary_label_size_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'primary_label_size',
										'value' => 22
									],
									[
										'type' => 0,
										'name' => 'primary_label_bold',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'primary_label_color',
										'value' => 'E1BEE7'
									],
									[
										'type' => 0,
										'name' => 'secondary_label_decimal_places',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'secondary_label_color',
										'value' => '00BCD4'
									],
									[
										'type' => 1,
										'name' => 'secondary_label_units',
										'value' => 'after'
									],
									[
										'type' => 1,
										'name' => 'bg_color',
										'value' => '9575CD'
									],
									[
										'type' => 1,
										'name' => 'thresholds.0.color',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'thresholds.0.threshold',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'thresholds.1.color',
										'value' => 'FFD54F'
									],
									[
										'type' => 1,
										'name' => 'thresholds.1.threshold',
										'value' => '200'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'ENJSC'
									]
								]
							]
						]
					],
					[
						'name' => 'Test_page'
					]
				]
			],
			[
				'name' => 'Dashboard for Paste widgets',
				'display_period' => 30,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => '',
						'widgets' => [
							[
								'name' => 'Test copy Map navigation tree',
								'type' => 'navtree',
								'x' => 0,
								'y' => 0,
								'width' => 10,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'FYKXG'
									]
								]
							],
							[
								'name' => 'Test widget for replace',
								'type' => 'clock',
								'x' => 10,
								'y' => 0,
								'width' => 21,
								'height' => 3,
								'view_mode' => 0
							]
						]
					]
				]
			]
		]);

		CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => $templateid,
				'name' => 'Templated dashboard with all widgets',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Clock widget',
								'width' => 19,
								'height' => 4
							],
							[
								'type' => 'discovery',
								'name' => 'Discovery status widget',
								'x' => 5,
								'y' => 10,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) widget',
								'x' => 19,
								'y' => 0,
								'width' => 31,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => $template_itemid
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'EFGHI'
									]
								]
							],
							[
								'type' => 'itemhistory',
								'name' => 'Item history widget',
								'x' => 50,
								'y' => 0,
								'width' => 13,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Item 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => $template_itemid
									]
								]
							],
							[
								'type' => 'url',
								'name' => 'URL widget',
								'x' => 63,
								'y' => 0,
								'width' => 9,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'url',
										'value' => 'http://zabbix.com'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'name' => 'Graph prototype widget',
								'x' => 46,
								'y' => 6,
								'width' => 26,
								'height' => 2,
								'fields' => [
									[
										'type' => 7,
										'name' => 'graphid',
										'value' => $graph_prototypeid
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'FGHIJ'
									]
								]
							],
							[
								'type' => 'item',
								'name' => 'Item value widget',
								'x' => 19,
								'y' => 4,
								'width' => 14,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'itemid.0',
										'value' => $template_itemid
									]
								]
							],
							[
								'type' => 'gauge',
								'name' => 'Gauge widget',
								'x' => 33,
								'y' => 4,
								'width' => 13,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => $template_itemid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'min',
										'value' => '5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'max',
										'value' => '123'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'angle',
										'value' => '270'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'description',
										'value' => 'Test templated description'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_size',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_v_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'desc_color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size',
										'value' => 13
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'value_color',
										'value' => '26C6DA'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_arc_size',
										'value' => 19
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'value_arc_color',
										'value' => '66BB6A'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'empty_color',
										'value' => 'FFFF00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'bg_color',
										'value' => '004D40'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units',
										'value' => 'KB'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_size',
										'value' => 15
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units_color',
										'value' => '8D6E63'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'needle_show',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'needle_color',
										'value' => 'E64A19'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'scale_decimal_places',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'scale_size',
										'value' => 9
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.0.color',
										'value' => '4527A0'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.0.threshold',
										'value' => '15'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'th_show_labels',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'th_show_arc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'th_arc_size',
										'value' => 52
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'toptriggers',
								'name' => 'Top triggers widget',
								'x' => 46,
								'y' => 4,
								'width' => 26,
								'height' => 2,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 10
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'problem',
										'value' => 'test top triggers'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'severities',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'evaltype',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => 25
									],
									[
										'type' =>ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.tag',
										'value' => 'top trigger tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.value',
										'value' => 'top trigger tag value1'
									],
									[
										'type' =>ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.1.tag',
										'value' => 'top trigger tag2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.1.operator',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.1.value',
										'value' => 'top trigger tag value2'
									]
								]
							],
							[
								'type' => 'piechart',
								'name' => 'Pie chart widget',
								'x' => 0,
								'y' => 4,
								'width' => 19,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Download speed for scenario "$1".'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => 'FF465C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.data_set_label',
										'value' => 'DATA SET LABEL 🍪'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'draw_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'width',
										'value' => 30
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'space',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'merge',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'merge_percent',
										'value' => 10
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'merge_color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'total_show',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size',
										'value' => 25
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_places',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'units_show',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'units',
										'value' => '🍪'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_bold',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'value_color',
										'value' => '78909C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => 'now-2h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => 'now-1h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend_aggregation',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend_lines',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend_columns',
										'value' => 3
									]
								]
							],
							[
								'type' => 'hostnavigator',
								'name' => 'Host navigator widget',
								'x' => 0,
								'y' => 7,
								'width' => 9,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 60
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'severities.0',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'maintenance',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_problems',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'group_by.0.attribute',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'group_by.0.tag_name',
										'value' => 'host tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'AZIBQ'
									]
								]
							],
							[
								'type' => 'itemnavigator',
								'name' => 'Item navigator widget',
								'x' => 9,
								'y' => 7,
								'width' => 10,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 60
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'trap item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'item_tags_evaltype',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'item_tags.0.tag',
										'value' => 'item tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'item_tags.0.operator',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'item_tags.0.value',
										'value' => 'item tag1 value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'state',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_problems',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'group_by.0.attribute',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'group_by.0.tag_name',
										'value' => 'item tag1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => 77
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'AZIBO'
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb widget',
								'x' => 17,
								'y' => 10,
								'width' => 5,
								'height' => 5,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'item_pattern'
									],
									[
										'type' => 1,
										'name' => 'item_tags.0.tag',
										'value' => 'tag1'
									],
									[
										'type' => 0,
										'name' => 'item_tags.0.operator',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'item_tags.0.value',
										'value' => 'val1'
									],
									[
										'type' => 0,
										'name' => 'maintenance',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'primary_label',
										'value' => 'TEXT'
									],
									[
										'type' => 0,
										'name' => 'primary_label_size_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'primary_label_size',
										'value' => 22
									],
									[
										'type' => 0,
										'name' => 'primary_label_bold',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'primary_label_color',
										'value' => 'E1BEE7'
									],
									[
										'type' => 0,
										'name' => 'secondary_label_decimal_places',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'secondary_label_color',
										'value' => '00BCD4'
									],
									[
										'type' => 1,
										'name' => 'secondary_label_units',
										'value' => 'test_unit'
									],
									[
										'type' => 1,
										'name' => 'bg_color',
										'value' => '9575CD'
									],
									[
										'type' => 1,
										'name' => 'thresholds.0.color',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'thresholds.0.threshold',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'thresholds.1.color',
										'value' => 'FFD54F'
									],
									[
										'type' => 1,
										'name' => 'thresholds.1.threshold',
										'value' => '200'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'FAXKO'
									]
								]
							]
						]
					],
					[
						'name' => 'Page for pasting widgets',
						'widgets' => []
					]
				]
			],
			[
				'templateid' => $templateid,
				'name' => 'Dashboard without widgets',
				'pages' => [[]]
			]
		]);

		return ['dashboardids' => CDataHelper::getIds('name')];
	}
}
