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

class testGeneric extends CWebTest {

	public static function provider() {
		return [
			// monitoring
			['zabbix.php?action=dashboard.view',					'Dashboard'],

			['dashconf.php',					'Dashboard configuration'],

			['overview.php',												'Overview [refreshed every 30 sec.]'],
			['overview.php?form_refresh=1&groupid=0&type=0&view_style=0',	'Overview [refreshed every 30 sec.]'],

			['overview.php?form_refresh=1&groupid=0&type=0&view_style=1',	'Overview [refreshed every 30 sec.]'],
			['overview.php?form_refresh=1&groupid=0&type=1&view_style=0',	'Overview [refreshed every 30 sec.]'],
			['overview.php?form_refresh=1&groupid=0&type=1&view_style=1',	'Overview [refreshed every 30 sec.]'],

			['zabbix.php?action=web.view',					'Web monitoring'],
			['latest.php',						'Latest data [refreshed every 30 sec.]'],
			['tr_status.php',					'Status of triggers [refreshed every 30 sec.]'],

			['events.php',						'Latest events [refreshed every 30 sec.]'],
			['events.php?source=0',			'Latest events [refreshed every 30 sec.]'],
			['events.php?source=1',			'Latest events [refreshed every 30 sec.]'],

			['charts.php',						'Custom graphs [refreshed every 30 sec.]'],
			['screens.php',					'Configuration of screens'],
			['slides.php',						'Configuration of slide shows'],
			['zabbix.php?action=map.view',							'Configuration of network maps'],
			['zabbix.php?action=discovery.view',					'Status of discovery'],
			['srv_status.php',					'IT services [refreshed every 30 sec.]'],

			// inventory
			['hostinventoriesoverview.php',	'Host inventory overview'],
			['hostinventories.php',			'Host inventory'],

			// reports
			['zabbix.php?action=report.status',					'Status of Zabbix'],
			['report2.php',										'Availability report'],
			['toptriggers.php',									'100 busiest triggers'],
			['toptriggers.php?severities[0]=0&filter_set=Filter',	'100 busiest triggers'],
			['toptriggers.php?severities[1]=1&filter_set=Filter',	'100 busiest triggers'],
			['toptriggers.php?severities[2]=2&filter_set=Filter',	'100 busiest triggers'],
			['toptriggers.php?severities[3]=3&filter_set=Filter',	'100 busiest triggers'],
			['toptriggers.php?severities[4]=4&filter_set=Filter',	'100 busiest triggers'],
			['toptriggers.php?severities[5]=5&filter_set=Filter',	'100 busiest triggers'],

			// configuration
			['hostgroups.php',					'Configuration of host groups'],
			['templates.php',					'Configuration of templates'],
			['hosts.php',						'Configuration of hosts'],
			['maintenance.php',				'Configuration of maintenance periods'],
			['httpconf.php',					'Configuration of web monitoring'],

			['actionconf.php',					'Configuration of actions'],
			['actionconf.php?eventsource=0',	'Configuration of actions'],
			['actionconf.php?eventsource=1',	'Configuration of actions'],
			['actionconf.php?eventsource=2',	'Configuration of actions'],
			['actionconf.php?eventsource=3',	'Configuration of actions'],

			['screenconf.php',					'Configuration of screens'],
			['slideconf.php',					'Configuration of slide shows'],
			['sysmaps.php',					'Configuration of network maps'],
			['discoveryconf.php',				'Configuration of discovery rules'],
			['services.php',					'Configuration of IT services'],

			// Administration
			['adm.gui.php',					'Configuration of GUI'],
			['adm.housekeeper.php',			'Configuration of housekeeping'],
			['adm.images.php',					'Configuration of images'],
			['adm.iconmapping.php',			'Configuration of icon mapping'],
			['adm.regexps.php',				'Configuration of regular expressions'],
			['adm.macros.php',					'Configuration of macros'],
			['adm.valuemapping.php',			'Configuration of value mapping'],
			['adm.workingtime.php',			'Configuration of working time'],
			['adm.triggerseverities.php',		'Configuration of trigger severities'],
			['adm.triggerdisplayoptions.php',	'Configuration of trigger displaying options'],
			['adm.other.php',					'Other configuration parameters'],

			['zabbix.php?action=proxy.list',						'Configuration of proxies'],
			['authentication.php',				'Configuration of authentication'],
			['usergrps.php',					'Configuration of user groups'],
			['users.php',						'Configuration of users'],
			['zabbix.php?action=mediatype.list',					'Configuration of media types'],
			['zabbix.php?action=script.list',						'Configuration of scripts'],
			['auditlogs.php',					'Audit log'],
			['auditacts.php',					'Action log'],

			['queue.php',						'Queue [refreshed every 30 sec.]'],
			['queue.php?config=0',				'Queue [refreshed every 30 sec.]'],
			['queue.php?config=1',				'Queue [refreshed every 30 sec.]'],
			['queue.php?config=2',				'Queue [refreshed every 30 sec.]'],

			['report4.php',					'Notification report'],
			['report4.php?period=daily',		'Notification report'],
			['report4.php?period=weekly',		'Notification report'],
			['report4.php?period=monthly',		'Notification report'],
			['report4.php?period=yearly',		'Notification report'],

			// Misc
			['search.php?search=server',		'Search'],
			['profile.php',					'User profile']
		];
	}

	/**
	* @dataProvider provider
	*/
	public function testGeneric_Pages($url, $title) {
		$this->zbxTestLogin($url);
		$this->zbxTestCheckTitle($title);
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestCheckMandatoryStrings();
	}
}
