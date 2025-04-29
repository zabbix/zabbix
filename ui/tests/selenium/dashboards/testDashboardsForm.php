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
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup dashboard, profiles
 *
 * @onBefore prepareDashboardData
 *
 * @dataSource LoginUsers, UserPermissions
 */
class testDashboardsForm extends CWebTest {

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

	/**
	 * Dashboard ids grouped by name.
	 *
	 * @var array
	 */
	protected static $ids;

	/**
	 * Get all dashboard related tables hash values.
	 */
	public static function getHash() {
		return [
			'dashboard' => CDBHelper::getHash('SELECT * FROM dashboard'),
			'dashboard_user' =>	CDBHelper::getHash('SELECT * FROM dashboard_user ORDER by dashboard_userid'),
			'dashboard_usrgrp' => CDBHelper::getHash('SELECT * FROM dashboard_usrgrp ORDER by dashboard_usrgrpid'),
			'dashboard_page' => CDBHelper::getHash('SELECT * FROM dashboard_page ORDER by dashboard_pageid'),
			'widget' => CDBHelper::getHash('SELECT * FROM widget ORDER by widgetid')
		];
	}

	/**
	 * Default values of dashboard properties.
	 */
	private $default_values = [
		'Owner' => 'Admin (Zabbix Administrator)',
		'Name' => 'New dashboard',
		'Default page display period' => '30 seconds',
		'Start slideshow automatically' => true
	];

	/**
	 * Dashboard properties for cancellation test.
	 */
	private $update_values = [
		'Owner' => 'guest',
		'Name' => 'Dashboard to test properties changes',
		'Default page display period' => '1 hour',
		'Start slideshow automatically' => false
	];

	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for update',
				'userid' => 2,
				'display_period' => 60,
				'auto_start' => 0,
				'private' => 1,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for clone and delete',
				'userid' => 2,
				'display_period' => 3600,
				'auto_start' => 0,
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page name',
						'display_period' => 1800,
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Custom clock name',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'time_type',
										'value' => 1
									]
								]
							]
						]
					]
				],
				'users' => [
					[
						'userid' => 1,
						'permission' => 3
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => 7,
						'permission' => 2
					]
				]
			],
			[
				'name' => 'Dashboard for share',
				'userid' => 1,
				'pages' => [[]],
				'users' => [
					[
						'userid' => 40,
						'permission' => 3
					],
					[
						'userid' => CDataHelper::get('LoginUsers.userids.disabled-user'),
						'permission' => 2
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => 11,
						'permission' => 2
					],
					[
						'usrgrpid' => 12,
						'permission' => 3
					]
				]
			]
		]);
		$this->assertArrayHasKey('dashboardids', $response);
		self::$ids = CDataHelper::getIds('name');
	}

	public function testDashboardsForm_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.list')->waitUntilReady();
		$this->query('button:Create dashboard')->one()->click();
		$this->page->assertHeader('New dashboard');
		$this->page->assertTitle('Dashboard');
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$form = $dialog->asForm();

		// Check default values.
		$form->checkValue($this->default_values);
		$this->assertEquals('255', $form->query('id:name')->one()->getAttribute('maxlength'));

		// Check available display periods.
		$this->assertEquals(['10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'],
				$form->getField('Default page display period')->getOptions()->asText()
		);

		// Close the dialog.
		$dialog->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Check if dashboard is empty.
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->isEmpty());

		// Cancel dashboard editing.
		$dashboard->cancelEditing();
	}

	public static function getPropertiesData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Name' => ''
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Name' => '   '
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Owner' => ''
					],
					'error_message' => 'Field "userid" is mandatory.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'dashboard_properties' => [
						'Name' => 'Global view'
					],
					'error_message' => 'Dashboard "Global view" already exists.',
					'save_dashboard' => true
				]
			],
			// Creation with default values or simple update without data changes.
			[
				[
					'expected' => TEST_GOOD,
					'dashboard_properties' => []
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'dashboard_properties' => [
						'Name' => 'Empty dashboard'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'dashboard_properties' => [
						'Name' => '!@#$%^&*()_+=-09[]{};:\'"',
						'Default page display period' => '10 seconds'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'dashboard_properties' => [
						'Owner' => 'guest',
						'Name' => 'кириллица',
						'Start slideshow automatically' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'dashboard_properties' => [
						'Owner' => 'guest',
						'Name' => '☺æų☺',
						'Default page display period' => '1 minute',
						'Start slideshow automatically' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'dashboard_properties' => [
						'Name' => '    Trailing & leading spaces    ',
						'Start slideshow automatically' => false
					],
					'trim' => true
				]
			]
		];
	}

	/**
	 * Check validation of the Dashboard properties overlay dialog when creating a dashboard.
	 *
	 * @dataProvider getPropertiesData
	 */
	public function testDashboardsForm_Create($data) {
		$old_hash = ($data['expected'] === TEST_BAD) ? $this->getHash() : null;
		$this->page->login()->open('zabbix.php?action=dashboard.view&new=1');
		$dashboard = CDashboardElement::find()->one();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->checkProperties($data, 'create', $dashboard, $dialog, $old_hash);
	}

	/**
	 * Check validation of the Dashboard properties overlay dialog when updating a dashboard.
	 *
	 * @dataProvider getPropertiesData
	 */
	public function testDashboardsForm_Update($data) {
		$old_hash = ($data['expected'] === TEST_BAD || empty($data['dashboard_properties'])) ? $this->getHash() : null;

		if (CTestArrayHelper::get($data, 'dashboard_properties.Name', false) && $data['expected'] === TEST_GOOD) {
			$data['dashboard_properties']['Name'] = $data['dashboard_properties']['Name'].microtime();
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for update']);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();
		$dialog = $dashboard->editProperties();
		$this->checkProperties($data, 'update', $dashboard, $dialog, $old_hash);
	}

	/**
	 * Check dashboard properties after form submit.
	 *
	 * @param array					$data			data provider
	 * @param string				$action			action that should be checked, create or update dashboard
	 * @param CDashboardElement		$dashboard		dashboard element
	 * @param COverlayDialogElement	$dialog			dashboard properties overlay dialog
	 * @param array					$old_hash		hashes values before form submit
	 */
	private function checkProperties($data, $action, $dashboard, $dialog, $old_hash = null) {
		$form = $dialog->asForm();
		$form->fill($data['dashboard_properties']);
		$form->submit();

		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data['dashboard_properties']['Name'] = trim($data['dashboard_properties']['Name']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			if (CTestArrayHelper::get($data, 'save_dashboard')) {
				$this->query('button:Save changes')->one()->click();
				$this->assertMessage(TEST_BAD, 'Failed to '.$action.' dashboard', $data['error_message']);
			}
			else {
				$this->assertMessage(TEST_BAD, null, $data['error_message']);
				$form->invalidate();
				$form->checkValue($data['dashboard_properties']);
				$dialog->close();
			}

			$dashboard->cancelEditing();
			$this->assertEquals($old_hash, $this->getHash());
		}
		else {
			COverlayDialogElement::ensureNotPresent();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard '.$action.'d');
			$default_name = ($action === 'create') ? $this->default_values['Name'] : 'Dashboard for update';

			if (CTestArrayHelper::get($data, 'trim', false)) {
				$title = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $data['dashboard_properties']['Name'])));
			}
			else {
				$title = CTestArrayHelper::get($data, 'dashboard_properties.Name', $default_name);
			}

			$this->assertEquals($title, $dashboard->getTitle());

			// Open dashboard from dashboard list.
			$this->page->login()->open('zabbix.php?action=dashboard.list')->waitUntilReady();
			$this->query('link', $title)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$dashboard->edit();

			if (empty($data['dashboard_properties'])) {
				if ($action === 'create') {
					$data['dashboard_properties'] = $this->default_values;
				}
				else {
					$this->assertEquals($old_hash, $this->getHash());
				}
			}

			// Check dashboard properties.
			$dashboard->editProperties();
			$form->invalidate();
			$form->checkValue($data['dashboard_properties']);
			$dashboard->cancelEditing();
		}
	}

	public static function getCancelCreateData() {
		return [
			[
				[
					'save_properties' => true
				]
			],
			[
				[
					'save_properties' => false,
					'opened_dashboard' => 'Global view'
				]
			]
		];
	}

	/**
	 * Test cancelling dashboard creation and remembering the previous dashboard page.
	 *
	 * @dataProvider getCancelCreateData
	 */
	public function testDashboardsForm_CancelCreate($data) {
		$old_hash = $this->getHash();

		$this->page->login()->open('zabbix.php?action=dashboard.list')->waitUntilReady();

		if (CTestArrayHelper::get($data, 'opened_dashboard', false)) {
			$this->query('link', $data['opened_dashboard'])->one()->click();
			$this->page->assertHeader($data['opened_dashboard']);
			$this->page->assertTitle('Dashboard');
			$this->query('id:dashboard-actions')->one()->click();
			CPopupMenuElement::find()->waitUntilVisible()->one()->select('Create new');
		}
		else {
			$this->query('button:Create dashboard')->one()->click();
		}

		$dashboard = CDashboardElement::find()->one();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$form->fill($this->update_values);

		// Save dashboard properties or discard changes to the dashboard properties.
		if ($data['save_properties']) {
			$form->submit();
		}
		else {
			$dialog->close();
		}

		$dashboard->cancelEditing();

		if (CTestArrayHelper::get($data, 'opened_dashboard', false)) {
			$url = 'zabbix.php?action=dashboard.view&dashboardid=1';
			$title = $data['opened_dashboard'];
		}
		else {
			$url = 'zabbix.php?action=dashboard.list';
			$title = 'Dashboards';
		}

		$this->page->assertHeader($title);
		$this->assertEquals(PHPUNIT_URL . $url, $this->page->getCurrentUrl());
		$this->assertEquals($old_hash, $this->getHash());
	}

	public static function getCancelData() {
		return [
			[
				[
					'action' => 'update',
					'save_properties' => true
				]
			],
			[
				[
					'action' => 'update',
					'save_properties' => false
				]
			],
			[
				[
					'action' => 'Delete'
				]
			],
			[
				[
					'action' => 'Clone',
					'save_properties' => true
				]
			]
		];
	}

	/**
	 * Test cancel dashboard update, delete and clone.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardsForm_Cancel($data) {
		$old_hash = $this->getHash();

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for update']);
		$dashboard = CDashboardElement::find()->one();

		// Open dashboard properties overlay dialog for update and clone action.
		if ($data['action'] === 'update') {
			$dashboard->edit();
			$dialog = $dashboard->editProperties();
		}
		else {
			$this->query('id:dashboard-actions')->one()->click();
			CPopupMenuElement::find()->waitUntilVisible()->one()->select($data['action']);

			if ($data['action'] === 'Delete') {
				$this->assertEquals('Delete dashboard?', $this->page->getAlertText());
				$this->page->dismissAlert();
				$this->assertEquals($old_hash, $this->getHash());
				return;
			}

			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		}

		// Change dashboard properties.
		$form = $dialog->asForm();
		$form->fill($this->update_values);

		if ($data['save_properties']) {
			$form->submit();
			// Cancel saving the dashboard if the dashboard properties have changed.
			$dashboard->cancelEditing();
		}
		else {
			$dialog->close();
			// Save the dashboard if the dashboard properties haven't changed.
			$dashboard->save();
		}

		$this->assertEquals($old_hash, $this->getHash());
	}

	public function testDashboardsForm_Clone() {
		$original_values = [
			'Name' => 'Dashboard for clone and delete',
			'Owner' => 'guest',
			'Default page display period' => '1 hour',
			'Start slideshow automatically' => false
		];
		$cloned_name = 'Cloned dashboard';
		$original_hashes = $this->getDashboardHashes($original_values['Name']);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$ids['Dashboard for clone and delete'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();

		// Clone dashboard.
		$this->query('id:dashboard-actions')->one()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Clone');
		$this->assertEquals($original_values['Name'], $dashboard->getTitle());
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$form = $dialog->asForm();

		// Check the properties values of the cloned dashboard.
		$original_values['Owner'] = 'Admin (Zabbix Administrator)';
		$form->checkValue($original_values);

		// Change name and save dashboard properties.
		$form->fill(['Name' => $cloned_name]);
		$original_values['Name'] = $cloned_name;
		$form->submit();
		$dashboard->save();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard created');
		$this->assertEquals($cloned_name, $dashboard->getTitle());
		$dashboard->getWidget('Custom clock name');
		$dashboard->edit();
		$dashboard->editProperties();
		$form->invalidate();

		// Check and compare dashboard properties and hashes.
		$form->checkValue($original_values);
		$actual_hashes = $this->getDashboardHashes($cloned_name);
		$this->assertEquals($original_hashes, $actual_hashes);

		$dashboard->cancelEditing();
	}

	/**
	 * Get all related dashboard tables hashes for cloning test.
	 *
	 * @param string $name		dashboard name
	 *
	 * @return array
	 */
	private function getDashboardHashes($name) {
		$ids = [];
		$query_value = $name;
		$query_id = [
			'dashboardid' => 'SELECT dashboardid FROM dashboard WHERE name=',
			'pageid' => 'SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid=',
			'widgetid' => 'SELECT widgetid FROM widget WHERE dashboard_pageid='
		];

		foreach ($query_id as $id => $hash) {
			$ids[$id] = CDBHelper::getValue($hash . zbx_dbstr($query_value));
			// Save previous id value for next query.
			$query_value = $ids[$id];
		}

		$result = [];
		$query_hash = [
			'dashboard' => 'SELECT private, templateid, display_period, auto_start, uuid FROM dashboard WHERE dashboardid='.$ids['dashboardid'],
			'dashboard_user' => 'SELECT userid, permission FROM dashboard_user WHERE dashboardid='.$ids['dashboardid'],
			'dashboard_usrgrp' => 'SELECT usrgrpid, permission FROM dashboard_usrgrp WHERE dashboardid='.$ids['dashboardid'],
			'dashboard_page' => 'SELECT name, display_period, sortorder FROM dashboard_page WHERE dashboard_pageid='.$ids['pageid'],
			'widget' => 'SELECT type, name, x, y, width, height, view_mode FROM widget WHERE dashboard_pageid='.$ids['pageid'],
			'widget_field' => 'SELECT type, name, value_int, value_str, value_groupid FROM widget_field WHERE widgetid='.$ids['widgetid']
		];
		foreach ($query_hash as $table => $hash) {
			$result[$table] = CDBHelper::getHash($hash);
		}

		return $result;
	}

	public static function getShareData() {
		return [
			// Add new user.
			[
				[
					'dashboard' => 'Dashboard for update',
					'groups' => [
						[
							'name' => 'Zabbix administrators'
						]
					]
				]
			],
			// Add new group.
			[
				[
					'dashboard' => 'Dashboard for update',
					'users' => [
						[
							'name' => 'Admin',
							'full_name' => 'Admin (Zabbix Administrator)'
						]
					]
				]
			],
			// Add new user and group.
			[
				[
					'dashboard' => 'Dashboard for update',
					'type' => 'Public',
					'groups' => [
						[
							'name' => 'Guests'
						]
					],
					'users' => [
						[
							'name' => 'guest'
						]
					]
				]
			],
			// Update existen user and group permissions.
			[
				[
					'dashboard' => 'Dashboard for clone and delete',
					'type' => 'Private',
					'groups' => [
						[
							'action' => USER_ACTION_UPDATE,
							'name' => 'Zabbix administrators',
							'permissions' => 'Read-write'
						]
					],
					'users' => [
						[
							'action' => USER_ACTION_UPDATE,
							'name' => 'Admin (Zabbix Administrator)',
							'permissions' => 'Read-only'
						]
					]
				]
			],
			// Add, update and remove user and groups.
			[
				[
					'dashboard' => 'Dashboard for share',
					'groups' => [
						[
							'name' => 'Selenium user group',
							'permissions' => 'Read-only'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'name' => 'Enabled debug mode',
							'permissions' => 'Read-write'
						],
						[
							'action' => USER_ACTION_REMOVE,
							'name' => 'No access to the frontend'
						]
					],
					'users' => [
						[
							'name' => 'user-zabbix',
							'permission' => 'Read-write'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'name' => 'admin-zabbix',
							'permissions' => 'Read-only'
						],
						[
							'action' => USER_ACTION_REMOVE,
							'name' => 'disabled-user'
						]
					]
				]
			],
			// Add all users and groups.
			[
				[
					'dashboard' => 'Dashboard for share',
					'groups' => ['all'],
					'users' => ['all']
				]
			]
		];
	}

	/**
	 * Test dashboard sharing form.
	 *
	 * @dataProvider getShareData
	 */
	public function testDashboardsForm_SharingPopup($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids[$data['dashboard']]);
		CDashboardElement::find()->one()->waitUntilReady();
		$this->query('id:dashboard-actions')->one()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Sharing');
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Dashboard sharing', $dialog->getTitle());
		$form = $dialog->asForm();

		// Fill form.
		$type = CTestArrayHelper::get($data, 'type', 'Private');
		$form->fill(['Type' => $type]);
		$this->fillSharingForm(CTestArrayHelper::get($data, 'groups', false), 'User groups');
		$this->fillSharingForm(CTestArrayHelper::get($data, 'users', false), 'Users');
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check sharing popup form.
		$this->query('id:dashboard-actions')->one()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Sharing');
		$form->invalidate();
		$form->checkValue(['Type' => $type]);
		$this->checkSharingForm(CTestArrayHelper::get($data, 'users', false), 'Users', $form, $type);
		$this->checkSharingForm(CTestArrayHelper::get($data, 'groups', false), 'User groups', $form, $type);
		$dialog->close();
	}

	/**
	 * Fill dashboard sharing form.
	 *
	 * @param array	 $data		users or user groups data
	 * @param string $list		users or user groups list
	 */
	private function fillSharingForm($data, $list) {
		if ($data) {
			$dialog = COverlayDialogElement::find()->one();
			$form = $dialog->asForm();
			$table = $form->getField(($list === 'Users') ? 'List of user shares' : 'List of user group shares')->asTable();

			foreach ($data as $share) {
				$action = CTestArrayHelper::get($share, 'action', USER_ACTION_ADD);

				switch ($action) {
					case USER_ACTION_ADD;
						$rows = $table->getRows()->count();
						$table->query('button:Add')->one()->click();
						$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

						if ($share === 'all') {
							// Count row with Add button.
							$rows = 1;
							$add_rows = $dialog->asTable()->getRows()->count();
							$this->selectTableRows();
							$dialog->query('button:Select')->one()->click();
						}
						else {
							$dialog->query('link', $share['name'])->one()->click();
							$add_rows = 1;
						}

						// Wait until new table row appears.
						$table->query('xpath://tbody/tr['.($rows + $add_rows).']')->waitUntilPresent();

						if (CTestArrayHelper::get($share, 'permissions', false)) {
							$row = $table->findRow($list, $share['name']);
							$row->getColumn('Permissions')->asSegmentedRadio()->fill($share['permissions']);
						}
						break;

					case USER_ACTION_UPDATE;
						$row = $table->findRow($list, $share['name']);
						$row->getColumn('Permissions')->asSegmentedRadio()->fill($share['permissions']);
						break;

					case USER_ACTION_REMOVE;
						$row = $table->findRow($list, $share['name']);
						$row->getColumn('Action')->query('button:Remove')->one()->click();
						// Wait until table row disappears.
						$row->waitUntilNotPresent();
						break;
				}
			}
		}
	}

	/**
	 * Check form of dashboard share after changes.
	 *
	 * @param array		   $data		users or user groups data
	 * @param string	   $list		users or user groups list of shares
	 * @param CFormElement $form		form element of dashboard share
	 * @param string	   $type		dashboard sharing type, private or public
	 */
	private function checkSharingForm($data, $list, $form, $type) {
		if ($data) {
			$table = $form->getField(($list === 'Users') ? 'List of user shares' : 'List of user group shares')->asTable();

			if ($data[0] === 'all') {
				if ($list === 'Users') {
					$query = 'SELECT username FROM users';
					$key = 'username';
					$selector = 'xpath://label[text()="List of user shares"]/ancestor::li//table';
				}
				else {
					$query = 'SELECT name FROM usrgrp';
					$key = 'name';
					$selector = 'xpath://label[text()="List of user group shares"]/ancestor::li//table';
				}

				$db_names = CDBHelper::getAll($query);

				// Database result format is [['username' => 'Admin'], ['username' => 'Tag-user']]
				foreach ($db_names as $array) {
					// Result format should be ['Admin', 'Tag-user'] to compare with table result in UI.
					$result[] = $array[$key];
				}

				natcasesort($result);
				$result = array_values($result);

				// Add name and surname to Admin user.
				if ($list === 'Users' && $result[0] === 'Admin') {
					$result[0] = 'Admin (Zabbix Administrator)';
				}

				$this->assertTableHasDataColumn($result, $list, $selector);

				return;
			}

			foreach ($data as $share) {
				$action = CTestArrayHelper::get($share, 'action', USER_ACTION_ADD);

				if ($action === USER_ACTION_ADD || $action === USER_ACTION_UPDATE) {
					// Default permission value depends on the sharing type.
					$default_permissions = ($type === 'Private') ? 'Read-only' : 'Read-write';
					$row = $table->findRow($list, CTestArrayHelper::get($share, 'full_name', $share['name']));
					$this->assertEquals(CTestArrayHelper::get($share, 'permissions', $default_permissions),
							$row->getColumn('Permissions')->asSegmentedRadio()->getValue()
					);
				}
				else {
					$this->assertFalse($table->query('xpath://tbody/tr/td[text()='.
							CXPathHelper::escapeQuotes($share['name']).']')->one(false)->isValid()
					);
				}
			}
		}
	}

	public function testDashboardsForm_Delete() {
		$pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid='.
				zbx_dbstr(self::$ids['Dashboard for clone and delete']));
		$widgetid = CDBHelper::getValue('SELECT widgetid FROM widget WHERE dashboard_pageid='.zbx_dbstr($pageid));

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$ids['Dashboard for clone and delete']);
		CDashboardElement::find()->one()->waitUntilReady();
		$this->query('id:dashboard-actions')->one()->click();
		CPopupMenuElement::find()->waitUntilVisible()->one()->select('Delete');
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Dashboard deleted');

		// Check related dashboard tables.
		$tables = [
			'SELECT NULL FROM dashboard_page dp INNER JOIN dashboard d'.
					' ON d.dashboardid=dp.dashboardid WHERE d.dashboardid='.zbx_dbstr(self::$ids['Dashboard for clone and delete']),
			'SELECT NULL FROM dashboard_user WHERE dashboardid='.zbx_dbstr(self::$ids['Dashboard for clone and delete']),
			'SELECT NULL FROM dashboard_usrgrp WHERE dashboardid='.zbx_dbstr(self::$ids['Dashboard for clone and delete']),
			'SELECT NULL FROM widget_field wf INNER JOIN widget w ON w.widgetid=wf.widgetid WHERE w.widgetid='.$widgetid
		];
		foreach ($tables as $query) {
			$this->assertEquals(0, CDBHelper::getCount($query));
		}
	}
}
