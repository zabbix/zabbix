<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * @onBefore prepareData
 *
 * @dataSource LoginUsers, ScheduledReports, UserPermissions
 *
 * @backup users
 */
class testPageUsers extends CWebTest {

	/**
	 * Attach MessageBehavior, CTableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class,
			CMessageBehavior::class
		];
	}

	protected static $time;
	protected static $users_count;

	const LINK = 'zabbix.php?action=user.list';
	const USERS_SQL = 'SELECT * FROM users ORDER BY userid';

	/**
	 * Data for CheckLayout, CheckFilter and MassDelete scenarios.
	 */
	public function prepareData() {
		CDataHelper::call('user.create', [
			[
				'username' => 'Ne-w admin абц 頑張って 😀',
				'name' => 'A Zabbixer ц-頑😀',
				'surname' => 'B Administratory д-張😀',
				'passwd' => 'xibbaz123',
				'roleid' => 2,
				'usrgrps' => [
					[
						'usrgrpid' => 7
					]
				]
			]
		]);

		CDataHelper::call('user.update', [
			[
				'userid' => 1,
				'medias' => [
					[
						'mediatypeid' => 71, // Discord.
						'sendto' => 'test@zabbix.com',
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 16,
						'period' => '1-7,00:00-24:00'
					],
					[
						'mediatypeid' => 78, // Jira.
						'sendto' => 'test_account',
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 63,
						'period' => '6-7,09:00-18:00'
					]
				]
			]
		]);

		// Add "Enabled debug mode" group to a user.
		CDataHelper::call('user.update', [
			[
				'userid' => 40,
				'usrgrps' => [
					[
						'usrgrpid' => 11
					]
				]
			]
		]);

		// Remove groups from a user.
		CDataHelper::call('user.update', [
			[
				'userid' => CDataHelper::get('LoginUsers.userids.test-user'),
				'usrgrps' => []
			]
		]);

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Testing share dashboard',
				'userid' => '9',
				'private' => 0,
				'pages' => [[]]
			]
		]);

		self::$users_count = CDBHelper::getCount(self::USERS_SQL);
	}

	public function testPageUsers_CheckLayout() {
		// Open Users page.
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->page->assertTitle('Configuration of users');
		$this->page->assertHeader('Users');

		// Check that all filter fields are present.
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->assertEquals(['Username', 'Name', 'Last name', 'User roles', 'User groups'], $form->getLabels()->asText());

		// Check placeholders.
		foreach (['filter_roles__ms', 'filter_usrgrpids__ms'] as $field_id) {
			$this->assertEquals('type here to search', $form->getField('id:'.$field_id)->getAttribute('placeholder'));
		}

		$select_dialogs = [
			'roles_' => 'User roles',
			'usrgrpids_' => 'User groups'
		];

		// Click Select buttons and check dialog titles.
		foreach ($select_dialogs as $id_suffix => $expected_title) {
			$form->query('xpath:.//div[@id="filter_'.$id_suffix.'"]/following::button[text()="Select"]')->waitUntilClickable()
					->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$this->assertEquals($expected_title, $dialog->getTitle());
			$dialog->query('button:Cancel')->one()->click();
			COverlayDialogElement::ensureNotPresent();
		}

		// Check maxlength.
		foreach (['Username', 'Name', 'Last name'] as $field) {
			$this->assertEquals(255, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check button states.
		$this->assertEquals(3, $this->query('button', ['Create user', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);
		$this->assertEquals(4, $this->query('button', ['Provision now', 'Reset TOTP secret', 'Unblock', 'Delete'])
				->all()->filter(CElementFilter::DISABLED)->count()
		);

		// Get filter element.
		$filter = CFilterElement::find()->one();

		// Check filter expanding/collapsing.
		$this->assertTrue($filter->isExpanded());
		foreach ([false, true] as $state) {
			$filter->expand($state);
			// Leave the page and reopen the previous page to make sure the filter state is still saved.
			$this->page->open('zabbix.php?action=host.list')->waitUntilReady();
			$this->page->open(self::LINK)->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		// Save the time in which table was loaded for later "Is online?" column value validation.
		$login_time = time();
		$is_online = [];
		for ($i = -1; $i <= 3; $i++) {
			$is_online[] = 'Yes ('.date('Y-m-d H:i:s', $login_time + $i).')';
		}

		// Check table headers and sortable headers.
		$table = $this->getTable();
		$this->assertEquals(['Username', 'Name', 'Last name', 'User role', 'Provisioned'], $table->getSortableHeaders()->asText());
		$this->assertEquals(['', 'Username', 'Name', 'Last name', 'User role', 'Groups', 'Is online?', 'Login', 'Frontend access',
				'API access', 'Debug mode', 'Status', 'Provisioned', 'Info'], $table->getHeadersText()
		);

		// Data for checking table rows in layout test.
		$table_data = [
			[
				'Username' => 'Admin',
				'Name' => 'Zabbix',
				'Last name' => 'Administrator',
				'User role' => 'Super admin role',
				'Groups' => 'Internal, Zabbix administrators',
				'Login' => 'Ok',
				'Frontend access' => 'Internal',
				'API access' => 'Enabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Enabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'admin-zabbix',
				'Name' => '',
				'Last name' => '',
				'User role' => 'Admin role',
				'Groups' => 'Enabled debug mode',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'System default',
				'API access' => 'Enabled',
				'Debug mode' => 'Enabled',
				'Status' => 'Enabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'disabled-user',
				'Name' => '',
				'Last name' => '',
				'User role' => 'User role',
				'Groups' => 'Disabled',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'System default',
				'API access' => 'Enabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Disabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'guest',
				'Name' => '',
				'Last name' => '',
				'User role' => 'Guest role',
				'Groups' => 'Disabled, Guests, Internal',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'Internal',
				'API access' => 'Disabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Disabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'LDAP user',
				'Name' => '',
				'Last name' => '',
				'User role' => 'Super admin role',
				'Groups' => 'LDAP user group',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'LDAP',
				'API access' => 'Enabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Enabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'no-access-to-the-frontend',
				'Name' => '',
				'Last name' => '',
				'User role' => 'User role',
				'Groups' => 'No access to the frontend',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'Disabled',
				'API access' => 'Enabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Enabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'test-user',
				'Name' => '',
				'Last name' => '',
				'User role' => 'User role',
				'Groups' => '',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'System default',
				'API access' => 'Enabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Enabled',
				'Provisioned' => ''
			],
			[
				'Username' => 'user-for-blocking',
				'Name' => '',
				'Last name' => '',
				'User role' => 'User role',
				'Groups' => 'Guests',
				'Is online?' => 'No',
				'Login' => 'Ok',
				'Frontend access' => 'System default',
				'API access' => 'Enabled',
				'Debug mode' => 'Disabled',
				'Status' => 'Enabled',
				'Provisioned' => '',
				'Info' => ''
			],
			[
				'Username' => 'Ne-w admin абц 頑張って 😀',
				'Name' => 'A Zabbixer ц-頑😀',
				'Last name' => 'B Administratory д-張😀'
			]
		];

		// Check rows in the table.
		$this->assertTableHasData($table_data);
		$this->assertTrue(in_array($table->findRow('Username', 'Admin')->getColumn('Is online?')->getText(), $is_online));

		// Check warning icon.
		$this->getTable()->findRow('Username', 'test-user')->getColumn('Info')->query('class:zi-i-warning')->one()->waitUntilClickable()->click();
		$this->assertEquals('User does not have user groups.', $this->query('class:hintbox-wrap')->one()->getText());

		// Data for checking link href.
		$expected_links = [
			'Username' => [
				'Admin' => 'zabbix.php?action=user.edit&userid=1'
			],
			'Groups' => [
				'Internal' => 'zabbix.php?action=usergroup.edit&usrgrpid=13',
				'Zabbix administrators' => 'zabbix.php?action=usergroup.edit&usrgrpid=7'
			]
		];

		// Check column value hrefs and that they are clickable.
		$row = $this->getTable()->findRow('Username', 'Admin');
		foreach ($expected_links as $column => $links) {
			foreach ($links as $link_text => $expected_href) {
				$link = $row->getColumn($column)->query('link', $link_text)->one();
				$this->assertTrue($link->isClickable(), $link_text.' in '.$column.' should be clickable');
				$this->assertEquals($expected_href, $link->getAttribute('href'));
			}
		}

		// Data for checking text color.
		$color_check_data = [
			'Admin' => [
				'Groups' => [
					'Internal' => 'green',
					'Zabbix administrators' => 'green'
				],
				'Is online?' => [
					'Yes' => 'green'
				],
				'Login' => [
					'Ok' => 'green'
				],
				'Frontend access' => [
					'Internal' => 'orange'
				],
				'API access' => [
					'Enabled' => 'green'
				],
				'Debug mode' => [
					'Disabled' => 'green'
				],
				'Status' => [
					'Enabled' => 'green'
				]
			],
			'admin-zabbix' => [
				'Is online?' => [
					'No' => 'red'
				],
				'Frontend access' => [
					'System default' => 'green'
				],
				'Debug mode' => [
					'Enabled' => 'orange'
				]
			],
			'guest' => [
				'Groups' => [
					'Disabled' => 'red',
					'Guests' => 'green',
					'Internal' => 'green'
				],
				'API access' => [
					'Disabled' => 'red'
				],
				'Status' => [
					'Disabled' => 'red'
				]
			],
			'LDAP user' => [
				'Frontend access' => [
					'LDAP' => 'green'
				]
			],
			'no-access-to-the-frontend' => [
				'Frontend access' => [
					'Disabled' => 'grey'
				]
			]
		];

		// Check text color by asserting class value.
		foreach ($color_check_data as $username => $columns) {
			$row = $this->getTable()->findRow('Username', $username);

			foreach ($columns as $column => $expected_pairs) {
				foreach ($expected_pairs as $expected_text => $expected_class) {
					if ($column === 'Is online?') {
						$column = $row->getColumn($column);
						$this->assertStringContainsString($expected_text, $column->getText());
						$this->assertTrue($column->hasClass($expected_class));
					}
					else {
						$xpath = 'xpath:.//*[text()='.CXPathHelper::escapeQuotes($expected_text).
							' and contains(@class, '.CXPathHelper::escapeQuotes($expected_class).')]';
						$this->assertEquals(1, $row->getColumn($column)->query($xpath)->all()->count(),
								'Expected exactly one element with text '.$expected_text.' and class '.$expected_class.
								' in column '.$column
						);
					}
				}
			}
		}

		// Check the text of displayed rows amount and the selected amount.
		$this->assertTableStats(self::$users_count);
		$this->assertSelectedCount(0);

		$this->selectTableRows();
		$this->assertSelectedCount(self::$users_count);
		// Check that buttons "Unblock" and "Delete" become clickable after some users are selected.
		$this->assertEquals(2, $this->query('button', ['Unblock', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);
		$this->assertEquals(2, $this->query('button', ['Provision now', 'Reset TOTP secret'])
				->all()->filter(CElementFilter::DISABLED)->count()
		);

		$this->query('id:all_users')->asCheckbox()->one()->uncheck();
		$this->assertSelectedCount(0);
	}

	public function getFilterData() {
		return [
			// #0 No match for name with special symbols.
			[
				[
					'filter' => [
						'Name' => '456abc😀%.!абц頑張って'
					]
				]
			],
			// #1 No match for username and role combination.
			[
				[
					'filter' => [
						'Username' => 'Admin',
						'User roles' => 'Guest role'
					]
				]
			],
			// #2 Partial username match.
			[
				[
					'filter' => [
						'Username' => 'dmin'
					],
					'expected' => [
						'Admin',
						'admin-zabbix',
						'admin user for testFormScheduledReport',
						'http-auth-admin',
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #3 Exact username match.
			[
				[
					'filter' => [
						'Username' => 'Ne-w admin абц 頑張って 😀'
					],
					'expected' => [
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #4 Username with trailing and leading spaces.
			[
				[
					'filter' => [
						'Username' => ' admin '
					],
					'expected' => [
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #5 Partial name match.
			[
				[
					'filter' => [
						'Name' => 'abbi'
					],
					'expected' => [
						'Admin',
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #6 Exact name match.
			[
				[
					'filter' => [
						'Name' => 'A Zabbixer ц-頑😀'
					],
					'expected' => [
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #7 Name with leading spaces.
			[
				[
					'filter' => [
						'Name' => ' Zabbix'
					],
					'expected' => [
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #8 Partial last name match.
			[
				[
					'filter' => [
						'Last name' => 'dministrat'
					],
					'expected' => [
						'Admin',
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #9 Exact last name match.
			[
				[
					'filter' => [
						'Last name' => 'B Administratory д-張😀'
					],
					'expected' => [
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #10 Last name with leading spaces.
			[
				[
					'filter' => [
						'Last name' => ' Administrator'
					],
					'expected' => [
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #11 Single user role.
			[
				[
					'filter' => [
						'User roles' => 'User role'
					],
					'expected' => [
						'disabled-user',
						'no-access-to-the-frontend',
						'Tag-user',
						'test-user',
						'user-for-blocking',
						'user-zabbix'
					]
				]
			],
			// #12 Multiple user roles.
			[
				[
					'filter' => [
						'User roles' => [
							'User role',
							'Guest role'
						]
					],
					'expected' => [
						'disabled-user',
						'guest',
						'no-access-to-the-frontend',
						'Tag-user',
						'test-user',
						'user-for-blocking',
						'user-zabbix'
					]
				]
			],
			// #13 Single user group.
			[
				[
					'filter' => [
						'User groups' => 'Guests'
					],
					'expected' => [
						'guest',
						'user-for-blocking'
					]
				]
			],
			// #14 Multiple user groups.
			[
				[
					'filter' => [
						'User groups' => [
							'Guests',
							'Internal'
						]
					],
					'expected' => [
						'Admin',
						'guest',
						'user-for-blocking'
					]
				]
			],
			// #15 Filter by multiple fields, fill filter fields in non-linear order.
			[
				[
					'filter' => [
						'User groups' => 'Zabbix administrators',
						'Username' =>  'Admin',
						'Last name' => 'Administrator'
					],
					'expected' => [
						'Admin',
						'Ne-w admin абц 頑張って 😀'
					]
				]
			],
			// #16 Filter by all fields, fill filter fields in non-linear order.
			[
				[
					'filter' => [
						'User groups' => 'Zabbix administrators',
						'Username' =>  'Admin',
						'Last name' => 'Administrator',
						'User roles' => 'Super admin role',
						'Name' => 'Zabbix'
					],
					'expected' => [
						'Admin'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageUsers_CheckFilter($data) {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();

		$form->fill($data['filter']);
		$form->submit()->waitUntilStalled();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected')) {
			// Using users username check that only the expected filters are returned in the list.
			$this->assertTableDataColumn($data['expected'], 'Username');
			// Assert text of displayed rows amount.
			$this->assertTableStats(count($data['expected']));
		}
		else {
			// Check no data found.
			$this->assertTableData();
		}
	}

	public function testPageUsers_FilterReset() {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->query('name:zbx_filter')->one()->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->assertTableStats(self::$users_count);
	}

	public function testPageUsers_Sort() {
		$this->page->login()->open(self::LINK.'&sortorder=DESC')->waitUntilReady();
		$this->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$table = $this->getTable();

		foreach (['Username', 'Name', 'Last name', 'User role', 'Provisioned'] as $column) {
			$values = $this->getTableColumnData($column);
			natcasesort($values);

			foreach ([$values, array_reverse($values)] as $sorted_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($sorted_values, $column);
			}
		}
	}

	public function getUnblockData() {
		return [
			// #0 Unblock one user.
			[
				[
					'users' => [
						'user-for-blocking'
					]
				]
			],
			// #1 Unblock two blocked users.
			[
				[
					'users' => [
						'test-user',
						'test-timezone'
					],
					'active_users' => [
						'guest'
					]
				]
			]
		];
	}

	/**
	 * Data for Unblock scenario.
	 */
	public function prepareUnblockData() {
		self::$time = time();

		CDataHelper::call('settings.update', [
			'login_block' => '3600s'
		]);

		// Make 3 users blocked.
		DBexecute('UPDATE users SET'.
				' attempt_failed = 5,'.
				' attempt_clock = '.self::$time.','.
				' attempt_ip = \'fe80::81b6:3d9c:4a2f:1e53%eth0\''.
				' WHERE username IN (\'user-for-blocking\', \'test-user\', \'test-timezone\');'
		);
	}

	/**
	 * @onBefore prepareUnblockData
	 *
	 * @dataProvider getUnblockData
	 */
	public function testPageUsers_Unblock($data) {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->query('name:zbx_filter')->one()->query('button:Reset')->waitUntilClickable()->one()->click();

		$users = CTestArrayHelper::get($data, 'users');
		// User count that will be selected before unblock action.
		$user_count = count($users);

		foreach ($users as $user) {
			$this->assertEquals('Blocked', $this->getTable()->findRow('Username', $user)->getColumn('Login')->getText());
		}

		$this->selectTableRows($users, 'Username');
		$this->query('button:Unblock')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'User'.(($user_count === 1) ? '' : 's').' unblocked');

		foreach ($users as $user) {
			$this->assertEquals('Ok', $this->getTable()->findRow('Username', $user)->getColumn('Login')->getText());
		}
		// After a successful unblock action, the user selection is reset.
		$this->assertSelectedCount(0);

		// Assert that all targeted users were unblocked.
		$db_check = CDBHelper::getCount('SELECT NULL FROM users'.
				' WHERE username IN ('.CDBHelper::escape($users).')'.
					' AND attempt_failed = 0'.
					' AND attempt_clock = '.self::$time.''.
					' AND attempt_ip = \'fe80::81b6:3d9c:4a2f:1e53%eth0\''
		);
		$this->assertEquals($user_count, $db_check);

		// If active users provided, check their data was not changed.
		if (CTestArrayHelper::get($data, 'active_users')) {
			$db_check = CDBHelper::getCount('SELECT NULL FROM users'.
					' WHERE username IN ('.CDBHelper::escape($data['active_users']).')'.
						' AND attempt_failed = 0'.
						' AND attempt_clock = 0'.
						' AND attempt_ip = \'\''
			);
			$this->assertEquals(count($data['active_users']), $db_check);
		}
	}

	/**
	 * Data for Reset TOTP scenario.
	 */
	public function prepareResetTOTPData() {
		CDataHelper::call('mfa.create', [
			'type' => MFA_TYPE_TOTP,
			'name' => 'Users page TOTP',
			'hash_function' => TOTP_HASH_SHA1,
			'code_length' => '6'
		]);
		CDataHelper::call('authentication.update', [
			'mfa_status' => MFA_ENABLED
		]);
	}

	/**
	 * @onBefore prepareResetTOTPData
	 */
	public function testPageUsers_ResetTOTP() {
		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->query('name:zbx_filter')->one()->query('button:Reset')->waitUntilClickable()->one()->click();

		$this->selectTableRows(['user-zabbix', 'guest', 'admin-zabbix'], 'Username');
		$this->query('button:Reset TOTP secret')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'TOTP secret reset successful.');
		$this->assertSelectedCount(0);
	}


	public function getCancelData() {
		return [
			// #0 Cancel delete.
			[
				[
					'action' => 'Delete',
					'users' => [
						'user-zabbix'
					]
				]
			],
			// #1 Cancel delete only for users that are allowed to be deleted.
			[
				[
					'action' => 'Delete',
					'users' => [
						'filter-create',
						'filter-delete',
						'filter-update'
					]
				]
			],
			// #2 Cancel delete of all users.
			[
				[
					'action' => 'Delete'
				]
			],
			// #3 Cancel unblock.
			[
				[
					'action' => 'Unblock',
					'users' => [
						'user-zabbix'
					]
				]
			],
			// #4 Cancel unblock of all users.
			[
				[
					'action' => 'Unblock'
				]
			],
			// #5 Cancel resetting TOTP secret.
			[
				[
					'action' => 'Reset TOTP secret',
					'users' => [
						'user-zabbix'
					]
				]
			],
			// #6 Cancel resetting TOTP secret of all users.
			[
				[
					'action' => 'Reset TOTP secret'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 *
	 * @depends testPageUsers_ResetTOTP
	 */
	public function testPageUsers_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::USERS_SQL);
		$action = CTestArrayHelper::get($data, 'action');
		// Get users, if 'users' key is missing, default to [] for mass delete.
		$users = CTestArrayHelper::get($data, 'users', []);
		// User count that will be selected before delete or unblock action.
		$user_count = ($users === []) ? self::$users_count : count($users);

		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->selectTableRows($users, 'Username');

		$this->query('button', $action)->one()->waitUntilClickable()->click();
		$expected_text = ($action === 'Reset TOTP secret')
			? 'Multi-factor TOTP secret'.(($user_count > 1) ? 's' : '').' will be deleted.'
			: $action.' selected user'.(($user_count > 1) ? 's?' : '?');

		$this->assertEquals($expected_text, $this->page->getAlertText());

		$this->page->dismissAlert();
		$this->page->waitUntilReady();
		$this->assertSelectedCount($user_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::USERS_SQL));
	}

	public function getDeleteData() {
		return [
			// #0 Delete one user.
			[
				[
					'users' => [
						'user-zabbix'
					],
					'expected' => TEST_GOOD
				]
			],
			// #1 Delete multiple users.
			[
				[
					'users' => [
						'filter-create',
						'filter-delete',
						'filter-update'
					],
					'expected' => TEST_GOOD
				]
			],
			// #2 Fail to delete user used in actions.
			[
				[
					'users' => [
						'user-for-blocking'
					],
					'message' => 'User "user-for-blocking" is used in "Action with user" action.'
				]
			],
			// #3 Fail to delete internal user.
			[
				[
					'users' => [
						'guest'
					],
					'message' => 'Cannot delete Zabbix internal user "guest", try disabling that user.'
				]
			],
			// #4 Fail to delete oneself.
			[
				[
					'users' => [
						'Admin'
					],
					'message' => 'User is not allowed to delete oneself.'
				]
			],
			// #5 Fail to delete multiple users where only one user is not allowed to delete.
			[
				[
					'users' => [
						'http-auth-admin',
						'user-for-blocking',
						'test-timezone'
					],
					'message' => 'User "user-for-blocking" is used in "Action with user" action.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageUsers_Delete($data) {
		// Get users, if 'users' key is missing, default to [] for mass delete.
		$users = CTestArrayHelper::get($data, 'users', []);
		// User count that will be selected before delete action.
		$user_count = count($users);

		$this->page->login()->open(self::LINK)->waitUntilReady();
		$this->query('name:zbx_filter')->one()->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->selectTableRows($users, 'Username');

		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'User'.(($user_count === 1) ? '' : 's').' deleted');
			// After a successful delete action, the user selection is reset.
			$this->assertSelectedCount(0);

			// Assert that 0 of the targeted users were found.
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM users'.
					' WHERE username IN ('.CDBHelper::escape($users).')')
			);
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot delete user'.(($user_count === 1) ? '' : 's'), $data['message']);
			// After an unsuccessful delete action, the user selection remained the same.
			$this->assertSelectedCount($user_count);

			// Assert that the users are still in the database.
			$this->assertEquals($user_count, CDBHelper::getCount('SELECT NULL FROM users'.
					' WHERE username IN ('.CDBHelper::escape($users).')')
			);
		}

		$this->assertTableStats(CDBHelper::getCount(self::USERS_SQL));
	}
}
