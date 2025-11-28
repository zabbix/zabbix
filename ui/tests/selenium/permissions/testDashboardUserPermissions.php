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

/**
 * @backup hosts
 *
 * @onBefore prepareTestData
 */
class testDashboardUserPermissions extends CWebTest {

	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	const USERNAME = 'test_user_123';
	const PASSWORD = 'zabbix_zabbix';
	const HOSTNAME = 'Check dashboard access';
	const USER_ROLE_GUEST = 4; // Default Zabbix user role 'Guest'.

	/**
	 * Created template dashboard ID.
	 *
	 * @var integer
	 */
	protected static $template_dashboardid;

	/**
	 * Created global dashboard ID.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * Created host group ID.
	 *
	 * @var integer
	 */
	protected static $host_groupid;

	/**
	 * Created host ID.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * List of created user group IDs.
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
		$template_groupid = CDataHelper::call('templategroup.create', [
			[
				'name' => 'Template group for dashboard access testing'
			]
		])['groupids'][0];

		self::$host_groupid = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Host group for dashboard access testing'
			]
		])['groupids'][0];

		$templateid = CDataHelper::call('template.create', [
			[
				'host' => 'Template with host dashboard',
				'groups' => ['groupid' => $template_groupid]
			]
		])['templateids'][0];

		self::$template_dashboardid = CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => $templateid,
				'name' => 'Check user group access',
				'pages' => [[]]
			]
		])['dashboardids'][0];

		self::$hostid = CDataHelper::call('host.create', [
			[
				'host' => self::HOSTNAME,
				'groups' => [
					[
						'groupid' => self::$host_groupid
					]
				],
				'templates' => [
					[
						'templateid' => $templateid
					]
				]
			]
		])['hostids'][0];

		CDataHelper::call('usergroup.create', [
			[
				'name' => 'Read/Write access to template and host',
				'templategroup_rights' => [
					'id' => $template_groupid,
					'permission' => PERM_READ_WRITE
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupid,
					'permission' => PERM_READ_WRITE
				]
			],
			[
				'name' => 'Read access to template and host',
				'templategroup_rights' => [
					'id' => $template_groupid,
					'permission' => PERM_READ
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupid,
					'permission' => PERM_READ
				]
			],
			[
				'name' => 'Deny access to template and host',
				'templategroup_rights' => [
					'id' => $template_groupid,
					'permission' => PERM_DENY
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupid,
					'permission' => PERM_DENY
				]
			],
			[
				'name' => 'Read access to host, but deny from template',
				'templategroup_rights' => [
					'id' => $template_groupid,
					'permission' => PERM_DENY
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupid,
					'permission' => PERM_READ
				]
			],
			[
				'name' => 'Deny access to host, but read from template',
				'templategroup_rights' => [
					'id' => $template_groupid,
					'permission' => PERM_READ
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupid,
					'permission' => PERM_DENY
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

		self::$dashboardid = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Test sharing functionality',
				'pages' => [[]],
				'userGroups' => [
					[
						'usrgrpid' => self::$user_groups['Read access to host, but deny from template'],
						'permission' => PERM_READ
					]
				]
			]
		])['dashboardids'][0];
	}

	public static function getDashboardPermissionsData() {
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
			'Super admin with deny-host and read-template access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_group' => 'Deny access to host, but read from template',
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
			'Regular admin with deny-host and read-template access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_group' => 'Deny access to host, but read from template',
					'view' => false,
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
					'edit' => false
				]
			],
			'User with deny-host and read-template access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_group' => 'Deny access to host, but read from template',
					'view' => false,
					'edit' => false
				]
			],
			'Guest with full access' => [
				[
					'user_role' => self::USER_ROLE_GUEST,
					'user_group' => 'Read/Write access to template and host',
					'view' => true,
					'edit' => false
				]
			],
			'Guest with read access' => [
				[
					'user_role' => self::USER_ROLE_GUEST,
					'user_group' => 'Read access to template and host',
					'view' => true,
					'edit' => false
				]
			],
			'Guest with deny access' => [
				[
					'user_role' => self::USER_ROLE_GUEST,
					'user_group' => 'Deny access to template and host',
					'view' => false,
					'edit' => false
				]
			],
			'Guest with read-host and deny-template access' => [
				[
					'user_role' => self::USER_ROLE_GUEST,
					'user_group' => 'Read access to host, but deny from template',
					'view' => true,
					'edit' => false
				]
			],
			'Guest with deny-host and read-template access' => [
				[
					'user_role' => self::USER_ROLE_GUEST,
					'user_group' => 'Deny access to host, but read from template',
					'view' => false,
					'edit' => false
				]
			]
		];
	}

	/**
	 * Check that users have access to host and template dashboards according to user group and user role permissions.
	 *
	 * @dataProvider getDashboardPermissionsData
	 */
	public function testDashboardUserPermissions_DashboardAccess($data) {
		// Update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_role'], $data['user_group']);
		$this->page->userLogin(self::USERNAME, self::PASSWORD);

		// Check user access to template dashboard via URL.
		$this->checkAccessViaUrlOnTemplate($data);

		// Check user access to host dashboard via URL.
		$this->checkAccessViaUrlOnHost($data);

		//  Check user access to host dashboard via frontend.
		$this->checkAccessViaMonitoringHosts($data);
	}

	/**
	 * Check that users has access to host dashboard according to user group and user role permissions.
	 *
	 * @param array		$data		Data from data provider.
	 */
	protected function checkAccessViaMonitoringHosts($data) {
		// Open host list in monitorting section.
		$this->page->open('zabbix.php?action=host.view&groupids%5B%5D='.self::$host_groupid)->waitUntilReady();
		$table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();

		if ($data['view']) {
			$column = $table->findRow('Name', self::HOSTNAME)->getColumn('Dashboards');

			// Check dashboard counter.
			$this->assertEquals('1', $column->query('xpath:./sup')->one()->getText());

			// Open inherited dashboard.
			$link = $column->query('link:Dashboards')->one();
			$this->assertTrue($link->isClickable());
			$link->click();
			$this->page->waitUntilReady();

			// Check dashboard name.
			$this->assertEquals('Check user group access', $this->query('class:host-dashboard-navigation')
					->query('xpath:.//div[@class="selected-tab"]')->one()->getText()
			);

			// Check that there is no editing panel.
			$this->assertFalse($this->query('xpath:.//nav[@class="dashboard-edit"]')->exists());
		}
		else {
			// No access, check that there is no host in table.
			$this->assertFalse($table->findRow('Name', self::HOSTNAME)->isPresent());
		}
	}

	/**
	 * Check that user has access to host dashboards via URL according to user group and user role permissions.
	 *
	 * @param array		$data		Data from data provider.
	 */
	protected function checkAccessViaUrlOnHost($data) {
		// Open inherited dashboard.
		$this->page->open('zabbix.php?action=host.dashboard.view&hostid='.self::$hostid)->waitUntilReady();

		if ($data['view']) {
			// Check dashboard name.
			$this->assertEquals('Check user group access', $this->query('class:host-dashboard-navigation')
					->query('xpath:.//div[@class="selected-tab"]')->one()->getText()
			);

			// Check that there is no editing panel.
			$this->assertFalse($this->query('xpath:.//nav[@class="dashboard-edit"]')->exists());
		}
		else {
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "'.self::USERNAME.
					'". You have no permissions to access this page.'
			);
			$this->assertTrue($this->query('button:Go to "Dashboards"')->one()->isClickable());
		}
	}

	/**
	 * Check that user has access to template dashboards via URL according to user group and user role permissions.
	 *
	 * @param array		$data		Data from data provider.
	 */
	protected function checkAccessViaUrlOnTemplate($data) {
		$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$template_dashboardid)->waitUntilReady();

		if ($data['edit']) {
			$dashboard = CDashboardElement::find()->one()->waitUntilVisible();
			$dashboard->edit();
			$this->assertTrue($dashboard->isEditable());
		}
		else {
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "'.self::USERNAME.
					'". You have no permissions to access this page.'
			);
			$this->assertTrue($this->query('button:Go to "Dashboards"')->one()->isClickable());
		}
	}

	public static function getSharingPermissionsData() {
		return [
			// List of user group shares.
			'Group access: Super admin with write access' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Group access: Super admin with read access' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Group access: Super admin with different user group' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Group access: Admin with write access' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Group access: Admin with read access' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Group access: Admin with different user group' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => false
				]
			],
			'Group access: User with write access' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Group access: User with read access' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Group access: User with different user group' => [
				[
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => false
				]
			],
			'Group access: Guest with write access' => [
				[
					'scenario' => 'group_access',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Group access: Guest with read access' => [
				[
					'scenario' => 'group_access',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Group access: Guest with different user group' => [
				[
					'scenario' => 'group_access',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => false
				]
			],
			// List of user shares.
			'User access: Super admin with write access' => [
				[
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User access: Super admin with read access' => [
				[
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User access: Admin with write access' => [
				[
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User access: Admin with read access' => [
				[
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User access: User with write access' => [
				[
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User access: User with read access' => [
				[
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User access: Guest with write access' => [
				[
					'scenario' => 'user_access',
					'user_role' => self::USER_ROLE_GUEST,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => false,
					'view' => true
				]
			],
			'User access: User with read access' => [
				[
					'scenario' => 'user_access',
					'user_role' => self::USER_ROLE_GUEST,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			// Shared dashboard with different user.
			'Access for unexpected user: Super admin with no access to dashboard' => [
				[
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'edit' => true,
					'view' => true
				]
			],
			'Access for unexpected user: Admin with no access to dashboard' => [
				[
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'edit' => false,
					'view' => false
				]
			],
			'Access for unexpected user: User with no access to dashboard' => [
				[
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'edit' => false,
					'view' => false
				]
			],
			'Access for unexpected user: User with no access to dashboard' => [
				[
					'scenario' => 'different_user',
					'user_role' => self::USER_ROLE_GUEST,
					'edit' => false,
					'view' => false
				]
			],
			// List of users + list of user groups.
			'User and group access: super admin - read, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - read, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - read, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - read, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - write, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - write, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - write, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: super admin - write, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: admin - read, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: admin - read, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: admin - read, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: admin - read, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: admin - write, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: admin - write, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: admin - write, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: admin - write, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: user - read, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: user - read, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: user - read, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: user - read, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: user - write, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: user - write, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: user - write, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: user - write, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'User and group access: guest - read, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - read, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - read, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - read, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - write, group - read, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - write, group - read, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - write, group - write, same groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => false,
					'view' => true
				]
			],
			'User and group access: guest - write, group - write, different groups' => [
				[
					'scenario' => 'group_and_user',
					'user_role' => self::USER_ROLE_GUEST,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => false,
					'view' => true
				]
			]
		];
	}

	/**
	 * Verify user access to the public dashboards through sharing.
	 *
	 * @dataProvider getSharingPermissionsData
	 */
	public function testDashboardUserPermissions_PublicDashboardShare($data) {
		$this->checkDashboardShare($data, PUBLIC_SHARING);
	}

	/**
	 * Verify user access to the private dashboards through sharing.
	 *
	 * @dataProvider getSharingPermissionsData
	 */
	public function testDashboardUserPermissions_PrivateDashboardShare($data) {
		$this->checkDashboardShare($data, PRIVATE_SHARING);
	}

	/**
	 * Verify user access to the private and public dashboards through sharing.
	 *
	 * @param array   $data          Data from data provider.
	 * @param integer $sharing_type	 Dashboard sharing type - public or private.
	 */
	protected function checkDashboardShare($data, $sharing_type) {
		// Update dashboard access settings and user permissions.
		switch ($data['scenario']) {
			case 'group_access':
				$this->updateUser($data['user_role'], $data['user_group']);
				$this->updateDashboardAccess($data['permissions'], $data['dashboard_group'], PERM_READ_WRITE,
						$data['scenario'], $sharing_type
				);
				break;
			case 'user_access':
				$this->updateUser($data['user_role']);
				$this->updateDashboardAccess(PERM_DENY, PERM_DENY, $data['user_permissions'], $data['scenario'], $sharing_type);
				break;
			case 'group_and_user':
				$user_group = (array_key_exists('dashboard_group', $data))
					? $data['dashboard_group']
					: 'Read/Write access to template and host';

				$this->updateUser($data['user_role'], $user_group);
				$this->updateDashboardAccess($data['permissions'], $data['dashboard_group'], $data['user_permissions'],
						$data['scenario'], $sharing_type
				);
				break;
			case 'different_user':
				$this->updateUser($data['user_role']);
				$this->updateDashboardAccess(PERM_DENY, PERM_DENY, PERM_READ_WRITE, $data['scenario'], $sharing_type);
				break;
		}

		// Login under updated user and open dashboard.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		if ($data['view'] || $sharing_type === PUBLIC_SHARING) {
			// Check dashboard name.
			$this->assertEquals('Test sharing functionality', $this->query('id:page-title-general')->one()->getText());

			if ($data['edit']) {
				$dashboard = CDashboardElement::find()->one()->waitUntilVisible();
				$dashboard->edit();
				$this->assertTrue($dashboard->isEditable());
			}
			else {
				$this->assertFalse($this->query('button:Edit dashboard')->one()->isClickable(), 'Button is clickable');
			}
		}
		else {
			$this->assertMessage(TEST_BAD, 'No permissions to referred object or it does not exist!');
		}
	}

	/**
	 * Change dashboard sharing access via API.
	 *
	 * @param integer $group_permissions	PERM_READ_WRITE, PERM_READ or PERM_DENY.
	 * @param string  $group				User group name.
	 * @param integer $user_permissions		PERM_READ_WRITE, PERM_READ or PERM_DENY.
	 * @param string  $scenario				Dashboard update scenario: group_access, user_access, group_and_user or different_user.
	 * @param integer $sharing				PUBLIC_SHARING or PRIVATE_SHARING.
	 */
	protected function updateDashboardAccess($group_permissions, $group, $user_permissions, $scenario, $sharing) {
		// Clear all previous permissions first.
		CDataHelper::call('dashboard.update', [
			[
				'dashboardid' => self::$dashboardid,
				'private' => $sharing,
				'userGroups' => [],
				'users' => []
			]
		]);

		// Change dashboard sharing permissions.
		switch ($scenario) {
			case 'group_access':
				CDataHelper::call('dashboard.update', [
					[
						'dashboardid' => self::$dashboardid,
						'userGroups' => [
							[
								'usrgrpid' => self::$user_groups[$group],
								'permission' => $group_permissions
							]
						]
					]
				]);
				break;
			case 'user_access':
				CDataHelper::call('dashboard.update', [
					[
						'dashboardid' => self::$dashboardid,
						'users' => [
							[
								'userid' => self::$userid,
								'permission' => $user_permissions
							]
						]
					]
				]);
				break;
			case 'group_and_user':
				CDataHelper::call('dashboard.update', [
					[
						'dashboardid' => self::$dashboardid,
						'userGroups' => [
							[
								'usrgrpid' => self::$user_groups[$group],
								'permission' => $group_permissions
							]
						],
						'users' => [
							[
								'userid' => self::$userid,
								'permission' => $user_permissions
							]
						]
					]
				]);
				break;
			case 'different_user':
				CDataHelper::call('dashboard.update', [
					[
						'dashboardid' => self::$dashboardid,
						'users' => [
							[
								'userid' => 1, // Admin.
								'permission' => $user_permissions
							]
						]
					]
				]);
				break;
		}
	}

	/**
	 * Change user role and user group request for existing user via API.
	 *
	 * @param integer	$user_roleid	User role ID.
	 * @param string	$user_group		User group name.
	 */
	protected function updateUser($user_roleid, $user_group = 'Read/Write access to template and host') {
		CDataHelper::call('user.update', [
			[
				'userid' => self::$userid,
				'usrgrps' => [
					['usrgrpid' => self::$user_groups[$user_group]]
				],
				'roleid' => $user_roleid
			]
		]);
	}
}
