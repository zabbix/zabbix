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
 * @backup hosts
 *
 * @onBefore prepareTestData
 */
class testDashboardsUserPermissions extends CWebTest {

	public function getBehaviors() {
		return [CMessageBehavior::class];
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
	 * ID of created dashboard.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

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
					'permission' => PERM_READ_WRITE
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => PERM_READ_WRITE
				]
			],
			[
				'name' => 'Read access to template and host',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => PERM_READ
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => PERM_READ
				]
			],
			[
				'name' => 'Deny access to template and host',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => PERM_DENY
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => PERM_DENY
				]
			],
			[
				'name' => 'Read access to host, but deny from template',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => PERM_DENY
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => PERM_READ
				]
			],
			[
				'name' => 'Deny access to host, but read from template',
				'templategroup_rights' => [
					'id' => self::$template_groupids['Template group for dashboard access testing'],
					'permission' => PERM_READ
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
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
			]
		];
	}

	/**
	 * Check that users has access to host dashboards according to user group and user role permissions.
	 *
	 * @dataProvider getDashboardPermissionsData
	 */
	public function testDashboardsUserPermissions_HostDashboard($data) {
		// Reuse and update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_role'], $data['user_group']);

		// Login under updated user and open host list in monitorting section.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=host.view&groupids%5B%5D='.
				self::$host_groupids['Host group for dashboard access testing']
		)->waitUntilReady();

		$table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();

		if ($data['view']) {
			$column = $table->findRow('Name', self::HOSTNAME)->getColumn('Dashboards');

			// Check dashboard counter.
			$this->assertEquals('1', $column->query('xpath:./sup')->one()->getText());

			// Open inherited dashboard.
			$this->assertTrue($column->query('link:Dashboards')->one()->isClickable());
			$column->query('link:Dashboards')->one()->click();
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
	 * @dataProvider getDashboardPermissionsData
	 */
	public function testDashboardsUserPermissions_HostDashboardURL($data) {
		// Reuse and update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_role'], $data['user_group']);

		// Login under updated user and open inherited dashboard.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=host.dashboard.view&hostid='.self::$hostid[self::HOSTNAME])->waitUntilReady();

		if ($data['view']) {
			// Check dashboard name.
			$this->assertEquals('Check user group access', $this->query('class:host-dashboard-navigation')
					->query('xpath:.//div[@class="selected-tab"]')->one()->getText()
			);

			// Check that there is no editing panel.
			$this->assertFalse($this->query('xpath:.//nav[@class="dashboard-edit"]')->exists());
		}
		else {
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "'.self::USERNAME.'". '.
					'You have no permissions to access this page.');
			$this->assertTrue($this->query('button:Go to "Dashboards"')->one()->isClickable());
		}
	}

	/**
	 * Check that user has access to template dashboards via URL according to user group and user role permissions.
	 *
	 * @dataProvider getDashboardPermissionsData
	 */
	public function testDashboardsUserPermissions_TemplateDashboardURL($data) {
		// Reuse and update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_role'], $data['user_group']);

		// Login under updated user and open inherited dashboard.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.
				self::$template_dashboardids['Check user group access'])->waitUntilReady();

		if ($data['edit']) {
			$dashboard = CDashboardElement::find()->one()->waitUntilVisible();
			$dashboard->edit();
			$this->assertTrue($dashboard->isEditable());
		}
		else {
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "'.self::USERNAME.'". '.
					'You have no permissions to access this page.');
			$this->assertTrue($this->query('button:Go to "Dashboards"')->one()->isClickable());
		}
	}

	public static function getSharingPermissionsData() {
		return [
			// List of user group shares.
			'Private dashboard. Group access: Super admin with write access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. Group access: Super admin with read access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. Group access: Super admin with different user group' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. Group access: Admin with write access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. Group access: Admin with read access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Private dashboard. Group access: Admin with different user group' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => false
				]
			],
			'Private dashboard. Group access: User with write access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. Group access: User with read access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Private dashboard. Group access: User with different user group' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'view' => false
				]
			],
			// List of user shares.
			'Private dashboard. User access: Super admin with write access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User access: Super admin with read access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User access: Admin with write access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User access: Admin with read access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'Private dashboard. User access: User with write access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User access: User with read access' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			// Shared dashboard with different user.
			'Private dashboard. Access for unexpected user: Super admin with no access to dashboard' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. Access for unexpected user: Admin with no access to dashboard' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'edit' => false,
					'view' => false
				]
			],
			'Private dashboard. Access for unexpected user: User with no access to dashboard' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'edit' => false,
					'view' => false
				]
			],
			// List of users + list of user groups.
			'Private dashboard. User and group access: super admin - read, group - read, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: super admin - read, group - read, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: super admin - read, group - write, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: super admin - read, group - write, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: super admin - write, group - read, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: super admin - write, group - read, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: super admin - write, group - write, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: super admin - write, group - write, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: admin - read, group - read, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: admin - read, group - read, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'Private dashboard. User and group access: admin - read, group - write, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: admin - read, group - write, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: admin - write, group - read, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: admin - write, group - read, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: admin - write, group - write, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: admin - write, group - write, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: user - read, group - read, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: user - read, group - read, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'Private dashboard. User and group access: user - read, group - write, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: user - read, group - write, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: user - write, group - read, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: user - write, group - read, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Private dashboard. User and group access: user - write, group - write, same groups' => [
				[
					'status' => PRIVATE_SHARING,
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
			'Private dashboard. User and group access: user - write, group - write, different groups' => [
				[
					'status' => PRIVATE_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			// Same scenarios, with public dashboards.
			// List of user group shares.
			'Public dashboard. Group access: Super admin with write access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. Group access: Super admin with read access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. Group access: Super admin with different user group' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. Group access: Admin with write access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. Group access: Admin with read access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. Group access: Admin with different user group' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. Group access: User with write access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. Group access: User with read access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. Group access: User with different user group' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_access',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			// List of user shares.
			'Public dashboard. User access: Super admin with write access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User access: Super admin with read access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User access: Admin with write access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User access: Admin with read access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. User access: User with write access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User access: User with read access' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'user_access',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			// Shared dashboard with different user.
			'Public dashboard. Access for unexpected user: Super admin with no access to dashboard' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. Access for unexpected user: Admin with no access to dashboard' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. Access for unexpected user: User with no access to dashboard' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'different_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'edit' => false,
					'view' => true
				]
			],
			// List of users + list of user groups.
			'Public dashboard. User and group access: super admin - read, group - read, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: super admin - read, group - read, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: super admin - read, group - write, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: super admin - read, group - write, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: super admin - write, group - read, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: super admin - write, group - read, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: super admin - write, group - write, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: super admin - write, group - write, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: admin - read, group - read, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: admin - read, group - read, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. User and group access: admin - read, group - write, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: admin - read, group - write, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: admin - write, group - read, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: admin - write, group - read, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: admin - write, group - write, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: admin - write, group - write, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: user - read, group - read, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard.  User and group access: user - read, group - read, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => false,
					'view' => true
				]
			],
			'Public dashboard. User and group access: user - read, group - write, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: user - read, group - write, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: user - write, group - read, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: user - write, group - read, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			],
			'Public dashboard. User and group access: user - write, group - write, same groups' => [
				[
					'status' => PUBLIC_SHARING,
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
			'Public dashboard. User and group access: user - write, group - write, different groups' => [
				[
					'status' => PUBLIC_SHARING,
					'scenario' => 'group_and_user',
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read access to template and host',
					'user_permissions' => PERM_READ_WRITE,
					'edit' => true,
					'view' => true
				]
			]
		];
	}

	/**
	 * Verify user access to the private and public dashboards through sharing.
	 *
	 * @dataProvider getSharingPermissionsData
	 */
	public function testDashboardsUserPermissions_DashboardShare($data) {

		// Update dashboard access settings and user permissions.
		switch ($data['scenario']) {
			case 'group_access':
				$this->updateUser($data['user_role'], $data['user_group']);
				$this->updateDashboardAccess($data['permissions'], $data['dashboard_group'], 0, 1, $data['status']);
				break;
			case 'user_access':
				$this->updateUser($data['user_role']);
				$this->updateDashboardAccess(0, 0, $data['user_permissions'], 2, $data['status']);
				break;
			case 'group_and_user':
				$user_group = (array_key_exists('dashboard_group', $data))
						? $data['dashboard_group']
						: 'Read/Write access to template and host';

				$this->updateUser($data['user_role'], $user_group);
				$this->updateDashboardAccess($data['permissions'], $data['dashboard_group'], $data['user_permissions'],
						3, $data['status']
				);
				break;
			case 'different_user':
				$this->updateUser($data['user_role']);
				$this->updateDashboardAccess(0, 0, PERM_READ_WRITE, 4, $data['status']);
				break;
		}

		// Login under updated user and open dashboard.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		if ($data['view']) {
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
	 * @param integer $group_permissions	PERM_READ_WRITE or PERM_READ.
	 * @param string  $group				User group name.
	 * @param integer $user_permissions		PERM_READ_WRITE or PERM_READ.
	 * @param integer $scenario				Dashboard update scenario: 1 - update only group, 2 - only user, 3 - group and user, 4 - different user.
	 * @param integer $private				PUBLIC_SHARING or PRIVATE_SHARING.
	 */
	protected function updateDashboardAccess($group_permissions, $group, $user_permissions, $scenario, $private) {
		// Clear all previous permissions first.
		CDataHelper::call('dashboard.update', [
			[
				'dashboardid' => self::$dashboardid,
				'private' => $private,
				'userGroups' => [],
				'users' => []
			]
		]);

		// Change dashboard sharing permissions.
		switch ($scenario) {
			case 1:
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
			case 2:
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
			case 3:
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
			case 4:
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
	 * @param integer	$role		User role ID.
	 * @param string	$group		User group name.
	 */
	protected function updateUser($role, $group = 'Read/Write access to template and host') {
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
