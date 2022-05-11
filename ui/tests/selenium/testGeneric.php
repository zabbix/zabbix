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
 * @backup profiles
 */
class testGeneric extends CLegacyWebTest {

	public static function provider() {
		return [
			// monitoring
			['zabbix.php?action=dashboard.view',					'Dashboard'],

			['zabbix.php?action=web.view',					'Web monitoring'],
			['zabbix.php?action=latest.view',	'Latest data'],

			['zabbix.php?action=problem.view',	'Problems'],

			['zabbix.php?action=charts.view',		'Custom graphs'],
			['zabbix.php?action=map.view',			'Configuration of network maps'],
			['zabbix.php?action=discovery.view',	'Status of discovery'],
			['zabbix.php?action=service.list',		'Services'],

			// inventory
			['hostinventoriesoverview.php',	'Host inventory overview'],
			['hostinventories.php',			'Host inventory'],

			// reports
			['zabbix.php?action=report.status',					'System information'],
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
			[self::HOST_LIST_PAGE,				'Configuration of hosts'],
			['maintenance.php',				'Configuration of maintenance periods'],
			['httpconf.php',					'Configuration of web monitoring'],

			['actionconf.php',					'Configuration of actions'],
			['actionconf.php?eventsource=0',	'Configuration of actions'],
			['actionconf.php?eventsource=1',	'Configuration of actions'],
			['actionconf.php?eventsource=2',	'Configuration of actions'],
			['actionconf.php?eventsource=3',	'Configuration of actions'],

			['sysmaps.php',							'Configuration of network maps'],
			['zabbix.php?action=discovery.list',	'Configuration of discovery rules'],
			['zabbix.php?action=service.list.edit',	'Services'],

			// Administration
			['zabbix.php?action=gui.edit',	'Configuration of GUI'],
			['zabbix.php?action=housekeeping.edit',		'Configuration of housekeeping'],
			['zabbix.php?action=image.list',	'Configuration of images'],
			['zabbix.php?action=iconmap.list',	'Configuration of icon mapping'],
			['zabbix.php?action=regex.list',	'Configuration of regular expressions'],
			['zabbix.php?action=macros.edit',	'Configuration of macros'],
			['zabbix.php?action=trigdisplay.edit',	'Configuration of trigger displaying options'],
			['zabbix.php?action=miscconfig.edit',	'Other configuration parameters'],

			['zabbix.php?action=proxy.list',						'Configuration of proxies'],
			['zabbix.php?action=authentication.edit',				'Configuration of authentication'],
			['zabbix.php?action=usergroup.list',					'Configuration of user groups'],
			['zabbix.php?action=user.edit',		'Configuration of users'],
			['zabbix.php?action=mediatype.list',					'Configuration of media types'],
			['zabbix.php?action=script.list',						'Configuration of scripts'],
			['zabbix.php?action=auditlog.list',					'Audit log'],
			['auditacts.php',					'Action log'],

			['zabbix.php?action=queue.overview',			'Queue [refreshed every 30 sec.]'],
			['zabbix.php?action=queue.overview.proxy',		'Queue [refreshed every 30 sec.]'],
			['zabbix.php?action=queue.details',				'Queue [refreshed every 30 sec.]'],

			['report4.php',					'Notification report'],
			['report4.php?period=daily',		'Notification report'],
			['report4.php?period=weekly',		'Notification report'],
			['report4.php?period=monthly',		'Notification report'],
			['report4.php?period=yearly',		'Notification report'],

			// Misc
			['zabbix.php?action=search&search=server',		'Search'],
			['zabbix.php?action=userprofile.edit',			'User profile']
		];
	}

	/**
	* @dataProvider provider
	*/
	public function testGeneric_Pages($url, $title) {
		$this->zbxTestLogin($url);
		$this->zbxTestCheckTitle($title);
		$this->zbxTestCheckMandatoryStrings();
	}
}
