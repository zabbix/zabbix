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
 * @backup hosts, hosts_groups, hosts_templates, users, dashboard_user, users_groups, dashboard
 *
 * @onBefore prepareTestData
 */
class testDashboardsUserPermissions extends CWebTest {

	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const USERNAME = 'test_user_123';
	const PASSWORD = 'zabbix_zabbix';
	const HOSTNAME = 'Check dashboard access';

	/**
	 * List of created template dashboard IDs.
	 *
	 * @var array
	 */
	protected static $template_dashboardids;

	/**
	 * List of created template group IDs.
	 *
	 * @var array
	 */
	protected static $template_groupids;

	/**
	 * List of created template IDs.
	 *
	 * @var array
	 */
	protected static $templateids;

	/**
	 * List of created host group IDs.
	 *
	 * @var attay
	 */
	protected static $host_groupids;

	/**
	 * List of created host IDs.
	 *
	 * @var array
	 */
	protected static $hostid;

	/**
	 * List of created user groups IDs.
	 *
	 * @var array
	 */
	protected static $user_groups;

	/**
	 * ID of created user.
	 *
	 * @var integer
	 */
	protected static $userid;

	/**
	 * Function used to create users, template, dashboards, hosts and user groups.
	 */
	public static function prepareTestData() {

		CDataHelper::call('templategroup.create', [
			[
				'name' => 'Template group for dashboard access testing'
			]
		]);
		self::$template_groupids = CDataHelper::getIds('name');

		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Host group for dashboard access testing'
			]
		]);
		self::$host_groupids = CDataHelper::getIds('name');

		CDataHelper::call('template.create', [
			[
				'host' => 'Template with host dashboard',
				'groups' => ['groupid' => self::$template_groupids['Template group for dashboard access testing']]
			]
		]);
		self::$templateids = CDataHelper::getIds('host');

		CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => self::$templateids['Template with host dashboard'],
				'name' => 'Check user group access',
				'pages' => [[]]
			]
		]);
		self::$template_dashboardids = CDataHelper::getIds('name');

		CDataHelper::call('host.create', [
			[
				'host' => self::HOSTNAME,
				'groups' => [
					[
						'groupid' => self::$host_groupids['Host group for dashboard access testing']
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateids['Template with host dashboard']
					]
				]
			]
		]);
		self::$hostid = CDataHelper::getIds('host');

		CDataHelper::call('usergroup.create', [
			[
				'name' => 'Read/Write access to template and host',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => 3 // Read-write access.
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => 3 // Read-write access.
				]
			],
			[
				'name' => 'Read access to template and host',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => 2 // Read-only access.
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => 2 // Read-only access.
				]
			],
			[
				'name' => 'Deny access to template and host',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => 0 // Denied access.
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => 0 // Access denied.
				]
			],
			[
				'name' => 'Read access to host, but deny from template',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => 0 // Denied access.
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => 2 // Read-only access.
				]
			]
		]);
		self::$user_groups = CDataHelper::getIds('name');

		self::$userid = CDataHelper::call('user.create', [
			[
				'username' => self::USERNAME,
				'passwd' => self::PASSWORD,
				'roleid' => USER_TYPE_SUPER_ADMIN
			]
		])['userids'][0];
	}

	public static function dashboardChecks() {
		return [
			'Super admin with full access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_group' => 'Read/Write access to template and host',
					'view' => true,
					'edit' => true
				]
			],
			'Super admin with read access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_group' => 'Read access to template and host',
					'view' => true,
					'edit' => true
				]
			],
			'Super admin with deny access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_group' => 'Deny access to template and host',
					'view' => true,
					'edit' => true
				]
			],
			'Super admin with read-host and deny-template access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_group' => 'Read access to host, but deny from template',
					'view' => true,
					'edit' => true
				]
			],
			'Regular admin with full access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_group' => 'Read/Write access to template and host',
					'view' => true,
					'edit' => true
				]
			],
			'Regular admin with read access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_group' => 'Read access to template and host',
					'view' => true,
					'edit' => false
				]
			],
			'Regular admin with deny access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_group' => 'Deny access to template and host',
					'view' => false,
					'edit' => false
				]
			],
			'Regular admin with read-host and deny-template access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_group' => 'Read access to host, but deny from template',
					'view' => true,
					'edit' => false
				]
			],
			'User with full access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_group' => 'Read/Write access to template and host',
					'view' => true,
					'edit' => false
				]
			],
			'User with read access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_group' => 'Read access to template and host',
					'view' => true,
					'edit' => false
				]
			],
			'User with deny access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_group' => 'Deny access to template and host',
					'view' => false,
					'edit' => false
				]
			],
			'User with read-host and deny-template access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_group' => 'Read access to host, but deny from template',
					'view' => true,
					'host' => false
				]
			]
		];
	}

	/**
	 * @dataProvider dashboardChecks
	 */
	public function testDashboardsUserPermissions_Dashboard($data) {
		// Reuse and update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_group'], $data['user_role']);

		// Login under updated user and open host list in monitorting section.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=host.view&groupids%5B%5D='.
				self::$host_groupids['Host group for dashboard access testing']
		)->waitUntilReady();

		$table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();
		if($data['view']){
			$this->assertTrue($table->findRow('Name', self::HOSTNAME)->getColumn('Dashboards')->query('link',
					'Dashboards')->one()->isClickable()
			);
			$table->findRow('Name', self::HOSTNAME)->getColumn('Dashboards')->query('link','Dashboards')->one()
					->waitUntilClickable()->click();
		}
		else if (!$data['view'] && $data['user_group'] === 'Read access to host, but deny from template') {
			$this->assertFalse($table->findRow('Name', self::HOSTNAME)->getColumn('Dashboards')->query('link',
					'Dashboards')->one()->isClickable()
			);
		}
		else {
			$this->assertFalse($table->findRow('Name', self::HOSTNAME)->isPresent());
		}

		// Check dashboard counter in table.
			// if user group has access: click on it, check Dashboard name
			// if denied access -> should be inactive 'Dashboards' link
	}

	/**
	 * @dataProvider dashboardChecks
	 */
	public function testDashboardsUserPermissions_DashboardURL($data) {
		// Open dashboard URL
		// If user has access, check Dashboard name
		// If denied access, compare Error message
	}

	/**
	 * @dataProvider dashboardChecks
	 */
	public function testDashboardsUserPermissions_Edit($data) {
		// Open page with selected template via URL
		// If user has access, check 'Dashboards' column
		//					   click on Dashboard, check Dashboard name, click on Edit button, check configuration form
		// If denied access, compare Error message
	}

	/**
	 * Update user role and group via API request.
	 *
	 * @param string  $group             user group name
	 * @param integer $password          user role ID
	 */
	public static function updateUser($group, $role) {
		CDataHelper::call('user.update', [
			[
				'userid' => self::$userid,
				'usrgrps' => [
					['usrgrpid' => self::$user_groups[$group]]
				],
				'roleid' => $role
			]
		]);
	}
}
