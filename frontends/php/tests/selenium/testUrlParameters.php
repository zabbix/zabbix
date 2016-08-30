<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testUrlParameters extends CWebTest {

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
						'url' => 'templates.php?form=update&templateid=10001&groupid=0',
						'text_present' => 'Templates'
					],
					[
						'url' => 'templates.php?form=update&templateid=10001&groupid=1',
						'text_present' => 'Templates'
					],
					[
						'url' => 'templates.php?form=update&templateid=9999999&groupid=1',
						'text_not_present' => 'Templates',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=abc&groupid=abc',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "templateid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=&groupid=',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "templateid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=-1&groupid=-1',
						'text_not_present' => 'Templates',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "templateid" field.',
							'Incorrect value "-1" for "groupid" field.'
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
				'title' => 'Configuration of hosts',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'hosts.php?form=update&hostid=10084&groupid=0',
						'text_present' => 'Hosts'
					],
					[
						'url' => 'hosts.php?form=update&hostid=10084&groupid=4',
						'text_present' => 'Hosts'
					],
					[
						'url' => 'hosts.php?form=update&hostid=9999999&groupid=4',
						'text_not_present' => 'Hosts',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hosts.php?form=update&hostid=abc&groupid=abc',
						'text_not_present' => 'Hosts',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'hosts.php?form=update&hostid=&groupid=',
						'text_not_present' => 'Hosts',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'hosts.php?form=update&hostid=-1&groupid=-1',
						'text_not_present' => 'Hosts',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "hostid" field.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'hosts.php?form=update',
						'text_not_present' => 'Hosts',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "hostid" is mandatory.'
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
						'url' => 'maintenance.php?form=update&maintenanceid=1&groupid=0',
						'text_present' => 'Maintenance periods'
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=1&groupid=4',
						'text_present' => 'Maintenance periods'
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=9999999&groupid=4',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=abc&groupid=abc',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "maintenanceid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=&groupid=',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "maintenanceid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=-1&groupid=-1',
						'text_not_present' => 'Maintenance periods',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "maintenanceid" field.',
							'Incorrect value "-1" for "groupid" field.'
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
				'title' => 'Configuration of screens',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'screenedit.php?screenid=16',
						'text_present' => 'Screens: Zabbix server'
					],
					[
						'url' => 'screenedit.php?screenid=9999999',
						'text_not_present' => 'Screens: Zabbix server',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'screenedit.php?screenid=abc',
						'text_not_present' => 'Screens: Zabbix server',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "screenid" is not integer.'
						]
					],
					[
						'url' => 'screenedit.php?screenid=',
						'text_not_present' => 'Screens: Zabbix server',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "screenid" is not integer.'
						]
					],
					[
						'url' => 'screenedit.php?screenid=-1',
						'text_not_present' => 'Screens: Zabbix server',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "screenid" field.'
						]
					],
					[
						'url' => 'screenedit.php',
						'text_not_present' => 'Screens: Zabbix server',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "screenid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of slide shows',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'slideconf.php',
						'text_present' => 'Slide shows'
					],
					[
						'url' => 'slideconf.php?form=update&slideshowid=9999999',
						'text_not_present' => 'Slide shows',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'slideconf.php?form=update&slideshowid=abc',
						'text_not_present' => 'Slide shows',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "slideshowid" is not integer.'
						]
					],
					[
						'url' => 'slideconf.php?form=update&slideshowid=',
						'text_not_present' => 'Slide shows',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "slideshowid" is not integer.'
						]
					],
					[
						'url' => 'slideconf.php?form=update',
						'text_not_present' => 'Slide shows',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "slideshowid" is mandatory.'
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
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'discoveryconf.php?form=update&druleid=2',
						'text_present' => 'Discovery rules'
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=9999999',
						'text_not_present' => 'Discovery rules',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=abc',
						'text_not_present' => 'Discovery rules',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "druleid" is not integer.'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=',
						'text_not_present' => 'Discovery rules',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "druleid" is not integer.'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=-1',
						'text_not_present' => 'Discovery rules',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "druleid" field.'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update',
						'text_not_present' => 'Discovery rules',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "druleid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Overview [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'overview.php?groupid=4&type=0',
						'text_present' => 'Overview'
					],
					[
						'url' => 'overview.php?groupid=9999999&type=0',
						'text_not_present' => 'Overview',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'overview.php?groupid=abc&type=abc',
						'text_not_present' => 'Overview',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "type" is not integer.'
						]
					],
					[
						'url' => 'overview.php?groupid=&type=',
						'text_not_present' => 'Overview',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "type" is not integer.'
						]
					],
					[
						'url' => 'overview.php?groupid=-1&type=-1',
						'text_not_present' => 'Overview',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "type" field.'
						]
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
				'title' => 'Latest data [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'latest.php?groupid=4&hostid=50009',
						'text_present' => 'Latest data'
					],
					[
						'url' => 'latest.php?groupids[]=9999999&hostids[]=50009',
						'text_not_present' => 'Latest data',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'latest.php?groupids[]=4&hostids[]=9999999',
						'text_not_present' => 'Latest data',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'latest.php?groupids[]=abc&hostids[]=abc',
						'text_not_present' => 'Latest data',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupids" is not integer.',
							'Field "hostids" is not integer.'
						]
					],
					[
						'url' => 'latest.php?groupids[]=&hostids[]=',
						'text_not_present' => 'Latest data',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupids" is not integer.',
							'Field "hostids" is not integer.'
						]
					],
					[
						'url' => 'latest.php?groupids[]=-1&hostids[]=-1',
						'text_not_present' => 'Latest data',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "Array" for "groupids" field.',
							'Incorrect value "Array" for "hostids" field.'
						]
					],
					[
						'url' => 'latest.php',
						'text_present' => 'Latest data'
					]
				]
			],
			[
				'title' => 'Triggers [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'tr_status.php?groupid=4&hostid=10084',
						'text_present' => 'Triggers'
					],
					[
						'url' => 'tr_status.php?groupid=9999999&hostid=10084',
						'text_not_present' => 'Triggers',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'tr_status.php?groupid=4&hostid=9999999',
						'text_not_present' => 'Triggers',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'tr_status.php?groupid=abc&hostid=abc',
						'text_not_present' => 'Triggers',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'tr_status.php?groupid=&hostid=',
						'text_not_present' => 'Triggers',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'tr_status.php?groupid=-1&hostid=-1',
						'text_not_present' => 'Triggers',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "hostid" field.'
						]
					],
					[
						'url' => 'tr_status.php',
						'text_present' => 'Triggers'
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
							'Not Found',
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
						'url' => 'zabbix.php?action=problem.view&filter_triggerids[]=abc',
						'text_not_present' => 'Problems',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: problem.view'
							]
					],
					[
						'url' => 'zabbix.php?action=problem.view&filter_triggerids[]=',
						'text_not_present' => 'Problems',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: problem.view'
						]
					],
					[
						'url' => 'zabbix.php?action=problem.view&filter_triggerids[]=-1',
						'text_not_present' => 'Problems',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: problem.view'
						]
					],
				]
			],
			[
				'title' => 'Custom graphs [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'charts.php?groupid=4&hostid=10084&graphid=524',
						'text_present' => 'Graphs'
					],
					[
						'url' => 'charts.php?groupid=9999999&hostid=0&graphid=0',
						'text_not_present' => 'Graphs',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'charts.php?groupid=0&hostid=9999999&graphid=0',
						'text_not_present' => 'Graphs',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'charts.php?groupid=0&hostid=0&graphid=9999999',
						'text_not_present' => 'Graphs',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'charts.php?groupid=abc&hostid=abc&graphid=abc',
						'text_not_present' => 'Graphs',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.',
							'Field "graphid" is not integer.'
						]
					],
					[
						'url' => 'charts.php?groupid=&hostid=&graphid=',
						'text_not_present' => 'Graphs',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.',
							'Field "graphid" is not integer.'
						]
					],
					[
						'url' => 'charts.php?groupid=-1&hostid=-1&graphid=-1',
						'text_not_present' => 'Graphs',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "hostid" field.',
							'Incorrect value "-1" for "graphid" field.'
						]
					],
					[
						'url' => 'charts.php',
						'text_present' => 'Graphs'
					]
				]
			],
			[
				'title' => 'Custom screens [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'screens.php?elementid=16',
						'text_present' => 'Screens'
					],
					[
						'url' => 'screens.php?elementid=9999999',
						'text_not_present' => 'Screens',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'screens.php?elementid=abc',
						'text_not_present' => 'Screens',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "elementid" is not integer.'
						]
					],
					[
						'url' => 'screens.php?elementid=',
						'text_not_present' => 'Screens',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "elementid" is not integer.'
						]
					],
					[
						'url' => 'screens.php?elementid=-1',
						'text_not_present' => 'Screens',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "elementid" field.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of screens',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'screens.php',
						'text_present' => 'Screens'
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
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "sysmapid" field.'
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
						'url' => 'zabbix.php?action=discovery.view&druleid=3',
						'text_present' => 'Status of discovery'
					],
					[
						'url' => 'zabbix.php?action=discovery.view',
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
						'url' => 'zabbix.php?action=discovery.view&druleid=abc',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
							]
					],
					[
						'url' => 'zabbix.php?action=discovery.view&druleid=',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
						]
					],
					[
						'url' => 'zabbix.php?action=discovery.view&druleid=-1',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Fatal error, please report to the Zabbix team',
							'Controller: discovery.view'
						]
					],
				]
			],
			[
				'title' => 'Warning [refreshed every 30 sec.]',
				'check_server_name' => false,
				'server_name_on_page' => false,
				'test_cases' => [
					[
						'url' => 'zabbix.php?action=discovery.view&druleid=9999999',
						'text_not_present' => 'Status of discovery',
						'text_present' => [
							'Access denied',
							'You are logged in as "Admin". You have no permissions to access this page.',
							'If you think this message is wrong, please consult your administrators about getting the necessary permissions.'
						]
					]
				]
			],
			[
				'title' => 'IT services [refreshed every 30 sec.]',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'srv_status.php?period=today',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=week',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=month',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=year',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=24',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=168',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=720',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=8760',
						'text_present' => 'IT services'
					],
					[
						'url' => 'srv_status.php?period=1',
						'text_not_present' => 'IT services',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "1" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php?period=abc',
						'text_not_present' => 'IT services',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "abc" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php?period=',
						'text_not_present' => 'IT services',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php?period=-1',
						'text_not_present' => 'IT services',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php',
						'text_present' => 'IT services'
					]
				]
			],
			[
				'title' => 'Host inventory overview',
				'check_server_name' => true,
				'server_name_on_page' => true,
				'test_cases' => [
					[
						'url' => 'hostinventoriesoverview.php?groupid=4&groupby=',
						'text_present' => 'Host inventory overview'
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=4&groupby=alias',
						'text_present' => 'Host inventory overview'
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=9999999&groupby=',
						'text_not_present' => 'Host inventory overview',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=abc&groupby=',
						'text_not_present' => 'Host inventory overview',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=&groupby=',
						'text_not_present' => 'Host inventory overview',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=-1&groupby=',
						'text_not_present' => 'Host inventory overview',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.'
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
						'url' => 'hostinventories.php?groupid=4',
						'text_present' => 'Host inventory'
					],
					[
						'url' => 'hostinventories.php?groupid=9999999',
						'text_not_present' => 'Host inventory',
						'text_present' => [
							'No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostinventories.php?groupid=abc',
						'text_not_present' => 'Host inventory',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventories.php?groupid=',
						'text_not_present' => 'Host inventory',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventories.php?groupid=-1',
						'text_not_present' => 'Host inventory',
						'text_present' => [
							'Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.'
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
	 */
	public function testUrlParameters_UrlLoad($title, $check_server_name, $server_name_on_page, $test_cases) {
		foreach ($test_cases as $test_case) {
			$this->zbxTestLogin($test_case['url'], $server_name_on_page);
			$this->zbxTestCheckTitle($title, $check_server_name);
			$this->zbxTestTextPresent($test_case['text_present']);
			if (isset($test_case['text_not_present'])) {
				$this->zbxTestHeaderNotPresent($test_case['text_not_present']);
			}
		}
	}
}
