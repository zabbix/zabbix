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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * @onBefore removeGuestFromDisabledGroup, prepareUserData
 *
 * @backup users
 */
class testUrlUserPermissions extends CLegacyWebTest {
	public function prepareUserData() {
		CDataHelper::call('user.create', [
			[
				'username' => 'test-admin',
				'passwd' => 'zabbix12345',
				'roleid' => USER_TYPE_ZABBIX_ADMIN,
				'usrgrps' => [['usrgrpid' => 7]] // Zabbix administrators.
			]
		]);
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (1552, 9, 2)');
	}

	public static function data() {
		return [
			// #0 Monitoring.
			[[
				'url' => 'zabbix.php?action=dashboard.view',
				'title' =>	'Dashboard',
				'header' =>	'Global view',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #1.
			[[
				'url' => 'zabbix.php?action=problem.view',
				'title' =>	'Problems',
				'header' =>	'Problems',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #2.
			[[
				'url' => 'zabbix.php?action=web.view',
				'title' =>	'Web monitoring',
				'header' =>	'Web monitoring',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #3.
			[[
				'url' => 'httpdetails.php?httptestid=94',
				'title' =>	'Details of web scenario',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #4.
			[[
				'url' => 'zabbix.php?action=latest.view',
				'title' =>	'Latest data',
				'header' =>	'Latest data',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #5.
			[[
				'url' => 'history.php?action=showgraph&itemids[]=23296',
				'title' =>	'History [refreshed every 30 sec.]',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #6.
			[[
				'url' => 'zabbix.php?action=charts.view',
				'title' =>	'Custom graphs',
				'header' =>	'Graphs',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #7.
			[[
				'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D=10084&filter_show=1&filter_set=1',
				'title' =>	'Custom graphs',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #8.
			[[
				'url' => 'zabbix.php?action=map.view',
				'title' =>	'Configuration of network maps',
				'header' =>	'Maps',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #9.
			[[
				'url' => 'sysmaps.php',
				'title' =>	'Configuration of network maps',
				'header' =>	'Maps',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #10.
			[[
				'url' => 'zabbix.php?action=map.view&sysmapid=1',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #11.
			[[
				'url' => 'zabbix.php?action=discovery.view',
				'title' =>	'Status of discovery',
				'header' =>	'Status of discovery',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #12.
			[[
				'url' => 'zabbix.php?action=service.list',
				'title' =>	'Services',
				'header' =>	'Services',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #13 Inventory.
			[[
				'url' => 'hostinventoriesoverview.php',
				'title' =>	'Host inventory overview',
				'header' =>	'Host inventory overview',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #14.
			[[
				'url' => 'hostinventories.php',
				'title' =>	'Host inventory',
				'header' =>	'Host inventory',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #15 Reports.
			[[
				'url' => 'zabbix.php?action=report.status',
				'title' =>	'System information',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #16.
			[[
				'url' => 'zabbix.php?action=availabilityreport.list',
				'title' =>	'Availability report',
				'header' =>	'Availability report',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #17.
			[[
				'url' => 'zabbix.php?action=toptriggers.list',
				'title' =>	'Top 100 triggers',
				'header' =>	'Top 100 triggers',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #18.
			[[
				'url' => 'zabbix.php?action=auditlog.list',
				'title' =>	'Audit log',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #19.
			[[
				'url' => 'zabbix.php?action=actionlog.list',
				'title' =>	'Action log',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #20.
			[[
				'url' => 'report4.php',
				'title' =>	'Notification report',
				'header' =>	'Notifications',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #21 Configuration.
			[[
				'url' => 'zabbix.php?action=hostgroup.list',
				'title' => 'Configuration of host groups',
				'header' => 'Host groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #22.
			[[
				'url' => 'zabbix.php?action=popup&popup=hostgroup.edit&groupid=4',
				'title' => 'Configuration of host groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			/* #23. TODO: In this test we getting expected Access denied message, but test fails with
			 * Uncaught TypeError: Cannot read properties of null (reading 'addEventListener') error
			 * Need to fix
			[[
				'url' => 'zabbix.php?action=templategroup.list',
				'title' => 'Configuration of template groups',
				'header' => 'Template groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			 */
			// #23.
			[[
				'url' => 'zabbix.php?action=popup&popup=templategroup.edit&groupid=1',
				'title' => 'Configuration of template groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #24.
			[[
				'url' => 'zabbix.php?action=template.list',
				'title' =>	'Configuration of templates',
				'header' => 'Templates',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #25.
			[[
				'url' => self::HOST_LIST_PAGE,
				'title' =>	'Configuration of hosts',
				'header' => 'Hosts',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #26.
			[[
				'url' => 'zabbix.php?action=item.list&context=host',
				'title' =>	'Configuration of items',
				'header' => 'Items',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #27.
			[[
				'url' => 'zabbix.php?action=trigger.list&context=host',
				'title' =>	'Configuration of triggers',
				'header' => 'Triggers',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #28.
			[[
				'url' => 'graphs.php?context=host',
				'title' =>	'Configuration of graphs',
				'header' => 'Graphs',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #29.
			[[
				'url' => 'host_discovery.php?context=host&hostid=10084',
				'title' =>	'Configuration of discovery rules',
				'no_permissions_to_object' => true,
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #30.
			[[
				'url' => 'httpconf.php?context=host',
				'title' =>	'Configuration of web monitoring',
				'header' => 'Web monitoring',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #31.
			[[
				'url' => 'zabbix.php?action=maintenance.list',
				'title' =>	'Configuration of maintenance periods',
				'header' => 'Maintenance periods',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #32.
			[[
				'url' => 'zabbix.php?action=action.list&eventsource=0',
				'title' =>	'Configuration of actions',
				'header' => 'Trigger actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #33.
			[[
				'url' => 'zabbix.php?action=action.list&eventsource=1',
				'title' =>	'Configuration of actions',
				'header' => 'Discovery actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #34.
			[[
				'url' => 'zabbix.php?action=action.list&eventsource=2',
				'title' =>	'Configuration of actions',
				'header' => 'Autoregistration actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #35.
			[[
				'url' => 'zabbix.php?action=action.list&eventsource=3',
				'title' =>	'Configuration of actions',
				'header' => 'Internal actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #36.
			[[
				'url' => 'zabbix.php?action=action.list&eventsource=4',
				'title' =>	'Configuration of actions',
				'header' => 'Service actions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #37.
			[[
				'url' => 'zabbix.php?action=correlation.list',
				'title' =>	'Event correlation rules',
				'header' => 'Event correlation',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #38.
			[[
				'url' => 'zabbix.php?action=discovery.list',
				'title' =>	'Configuration of discovery rules',
				'header' => 'Discovery rules',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #39.
			[[
				'url' => 'zabbix.php?action=service.list.edit',
				'title' =>	'Services',
				'header' => 'Services',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => true
				]
			]],
			// #40 Administration.
			[[
				'url' => 'zabbix.php?action=gui.edit',
				'title' =>	'Configuration of GUI',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #41.
			[[
				'url' => 'zabbix.php?action=housekeeping.edit',
				'title' =>	'Configuration of housekeeping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #42.
			[[
				'url' => 'zabbix.php?action=image.list',
				'title' =>	'Configuration of images',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #43.
			[[
				'url' => 'zabbix.php?action=iconmap.list',
				'title' =>	'Configuration of icon mapping',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #44.
			[[
				'url' => 'zabbix.php?action=regex.list',
				'title' =>	'Configuration of regular expressions',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #45.
			[[
				'url' => 'zabbix.php?action=macros.edit',
				'title' =>	'Configuration of macros',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #46.
			[[
				'url' => 'zabbix.php?action=trigdisplay.edit',
				'title' =>	'Configuration of trigger displaying options',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #47.
			[[
				'url' => 'zabbix.php?action=miscconfig.edit',
				'title' =>	'Other configuration parameters',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #48.
			[[
				'url' => 'zabbix.php?action=proxy.list',
				'title' =>	'Configuration of proxies',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #49.
			[[
				'url' => 'zabbix.php?action=authentication.edit',
				'title' =>	'Authentication',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #50.
			[[
				'url' => 'zabbix.php?action=usergroup.list',
				'title' =>	'Configuration of user groups',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #51.
			[[
				'url' => 'zabbix.php?action=user.list',
				'title' =>	'Configuration of users',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #52.
			[[
				'url' => 'zabbix.php?action=mediatype.list',
				'title' =>	'Configuration of media types',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #53.
			[[
				'url' => 'zabbix.php?action=script.list',
				'title' =>	'Configuration of scripts',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #54.
			[[
				'url' => 'zabbix.php?action=queue.overview',
				'title' =>	'Queue [refreshed every 30 sec.]',
				'users' => [
					'guest' => false,
					'user-zabbix' => false,
					'test-admin' => false
				]
			]],
			// #55 Misc.
			[[
				'url' => 'zabbix.php?action=search&search=server',
				'title' =>	'Search',
				'header' => 'Search: server',
				'users' => [
					'guest' => true,
					'user-zabbix' => true,
					'test-admin' => true
				]
			]],
			// #56.
			[[
				'url' => 'zabbix.php?action=userprofile.edit',
				'title' =>	'User profile',
				'header' => 'User profile: ',
				'users' => [
					'guest' => false,
					'user-zabbix' => true,
					'test-admin' => true
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
				case 'test-admin':
					$this->page->userLogin('test-admin', 'zabbix12345');
					break;
				case 'user-zabbix':
					$this->page->userLogin('user-zabbix', 'zabbix');
					break;
			}
			if ($user && !array_key_exists('no_permissions_to_object', $data)) {
				$this->zbxTestOpen($data['url']);

				if ($alias === 'guest') {
					$this->guestLogin();
				}

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

				if ($alias === 'guest') {
					$this->guestLogin();
				}

				$this->zbxTestCheckTitle($data['title']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'No permissions to referred object or it does not exist!');
			}
			else {
				$this->zbxTestOpen($data['url']);

				if ($alias === 'guest') {
					$this->guestLogin();
				}

				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Access denied');
				$this->zbxTestAssertElementText("//ul/li[1]", 'You are logged in as "'.$alias.'". You have no permissions to access this page.');
				$this->zbxTestAssertElementText("//ul/li[2]", 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.');
			}

			$this->page->logout();
		}
	}

	/**
	 * @onBeforeOnce addGuestToDisabledGroup
	 *
	 * @dataProvider data
	 */
	public function testUrlUserPermissions_DisabledGuest($data) {
		$this->zbxTestOpen($data['url']);
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'You are not logged in');
		$this->zbxTestAssertElementText("//ul/li[1]", 'You must login to view this page.');
		$this->zbxTestAssertElementText("//ul/li[2]", 'Possibly the session has expired or the password was changed.');
		$this->zbxTestAssertElementText("//ul/li[3]", 'If you think this message is wrong, please consult your administrators about getting the necessary permissions.');
	}

	/**
	 * Login as guest user.
	 */
	protected function guestLogin() {
		$this->query('button:Login')->one()->click();
		$this->page->waitUntilReady();
		$this->query('link:sign in as guest')->one()->click();
		$this->page->waitUntilReady();
	}
}
