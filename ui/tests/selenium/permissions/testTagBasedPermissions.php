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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * Test tag based permissions.
 *
 * @dataSource UserPermissions
 */
class testTagBasedPermissions extends CLegacyWebTest {
	const USER = 'Tag-user';
	const PASSWORD = 'Zabbix_Test_123';
	const TRIGGER_HOST = 'Host for tag permissions';

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
		$this->zbxTestOpen('zabbix.php?action=problem.view');
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
		$this->zbxTestOpen('zabbix.php?action=problem.view');
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
			$this->zbxTestOpen('zabbix.php?action=problem.view');
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
		$this->zbxTestOpen('zabbix.php?action=problem.view');
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
			$this->zbxTestOpen('zabbix.php?action=problem.view');
		}
	}
}
