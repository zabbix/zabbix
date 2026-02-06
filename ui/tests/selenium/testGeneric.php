<?php
/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../include/CWebTest.php';

/**
 * @backup !profiles
 */
class testGeneric extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getPagesData() {
		return [
			// Search.
			[
				[
					'url' => 'zabbix.php?action=search&search=server',
					'title' => 'Search',
					'header' => 'Search: server'
				]
			],
			// Monitoring.
			[
				[
					//TODO: testGeneric#1 fails, if it runs alone due to another dashboard being opened, would be fixed in DEV-4728
					'url' => 'zabbix.php?action=dashboard.view',
					'title' => 'Dashboard',
					'header' => 'Global view'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=dashboard.list',
					'title' => 'Dashboards',
					'header' => 'Dashboards'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=problem.view',
					'title' => 'Problems',
					'header' => 'Problems'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=host.view',
					'title' => 'Hosts',
					'header' => 'Hosts'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=charts.view',
					'title' => 'Custom graphs',
					'header' => 'Graphs'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=web.view',
					'title' => 'Web monitoring',
					'header' => 'Web monitoring'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=latest.view',
					'title' => 'Latest data',
					'header' => 'Latest data'
				]
			],
			[
				[
					'url' => 'sysmaps.php',
					'title' => 'Configuration of network maps',
					'header' => 'Maps'
				]
			],
			[
				[
					'url' => 'sysmaps.php?form=Create+map',
					'title' => 'Configuration of network maps',
					'header' => 'Network maps'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=discovery.view',
					'title' => 'Status of discovery',
					'header' => 'Status of discovery'
				]
			],
			// Services.
			[
				[
					'url' => 'zabbix.php?action=service.list',
					'title' => 'Services',
					'header' => 'Services'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=service.list.edit',
					'title' => 'Services',
					'header' => 'Services'
				]
			],
			[
				[
					'url' => 'actionconf.php?eventsource=4',
					'title' => 'Configuration of actions',
					'header' => 'Service actions'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=sla.list',
					'title' => 'SLA',
					'header' => 'SLA'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=slareport.list',
					'title' => 'SLA report',
					'header' => 'SLA report'
				]
			],
			// Inventory.
			[
				[
					'url' => 'hostinventoriesoverview.php',
					'title' => 'Host inventory overview',
					'header' => 'Host inventory overview'
				]
			],
			[
				[
					'url' => 'hostinventories.php',
					'title' => 'Host inventory',
					'header' => 'Host inventory'
				]
			],
			// Reports.
			[
				[
					'url' => 'zabbix.php?action=report.status',
					'title' => 'System information',
					'header' => 'System information'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=scheduledreport.list',
					'title' => 'Scheduled reports',
					'header' => 'Scheduled reports'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=scheduledreport.edit',
					'title' => 'Scheduled reports',
					'header' => 'Scheduled reports'
				]
			],
			[
				[
					'url' => 'report2.php',
					'title' => 'Availability report',
					'header' => 'Availability report'
				]
			],
			[
				[
					'url' => 'report2.php?mode=0',
					'title' => 'Availability report',
					'header' => 'Availability report'
				]
			],
			[
				[
					'url' => 'report2.php?mode=1',
					'title' => 'Availability report',
					'header' => 'Availability report'
				]
			],
			[
				[
					'url' => 'toptriggers.php',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'toptriggers.php?severities[0]=0&filter_set=1',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'toptriggers.php?severities[1]=1&filter_set=1',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'toptriggers.php?severities[2]=2&filter_set=1',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'toptriggers.php?severities[3]=3&filter_set=1',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'toptriggers.php?severities[4]=4&filter_set=1',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'toptriggers.php?severities[5]=5&filter_set=1',
					'title' => '100 busiest triggers',
					'header' => '100 busiest triggers'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=auditlog.list',
					'title' => 'Audit log',
					'header' => 'Audit log'
				]
			],
			[
				[
					'url' => 'auditacts.php',
					'title' => 'Action log',
					'header' => 'Action log'
				]
			],
			[
				[
					'url' => 'report4.php',
					'title' => 'Notification report',
					'header' => 'Notifications'
				]
			],
			[
				[
					'url' => 'report4.php?period=daily',
					'title' => 'Notification report',
					'header' => 'Notifications'
				]
			],
			[
				[
					'url' => 'report4.php?period=weekly',
					'title' => 'Notification report',
					'header' => 'Notifications'
				]
			],
			[
				[
					'url' => 'report4.php?period=monthly',
					'title' => 'Notification report',
					'header' => 'Notifications'
				]
			],
			[
				[
					'url' => 'report4.php?period=yearly',
					'title' => 'Notification report',
					'header' => 'Notifications'
				]
			],
			// Configuration.
			[
				[
					'url' => 'hostgroups.php',
					'title' => 'Configuration of host groups',
					'header' => 'Host groups'
				]
			],
			[
				[
					'url' => 'templates.php',
					'title' => 'Configuration of templates',
					'header' => 'Templates'
				]
			],
			[
				[
					'url' => 'items.php?context=template',
					'title' => 'Configuration of items',
					'header' => 'Items'
				]
			],
			[
				[
					'url' => 'triggers.php?context=template',
					'title' => 'Configuration of triggers',
					'header' => 'Triggers'
				]
			],
			[
				[
					'url' => 'graphs.php?context=template',
					'title' => 'Configuration of graphs',
					'header' => 'Graphs'
				]
			],
			[
				[
					'url' => 'host_discovery.php?context=template',
					'title' => 'Configuration of discovery rules',
					'header' => 'Discovery rules'
				]
			],
			[
				[
					'url' => 'httpconf.php?context=template',
					'title' => 'Configuration of web monitoring',
					'header' => 'Web monitoring'
				]
			],
			[
				[
					'url' => self::HOST_LIST_PAGE,
					'title' => 'Configuration of hosts',
					'header' => 'Hosts'
				]
			],
			[
				[
					'url' => 'items.php?context=host',
					'title' => 'Configuration of items',
					'header' => 'Items'
				]
			],
			[
				[
					'url' => 'triggers.php?context=host',
					'title' => 'Configuration of triggers',
					'header' => 'Triggers'
				]
			],
			[
				[
					'url' => 'graphs.php?context=host',
					'title' => 'Configuration of graphs',
					'header' => 'Graphs'
				]
			],
			[
				[
					'url' => 'host_discovery.php?context=host',
					'title' => 'Configuration of discovery rules',
					'header' => 'Discovery rules'
				]
			],
			[
				[
					'url' => 'httpconf.php?context=host',
					'title' => 'Configuration of web monitoring',
					'header' => 'Web monitoring'
				]
			],
			[
				[
					'url' => 'maintenance.php',
					'title' => 'Configuration of maintenance periods',
					'header' => 'Maintenance periods'
				]
			],
			[
				[
					'url' => 'actionconf.php?eventsource=0',
					'title' => 'Configuration of actions',
					'header' => 'Trigger actions'
				]
			],
			[
				[
					'url' => 'actionconf.php?eventsource=1',
					'title' => 'Configuration of actions',
					'header' => 'Discovery actions'
				]
			],
			[
				[
					'url' => 'actionconf.php?eventsource=2',
					'title' => 'Configuration of actions',
					'header' => 'Autoregistration actions'
				]
			],
			[
				[
					'url' => 'actionconf.php?eventsource=3',
					'title' => 'Configuration of actions',
					'header' => 'Internal actions'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=correlation.list',
					'title' => 'Event correlation rules',
					'header' => 'Event correlation'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=discovery.list',
					'title' => 'Configuration of discovery rules',
					'header' => 'Discovery rules'
				]
			],
			// Administration.
			[
				[
					'url' => 'zabbix.php?action=gui.edit',
					'title' => 'Configuration of GUI',
					'header' => 'GUI'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=autoreg.edit',
					'title' => 'Autoregistration',
					'header' => 'Autoregistration'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=housekeeping.edit',
					'title' => 'Configuration of housekeeping',
					'header' => 'Housekeeping'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=audit.settings.edit',
					'title' => 'Configuration of audit log',
					'header' => 'Audit log'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=image.list',
					'title' => 'Configuration of images',
					'header' => 'Images'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=image.list&imagetype=1',
					'title' => 'Configuration of images',
					'header' => 'Images'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=image.edit&imagetype=2',
					'title' => 'Configuration of images',
					'header' => 'Images'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=iconmap.list',
					'title' => 'Configuration of icon mapping',
					'header' => 'Icon mapping'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=iconmap.edit',
					'title' => 'Configuration of icon mapping',
					'header' => 'Icon mapping'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=regex.list',
					'title' => 'Configuration of regular expressions',
					'header' => 'Regular expressions'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=regex.edit',
					'title' => 'Configuration of regular expressions',
					'header' => 'Regular expressions'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=macros.edit',
					'title' => 'Configuration of macros',
					'header' => 'Macros'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=trigdisplay.edit',
					'title' => 'Configuration of trigger displaying options',
					'header' => 'Trigger displaying options'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=geomaps.edit',
					'title' => 'Geographical maps',
					'header' => 'Geographical maps'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=module.list',
					'title' => 'Modules',
					'header' => 'Modules'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=token.list',
					'title' => 'API tokens',
					'header' => 'API tokens'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=miscconfig.edit',
					'title' => 'Other configuration parameters',
					'header' => 'Other configuration parameters'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=proxy.list',
					'title' => 'Configuration of proxies',
					'header' => 'Proxies'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=authentication.edit',
					'title' => 'Configuration of authentication',
					'header' => 'Authentication'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=usergroup.list',
					'title' => 'Configuration of user groups',
					'header' => 'User groups'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=usergroup.edit',
					'title' => 'Configuration of user groups',
					'header' => 'User groups'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=userrole.list',
					'title' => 'Configuration of user roles',
					'header' => 'User roles'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=userrole.edit',
					'title' => 'Configuration of user roles',
					'header' => 'User roles'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=0',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=9',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=11',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=8',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=12',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=13',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=90',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=91',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=15',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=14',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=92',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.list&filter_usrgrpid=7',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.edit',
					'title' => 'Configuration of users',
					'header' => 'Users'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=mediatype.list',
					'title' => 'Configuration of media types',
					'header' => 'Media types'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=mediatype.edit',
					'title' => 'Configuration of media types',
					'header' => 'Media types'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=script.list',
					'title' => 'Configuration of scripts',
					'header' => 'Scripts'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=script.edit',
					'title' => 'Configuration of scripts',
					'header' => 'Scripts'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=queue.overview',
					'title' => 'Queue [refreshed every 30 sec.]',
					'header' => 'Queue overview',
					'error' => true
				]
			],
			[
				[
					'url' => 'zabbix.php?action=queue.overview.proxy',
					'title' => 'Queue [refreshed every 30 sec.]',
					'header' => 'Queue overview by proxy',
					'error' => true
				]
			],
			[
				[
					'url' => 'zabbix.php?action=queue.details',
					'title' => 'Queue [refreshed every 30 sec.]',
					'header' => 'Queue details',
					'error' => true
				]
			],
			// User settings.
			[
				[
					'url' => 'zabbix.php?action=userprofile.edit',
					'title' => 'User profile',
					'header' => 'User profile: Zabbix Administrator'
				]
			],
			[
				[
					'url' => 'zabbix.php?action=user.token.list',
					'title' => 'API tokens',
					'header' => 'API tokens'
				]
			]
		];
	}

	/**
	 * @dataProvider getPagesData
	 */
	public function testGeneric_Pages($data) {
		$this->page->login()->open($data['url'])->waitUntilReady();
		$this->page->assertTitle($data['title']);
		$this->page->assertHeader($data['header']);

		if (CTestArrayHelper::get($data, 'error', false)) {
			// Error message is expected for 'Queue' pages as case is checked without running server.

			$this->assertMessage(TEST_BAD, 'Cannot display item queue.');
		}
		else {
			$this->assertFalse($this->query('class:msg-bad')->one(false)->isValid(), 'Unexpected error on page.');
		}

		// Verify that user menu contains default sections.
		$menu_user = ['Support', 'Integrations', 'Help', 'User settings', 'Sign out'];
		foreach ($menu_user as $text) {
			$this->assertTrue($this->query('link', $text)->exists());
		}
	}
}
