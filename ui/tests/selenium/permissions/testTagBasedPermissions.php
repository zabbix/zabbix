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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * Test tag based permissions.
 *
 * @backup profiles
 *
 * @dataSource UserPermissions
 */
class testTagBasedPermissions extends CLegacyWebTest {

	public function getBehaviors() {
		return [
			CTableBehavior::class
		];
	}

	const URL = 'zabbix.php?action=problem.view';
	const USER = 'Tag-user';
	const PASSWORD = 'Zabbix_Test_123';
	const TRIGGER_HOST = 'Host for tag permissions';
	protected static $time;
	protected static $hostgroupids;
	protected static $hostsids;

	public function prepareProblemsData() {
		/**
		 * Change refresh interval so Problems page doesn't refresh automatically,
		 * and popup dialogs don't disappear.
		 */

		// Create host group for hosts with item and trigger.
		CDataHelper::call('hostgroup.create', [
			['name' => 'First Group for Tag filter permission test'],
			['name' => 'Second Group for Tag filter permission test']
		]);
		self::$hostgroupids = CDataHelper::getIds('name');

		// Create hosts.
		self::$hostsids = CDataHelper::createHosts([
			[
				'host' => 'First Host for tag filter permission test',
				'groups' => [['groupid' => self::$hostgroupids['First Group for Tag filter permission test']]],
				'items' => [
					[
						'name' => 'First item without tags',
						'key_' => 'trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Second item with tag',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item OS',
								'value' => 'Linux'
							]
						]
					]
				]
			],
			[
				'host' => 'Second Host for tag filter permission test',
				'groups' => [['groupid' => self::$hostgroupids['Second Group for Tag filter permission test']]],
				'items' => [
					[
						'name' => 'First item with tag',
						'key_' => 'trap3-1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item level',
								'value' => 'Development'
							]
						]
					],
					[
						'name' => 'Second item with trigger tag',
						'key_' => 'trap3-2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Third item with tag',
						'key_' => 'trap4-1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item department',
								'value' => 'HR'
							]
						]
					],
					[
						'name' => 'Fourth item with trigger tag',
						'key_' => 'trap4-2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		// Create triggers based on items.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'First trigger for tag filter permission check',
				'expression' => 'last(/First Host for tag filter permission test/trap)<>0', // without tags.
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Second trigger for tag filter permission check',
				'expression' => 'last(/First Host for tag filter permission test/trap2)<>0', // with item tag.
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Third trigger for tag filter permission check',
				'expression' => 'last(/First Host for tag filter permission test/trap2)<>0', // with item and trigger tag.
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'tags' => [
					[
						'tag' => 'trigger OS',
						'value' => 'Windows'
					]
				]
			],
			[
				'description' => 'Fourth trigger for tag filter permission check',
				'expression' => 'last(/Second Host for tag filter permission test/trap3-1)<>0',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Fifth trigger for tag filter permission check',
				'expression' => 'last(/Second Host for tag filter permission test/trap3-2)<>0',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'tags' => [
					[
						'tag' => 'trigger level',
						'value' => 'Production'
					]
				]
			],
			[
				'description' => 'Sixth trigger for tag filter permission check',
				'expression' => 'last(/Second Host for tag filter permission test/trap4-1)<>0',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Seventh trigger for tag filter permission check',
				'expression' => 'last(/Second Host for tag filter permission test/trap4-2)<>0',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'tags' => [
					[
						'tag' => 'trigger department',
						'value' => 'QA'
					]
				]
			]
		]);

		// Create user groups and users for problem tag filter permissions check.
		CDataHelper::call('usergroup.create', [
			[
				'name' => 'User group for problem tag filter permissions',
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids['First Group for Tag filter permission test'],
						'permission' => PERM_READ_WRITE
					],
					[
						'id' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'permission' => PERM_READ_WRITE
					]
				],
				'tag_filters' => [
					[
						'groupid' => self::$hostgroupids['First Group for Tag filter permission test'],
						'tag' => '',
						'value' => ''
					],
					[
						'groupid' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'tag' => 'trigger level'
					],
					[
						'groupid' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'tag' => 'item department'
					]
				]
			],
			[
				'name' => 'User group for problem tag filter permissions 2',
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids['First Group for Tag filter permission test'],
						'permission' => PERM_READ_WRITE
					],
					[
						'id' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'permission' => PERM_READ_WRITE
					]
				],
				'tag_filters' => [
					[
						'groupid' => self::$hostgroupids['First Group for Tag filter permission test'],
						'tag' => '',
						'value' => ''
					],
					[
						'groupid' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'tag' => 'item level'
					],
					[
						'groupid' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'tag' => 'trigger department'
					]
				]
			],
			[
				'name' => 'User group for problem tag filter permissions 3',
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'permission' => PERM_READ_WRITE
					]
				],
				'tag_filters' => [
					[
						'groupid' => self::$hostgroupids['Second Group for Tag filter permission test'],
						'tag' => 'item department'
					]
				]
			]
		]);
		$usergroupids = CDataHelper::getIds('name');

		CDataHelper::call('user.create', [
			[
				'username' => 'admin for tag filter',
				'passwd' => 'z@$$ix!#%1',
				'roleid' => USER_TYPE_ZABBIX_ADMIN,
				'usrgrps' => [
					['usrgrpid' => $usergroupids['User group for problem tag filter permissions']]
				]
			],
			[
				'username' => 'user for tag filter',
				'passwd' => 'z@$$ix!#%2',
				'roleid' => USER_TYPE_ZABBIX_USER,
				'usrgrps' => [
					['usrgrpid' => $usergroupids['User group for problem tag filter permissions 2']]
				]
			]
		]);

		// Enable guest role for tag permissions test.
		CDataHelper::call('user.update', [
			[
				'userid' => 2, // guest.
				'usrgrps' => [
					['usrgrpid' => 8], // Guests.
					['usrgrpid' => $usergroupids['User group for problem tag filter permissions 3']]
				]
			]
		]);

		self::$time = time();
		$trigger_data = [
			'First trigger for tag filter permission check' => ['clock' => self::$time - 120],
			'Second trigger for tag filter permission check' => ['clock' => self::$time - 121],
			'Third trigger for tag filter permission check' => ['clock' => self::$time - 122],
			'Fourth trigger for tag filter permission check' => ['clock' => self::$time - 123],
			'Fifth trigger for tag filter permission check' => ['clock' => self::$time - 124],
			'Sixth trigger for tag filter permission check' => ['clock' => self::$time - 125],
			'Seventh trigger for tag filter permission check' => ['clock' => self::$time - 126]
		];
		foreach ($trigger_data as $trigger_name => $clock) {
			CDBHelper::setTriggerProblem($trigger_name, TRIGGER_VALUE_TRUE, $clock);
		}
	}

	/**
	 * Set tags permissions in user groups and login as simple user
	 */
	public function setTagFilter($user_groups) {
		foreach ($user_groups as $group_name => $hostgroups) {
			$this->zbxTestLogin('zabbix.php?action=usergroup.list');

			if (empty($hostgroups)) {
				break;
			}

			$this->zbxTestClickLinkTextWait($group_name);
			$this->zbxTestTabSwitch('Problem tag filter');

			// Add tag permissions
			$i = 1;
			foreach ($hostgroups as $hostgroup => $tags) {
				if (empty($tags)) {
					$tags = ['' => ''];
				}

				foreach ($tags as $tag => $values) {
					if (!is_array($values)) {
						$values = [$values];
					}

					if (empty($values)) {
						$values = [''];
					}

					foreach ($values as $value) {
						$this->query('id:tag-filter-table')->query('button', 'Add')->one()->click();
						$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
						$form = $dialog->asForm();
						$dialog->query('button', 'Select')->one()->click();
						$this->query('link', $hostgroup)->waitUntilVisible()->one()->click();

						if ($tag !== '' || $value !== '') {
							$form->fill(['Filter' => 'Tag list', 'id:new_tag_filter_0_tag' => $tag,
									'id:new_tag_filter_0_value' => $value]
							);
						}

						$form->submit();
						COverlayDialogElement::ensureNotPresent();
					}
				}

				$xpath = '//table[@id="tag-filter-table"]//tbody//tr['.$i.']//td/button[text()="Remove"]';
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath($xpath));
				$i++;
			}

			$this->zbxTestClick('update');
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User group updated');
		}

		// Logout as super admin and login as simple user.
		$this->zbxTestLogout();
		$this->zbxTestWaitForPageToLoad();
		$this->webDriver->manage()->deleteAllCookies();
		$this->page->userLogin(self::USER, self::PASSWORD);
	}

	public static function incorrect_tags() {
		return [
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'service' => ''
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Servi' => ''
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'service' => 'MySQL'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Serv' => 'MySQL'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Service' => 'MYSQL'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Service' => 'MyS'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			]
		];
	}

	/**
	 * @backup usrgrp
	 *
	 * @dataProvider incorrect_tags
	 *
	 * Test incorrect tags in filter, user should not see any problems on frontend
	 */
	public function testTagBasedPermissions_IncorrectTags($data) {
		$this->setTagFilter($data['user_groups']);

		// Go to Dashboard and check user name
		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertAttribute("//a[@class='zi-user-settings']", 'title', self::USER);

		// Check tag filter in Problem widget
		CDashboardElement::find()->one()->getWidget('Current problems', true);
		$this->zbxTestTextNotPresent($data['trigger_names']);
		$this->zbxTestAssertElementText('//h4[text()="Current problems"]/../../..//div[contains(@class, "no-data-message")]', 'No data found');

		// Check problem displaying on Problem page
		$this->zbxTestOpen(self::URL);
		$table = $this->query('xpath://table['.CXPathHelper::fromClass('list-table').']')->asTable()->one()->waitUntilVisible();
		$this->zbxTestTextNotPresent($data['trigger_names']);
		$this->assertFalse($this->query('xpath://div[@class="table-stats"]')->one(false)->isValid());
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');

		// Check trigger filter on Problem page
		foreach ($data['trigger_names'] as $name) {
			// Select trigger
			$this->zbxTestClickButtonMultiselect('triggerids_0');
			$this->zbxTestLaunchOverlayDialog('Triggers');
			COverlayDialogElement::find()->waitUntilReady()->one()->setDataContext(self::TRIGGER_HOST);
			$this->zbxTestClickLinkTextWait($name);
			COverlayDialogElement::ensureNotPresent();
			// Apply filter
			$this->query('name:filter_apply')->one()->click();
			$table->waitUntilReloaded();
			$this->zbxTestTextPresent($name);
			$this->assertFalse($this->query('xpath://div[@class="table-stats"]')->one(false)->isValid());
			$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
			// Reset filter.
			$this->zbxTestClickButtonText('Reset');
			$table->waitUntilReloaded();
		}
		$this->zbxTestTextNotPresent($data['trigger_names']);
	}

	public static function create() {
		return [
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => []
						]
					],
					'host_group' => 'Host group for tag permissions',
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Service' => ''
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Service' => 'MySQL'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Service' => ['MySQL', 'Oracle']
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			]
		];
	}

	/**
	 * @backup usrgrp
	 *
	 * @dataProvider create
	 *
	 * Test tag filter with one user group
	 */
	public function testTagBasedPermissions_AddTags($data) {
		$this->setTagFilter($data['user_groups']);

		// Count triggers
		$countTriggers = count($data['trigger_names']);

		// Go to Dashboard and check user name
		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertAttribute("//a[@class='zi-user-settings']", 'title', self::USER);

		// Check tag filter in Problem widget
		CDashboardElement::find()->one()->getWidget('Current problems', true);
		$this->zbxTestTextPresent($data['trigger_names']);

		// Check problem displaying on Problem page
		$this->zbxTestOpen(self::URL);
		$table = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilVisible();
		$this->zbxTestTextPresent($data['trigger_names']);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$countTriggers.' of '.$countTriggers.' found');

		// Check trigger filter on Problem page
		foreach ($data['trigger_names'] as $name) {
			// Select trigger
			$this->zbxTestClickButtonMultiselect('triggerids_0');
			COverlayDialogElement::find()->one()->waitUntilReady();
			$this->zbxTestLaunchOverlayDialog('Triggers');
			COverlayDialogElement::find()->one()->setDataContext(self::TRIGGER_HOST);
			$this->zbxTestClickXpathWait("//div[@class='overlay-dialogue-body']//a[text()='$name']");
			// Apply filter
			$this->query('name:filter_apply')->one()->click();
			$table->waitUntilReloaded();
			$this->zbxTestTextPresent($name);
			$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 1 of 1 found');
			// Reset filter.
			$this->zbxTestClickButtonText('Reset');
			$table->waitUntilReloaded();
		}

		// Check Event details page
		foreach ($data['trigger_names'] as $name) {
			$triggerid = DBfetch(DBselect('SELECT triggerid FROM triggers WHERE description='. zbx_dbstr($name)));
			$this->zbxTestClickXpathWait("//a[contains(@href,'tr_events.php?triggerid=".$triggerid['triggerid']."')]");
			$this->zbxTestCheckHeader('Event details');
			// Go back to problem page
			$this->zbxTestOpen(self::URL);
		}
	}

	public static function multiple_groups() {
		return [
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions BBB' => [
							'Host group for tag permissions' => [
								'Service' => 'Oracle'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => []
						],
						'Selenium user group for tag permissions BBB' => [
							'Host group for tag permissions' => [
								'Service' => 'Oracle'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			],
			[
				[
					'user_groups' => [
						'Selenium user group for tag permissions AAA' => [
							'Host group for tag permissions' => [
								'Service' => 'MySQL'
							]
						],
						'Selenium user group for tag permissions BBB' => [
							'Host group for tag permissions' => [
								'Service' => 'Oracle'
							]
						]
					],
					'trigger_names' => ['Trigger for tag permissions MySQL', 'Trigger for tag permissions Oracle']
				]
			]
		];
	}

	/**
	 * @backup usrgrp
	 *
	 * @dataProvider multiple_groups
	 *
	 * Test tag filter with two user group
	 */
	public function testTagBasedPermissions_MultipleUserGroups($data) {
		$this->setTagFilter($data['user_groups']);

		// Count triggers
		$countTriggers = count($data['trigger_names']);

		// Go to Dashboard and check user name
		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertAttribute("//a[@class='zi-user-settings']", 'title', self::USER);

		// Check tag filter in Problem widget
		CDashboardElement::find()->one()->getWidget('Current problems', true);
		$this->zbxTestTextPresent($data['trigger_names']);

		// Check problem displaying on Problem page
		$this->zbxTestOpen(self::URL);
		$table = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilVisible();
		$this->zbxTestTextPresent($data['trigger_names']);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$countTriggers.' of '.$countTriggers.' found');

		// Check filter on Problem page
		foreach ($data['trigger_names'] as $name) {
			// Select trigger
			$this->zbxTestClickButtonMultiselect('triggerids_0');
			$this->zbxTestLaunchOverlayDialog('Triggers');
			COverlayDialogElement::find()->one()->setDataContext(self::TRIGGER_HOST);
			$this->zbxTestClickXpathWait("//div[@class='overlay-dialogue-body']//a[text()='$name']");
			// Apply filter
			$this->query('name:filter_apply')->one()->click();
			$table->waitUntilReloaded();
			$this->zbxTestTextPresent($name);
			$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 1 of 1 found');
			// Reset filter.
			$this->zbxTestClickButtonText('Reset');
			$table->waitUntilReloaded();
		}

		// Check Event details page
		foreach ($data['trigger_names'] as $name) {
			$triggerid = DBfetch(DBselect('SELECT triggerid FROM triggers WHERE description='. zbx_dbstr($name)));
			$this->zbxTestClickXpathWait("//a[contains(@href,'tr_events.php?triggerid=".$triggerid['triggerid']."')]");
			$this->zbxTestCheckHeader('Event details');
			// Go back to problem page
			$this->zbxTestOpen(self::URL);
		}
	}

	public static function getProblemsData() {
		return [
			// #0 Problem tag filter limited permissions for admin role.
			[
				[
					'user' => 'admin for tag filter',
					'password' => 'z@$$ix!#%1',
					'result' => [
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'First trigger for tag filter permission check',
							'Tags' => ''
						],
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'Second trigger for tag filter permission check',
							'Tags' => 'item OS: Linux'
						],
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'Third trigger for tag filter permission check',
							'Tags' => implode("\n", ['item OS: Linux', 'trigger OS: Windows'])
						],
						[

							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Fifth trigger for tag filter permission check',
							'Tags' => 'trigger level: Production'
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Sixth trigger for tag filter permission check',
							'Tags' => 'item department: HR'
						]
					]
				]
			],
			// #1 Problem tag filter limited permissions for user role.
			[
				[
					'user' => 'user for tag filter',
					'password' => 'z@$$ix!#%2',
					'result' => [
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'First trigger for tag filter permission check',
							'Tags' => ''
						],
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'Second trigger for tag filter permission check',
							'Tags' => 'item OS: Linux'
						],
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'Third trigger for tag filter permission check',
							'Tags' => implode("\n", ['item OS: Linux', 'trigger OS: Windows'])
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Fourth trigger for tag filter permission check',
							'Tags' => 'item level: Development'
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Seventh trigger for tag filter permission check',
							'Tags' => 'trigger department: QA'
						]
					]
				]
			],
			// #2 Super admin should see all problem tags without limitations.
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'result' => [
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'First trigger for tag filter permission check',
							'Tags' => ''
						],
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'Second trigger for tag filter permission check',
							'Tags' => 'item OS: Linux'
						],
						[
							'Host' => 'First Host for tag filter permission test',
							'Problem' => 'Third trigger for tag filter permission check',
							'Tags' => implode("\n", ['item OS: Linux', 'trigger OS: Windows'])
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Fourth trigger for tag filter permission check',
							'Tags' => 'item level: Development'
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Fifth trigger for tag filter permission check',
							'Tags' => 'trigger level: Production'
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Sixth trigger for tag filter permission check',
							'Tags' => 'item department: HR'
						],
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Seventh trigger for tag filter permission check',
							'Tags' => 'trigger department: QA'
						]
					]
				]
			],
			// #3 Guest sees only one problem with particular tag.
			[
				[
					'guest' => true,
					'result' => [
						[
							'Host' => 'Second Host for tag filter permission test',
							'Problem' => 'Sixth trigger for tag filter permission check',
							'Tags' => 'item department: HR'
						]
					]
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareProblemsData
	 *
	 * @dataProvider getProblemsData
	 *
	 * Check that problems page show problems regarding tag filter permissions.
	 */
	public function testTagBasedPermissions_TagPermissions($data) {
		$url = self::URL.
			'&hostids[]='.self::$hostsids['hostids']['First Host for tag filter permission test'].
			'&hostids[]='.self::$hostsids['hostids']['Second Host for tag filter permission test'];

		if (array_key_exists('guest', $data)) {
			$this->page->open($url)->waitUntilReady();
			$this->query('button:Login')->one()->click();
			$this->query('link:sign in as guest')->one()->click();
			$this->page->waitUntilReady();
		}
		else {
			$this->page->userLogin($data['user'], $data['password'])->open($url)->waitUntilReady();
		}

		$this->assertTableData($data['result']);
	}
}
