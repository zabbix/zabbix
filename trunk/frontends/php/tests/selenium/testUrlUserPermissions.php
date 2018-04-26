<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class testUrlUserPermissions extends CWebTest {

	public static function data() {
		return [
			// Monitoring
			[[
				'url' => 'zabbix.php?action=dashboard.view',
				'title' =>	'Dashboard',
				'header' =>	'Dashboard',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=problem.view',
				'title' =>	'Problems',
				'header' =>	'Problems',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'overview.php',
				'title' =>	'Overview [refreshed every 30 sec.]',
				'header' =>	'Overview',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'overview.php?form_refresh=1&groupid=0&type=0&view_style=0',
				'title' =>	'Overview [refreshed every 30 sec.]',
				'header' =>	'Overview',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=web.view',
				'title' =>	'Web monitoring',
				'header' =>	'Web monitoring',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'httpdetails.php?httptestid=94',
				'title' =>	'Details of web scenario',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'latest.php',
				'title' =>	'Latest data [refreshed every 30 sec.]',
				'header' =>	'Latest data',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'history.php?action=showgraph&itemids[]=23296',
				'title' =>	'History [refreshed every 30 sec.]',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'charts.php',
				'title' =>	'Custom graphs [refreshed every 30 sec.]',
				'header' =>	'Graphs',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'charts.php?fullscreen=0&groupid=0&hostid=0&graphid=523',
				'title' =>	'Custom graphs [refreshed every 30 sec.]',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'screens.php',
				'title' =>	'Configuration of screens',
				'header' =>	'Screens',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'screenconf.php',
				'title' =>	'Configuration of screens',
				'header' =>	'Screens',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'screens.php?elementid=200001',
				'title' =>	'Custom screens [refreshed every 30 sec.]',
				'header' =>	'Screens',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'screens.php?elementid=200009',
				'title' =>	'Custom screens [refreshed every 30 sec.]',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'slideconf.php',
				'title' =>	'Configuration of slide shows',
				'header' =>	'Slide shows',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=map.view',
				'title' =>	'Configuration of network maps',
				'header' =>	'Maps',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'sysmaps.php',
				'title' =>	'Configuration of network maps',
				'header' =>	'Maps',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=map.view&sysmapid=1',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=discovery.view',
				'title' =>	'Status of discovery',
				'header' =>	'Status of discovery',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'srv_status.php',
				'title' =>	'Services [refreshed every 30 sec.]',
				'header' =>	'Services',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			// Inventory
			[[
				'url' => 'hostinventoriesoverview.php',
				'title' =>	'Host inventory overview',
				'header' =>	'Host inventory overview',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'hostinventories.php',
				'title' =>	'Host inventory',
				'header' =>	'Host inventory',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			// Reports
			[[
				'url' => 'zabbix.php?action=report.status',
				'title' =>	'System information',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'report2.php',
				'title' =>	'Availability report',
				'header' =>	'Availability report',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'toptriggers.php',
				'title' =>	'100 busiest triggers',
				'header' =>	'100 busiest triggers',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'auditlogs.php',
				'title' =>	'Audit log',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'auditacts.php',
				'title' =>	'Action log',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'report4.php',
				'title' =>	'Notification report',
				'header' =>	'Notifications',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			// Configuration
			[[
				'url' => 'hostgroups.php',
				'title' =>	'Configuration of host groups',
				'header' => 'Host groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'hostgroups.php?form=update&groupid=1',
				'title' =>	'Configuration of host groups',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'hostgroups.php?form=Create+host+group',
				'title' =>	'Configuration of host groups',
				'header' => 'Host groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'templates.php',
				'title' =>	'Configuration of templates',
				'header' => 'Templates',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'templates.php?form=update&templateid=10093',
				'title' =>	'Configuration of templates',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'hosts.php',
				'title' =>	'Configuration of hosts',
				'header' => 'Hosts',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'hosts.php?groupid=0&form=Create+host',
				'title' =>	'Configuration of hosts',
				'header' => 'Hosts',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'applications.php',
				'title' =>	'Configuration of applications',
				'header' => 'Applications',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'items.php',
				'title' =>	'Configuration of items',
				'header' => 'Items',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'triggers.php',
				'title' =>	'Configuration of triggers',
				'header' => 'Triggers',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'graphs.php',
				'title' =>	'Configuration of graphs',
				'header' => 'Graphs',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'host_discovery.php?hostid=10084',
				'title' =>	'Configuration of discovery rules',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'httpconf.php',
				'title' =>	'Configuration of web monitoring',
				'header' => 'Web monitoring',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'maintenance.php',
				'title' =>	'Configuration of maintenance periods',
				'header' => 'Maintenance periods',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php',
				'title' =>	'Configuration of actions',
				'header' => 'Actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=0',
				'title' =>	'Configuration of actions',
				'header' => 'Actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=1',
				'title' =>	'Configuration of actions',
				'header' => 'Actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=2',
				'title' =>	'Configuration of actions',
				'header' => 'Actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=3',
				'title' =>	'Configuration of actions',
				'header' => 'Actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'correlation.php',
				'title' =>	'Event correlation rules',
				'header' => 'Event correlation',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'correlation.php?form=Create+correlation',
				'title' =>	'Event correlation rules',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'discoveryconf.php',
				'title' =>	'Configuration of discovery rules',
				'header' => 'Discovery rules',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'services.php',
				'title' =>	'Configuration of services',
				'header' => 'Services',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			// Administration
			[[
				'url' => 'adm.gui.php',
				'title' =>	'Configuration of GUI',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.housekeeper.php',
				'title' =>	'Configuration of housekeeping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.images.php',
				'title' =>	'Configuration of images',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.iconmapping.php',
				'title' =>	'Configuration of icon mapping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.regexps.php',
				'title' =>	'Configuration of regular expressions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.macros.php',
				'title' =>	'Configuration of macros',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.valuemapping.php',
				'title' =>	'Configuration of value mapping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.workingtime.php',
				'title' =>	'Configuration of working time',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.triggerseverities.php',
				'title' =>	'Configuration of trigger severities',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.triggerdisplayoptions.php',
				'title' =>	'Configuration of trigger displaying options',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'adm.other.php',
				'title' =>	'Other configuration parameters',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=proxy.list',
				'title' =>	'Configuration of proxies',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'authentication.php',
				'title' =>	'Configuration of authentication',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'usergrps.php',
				'title' =>	'Configuration of user groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'users.php',
				'title' =>	'Configuration of users',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=mediatype.list',
				'title' =>	'Configuration of media types',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=script.list',
				'title' =>	'Configuration of scripts',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'queue.php',
				'title' =>	'Queue [refreshed every 30 sec.]',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			// Misc
			[[
				'url' => 'search.php?search=server',
				'title' =>	'Search',
				'header' => 'Search: server',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'profile.php',
				'title' =>	'User profile',
				'header' => 'User profile: ',
				'users' => [
					'guest' => false,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'conf.import.php',
				'title' =>	'Configuration import',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => false,
					'user-zabbix' => false
				]
			]]
		];
	}

	/**
	 * @dataProvider data
	 */
	public function testUrlUserPermissions_Users($data) {
		foreach ($data['users'] as $alias => $user) {
			switch ($alias) {
				case 'admin-zabbix' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55c' , 4);
					break;
				case 'user-zabbix' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55d' , 5);
					break;
			}
			if ($user && !array_key_exists('no_permissions_to_object', $data)) {
				$this->zbxTestOpen($data['url']);
				$this->zbxTestCheckTitle($data['title']);
				if ($data['url'] == 'profile.php') {
					$this->zbxTestCheckHeader($data['header'].$alias);
				}
				else {
					$this->zbxTestCheckHeader($data['header']);
				}
				$this->zbxTestCheckFatalErrors();
				$this->webDriver->manage()->deleteAllcookies();
			}
			elseif ($user && array_key_exists('no_permissions_to_object', $data) ) {
				$this->zbxTestOpen($data['url']);
				$this->zbxTestCheckTitle($data['title']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'No permissions to referred object or it does not exist!');
				$this->zbxTestCheckFatalErrors();
				$this->webDriver->manage()->deleteAllcookies();
			}
			else {
				$this->zbxTestOpen($data['url']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Access denied');
				$this->zbxTestAssertElementText("//ul/li[1]", 'You are logged in as "'.$alias.'". You have no permissions to access this page.');
				$this->zbxTestAssertElementText("//ul/li[2]", 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.');
				$this->zbxTestCheckFatalErrors();
				$this->webDriver->manage()->deleteAllcookies();
			}
		}
	}

	public function testUrlUserPermissions_DisableGuest() {
		DBexecute("INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)");
	}

	/**
	 * @dataProvider data
	 */
	public function testUrlUserPermissions_DisabledGuest($data) {
		$this->zbxTestOpen($data['url']);
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'You are not logged in');
		$this->zbxTestAssertElementText("//ul/li[1]", 'You must login to view this page.');
		$this->zbxTestAssertElementText("//ul/li[2]", 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.');
	}

	public function testUrlUserPermissions_EnableGuest() {
		DBexecute("DELETE FROM users_groups WHERE id=150");
	}
}
