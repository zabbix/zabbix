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
			['zabbix.php?action=availabilityreport.list',		'Availability report'],
			['zabbix.php?action=toptriggers.list',				'Top 100 triggers'],
			['zabbix.php?action=toptriggers.list&filter_severities[0]=0&filter_set=1',	'Top 100 triggers'],
			['zabbix.php?action=toptriggers.list&filter_severities[1]=1&filter_set=1',	'Top 100 triggers'],
			['zabbix.php?action=toptriggers.list&filter_severities[2]=2&filter_set=1',	'Top 100 triggers'],
			['zabbix.php?action=toptriggers.list&filter_severities[3]=3&filter_set=1',	'Top 100 triggers'],
			['zabbix.php?action=toptriggers.list&filter_severities[4]=4&filter_set=1',	'Top 100 triggers'],
			['zabbix.php?action=toptriggers.list&filter_severities[5]=5&filter_set=1',	'Top 100 triggers'],

			// configuration
			['zabbix.php?action=hostgroup.list',		'Configuration of host groups'],
			['zabbix.php?action=templategroup.list',	'Configuration of template groups'],
			['zabbix.php?action=template.list',			'Configuration of templates'],
			[self::HOST_LIST_PAGE,				'Configuration of hosts'],
			['zabbix.php?action=maintenance.list',		'Configuration of maintenance periods'],
			['httpconf.php',					'Configuration of web monitoring'],

			['zabbix.php?action=action.list&eventsource=0',	'Configuration of actions'],
			['zabbix.php?action=action.list&eventsource=1',	'Configuration of actions'],
			['zabbix.php?action=action.list&eventsource=2',	'Configuration of actions'],
			['zabbix.php?action=action.list&eventsource=3',	'Configuration of actions'],
			['zabbix.php?action=action.list&eventsource=4',	'Configuration of actions'],

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
			['zabbix.php?action=actionlog.list',					'Action log'],

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
