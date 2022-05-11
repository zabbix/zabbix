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

/**
 * @onBefore removeGuestFromDisabledGroup
 * @onAfter addGuestToDisabledGroup
 */
class testUrlUserPermissions extends CLegacyWebTest {

	public static function data() {
		return [
			// Monitoring
			[[
				'url' => 'zabbix.php?action=dashboard.view',
				'title' =>	'Dashboard',
				'header' =>	'Global view',
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
				'url' => 'zabbix.php?action=latest.view',
				'title' =>	'Latest data',
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
				'url' => 'zabbix.php?action=charts.view',
				'title' =>	'Custom graphs',
				'header' =>	'Graphs',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D=10084&filter_show=1&filter_set=1',
				'title' =>	'Custom graphs',
				'no_permissions_to_object' => true,
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
				'url' => 'zabbix.php?action=service.list',
				'title' =>	'Services',
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
				'url' => 'zabbix.php?action=auditlog.list',
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
				'url' => self::HOST_LIST_PAGE,
				'title' =>	'Configuration of hosts',
				'header' => 'Hosts',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=host.edit',
				'title' =>	'Configuration of host',
				'header' => 'New host',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'items.php?context=host',
				'title' =>	'Configuration of items',
				'header' => 'Items',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'triggers.php?context=host',
				'title' =>	'Configuration of triggers',
				'header' => 'Triggers',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'graphs.php?context=host',
				'title' =>	'Configuration of graphs',
				'header' => 'Graphs',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'host_discovery.php?context=host&hostid=10084',
				'title' =>	'Configuration of discovery rules',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'httpconf.php?context=host',
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
				'header' => 'Trigger actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=0',
				'title' =>	'Configuration of actions',
				'header' => 'Trigger actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=1',
				'title' =>	'Configuration of actions',
				'header' => 'Discovery actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=2',
				'title' =>	'Configuration of actions',
				'header' => 'Autoregistration actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'actionconf.php?eventsource=3',
				'title' =>	'Configuration of actions',
				'header' => 'Internal actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=correlation.list',
				'title' =>	'Event correlation rules',
				'header' => 'Event correlation',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=correlation.edit',
				'title' =>	'Event correlation rules',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=discovery.list',
				'title' =>	'Configuration of discovery rules',
				'header' => 'Discovery rules',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=service.list.edit',
				'title' =>	'Services',
				'header' => 'Services',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => true
				]
			]],
			// Administration
			[[
				'url' => 'zabbix.php?action=gui.edit',
				'title' =>	'Configuration of GUI',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=housekeeping.edit',
				'title' =>	'Configuration of housekeeping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=image.list',
				'title' =>	'Configuration of images',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=iconmap.list',
				'title' =>	'Configuration of icon mapping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=regex.list',
				'title' =>	'Configuration of regular expressions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=macros.edit',
				'title' =>	'Configuration of macros',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=trigdisplay.edit',
				'title' =>	'Configuration of trigger displaying options',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=miscconfig.edit',
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
				'url' => 'zabbix.php?action=authentication.edit',
				'title' =>	'Authentication',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=usergroup.list',
				'title' =>	'Configuration of user groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			[[
				'url' => 'zabbix.php?action=user.list',
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
				'url' => 'zabbix.php?action=queue.overview',
				'title' =>	'Queue [refreshed every 30 sec.]',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'admin-zabbix' => false
				]
			]],
			// Misc
			[[
				'url' => 'zabbix.php?action=search&search=server',
				'title' =>	'Search',
				'header' => 'Search: server',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'admin-zabbix' => true
				]
			]],
			[[
				'url' => 'zabbix.php?action=userprofile.edit',
				'title' =>	'User profile',
				'header' => 'User profile: ',
				'users' => [
					'guest' => false,
					'user-zabbix' => true,
					'admin-zabbix' => true
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
				if ($data['url'] === 'zabbix.php?action=userprofile.edit') {
					$this->zbxTestCheckHeader($data['header'].$alias);
				}
				else {
					$this->zbxTestCheckHeader($data['header']);
				}
			}
			elseif ($user && array_key_exists('no_permissions_to_object', $data) ) {
				$this->zbxTestOpen($data['url']);
				$this->zbxTestCheckTitle($data['title']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'No permissions to referred object or it does not exist!');
			}
			else {
				$this->zbxTestOpen($data['url']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Access denied');
				$this->zbxTestAssertElementText("//ul/li[1]", 'You are logged in as "'.$alias.'". You have no permissions to access this page.');
				$this->zbxTestAssertElementText("//ul/li[2]", 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.');
			}

			$this->webDriver->manage()->deleteAllCookies();
		}
	}

	/**
	 * @onBefore addGuestToDisabledGroup
	 * @onAfter removeGuestFromDisabledGroup
	 *
	 * @dataProvider data
	 */
	public function testUrlUserPermissions_DisabledGuest($data) {
		$this->zbxTestOpen($data['url']);
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'You are not logged in');
		$this->zbxTestAssertElementText("//ul/li[1]", 'You must login to view this page.');
		$this->zbxTestAssertElementText("//ul/li[2]", 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.');
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public static function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)');
	}
}
