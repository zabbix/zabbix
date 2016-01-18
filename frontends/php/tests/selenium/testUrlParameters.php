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
				'test_cases' => [
					[
						'url' => 'hostgroups.php?form=update&groupid=1',
						'text_present' => 'CONFIGURATION OF HOST GROUPS'
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=9999999',
						'text_not_present' => 'CONFIGURATION OF HOST GROUPS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=abc',
						'text_not_present' => 'CONFIGURATION OF HOST GROUPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=',
						'text_not_present' => 'CONFIGURATION OF HOST GROUPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostgroups.php?form=update&groupid=-1',
						'text_not_present' => 'CONFIGURATION OF HOST GROUPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'hostgroups.php?form=update',
						'text_not_present' => 'CONFIGURATION OF HOST GROUPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of templates',
				'test_cases' => [
					[
						'url' => 'templates.php?form=update&templateid=10001&groupid=0',
						'text_present' => 'CONFIGURATION OF TEMPLATES'
					],
					[
						'url' => 'templates.php?form=update&templateid=10001&groupid=1',
						'text_present' => 'CONFIGURATION OF TEMPLATES'
					],
					[
						'url' => 'templates.php?form=update&templateid=9999999&groupid=1',
						'text_not_present' => 'CONFIGURATION OF TEMPLATES',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=abc&groupid=abc',
						'text_not_present' => 'CONFIGURATION OF TEMPLATES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "templateid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=&groupid=',
						'text_not_present' => 'CONFIGURATION OF TEMPLATES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "templateid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'templates.php?form=update&templateid=-1&groupid=-1',
						'text_not_present' => 'CONFIGURATION OF TEMPLATES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "templateid" field.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'templates.php?form=update',
						'text_not_present' => 'CONFIGURATION OF TEMPLATES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "templateid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of hosts',
				'test_cases' => [
					[
						'url' => 'hosts.php?form=update&hostid=10084&groupid=0',
						'text_present' => 'CONFIGURATION OF HOSTS'
					],
					[
						'url' => 'hosts.php?form=update&hostid=10084&groupid=4',
						'text_present' => 'CONFIGURATION OF HOSTS'
					],
					[
						'url' => 'hosts.php?form=update&hostid=9999999&groupid=4',
						'text_not_present' => 'CONFIGURATION OF HOSTS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hosts.php?form=update&hostid=abc&groupid=abc',
						'text_not_present' => 'CONFIGURATION OF HOSTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'hosts.php?form=update&hostid=&groupid=',
						'text_not_present' => 'CONFIGURATION OF HOSTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'hosts.php?form=update&hostid=-1&groupid=-1',
						'text_not_present' => 'CONFIGURATION OF HOSTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "hostid" field.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'hosts.php?form=update',
						'text_not_present' => 'CONFIGURATION OF HOSTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "hostid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of maintenance periods',
				'test_cases' => [
					[
						'url' => 'maintenance.php?form=update&maintenanceid=1&groupid=0',
						'text_present' => 'CONFIGURATION OF MAINTENANCE PERIODS'
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=1&groupid=4',
						'text_present' => 'CONFIGURATION OF MAINTENANCE PERIODS'
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=9999999&groupid=4',
						'text_not_present' => 'CONFIGURATION OF MAINTENANCE PERIODS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=abc&groupid=abc',
						'text_not_present' => 'CONFIGURATION OF MAINTENANCE PERIODS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "maintenanceid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=&groupid=',
						'text_not_present' => 'CONFIGURATION OF MAINTENANCE PERIODS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "maintenanceid" is not integer.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'maintenance.php?form=update&maintenanceid=-1&groupid=-1',
						'text_not_present' => 'CONFIGURATION OF MAINTENANCE PERIODS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "maintenanceid" field.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'maintenance.php?form=update',
						'text_not_present' => 'CONFIGURATION OF MAINTENANCE PERIODS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "maintenanceid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of actions',
				'test_cases' => [
					[
						'url' => 'actionconf.php?form=update&actionid=3',
						'text_present' => 'CONFIGURATION OF ACTIONS'
					],
					[
						'url' => 'actionconf.php?form=update&actionid=9999999',
						'text_not_present' => 'CONFIGURATION OF ACTIONS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'actionconf.php?form=update&actionid=abc',
						'text_not_present' => 'CONFIGURATION OF ACTIONS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "actionid" is not integer.'
						]
					],
					[
						'url' => 'actionconf.php?form=update&actionid=',
						'text_not_present' => 'CONFIGURATION OF ACTIONS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "actionid" is not integer.'
						]
					],
					[
						'url' => 'actionconf.php?form=update&actionid=-1',
						'text_not_present' => 'CONFIGURATION OF ACTIONS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "actionid" field.'
						]
					],
					[
						'url' => 'actionconf.php?form=update',
						'text_not_present' => 'CONFIGURATION OF ACTIONS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "actionid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of screens',
				'test_cases' => [
					[
						'url' => 'screenedit.php?screenid=16',
						'text_present' => 'CONFIGURATION OF SCREEN'
					],
					[
						'url' => 'screenedit.php?screenid=9999999',
						'text_not_present' => 'CONFIGURATION OF SCREENS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'screenedit.php?screenid=abc',
						'text_not_present' => 'CONFIGURATION OF SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "screenid" is not integer.'
						]
					],
					[
						'url' => 'screenedit.php?screenid=',
						'text_not_present' => 'CONFIGURATION OF SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "screenid" is not integer.'
						]
					],
					[
						'url' => 'screenedit.php?screenid=-1',
						'text_not_present' => 'CONFIGURATION OF SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "screenid" field.'
						]
					],
					[
						'url' => 'screenedit.php',
						'text_not_present' => 'CONFIGURATION OF SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "screenid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of slide shows',
				'test_cases' => [
					[
						'url' => 'slideconf.php?form=update&slideshowid=200001',
						'text_present' => 'CONFIGURATION OF SLIDE SHOWS'
					],
					[
						'url' => 'slideconf.php?form=update&slideshowid=9999999',
						'text_not_present' => 'CONFIGURATION OF SLIDE SHOWS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'slideconf.php?form=update&slideshowid=abc',
						'text_not_present' => 'CONFIGURATION OF SLIDE SHOWS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "slideshowid" is not integer.'
						]
					],
					[
						'url' => 'slideconf.php?form=update&slideshowid=',
						'text_not_present' => 'CONFIGURATION OF SLIDE SHOWS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "slideshowid" is not integer.'
						]
					],
					[
						'url' => 'slideconf.php?form=update',
						'text_not_present' => 'CONFIGURATION OF SLIDE SHOWS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "slideshowid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of network maps',
				'test_cases' => [
					[
						'url' => 'sysmap.php?sysmapid=1',
						'text_present' => 'CONFIGURATION OF NETWORK MAPS'
					],
					[
						'url' => 'sysmap.php?sysmapid=9999999',
						'text_not_present' => 'CONFIGURATION OF NETWORK MAPS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'sysmap.php?sysmapid=abc',
						'text_not_present' => 'CONFIGURATION OF NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.'
						]
					],
					[
						'url' => 'sysmap.php?sysmapid=',
						'text_not_present' => 'CONFIGURATION OF NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.'
						]
					],
					[
						'url' => 'sysmap.php?sysmapid=-1',
						'text_not_present' => 'CONFIGURATION OF NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "sysmapid" field.'
						]
					],
					[
						'url' => 'sysmap.php',
						'text_not_present' => 'CONFIGURATION OF NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "sysmapid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Configuration of discovery rules',
				'test_cases' => [
					[
						'url' => 'discoveryconf.php?form=update&druleid=2',
						'text_present' => 'CONFIGURATION OF DISCOVERY RULE'
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=9999999',
						'text_not_present' => 'CONFIGURATION OF DISCOVERY RULE',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=abc',
						'text_not_present' => 'CONFIGURATION OF DISCOVERY RULE',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "druleid" is not integer.'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=',
						'text_not_present' => 'CONFIGURATION OF DISCOVERY RULE',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "druleid" is not integer.'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update&druleid=-1',
						'text_not_present' => 'CONFIGURATION OF DISCOVERY RULE',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "druleid" field.'
						]
					],
					[
						'url' => 'discoveryconf.php?form=update',
						'text_not_present' => 'CONFIGURATION OF DISCOVERY RULE',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "druleid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Overview \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'overview.php?groupid=4&type=0',
						'text_present' => 'OVERVIEW'
					],
					[
						'url' => 'overview.php?groupid=9999999&type=0',
						'text_not_present' => 'OVERVIEW',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'overview.php?groupid=abc&type=abc',
						'text_not_present' => 'OVERVIEW',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "type" is not integer.'
						]
					],
					[
						'url' => 'overview.php?groupid=&type=',
						'text_not_present' => 'OVERVIEW',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "type" is not integer.'
						]
					],
					[
						'url' => 'overview.php?groupid=-1&type=-1',
						'text_not_present' => 'OVERVIEW',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "type" field.'
						]
					]
				]
			],
			[
				'title' => 'Details of web scenario',
				'test_cases' => [
					[
						'url' => 'httpdetails.php?httptestid=94',
						'text_present' => 'DETAILS OF WEB SCENARIO'
					],
					[
						'url' => 'httpdetails.php?httptestid=9999999',
						'text_not_present' => 'DETAILS OF WEB SCENARIO',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'httpdetails.php?httptestid=abc',
						'text_not_present' => 'DETAILS OF WEB SCENARIO',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "httptestid" is not integer.'
						]
					],
					[
						'url' => 'httpdetails.php?httptestid=',
						'text_not_present' => 'DETAILS OF WEB SCENARIO',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "httptestid" is not integer.'
						]
					],
					[
						'url' => 'httpdetails.php?httptestid=-1',
						'text_not_present' => 'DETAILS OF WEB SCENARIO',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "httptestid" field.'
						]
					],
					[
						'url' => 'httpdetails.php',
						'text_not_present' => 'DETAILS OF WEB SCENARIO',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "httptestid" is mandatory.'
						]
					]
				]
			],
			[
				'title' => 'Latest data \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'latest.php?groupid=4&hostid=10084',
						'text_present' => 'LATEST DATA'
					],
					[
						'url' => 'latest.php?groupid=9999999&hostid=10084',
						'text_not_present' => 'LATEST DATA',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'latest.php?groupid=4&hostid=9999999',
						'text_not_present' => 'LATEST DATA',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'latest.php?groupid=abc&hostid=abc',
						'text_not_present' => 'LATEST DATA',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'latest.php?groupid=&hostid=',
						'text_not_present' => 'LATEST DATA',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'latest.php?groupid=-1&hostid=-1',
						'text_not_present' => 'LATEST DATA',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "hostid" field.'
						]
					],
					[
						'url' => 'latest.php',
						'text_present' => 'LATEST DATA'
					]
				]
			],
			[
				'title' => 'Status of triggers \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'tr_status.php?groupid=4&hostid=10084',
						'text_present' => 'STATUS OF TRIGGERS'
					],
					[
						'url' => 'tr_status.php?groupid=9999999&hostid=10084',
						'text_not_present' => 'STATUS OF TRIGGERS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'tr_status.php?groupid=4&hostid=9999999',
						'text_not_present' => 'STATUS OF TRIGGERS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'tr_status.php?groupid=abc&hostid=abc',
						'text_not_present' => 'STATUS OF TRIGGERS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'tr_status.php?groupid=&hostid=',
						'text_not_present' => 'STATUS OF TRIGGERS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.'
						]
					],
					[
						'url' => 'tr_status.php?groupid=-1&hostid=-1',
						'text_not_present' => 'STATUS OF TRIGGERS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "hostid" field.'
						]
					],
					[
						'url' => 'tr_status.php',
						'text_present' => 'STATUS OF TRIGGERS'
					]
				]
			],
			[
				'title' => 'Latest events \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'events.php?triggerid=13491',
						'text_present' => 'HISTORY OF EVENTS'
					],
					[
						'url' => 'events.php?triggerid=9999999',
						'text_not_present' => 'HISTORY OF EVENTS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'events.php?triggerid=abc',
						'text_not_present' => 'HISTORY OF EVENTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "triggerid" is not integer.'
						]
					],
					[
						'url' => 'events.php?triggerid=',
						'text_not_present' => 'HISTORY OF EVENTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "triggerid" is not integer.'
						]
					],
					[
						'url' => 'events.php?triggerid=-1',
						'text_not_present' => 'HISTORY OF EVENTS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "triggerid" field.'
						]
					],
					[
						'url' => 'events.php',
						'text_present' => 'HISTORY OF EVENTS'
					]
				]
			],
			[
				'title' => 'Custom graphs \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'charts.php?groupid=4&hostid=10084&graphid=524',
						'text_present' => 'GRAPHS'
					],
					[
						'url' => 'charts.php?groupid=9999999&hostid=0&graphid=0',
						'text_not_present' => 'GRAPHS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'charts.php?groupid=0&hostid=9999999&graphid=0',
						'text_not_present' => 'GRAPHS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'charts.php?groupid=0&hostid=0&graphid=9999999',
						'text_not_present' => 'GRAPHS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'charts.php?groupid=abc&hostid=abc&graphid=abc',
						'text_not_present' => 'GRAPHS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.',
							'Field "graphid" is not integer.'
						]
					],
					[
						'url' => 'charts.php?groupid=&hostid=&graphid=',
						'text_not_present' => 'GRAPHS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.',
							'Field "hostid" is not integer.',
							'Field "graphid" is not integer.'
						]
					],
					[
						'url' => 'charts.php?groupid=-1&hostid=-1&graphid=-1',
						'text_not_present' => 'GRAPHS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.',
							'Incorrect value "-1" for "hostid" field.',
							'Incorrect value "-1" for "graphid" field.'
						]
					],
					[
						'url' => 'charts.php',
						'text_present' => 'GRAPHS'
					]
				]
			],
			[
				'title' => 'Custom screens \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'screens.php?elementid=16',
						'text_present' => 'SCREENS'
					],
					[
						'url' => 'screens.php?elementid=9999999',
						'text_not_present' => 'SCREENS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'screens.php?elementid=abc',
						'text_not_present' => 'SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "elementid" is not integer.'
						]
					],
					[
						'url' => 'screens.php?elementid=',
						'text_not_present' => 'SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "elementid" is not integer.'
						]
					],
					[
						'url' => 'screens.php?elementid=-1',
						'text_not_present' => 'SCREENS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "elementid" field.'
						]
					],
					[
						'url' => 'screens.php',
						'text_present' => 'SCREENS'
					]
				]
			],
			[
				'title' => 'Network maps \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'maps.php?sysmapid=1&severity_min=0',
						'text_present' => 'NETWORK MAPS'
					],
					[
						'url' => 'maps.php?sysmapid=9999999&severity_min=0',
						'text_not_present' => 'NETWORK MAPS',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'maps.php?sysmapid=1&severity_min=6',
						'text_not_present' => 'NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "6" for "severity_min" field.'
						]
					],
					[
						'url' => 'maps.php?sysmapid=1&severity_min=-1',
						'text_not_present' => 'NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "severity_min" field.'
						]
					],
					[
						'url' => 'maps.php?sysmapid=-1&severity_min=0',
						'text_not_present' => 'NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "sysmapid" field.'
						]
					],
					[
						'url' => 'maps.php?sysmapid=abc&severity_min=abc',
						'text_not_present' => 'NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.',
							'Field "severity_min" is not integer.'
						]
					],
					[
						'url' => 'maps.php?sysmapid=&severity_min=',
						'text_not_present' => 'NETWORK MAPS',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "sysmapid" is not integer.',
							'Field "severity_min" is not integer.'
						]
					],
					[
						'url' => 'maps.php?sysmapid=1&severity_min=0',
						'text_present' => 'NETWORK MAPS'
					]
				]
			],
			[
				'title' => 'Status of discovery',
				'test_cases' => [
					[
						'url' => 'discovery.php?druleid=3',
						'text_present' => 'STATUS OF DISCOVERY'
					],
					[
						'url' => 'discovery.php?druleid=9999999',
						'text_not_present' => 'STATUS OF DISCOVERY',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'discovery.php?druleid=abc',
						'text_not_present' => 'STATUS OF DISCOVERY',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "druleid" is not integer.'
						]
					],
					[
						'url' => 'discovery.php?druleid=',
						'text_not_present' => 'STATUS OF DISCOVERY',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "druleid" is not integer.'
						]
					],
					[
						'url' => 'discovery.php?druleid=-1',
						'text_not_present' => 'STATUS OF DISCOVERY',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "druleid" field.'
						]
					],
					[
						'url' => 'discovery.php',
						'text_present' => 'STATUS OF DISCOVERY'
					]
				]
			],
			[
				'title' => 'IT services \[refreshed every 30 sec.\]',
				'test_cases' => [
					[
						'url' => 'srv_status.php?period=today',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=week',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=month',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=year',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=24',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=168',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=720',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=8760',
						'text_present' => 'IT SERVICES'
					],
					[
						'url' => 'srv_status.php?period=1',
						'text_not_present' => 'IT SERVICES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "1" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php?period=abc',
						'text_not_present' => 'IT SERVICES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "abc" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php?period=',
						'text_not_present' => 'IT SERVICES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php?period=-1',
						'text_not_present' => 'IT SERVICES',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "period" field.'
						]
					],
					[
						'url' => 'srv_status.php',
						'text_present' => 'IT SERVICES'
					]
				]
			],
			[
				'title' => 'Host inventory overview',
				'test_cases' => [
					[
						'url' => 'hostinventoriesoverview.php?groupid=4&groupby=',
						'text_present' => 'HOST INVENTORY OVERVIEW'
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=4&groupby=alias',
						'text_present' => 'HOST INVENTORY OVERVIEW'
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=9999999&groupby=',
						'text_not_present' => 'HOST INVENTORY OVERVIEW',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=abc&groupby=',
						'text_not_present' => 'HOST INVENTORY OVERVIEW',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=&groupby=',
						'text_not_present' => 'HOST INVENTORY OVERVIEW',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php?groupid=-1&groupby=',
						'text_not_present' => 'HOST INVENTORY OVERVIEW',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'hostinventoriesoverview.php',
						'text_present' => 'HOST INVENTORY OVERVIEW'
					]
				]
			],
			[
				'title' => 'Host inventory',
				'test_cases' => [
					[
						'url' => 'hostinventories.php?groupid=4',
						'text_present' => 'HOST INVENTORY'
					],
					[
						'url' => 'hostinventories.php?groupid=9999999',
						'text_not_present' => 'HOST INVENTORY',
						'text_present' => [
							'ERROR: No permissions to referred object or it does not exist!'
						]
					],
					[
						'url' => 'hostinventories.php?groupid=abc',
						'text_not_present' => 'HOST INVENTORY',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventories.php?groupid=',
						'text_not_present' => 'HOST INVENTORY',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Field "groupid" is not integer.'
						]
					],
					[
						'url' => 'hostinventories.php?groupid=-1',
						'text_not_present' => 'HOST INVENTORY',
						'text_present' => [
							'ERROR: Zabbix has received an incorrect request.',
							'Incorrect value "-1" for "groupid" field.'
						]
					],
					[
						'url' => 'hostinventories.php',
						'text_present' => 'HOST INVENTORY'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider data
	 */
	public function testUrlParameters_UrlLoad($title, $test_cases) {
		$this->zbxTestLogin();

		foreach ($test_cases as $test_case) {
			$this->zbxTestOpenWait($test_case['url']);
			$this->zbxTestCheckTitle($title);
			$this->zbxTestTextPresent($test_case['text_present']);
			if (isset($test_case['text_not_present'])) {
				$this->zbxTestTextNotPresent($test_case['text_not_present']);
			}
		}
	}
}
