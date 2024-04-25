<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
								'width' => 7,
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
								'x' => 18,
								'y' => 7,
								'width' => 2,
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
								'name' => 'Test copy Data overview',
								'type' => 'dataover',
								'x' => 18,
								'y' => 10,
								'width' => 6,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '0'
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'style',
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
									]
								]
							],
							[
								'name' => 'Test copy classic Graph',
								'type' => 'graph',
								'x' => 7,
								'y' => 0,
								'width' => 11,
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
									]
								]
							],
							[
								'name' => 'Test copy Favourite graphs',
								'type' => 'favgraphs',
								'x' => 20,
								'y' => 7,
								'width' => 4,
								'height' => 3,
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
								'name' => 'Test copy Favourite maps',
								'type' => 'favmaps',
								'x' => 14,
								'y' => 10,
								'width' => 4,
								'height' => 2,
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
								'x' => 8,
								'y' => 10,
								'width' => 6,
								'height' => 2,
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
								'x' => 0,
								'y' => 4,
								'width' => 13,
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
										'type' => '0',
										'name' => 'dynamic',
										'value' => 1
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
									]
								]
							],
							[
								'name' => 'Test copy Host availability',
								'type' => 'hostavail',
								'x' => 13,
								'y' => 4,
								'width' => 5,
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
								'x' => 18,
								'y' => 3,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'OYKZW'
									],
									[
										'type' => 8,
										'name' => 'sysmapid',
										'value' => 3
									]
								]
							],
							[
								'name' => 'Test copy Map from tree',
								'type' => 'map',
								'x' => 14,
								'y' => 8,
								'width' => 4,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '120'
									],
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'filter_widget_reference',
										'value' => 'STZDI'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'PVEYR'
									]
								]
							],
							[
								'name' => 'Test copy Map navigation tree',
								'type' => 'navtree',
								'x' => 8,
								'y' => 8,
								'width' => 6,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'navtree.order.2',
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
										'name' => 'navtree.name.1',
										'value' => 'Map with icon mapping'
									],
									[
										'type' => 1,
										'name' => 'navtree.name.2',
										'value' => 'Public map with image'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'STZDI'
									],
									[
										'type' => 8,
										'name' => 'navtree.sysmapid.1',
										'value' => 6
									],
									[
										'type' => 8,
										'name' => 'navtree.sysmapid.2',
										'value' => 10
									]
								]
							],
							[
								'name' => 'Test copy Problems',
								'type' => 'problems',
								'x' => 0,
								'y' => 12,
								'width' => 8,
								'height' => 6,
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
										'name' => 'tags.operator.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tags.operator.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'unacknowledged',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test2'
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
										'value' => '2'
									],
									[
										'type' => 1,
										'name' => 'tags.value.1',
										'value' => '33'
									],
									[
										'type' => 1,
										'name' => 'tag_priority',
										'value' => '1,2'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'tag2'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.1',
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
								'x' => 8,
								'y' => 14,
								'width' => 16,
								'height' => 4,
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
										'name' => 'tags.operator.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test problem'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'tag5'
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
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
								'x' => 16,
								'y' => 0,
								'width' => 8,
								'height' => 3,
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
								'width' => 16,
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
										'name' => 'style',
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
								'y' => 5,
								'width' => 9,
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
										'name' => 'tags.operator.0',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'tags.operator.1',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'unacknowledged',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'test4'
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
										'value' => '3'
									],
									[
										'type' => 1,
										'name' => 'tags.value.1',
										'value' => '44'
									],
									[
										'type' => 1,
										'name' => 'tag_priority',
										'value' => 'test5, test6'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'tag3'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.1',
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
								'x' => 16,
								'y' => 3,
								'width' => 8,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'dynamic',
										'value' => 1
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
								'name' => 'Test copy plain text',
								'type' => 'plaintext',
								'x' => 5,
								'y' => 3,
								'width' => 5,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'dynamic',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => '0'
									],
									[
										'type' => 0,
										'name' => 'show_as_html',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'show_lines',
										'value' => 12
									],
									[
										'type' => 0,
										'name' => 'style',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemids',
										'value' => 42230
									]
								]
							],
							[
								'name' => 'Test copy Problem hosts',
								'type' => 'problemhosts',
								'x' => 10,
								'y' => 3,
								'width' => 6,
								'height' => 2,
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
										'name' => 'tags.operator.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'problem',
										'value' => 'Test'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'Tag1'
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
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
								'x' => 0,
								'y' => 3,
								'width' => 5,
								'height' => 2,
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
								'x' => 9,
								'y' => 5,
								'width' => 7,
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
										'name' => 'tags.operator.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'tag_name_format',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'unacknowledged',
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
										'name' => 'tags.tag.0',
										'value' => 'tag9'
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
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
								'x' => 10,
								'y' => 8,
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
										'type' => 0,
										'name' => 'dynamic',
										'value' => 0
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
									]
								]
							],
							[
								'name' => 'Test copy item value',
								'type' => 'item',
								'x' => 0,
								'y' => 28,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => 42230
									],
									[
										'type' => 0,
										'name' => 'columns',
										'value' => 20
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => 60
									],
									[
										'type' => 0,
										'name' => 'adv_conf',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'desc_h_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'desc_v_pos',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'desc_bold',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'time_h_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'time_v_pos',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'time_bold',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'time_size',
										'value' => 16
									],
									[
										'type' => 0,
										'name' => 'units_size',
										'value' => 34
									],
									[
										'type' => 1,
										'name' => 'units',
										'value' => 'some'
									],
									[
										'type' => 1,
										'name' => 'bg_color',
										'value' => 'E1E1E1'
									],
									[
										'type' => 1,
										'name' => 'updown_color',
										'value' => 'FFB300'
									],
									[
										'type' => 1,
										'name' => 'up_color',
										'value' => 'CE93D8'
									],
									[
										'type' => 1,
										'name' => 'down_color',
										'value' => '29B6F6'
									],
									[
										'type' => 0,
										'name' => 'value_v_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'dynamic',
										'value' => 1
									]
								]
							],
							[
								'name' => 'Geomap widget for copying',
								'type' => 'geomap',
								'x' => 0,
								'y' => 8,
								'width' => 10,
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
										'name' => 'tags.tag.0',
										'value' => 'tag'
									],
									[
										'type' => 0,
										'name' => 'tags.operator.0',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
										'value' => 'value'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.1',
										'value' => 'tag2'
									],
									[
										'type' => 0,
										'name' => 'tags.operator.1',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'tags.value.1',
										'value' => 'value2'
									]
								]
							],
							[
								'name' => 'Test copy Top hosts',
								'type' => 'tophosts',
								'x' => 16,
								'y' => 5,
								'width' => 8,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
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
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'tag_name'
									],
									[
										'type' => 0,
										'name' => 'tags.operator.0',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
										'value' => 'tag_value'
									],
									[
										'type' => 1,
										'name' => 'columns.name.0',
										'value' => 'Item name'
									],
									[
										'type' => 0,
										'name' => 'columns.data.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.0',
										'value' => '3_item'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'columns.aggregate_interval.0',
										'value' => '1h'
									],
									[
										'type' => 1,
										'name' => 'columns.min.0',
										'value' => '10'
									],
									[
										'type' => 1,
										'name' => 'columns.max.0',
										'value' =>  '50'
									],
									[
										'type' => 0,
										'name' => 'columns.display.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'columns.history.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.0',
										'value' => 'FF0000'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columns.name.1',
										'value' => 'Host name'
									],
									[
										'type' => 0,
										'name' => 'columns.data.1',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.1',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.1',
										'value' => 'BF00FF'
									],
									[
										'type' => 1,
										'name' => 'columns.name.2',
										'value' => 'Text name'
									],
									[
										'type' => 0,
										'name' => 'columns.data.2',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.2',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.2',
										'value' => '00BFFF'
									],
									[
										'type' => 1,
										'name' => 'columns.text.2',
										'value' => 'text_here'
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
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
								'name' => 'Test widget for replace',
								'type' => 'clock',
								'x' => 6,
								'y' => 0,
								'width' => 13,
								'height' => 8,
								'view_mode' => 0
							],
							[
								'name' => 'Test copy Map navigation tree',
								'type' => 'navtree',
								'x' => 0,
								'y' => 0,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'FYKXG'
									]
								]
							]
						]
					]
				]
			]
		]);

		CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => 50000,
				'name' => 'Templated dashboard with all widgets',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Clock widget',
								'width' => 4,
								'height' => 4
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) widget',
								'x' => 4,
								'y' => 0,
								'width' => 8,
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
										'value' => 400410
									]
								]
							],
							[
								'type' => 'plaintext',
								'name' => 'Plain text widget',
								'x' => 12,
								'y' => 0,
								'width' => 6,
								'height' => 4,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemids',
										'value' => 400410
									]
								]
							],
							[
								'type' => 'url',
								'name' => 'URL widget',
								'x' => 18,
								'y' => 0,
								'width' => 6,
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
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 6,
								'fields' => [
									[
										'type' => 7,
										'name' => 'graphid',
										'value' => 700016
									]
								]
							],
							[
								'type' => 'item',
								'name' => 'Item value widget',
								'x' => 13,
								'y' => 4,
								'width' => 4,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'itemid',
										'value' => 400410
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
				'templateid' => 50000,
				'name' => 'Dashboard without widgets',
				'pages' => [[]]
			]
		]);

		return ['dashboardids' => CDataHelper::getIds('name')];
	}
}
