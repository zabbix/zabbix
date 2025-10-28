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

require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @backup usrgrp, hosts, dashboard
 */
class testDashboardsUserPermissions extends CWebTest {

	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * Id of created dashboard.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * Id of created template group.
	 *
	 * @var integer
	 */
	protected static $template_groupid;

	/**
	 * Id of created template.
	 *
	 * @var integer
	 */
	protected static $templateid;

	/**
	 * Id of created host.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Function used to create users, template, dashboards, hosts and user groups.
	 */
	public function prepareUserData() {

		$response = CDataHelper::call('templategroup.create', [
			[
				'name' => 'Template group for dashboard access testing'
			]
		]);
		self::$template_groupid = $response['groupids'][0];

		$response = CDataHelper::call('template.create', [
			'host' => 'Template with host dashboard',
			'groups' => ['groupid' => self::$template_groupid]
		]);
		self::$templateid = $response['templateids'][0];

		$response = CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => self::$templateid,
				'name' => 'Check user group access',
				'pages' => [[]]
			]
		]);
		self::$template_dashboardid = $response['dashboardids'][0];

		$response = CDataHelper::call('host.create', [
			[
				'host' => 'Check dashboard access',
				'groups' => [
					[
						'groupid' => 4 // Zabbix servers.
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateid
					]
				]
			]
		]);
		self::$hostid = $response['hostids'][0];
	}
}
