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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource ScheduledReports
 *
 * @backup report
 *
 * @onBefore prepareData
 */
class testScheduledReportPermissions extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	protected static $roleids;
	protected static $userids;
	protected static $usergroupids;
	protected static $dashboardids;

	public function prepareData() {
		$roles = CDataHelper::call('role.create', [
			[
				'name' => 'admin role without access to reports',
				'type' => 2,
				'rules' => [
					'actions' => [
						[
							'name' => 'manage_scheduled_reports',
							'status' => '0'
						]
					]
				]
			],
			[
				'name' => 'super-admin role without access to reports',
				'type' => 3,
				'rules' => [
					'actions' => [
						[
							'name' => 'manage_scheduled_reports',
							'status' => '0'
						]
					]
				]
			],
			[
				'name' => 'admin role for reports',
				'type' => 2
			],
			[
				'name' => 'super-admin role for reports',
				'type' => 3
			]
		]);
		$this->assertArrayHasKey('roleids', $roles);
		self::$roleids = CDataHelper::getIds('name');

		$group = CDataHelper::call('usergroup.create', [
			[
				'name' => 'usergroup for report test'
			],
			[
				'name' => 'usergroup for report test second'
			],
			[
				'name' => 'usergroup for report test third'
			]
		]);
		$this->assertArrayHasKey('usrgrpids', $group);
		self::$usergroupids = CDataHelper::getIds('name');

		$users = CDataHelper::call('user.create', [
			[
				'username' => 'admin without report permissions',
				'passwd' => 'xibbaz123',
				'roleid' => self::$roleids['admin role without access to reports'],
				'usrgrps' => [
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test']
					]
				]
			],
			[
				'username' => 'super-admin without report permissions',
				'passwd' => 'xibbaz123',
				'roleid' => self::$roleids['super-admin role without access to reports'],
				'usrgrps' => [
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test']
					]
				]
			],
			[
				'username' => 'admin report permissions',
				'passwd' => 'xibbaz123',
				'roleid' => self::$roleids['admin role for reports'],
				'usrgrps' => [
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test']
					],
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test second']
					],
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test third']
					]
				]
			],
			[
				'username' => 'super-admin report permissions',
				'passwd' => 'xibbaz123',
				'roleid' => self::$roleids['super-admin role for reports'],
				'usrgrps' => [
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test']
					]
				]
			],
			[
				'username' => 'second super-admin report permissions',
				'passwd' => 'xibbaz123',
				'roleid' => self::$roleids['super-admin role for reports'],
				'usrgrps' => [
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test']
					]
				]
			]
		]);
		$this->assertArrayHasKey('userids', $users);
		self::$userids = CDataHelper::getIds('username');

		$dashboards = CDataHelper::call('dashboard.create', [
			[
				'name' => 'dashboard for report permissions',
				'userid' => 1,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5
							]
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('dashboardids', $dashboards);
		self::$dashboardids = CDataHelper::getIds('name');

		$reports = CDataHelper::call('report.create', [
			[
				'userid' => 1,
				'name' => 'report to check users without permissions',
				'dashboardid' => '1',
				'users' => [
					[
						'userid' => 1,
						'access_userid' => 1
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => 7,
						'access_userid' => 1
					]
				]
			],
			[
				'userid' => 1,
				'name' => 'report to check the dashboard change',
				'dashboardid' => self::$dashboardids['dashboard for report permissions'],
				'users' => [
					[
						'userid' => 1,
						'access_userid' => 1
					],
					[
						'userid' => 40
					],
					[
						'userid' => 50,
						'access_userid' => self::$userids['super-admin report permissions']
					],
					[
						'userid' => self::$userids['super-admin report permissions'],
						'access_userid' => self::$userids['super-admin report permissions']
					],
					[
						'userid' => self::$userids['admin without report permissions']
					],
					[
						'userid' => self::$userids['super-admin without report permissions'],
						'access_userid' => 1
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => 7,
						'access_userid' => 1
					],
					[
						'usrgrpid' => 8
					],
					[
						'usrgrpid' => 11,
						'access_userid' => self::$userids['super-admin report permissions']
					],
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test'],
						'access_userid' => self::$userids['super-admin report permissions']
					],
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test third']
					],
					[
						'usrgrpid' => self::$usergroupids['usergroup for report test second'],
						'access_userid' => 1
					]
				]
			]
		]);
		$this->assertArrayHasKey('reportids', $reports);
	}

	public static function getUsersWithoutPermissions() {
		return [
			[
				[
					'alias' => 'admin without report permissions',
					'password' => 'xibbaz123',
					'type' => 'admin'
				]
			],
			[
				[
					'alias' => 'super-admin without report permissions',
					'password' => 'xibbaz123',
					'type' => 'super-admin'
				]
			]
		];
	}

	/**
	 * Users without option "Manage scheduled reports" in the role can only view the report.
	 *
	 * @dataProvider getUsersWithoutPermissions
	 */
	public function testScheduledReportPermissions_UsersWithoutPermissions($data) {
		$report = 'report to check users without permissions';
		// User with admin type can't see other user alias or user group name.
		$owner = ($data['type'] === 'admin') ? 'Inaccessible user' : 'Admin (Zabbix Administrator)';
		$group = ($data['type'] === 'admin') ? 'Inaccessible user group' : 'Zabbix administrators';
		$this->page->userLogin($data['alias'], $data['password']);

		// Check report in dashboard.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
		$this->query('id:dashboard-actions')->one()->waitUntilClickable()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($popup->hasItems('View related reports'));
		// "Create new report" option is not available in dashboard.
		$this->assertFalse($popup->query('xpath://a[@aria-label="Create new report"]')->one(false)->isValid());

		// Check report on reports list page.
		$this->page->open('zabbix.php?action=scheduledreport.list')->waitUntilReady();
		$this->assertFalse($this->query('button:Create report')->one()->isEnabled());
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', $report);
		// Check the visibility of the user's alias for different types of users.
		$this->assertEquals($owner, $row->getColumn('Owner')->getText());

		// Check the report form.
		$this->query('link', $report)->one()->click();
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		foreach ($form->getLabels()->asText() as $label) {
			if ($label === 'Owner') {
				$form->checkValue(['Owner' => $owner]);
			}

			if ($label === 'Start time') {
				$time_container = $form->getFieldContainer('Start time');
				$this->assertFalse($time_container->query('id:hours')->one()->isEnabled());
				$this->assertFalse($time_container->query('id:minutes')->one()->isEnabled());
				continue;
			}

			if ($label === 'Subscriptions') {
				$subscriptions = $form->getField('Subscriptions')->asTable();

				foreach ($subscriptions->getRows() as $row) {
					// User with admin type should see "Inaccessible user" in columns 'Generate report by' and 'Recipient'
					$this->assertEquals($owner, $row->getColumn('Generate report by')->getText());

					if ($row->getColumn('Recipient')->query('class:zi-user-filled-small')->one(false)->isValid()) {
						$this->assertEquals($owner, $row->getColumn('Recipient')->getText());
					}
					else {
						$this->assertEquals($group, $row->getColumn('Recipient')->getText());
					}

					// "Recipient", "Status" and "Action" columns should not be clickable.
					foreach ($row->getColumns() as $column) {
						$this->assertFalse($column->query('tag:a')->one(false)->isValid());
					}
				}

				continue;
			}

			// All fields are disabled.
			$this->assertFalse($form->getField($label)->isEnabled());
		}
	}

	/**
	 * Check the report cloning, when the admin user see inaccessible user/user groups and dashboard.
	 */
	public function testScheduledReportPermissions_Clone() {
		$report = 'report to check the dashboard change';
		$before = [
			'fields' => ['Dashboard' => 'Inaccessible dashboard', 'Owner' => 'Inaccessible user'],
			'Subscriptions' => [
				[
					'Recipient' => 'admin without report permissions',
					'Generate report by' => 'Recipient'
				],
				[
					'Recipient' => 'Inaccessible user',
					'Generate report by' => 'Inaccessible user'
				],
				[
					'Recipient' => 'Inaccessible user',
					'Generate report by' => 'Recipient'
				],
				[
					'Recipient' => 'Inaccessible user',
					'Generate report by' => 'super-admin report permissions'
				],
				[
					'Recipient' => 'Inaccessible user group',
					'Generate report by' => 'Inaccessible user'
				],
				[
					'Recipient' => 'Inaccessible user group',
					'Generate report by' => 'Recipient'
				],
				[
					'Recipient' => 'Inaccessible user group',
					'Generate report by' => 'super-admin report permissions'
				],
				[
					'Recipient' => 'super-admin report permissions',
					'Generate report by' => 'super-admin report permissions'
				],
				[
					'Recipient' => 'super-admin without report permissions',
					'Generate report by' => 'Inaccessible user'
				],
				[
					'Recipient' => 'usergroup for report test',
					'Generate report by' => 'super-admin report permissions'
				],
				[
					'Recipient' => 'usergroup for report test second',
					'Generate report by' => 'Inaccessible user'
				],
				[
					'Recipient' => 'usergroup for report test third',
					'Generate report by' => 'Recipient'
				]
			]
		];
		// After cloning the report, all "Inaccessible" entities should be changed to current user or removed.
		$after = [
			'fields' => ['Owner' => 'admin report permissions'],
			'Subscriptions' => [
				[
					'Recipient' => 'admin without report permissions',
					'Generate report by' => 'Recipient'
				],
				[
					'Recipient' => 'super-admin report permissions',
					'Generate report by' => 'admin report permissions'
				],
				[
					'Recipient' => 'super-admin without report permissions',
					'Generate report by' => 'admin report permissions'
				],
				[
					'Recipient' => 'usergroup for report test',
					'Generate report by' => 'admin report permissions'
				],
				[
					'Recipient' => 'usergroup for report test second',
					'Generate report by' => 'admin report permissions'
				],
				[
					'Recipient' => 'usergroup for report test third',
					'Generate report by' => 'Recipient'
				]
			]
		];

		$this->page->userLogin('admin report permissions', 'xibbaz123');
		$this->page->open('zabbix.php?action=scheduledreport.list')->waitUntilReady();
		$this->query('link', $report)->waitUntilClickable()->one()->click();
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->checkValue($before['fields']);
		$this->assertTableData($before['Subscriptions'], 'id:subscriptions-table');

		$this->query('button:Clone')->one()->click();
		$this->page->waitUntilReady();
		$form->invalidate();
		$form->checkValue($after['fields']);
		$this->assertTableData($after['Subscriptions'], 'id:subscriptions-table');
		$form->fill(['Name' => 'check permission after clone', 'Dashboard' => 'Global view']);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Scheduled report added');

		$this->query('link', 'check permission after clone')->waitUntilClickable()->one()->click();
		$form->invalidate();
		$after['fields']['Name'] = 'check permission after clone';
		$after['fields']['Dashboard'] = 'Global view';
		$form->checkValue($after['fields']);
		$this->assertTableData($after['Subscriptions'], 'id:subscriptions-table');
	}

	public static function getReportData() {
		return [
			[
				[
					'alias' => 'admin report permissions',
					'password' => 'xibbaz123',
					'Subscriptions' => [
						[
							'Recipient' => 'admin without report permissions',
							'Generate report by' => 'Recipient'
						],
						[
							'Recipient' => 'super-admin report permissions',
							'Generate report by' => 'admin report permissions'
						],
						[
							'Recipient' => 'super-admin without report permissions',
							'Generate report by' => 'admin report permissions'
						],
						[
							'Recipient' => 'usergroup for report test',
							'Generate report by' => 'admin report permissions'
						],
						[
							'Recipient' => 'usergroup for report test second',
							'Generate report by' => 'admin report permissions'
						],
						[
							'Recipient' => 'usergroup for report test third',
							'Generate report by' => 'Recipient'
						]
					]
				]
			],
			[
				[
					'alias' => 'second super-admin report permissions',
					'password' => 'xibbaz123',
					'Subscriptions' => [
						[
							'Recipient' => 'Admin (Zabbix Administrator)',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'admin-zabbix',
							'Generate report by' => 'Recipient'
						],
						[
							'Recipient' => 'admin without report permissions',
							'Generate report by' => 'Recipient'
						],
						[
							'Recipient' => 'Enabled debug mode',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'Guests',
							'Generate report by' => 'Recipient'
						],
						[
							'Recipient' => 'super-admin report permissions',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'super-admin without report permissions',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'user-zabbix',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'usergroup for report test',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'usergroup for report test second',
							'Generate report by' => 'second super-admin report permissions'
						],
						[
							'Recipient' => 'usergroup for report test third',
							'Generate report by' => 'Recipient'
						],
						[
							'Recipient' => 'Zabbix administrators',
							'Generate report by' => 'second super-admin report permissions'
						]
					]
				]
			]
		];
	}

	/**
	 * After changing dashboard, all "Generate report by" users should be set to the current user.
	 *
	 * @dataProvider getReportData
	 *
	 * @backup report
	 */
	public function testScheduledReportPermissions_ChangeDashboard($data) {
		$report = 'report to check the dashboard change';
		$this->page->userLogin($data['alias'], $data['password']);
		$this->page->open('zabbix.php?action=scheduledreport.list')->waitUntilReady();
		$this->query('link', $report)->waitUntilClickable()->one()->click();
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->fill(['Dashboard' => 'Global view']);
		$form->submit();
		$overlay = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('Report generated by other users will be changed to the current user.',
				$overlay->query('class:overlay-dialogue-body')->one()->getText());
		$overlay->query('button:OK')->one()->click();
		$this->assertMessage(TEST_GOOD, 'Scheduled report updated');
		$this->query('link', $report)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->assertTableData($data['Subscriptions'], 'id:subscriptions-table');
	}

	public static function getCreateData() {
		return [
			[
				[
					'alias' => 'admin report permissions',
					'password' => 'xibbaz123',
					'type' => 'admin'
				]
			],
			[
				[
					'alias' => 'super-admin report permissions',
					'password' => 'xibbaz123',
					'type' => 'super-admin'
				]
			]
		];
	}

	/**
	 * Check that report owner value is current logged in user and for admin type 'owner' field is disabled.
	 *
	 * @dataProvider getCreateData
	 */
	public function testScheduledReportPermissions_Create($data) {
		$state = ($data['type'] === 'admin') ? false : true;

		// Check create form on page.
		$this->page->userLogin($data['alias'], $data['password']);
		$this->page->open('zabbix.php?action=scheduledreport.list')->waitUntilReady();
		$this->page->query('button:Create report')->waitUntilClickable()->one()->click();
		$form = $this->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->checkValue(['Owner' => $data['alias']]);
		$this->assertTrue($form->getField('Owner')->isEnabled($state));

		// Check create form on dashboard.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid=1')->waitUntilReady();
		$this->query('id:dashboard-actions')->waitUntilClickable()->one()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Create new report');
		$overlay = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $overlay->query('id:scheduledreport-form')->waitUntilVisible()->asForm()->one();
		$form->checkValue(['Owner' => $data['alias']]);
		$this->assertTrue($form->getField('Owner')->isEnabled($state));
	}

	public static function getDeleteData() {
		return [
			[
				[
					'url' => 'dashboard.list',
					'name' => 'dashboard for report permissions',
					'error' => 'Cannot delete dashboard',
					'details' => 'Dashboard "dashboard for report permissions" is used in report "report to check the dashboard change".'
				]
			],
			[
				[
					'url' => 'user.list',
					'name' => 'super-admin report permissions',
					'error' => 'Cannot delete user',
					'details' => 'User "super-admin report permissions" is user on whose behalf report "report to check the dashboard change" is created.'
				]
			],
			[
				[
					'url' => 'user.list',
					'name' => 'user-recipient of the report',
					'error' => 'Cannot delete user',
					'details' => 'User "user-recipient of the report" is report "Report for delete" recipient.'
				]
			],
			[
				[
					'url' => 'usergroup.list',
					'name' => 'usergroup for report test third',
					'error' => 'Cannot delete user group',
					'details' => 'User group "usergroup for report test third" is report "report to check the dashboard change" recipient.'
				]
			]
		];
	}

	/**
	 * Check that cannot delete a user, user group, and dashboard if they are linked to the report.
	 *
	 * @dataProvider getDeleteData
	 *
	 * @backupOnce profiles
	 */
	public function testScheduledReportPermissions_Delete($data) {
		$this->page->userLogin('Admin', 'zabbix');
		$this->page->open('zabbix.php?action='.$data['url'])->waitUntilReady();
		$this->query('link', $data['name'])->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		if ($data['url'] === 'dashboard.list') {
			$this->query('id:dashboard-actions')->waitUntilClickable()->one()->click();
			CPopupMenuElement::find()->waitUntilVisible()->one()->select('Delete');
		}
		else {
			$this->query('button:Delete')->waitUntilClickable()->one()->click();
		}
		$this->page->acceptAlert();
		$this->assertMessage(TEST_BAD, $data['error'], $data['details']);
	}
}
