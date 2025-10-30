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
require_once __DIR__.'/../common/testWidgets.php';

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
					'permission' => PERM_DENY // Denied access.
				],
				'hostgroup_rights' => [
					'id' => self::$host_groupids['Host group for dashboard access testing'],
					'permission' => PERM_READ
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

	public static function dashboardPermissions() {
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
					'edit' => false
				]
			]
		];
	}

	/**
	 * @dataProvider dashboardPermissions
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
	 * @dataProvider dashboardPermissions
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
			$this->query('button:Go to "Dashboards"')->one()->waitUntilClickable()->click();
		}
	}

	/**
	 * @dataProvider dashboardPermissions
	 */
	public function testDashboardsUserPermissions_TemplateDashboardURL($data) {
		// Reuse and update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_role'], $data['user_group']);

		// Login under updated user and open inherited dashboard.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.
				self::$template_dashboardids['Check user group access'])->waitUntilReady();

		if ($data['edit']) {
			$this->addClockWidget();
			// Check success message after dashboard update.
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "'.self::USERNAME.'". '.
					'You have no permissions to access this page.');
			$this->query('button:Go to "Dashboards"')->one()->waitUntilClickable()->click();
		}
	}

	public static function sharingPermissions() {
		return [
			'Super admin with write access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Super admin with read access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Super admin with deny access' => [
				[
					'user_role' => USER_TYPE_SUPER_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Admin with write access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'Admin with read access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'Admin with deny access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_ADMIN,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'view' => false
				]
			],
			'User with write access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ_WRITE,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read/Write access to template and host',
					'edit' => true,
					'view' => true
				]
			],
			'User with read access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read access to template and host',
					'user_group' => 'Read access to template and host',
					'edit' => false,
					'view' => true
				]
			],
			'User with deny access' => [
				[
					'user_role' => USER_TYPE_ZABBIX_USER,
					'permissions' => PERM_READ,
					'dashboard_group' => 'Read/Write access to template and host',
					'user_group' => 'Read access to template and host',
					'view' => false
				]
			]
		];
	}

	/**
	 * Verify user access to the dashboard through Sharing.
	 *
	 * @dataProvider sharingPermissions
	 */
	public function testDashboardsUserPermissions_DashboardShare($data) {
		// Reuse and update the existing user to prevent creating 10+ separate test users during test preparation.
		$this->updateUser($data['user_role'], $data['user_group']);

		// Update dashboard permissions.
		$this->updateDashboardAccess($data['permissions'], $data['dashboard_group']);

		// Login under updated user and open inherited dashboard.
		$this->page->userLogin(self::USERNAME, self::PASSWORD);
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		if ($data['view']) {
			// Check dashboard name.
			$this->assertEquals('Test sharing functionality', $this->query('id:page-title-general')->one()->getText());

			if ($data['edit']) {
				$this->addClockWidget('global');
				// Check success message after dashboard update.
				$this->page->waitUntilReady();
				$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			}
			else {
				$this->assertFalse($this->query('xpath:.//nav[@class="dashboard-edit"]')->one()->isClickable());
			}
		}
		else {
			$this->assertMessage(TEST_BAD, 'No permissions to referred object or it does not exist!');
		}
	}

	/**
	 * Change dashboard permissions using API request.
	 *
	 * @param integer $permissions      read or write
	 * @param integer $group            user group ID
	 */
	public static function updateDashboardAccess($permissions, $group) {
		CDataHelper::call('dashboard.update', [
			[
				'dashboardid' => self::$dashboardid,
				'userGroups' => [
					[
						'usrgrpid' => self::$user_groups[$group],
						'permission' => $permissions
					]
				]
			]
		]);
	}

	/**
	 * Change user role and group via API request for existing user.
	 *
	 * @param string  $group             user group name
	 * @param integer $password          user role ID
	 */
	public static function updateUser($role, $group = 'Read/Write access to template and host') {
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

	/**
	 * Add 'Clock' widget to test edit functionality.
	 *
	 * @param string  $source		flag for additional edit() click
	 */
	public static function addClockWidget($source = 'template') {
		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();

		if ($source === 'global'){
			$dashboard->edit()->addWidget()->asForm();
		}
		else {
			$dashboard->addWidget()->asForm();
		}

		$dialog = COverlayDialogElement::find()->one()->asForm();
		$dialog->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);
		$dialog->submit();

		$dashboard->getControls()->query('id:dashboard-save')->one()->click();
	}
}
