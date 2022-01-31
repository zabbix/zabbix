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


require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testUrlParameters extends CLegacyWebTest {

	public static function data() {
		return [
			[
				'title' => 'Configuration of host groups',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'hostgroups.php?form=update&groupid=1',
						'text_present' => 'Host groups'
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=9999999',
						'text_not_present' => 'Host groups',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=abc',
						'text_not_present' => 'Host groups',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=',
						'text_not_present' => 'Host groups',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=-1',
						'text_not_present' => 'Host groups',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'hostgroups.php?form=update',
						'text_not_present' => 'Host groups',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of templates',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'templates.php?form=update&templateid=10001',
						'text_present' => 'Templates'
					],
					[
						'url' => 'templates.php?form=update&templateid=9999999',
						'text_not_present' => 'Templates',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=abc',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "templateid" is not integer.'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "templateid" is not integer.'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=-1',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "templateid" field.'
						]
					],
					[
						'url' => 'templates.php?form=update',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "templateid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of host',
				'check_server_name' => true,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=host.edit&hostid=10084',
						'text_present' => 'Host'
					],
					[
						'url' => 'zabbix.php?action=host.edit&hostid=9999999',
						'text_not_present' => 'Host',
						'access_denied' => true,
						'text_present' => [
							'You are logged in as "Admin". You have no permissions to access this page.'
						]
					]
				]
			],
			[
				'title' => 'Fatal error, please report to the Zabbix team',
				'check_server_name' => true,
				'server_name_on_page' => false,
				'test_cases' => [

					[
						'url' => 'zabbix.php?action=host.edit&hostid=abc',
						'text_not_present' => 'Host',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "abc" for "hostid" field.',
							'Controller: host.edit',
							'action: host.edit',
							'hostid: abc'
						]
					],
					[
						'url' => 'zabbix.php?action=host.edit&hostid= ',
						'text_not_present' => 'Host',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "" for "hostid" field.',
							'Controller: host.edit',
							'action: host.edit',
							'hostid:'
						]
					],
					[
						'url' => 'zabbix.php?action=host.edit&hostid=-1',
						'text_not_present' => 'Host',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "-1" for "hostid" field.',
							'Controller: host.edit',
							'action: host.edit',
							'hostid: -1'
						]
					],
					[
						'url' => 'zabbix.php?action=host.edit&hostid=',
						'text_not_present' => 'Host',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "" for "hostid" field.',
							'Controller: host.edit',
							'action: host.edit',
							'hostid:'
						]
					]
				]
			],
			[
				'title' => 'Configuration of maintenance periods',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'maintenance.php?form=update&maintenanceid=1',
						'text_present' => 'Maintenance periods'
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=9999999',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=abc',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "maintenanceid" is not integer.'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "maintenanceid" is not integer.'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=-1',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "maintenanceid" field.'
						]
					],
					[
						'url' => 'maintenance.php?form=update',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "maintenanceid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of actions',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'actionconf.php?form=update&actionid=3',
						'text_present' => 'Actions'
					],
					[
						'url' => 'actionconf.php?form=update&actionid=9999999',
						'text_not_present' => 'Actions',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'actionconf.php?form=update&actionid=abc',
						'text_not_present' => 'Actions',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "actionid" is not integer.'
						]
					],
					[
						'url' => 'actionconf.php?form=update&actionid=',
						'text_not_present' => 'Actions',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "actionid" is not integer.'
						]
					],
					[
						'url' => 'actionconf.php?form=update&actionid=-1',
						'text_not_present' => 'Actions',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "actionid" field.'
						]
					],
					[
						'url' => 'actionconf.php?form=update',
						'text_not_present' => 'Actions',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "actionid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of network maps',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'sysmap.php?sysmapid=1',
						'text_present' => 'Network maps'
					],
					[
						'url' => 'sysmap.php?sysmapid=9999999',
						'text_not_present' => 'Network maps',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'sysmap.php?sysmapid=abc',
						'text_not_present' => 'Network maps',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.'
						]
					],
					[
						'url' => 'sysmap.php?sysmapid=',
						'text_not_present' => 'Network maps',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.'
						]
					],
					[
						'url' => 'sysmap.php?sysmapid=-1',
						'text_not_present' => 'Network maps',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "sysmapid" field.'
						]
					],
					[
						'url' => 'sysmap.php',
						'text_not_present' => 'Network maps',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "sysmapid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of discovery rules',
				'check_server_name' => true,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=discovery.edit&druleid=2',
						'text_present' => 'Discovery rules'
					],
					[
						'url' => 'zabbix.php?action=discovery.edit&druleid=9999999',
						'text_not_present' => 'Discovery rules',
						'access_denied' => true,
						'text_present' => [
							'You are logged in as "Admin". You have no permissions to access this page.'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.edit&druleid=abc',
						'text_not_present' => 'Discovery rules',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "abc" for "druleid" field.',
							'Controller: discovery.edit',
							'action: discovery.edit',
							'druleid: abc'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.edit&druleid=',
						'text_not_present' => 'Discovery rules',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "" for "druleid" field.',
							'Controller: discovery.edit',
							'action: discovery.edit',
							'druleid:'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.edit&druleid=-1',
						'text_not_present' => 'Discovery rules',
						'fatal_error' => true,
						'text_present' => [
							'Incorrect value "-1" for "druleid" field.',
							'Controller: discovery.edit',
							'action: discovery.edit',
							'druleid: -1'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.edit',
						'text_present' => 'Discovery rules'
					]
				]
			],
			[
				'title' => 'Details of web scenario',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'httpdetails.php?httptestid=94',
						'text_present' => 'Details of web scenario'
					],
					[
						'url' => 'httpdetails.php?httptestid=9999999',
						'text_not_present' => 'Details of web scenario',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'httpdetails.php?httptestid=abc',
						'text_not_present' => 'Details of web scenario',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "httptestid" is not integer.'
						]
					],
					[
						'url' => 'httpdetails.php?httptestid=',
						'text_not_present' => 'Details of web scenario',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "httptestid" is not integer.'
						]
					],
					[
						'url' => 'httpdetails.php?httptestid=-1',
						'text_not_present' => 'Details of web scenario',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "httptestid" field.'
						]
					],
					[
						'url' => 'httpdetails.php',
						'text_not_present' => 'Details of web scenario',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "httptestid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Latest data',
				'check_server_name' => true,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=latest.view&groupids[]=4&hostids[]=50009',
						'text_present' => 'Latest data'
					],
					[
						'url' => 'zabbix.php?action=latest.view&groupids[]=9999999&hostids[]=50009',
						'text_present' => 'Latest data'
					],
					[
						'url' => 'zabbix.php?action=latest.view&groupids[]=4&hostids[]=9999999',
						'text_present' => 'Latest data'
					],
					[
						'url' => 'zabbix.php?action=latest.view&groupids[]=abc&hostids[]=abc',
						'text_not_present' => 'Latest data',
						'fatal_error' => true,
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Incorrect value for "groupids" field.',
							'Incorrect value for "hostids" field.'
						]
					],
					[
						'url' => 'zabbix.php?action=latest.view&groupids[]=&hostids[]=',
						'text_not_present' => 'Latest data',
						'fatal_error' => true,
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Incorrect value for "groupids" field.',
							'Incorrect value for "hostids" field.'
						]
					],
					[
						'url' => 'zabbix.php?action=latest.view&groupids[]=-1&hostids[]=-1',
						'text_not_present' => 'Latest data',
						'fatal_error' => true,
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Incorrect value for "groupids" field.',
							'Incorrect value for "hostids" field.'
						]
					],
					[
						'url' => 'zabbix.php?action=latest.view',
						'text_present' => 'Latest data'
					]
				]
			],
			[
				'title' => '404 Not Found',
				'check_server_name' => false,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'events.php',
						'text_not_present' => 'Events',
						'text_present' => [
							'Not Found'
						]
					],
					[
						'url' => 'events.php?triggerid=13491',
						'text_not_present' => 'Events',
						'text_present' => [
							'Not Found'
						]
					]
				]
			],
			[
				'title' => 'Problems',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=problem.view',
						'text_present' => 'Problems'
					],
					[
						'url' => 'zabbix.php?action=problem.view&filter_triggerids[]=13491',
						'text_present' => 'Problems'
					]
				]
			],
			[
				'title' => 'Fatal error, please report to the Zabbix team',
				'check_server_name' => false,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=problem.view&triggerids%5B%5D=abc',
						'text_not_present' => 'Problems',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: problem.view'
							]
					],
					[
						'url' => 'zabbix.php?action=problem.view&triggerids%5B%5D=',
						'text_not_present' => 'Problems',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: problem.view'
						]
					],
					[
						'url' => 'zabbix.php?action=problem.view&triggerids%5B%5D=-1',
						'text_not_present' => 'Problems',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: problem.view'
						]
					]
				]
			],
			[
				'title' => 'Custom graphs',
				'check_server_name' => true,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=66666&filter_show=2&filter_set=1',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=99012&filter_hostids%5B%5D=66666&'.
								'filter_show=1&filter_set=1',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=50011&filter_hostids%5B%5D=66666&'.
						'filter_name=2_item&filter_show=0&filter_set=1',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D=abc&filter_show=1&filter_set=1',
						'text_not_present' => 'Graphs',
						'fatal_error' => true,
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Incorrect value for "filter_hostids" field.'
						]
					],
					[
						'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D=-1&filter_show=1&filter_set=1',
						'text_not_present' => 'Graphs',
						'fatal_error' => true,
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Incorrect value for "filter_hostids" field.'
						]
					]
				]
			],
			[
				'title' => 'History [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'history.php?action=showgraph&itemids%5B%5D=66666',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'history.php?action=showgraph&itemids%5B%5D=',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "itemids" is not integer.'
						]
					],
					[
						'url' => 'history.php?action=showgraph&itemids%5B%5D=abc',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "itemids" is not integer.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of network maps',
				'check_serer_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'sysmaps.php?sysmapid=1&severity_min=0',
						'text_present' => 'Maps'
					],
					[
						'url' => 'sysmaps.php?sysmapid=9999999&severity_min=0',
						'text_not_present' => 'Maps',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'sysmaps.php?sysmapid=1&severity_min=6',
						'text_present' => [
							'Page received incorrect data',
							'Incorrect value "6" for "severity_min" field.'
						]
					],
					[
						'url' => 'sysmaps.php?sysmapid=1&severity_min=-1',
						'text_present' => [
							'Page received incorrect data',
							'Incorrect value "-1" for "severity_min" field.'
						]
					],
					[
						'url' => 'sysmaps.php?sysmapid=-1&severity_min=0',
						'text_not_present' => 'Maps',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'sysmaps.php?sysmapid=abc&severity_min=abc',
						'text_not_present' => 'Maps',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.',
							'Field "severity_min" is not integer.'
						]
					],
					[
						'url' => 'sysmaps.php?sysmapid=&severity_min=',
						'text_not_present' => 'Maps',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.',
							'Field "severity_min" is not integer.'
						]
					],
					[
						'url' => 'sysmaps.php?sysmapid=1&severity_min=0',
						'text_present' => 'Maps'
					]
				]
			],
			[
				'title' => 'Status of discovery',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=3&filter_set=1',
						'text_present' => 'Status of discovery'
					],
					[
						'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=3',
						'text_present' => 'Status of discovery'
					],
					[
						'url' => 'zabbix.php?action=discovery.view',
						'text_present' => 'Status of discovery'
					],
					[
						'url' => 'zabbix.php?action=discovery.view&filter_rst=1',
						'text_present' => 'Status of discovery'
					]
				]
			],
			[
				'title' => 'Fatal error, please report to the Zabbix team',
				'check_server_name' => false,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=abc',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=-123',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.view&filter_druleids=123',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.view&filter_druleids=',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
						]
					]
				]
			],
			[
				'title' => 'Host inventory overview',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'hostinventoriesoverview.php?groupby=&filter_set=1',
						'text_present' => 'Host inventory overview'
					],
					[
						'url' => 'hostinventoriesoverview.php?filter_groupby=alias&filter_set=1',
						'text_present' => 'Host inventory overview'
					],
					[
						'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=abc&filter_groupby=&filter_set=1',
						'text_present' => [
							'Page received incorrect data',
							'Field "filter_groups" is not integer.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=&filter_groupby=&filter_set=1',
						'text_present' => [
							'Page received incorrect data',
							'Field "filter_groups" is not integer.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=-1&filter_groupby=&filter_set=1',
						'text_present' => [
							'Page received incorrect data',
							'Incorrect value for "filter_groups" field.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=9999999&filter_groupby=&filter_set=1',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php',
						'text_present' => 'Host inventory overview'
					]
				]
			],
			[
				'title' => 'Host inventory',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'hostinventories.php?filter_groups%5B%5D=4&filter_set=1',
						'text_present' => 'Host inventory'
					],
					[
						'url' => 'hostinventories.php?filter_groups%5B%5D=9999999&filter_set=1',
						'text_present' => [
							'text_present' => 'type here to search'
						]
					],
					[
						'url' => 'hostinventories.php?filter_groups%5B%5D=abc&filter_set=1',
						'text_present' => [
							'Page received incorrect data',
							'Field "filter_groups" is not integer.'
						]
					],
					[
						'url' => 'hostinventories.php?filter_groups%5B%5D=&filter_set=1',
						'text_present' => [
							'Page received incorrect data',
							'Field "filter_groups" is not integer.'
						]
					],
					[
						'url' => 'hostinventories.php?filter_groups%5B%5D=-1&filter_set=1',
						'text_present' => [
							'Page received incorrect data',
							'Incorrect value for "filter_groups" field.'
						]
					],
					[
						'url' => 'hostinventories.php',
						'text_present' => 'Host inventory'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider data
	 * @ignoreBrowserErrors
	 */
	public function testUrlParameters_UrlLoad($title, $check_server_name, $server_name_on_page, $test_cases) {
		foreach ($test_cases as $test_case) {
			$this->zbxTestLogin($test_case['url'], $server_name_on_page);
			if (array_key_exists('fatal_error', $test_case)) {
				$this->zbxTestCheckTitle('Fatal error, please report to the Zabbix team', false);
			}
			elseif (array_key_exists('access_denied', $test_case)) {
				$this->zbxTestCheckTitle('Warning [refreshed every 30 sec.]', false);
			}
			else {
				$this->zbxTestCheckTitle($title, $check_server_name);
			}
			$this->zbxTestTextPresent($test_case['text_present']);
			if (isset($test_case['text_not_present'])) {
				$this->zbxTestHeaderNotPresent($test_case['text_not_present']);
			}
		}
	}
}
