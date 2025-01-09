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
 * @backup role, users, usrgrp, module
 *
 * @onBefore prepareUserData
 *
 * @dataSource UserPermissions
 */
class testFormUserPermissions extends CWebTest {

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
	 * Id of role that created for future role rule change.
	 *
	 * @var integer
	 */
	protected static $admin_roleid;

	/**
	 * Id of super user.
	 *
	 * @var integer
	 */
	protected static $admin_user;

	/**
	 * Function used to create roles.
	 */
	public function prepareUserData() {
		$role = CDataHelper::call('role.create', [
			[
				'name' => 'admin_role',
				'type' => 2,
				'rules' => [
					'api' => [
						'host.create'
					]
				]
			]
		]);
		$this->assertArrayHasKey('roleids', $role);
		self::$admin_roleid = $role['roleids'][0];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'admin_role_check',
				'passwd' => 'test5678',
				'roleid' => self::$admin_roleid,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$admin_user = $user['userids'][0];
	}

	public static function getUpdateRoleData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'user_name' => 'http-auth-admin',
					'new_role' => ''
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_name' => 'Tag-user',
					'new_role' => 'Admin role',
					'user_type' => 'Admin'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_name' => 'admin-zabbix',
					'new_role' => 'Super admin role',
					'user_type' => 'Super admin'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'user_name' => 'filter-create',
					'new_role' => 'User role',
					'user_type' => 'User'
				]
			]
		];
	}

	/**
	 * Check that role changed and correctly displayed on users page.
	 *
	 * @dataProvider getUpdateRoleData
	 */
	public function testFormUserPermissions_UpdateRole($data) {
		$this->page->login()->open('zabbix.php?action=user.list');
		$table = $this->query('class:list-table')->one()->asTable();

		// Find user current role.
		if ($data['expected'] === TEST_BAD) {
			$hash_before = CDBHelper::getHash('SELECT * FROM users');
			$standard_role = $table->findRow('Username', $data['user_name'])->getColumn('User role')->getText();
		}
		$this->query('link', $data['user_name'])->waitUntilClickable()->one()->click();

		// Change user role.
		$form = $this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm();
		$form->selectTab('Permissions');
		$form->getField('Role')->fill($data['new_role']);

		if (array_key_exists('User type', $data)) {
			$form->checkValue(['User type' => $data['user_type']]);
		}

		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update user', 'Field "roleid" is mandatory.');
			$this->page->open('zabbix.php?action=user.list');
			$this->assertEquals($standard_role, $table->findRow('Username', $data['user_name'])->getColumn('User role')->getText());
			$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM users'));
		}
		else {
			// Check that user role changed on Users page and permission page.
			$this->assertMessage(TEST_GOOD, 'User updated');
			$this->assertEquals($data['new_role'], $table->findRow('Username',
					$data['user_name'])->getColumn('User role')->getText()
			);
			$this->query('link', $data['user_name'])->one()->click();
			$this->page->waitUntilReady();
			$this->query('link:Permissions')->one()->click();
			$form->invalidate();

			$values = array_key_exists('User type', $data)
				? ['Role' => $data['new_role'], 'User type' => $data['user_type']]
				: ['Role' => $data['new_role']];

			$form->checkValue($values);
		}
	}

	public static function getDisplayData() {
		return [
			[
				[
					'user_name' => 'Admin'
				]
			],
			[
				[
					'user_name' => 'guest'
				]
			],
			[
				[
					'user_name' => 'user-zabbix'
				]
			],
			[
				[
					'user_name' => 'admin-zabbix'
				]
			],
			[
				[
					'user_name' => 'filter-create'
				]
			]
		];
	}

	/**
	 * Check displayed rules for every standard role on permission page.
	 *
	 * @dataProvider getDisplayData
	 */
	public function testFormUserPermissions_Display($data) {
		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $data['user_name'])->waitUntilClickable()->one()->click();
		$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');
		$screenshot_area = $this->query('xpath://div[@class="ui-tabs-panel ui-corner-bottom ui-widget-content"][3]')->one();
		$this->assertScreenshot($screenshot_area, $data['user_name']);
	}

	public static function getUpdateUserRoletypeData() {
		return [
			[
				[
					'Role' => 'User role'
				]
			],
			[
				[
					'Role' => 'Super admin role'
				]
			],
			[
				[
					'Role' => 'Guest role'
				]
			]
		];
	}

	/**
	 * Check role rules changing role for user.
	 *
	 * @dataProvider getUpdateUserRoletypeData
	 */
	public function testFormUserPermissions_UpdateUserRoletype($data) {
		$this->page->login()->open('zabbix.php?action=user.edit&userid=40');
		$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');
		$form = $this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm();
		$form->fill($data);
		$screenshot_area = $this->query('xpath://div[@class="ui-tabs-panel ui-corner-bottom ui-widget-content"][3]')->waitUntilVisible()->one();
		$this->assertScreenshot($screenshot_area, $data['Role']);
	}

	public static function getUpdateRoleParametersData() {
		return [
			// Change role name.
			[
				[
					'before' => [
						'Role' => 'admin_role'
					],
					'change' => [
						'Name' => 'changed_admin_role'
					],
					'after' => [
						'Role' => 'changed_admin_role'
					]
				]
			],
			// Change role type.
			[
				[
					'before' => [
						'User type' => 'Admin'
					],
					'after' => [
						'User type' => 'Super admin'
					]
				]
			]
		];
	}

	/**
	 * Check, that changing roles name and type, it is changed on permission page.
	 *
	 * @dataProvider getUpdateRoleParametersData
	 */
	public function testFormUserPermissions_UpdateRoleParameters($data) {
		$this->page->login()->open('zabbix.php?action=user.edit&userid='.self::$admin_user);

		$form = $this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm();
		$form->selectTab('Permissions');
		$form->checkValue($data['before']);

		$this->page->open('zabbix.php?action=userrole.edit&roleid='.self::$admin_roleid);
		$role_form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();

		$update_field = (array_key_exists('User type', $data['before'])) ? $data['after'] : $data['change'];
		$role_form->fill($update_field);
		$role_form->submit();

		$this->page->open('zabbix.php?action=user.edit&userid='.self::$admin_user);
		$form->selectTab('Permissions');
		$form->checkValue($data['after']);
	}

	/**
	 * Check that changing rules (UI) color changed in permission page for UI and action.
	 */
	public function testFormUserPermissions_UpdateFrontendAccess() {
		$this->page->login()->open('zabbix.php?action=user.edit&userid='.self::$admin_user);

		// UI elements that should be DISPLAYED. Other UI elements from Reports, will be disabled. Action checked out.
		$fields = (['Reports' => ['Notifications', 'Top 100 triggers'], 'Create and edit maps' => false]);
		foreach (['status-green', 'status-grey'] as $status) {
			$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');

			// Checking color of UI element and action before/after disabling.
			foreach (['Availability report', 'Create and edit maps'] as $field) {
				$this->assertEquals($status, $this->query('xpath://div[@class="rules-status-container"]/span[text()='.
					CXPathHelper::escapeQuotes($field).']')->one()->getAttribute('class'));
			}

			// Select UI elements from $fields. Other elements from report, will be disabled.
			if ($status === 'status-green') {
				$this->page->open('zabbix.php?action=userrole.edit&roleid='.self::$admin_roleid);
				$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
				$form->fill($fields);
				$form->submit();
				$this->page->open('zabbix.php?action=user.edit&userid='.self::$admin_user);
			}
		}
	}

	/**
	 * Check that changing rules (API) color changed in permission page for API. Add/Remove api requests.
	 */
	public function testFormUserPermissions_UpdateApiAccess() {
		$this->page->login()->open('zabbix.php?action=user.edit&userid='.self::$admin_user);
		$selector = 'xpath://h4[text()="Access to API"]/../../following::li/div/div/span[text()=';

		// Access to API enabled or disabled.
		foreach (['Enabled', 'Disabled'] as $api_status_field) {
			// In newly created role, all API requests are in Deny list. They are greyed out on permission page.
			foreach (['status-grey', 'status-green'] as $status) {
				$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');
				if ($status === 'status-green') {
					if ($api_status_field === 'Enabled') {
						// After enabling Allow list all request should be in green color.
						foreach (['host.create', 'host.delete'] as $request) {
							$this->assertEquals($status, $this->query($selector.CXPathHelper::escapeQuotes($request).']')
									->one()->getAttribute('class'));
						}
						$this->assertTrue($this->query('xpath://div[@class="table-forms-td-left"]/label[text()="Allowed methods"]')
								->exists());
					}
					else {
						// After disabling API there displayed "Disabled".
						$this->assertEquals('status-grey', $this->query($selector.CXPathHelper::escapeQuotes($api_status_field).']')
								->one()->getAttribute('class'));
					}
				}
				else {
					// Request displayed in grey color because it is in Deny list. But API Enabled and displayed in green.
					if ($api_status_field === 'Enabled') {
						$this->assertEquals($status, $this->query($selector.'"host.create"]')->one()->getAttribute('class'));
						$this->assertEquals('status-green', $this->query($selector.CXPathHelper::escapeQuotes($api_status_field).']')
								->one()->getAttribute('class'));
						$this->assertTrue($this->query('xpath://div[@class="table-forms-td-left"]/label[text()="Denied methods"]')
								->exists());
					}

					// User role page.
					$this->page->open('zabbix.php?action=userrole.edit&roleid='.self::$admin_roleid);
					$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();

					// API gets disabled.
					if ($api_status_field === 'Disabled') {
						$form->fill(['Enabled' => false]);
					}
					else {
						// Change Deny to Allow list and add request. Now they became green on permission page.
						$form->fill(['API methods' => 'Allow list']);
						$this->query('xpath:(//div[@class="multiselect-control"])[3]')->asMultiselect()->one()
								->setFillMode(CMultiselectElement::MODE_SELECT_MULTIPLE)->fill(['host.create', 'host.delete']);
					}
					$form->submit();
					$this->page->open('zabbix.php?action=user.edit&userid='.self::$admin_user);
				}
			}
		}
	}

	/**
	 * Check group permission field.
	 */
	public function testFormUserPermissions_UpdatePermissions() {
		$table_selector = 'xpath://ul[@id="permissionsFormList"]//table';
		$this->page->login()->open('zabbix.php?action=user.edit&userid=2')->waitUntilReady();
		$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');
		$this->assertEquals('Permissions can be assigned for user groups only.',
				$this->query('xpath://ul[@id="permissionsFormList"]/li[4]')->one()->getText()
		);
		$table = $this->query($table_selector)->asTable()->one();
		$this->assertEquals(['Group', 'Type', 'Permissions'], $table->getHeadersText());
		$permissions_before = [
			[
				'Group' => 'All groups',
				'Type' => 'Hosts',
				'Permissions' => 'None'
			],
			[
				'Group' => 'All groups',
				'Type' => 'Templates',
				'Permissions' => 'None'
			]
		];
		$this->assertTableData($permissions_before, $table_selector);

		$this->page->open('zabbix.php?action=usergroup.edit&usrgrpid=8')->waitUntilReady();
		$this->query('link:Host permissions')->one()->click();
		$this->query('id:hostgroup-right-table')->asMultifieldTable()->one()->fill([
			[
				'Host groups' => 'Empty group',
				'Permissions' => 'Deny'
			],
			[
				'Host groups' => 'Discovered hosts',
				'Permissions' => 'Read'
			],
			[
				'Host groups' => 'Group to check Overview',
				'Permissions' => 'Read-write'
			]
		]);
		$this->query('button:Update')->one()->click();

		$this->page->open('zabbix.php?action=user.edit&userid=2')->waitUntilReady();
		$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');
		$permissions_after = [
			[
				'Group' => 'All groups',
				'Type' => 'Hosts',
				'Permissions' => 'None'
			],
			[
				'Group' => 'Discovered hosts',
				'Type' => 'Hosts',
				'Permissions' => 'Read'
			],
			[
				'Group' => 'Empty group',
				'Type' => 'Hosts',
				'Permissions' => 'Deny'
			],
			[
				'Group' => 'Group to check Overview',
				'Type' => 'Hosts',
				'Permissions' => 'Read-write'
			],
			[
				'Group' => 'All groups',
				'Type' => 'Templates',
				'Permissions' => 'None'
			]
		];
		$this->assertTableData($permissions_after, $table_selector);
	}

	/**
	 * Check enabled/disabled module.
	 */
	public function testFormUserPermissions_Module() {
		$widget_modules = ['Action log', 'Clock', 'Discovery status', 'Favorite graphs', 'Favorite maps',
			'Gauge', 'Geomap', 'Graph', 'Graph (classic)', 'Graph prototype', 'Honeycomb', 'Host availability',
			'Host card', 'Host navigator', 'Item history', 'Item navigator', 'Item value', 'Map', 'Map navigation tree',
			'Pie chart', 'Problem hosts', 'Problems', 'Problems by severity', 'SLA report', 'System information',
			'Top hosts', 'Top items', 'Top triggers', 'Trigger overview', 'URL', 'Web monitoring'
		];

		$this->page->login()->open('zabbix.php?action=user.edit&userid='.self::$admin_user)->waitUntilReady();
		$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');

		// Check that the default modules are present in form.
		$modules_selector = 'xpath://h4[text()="Access to modules"]/../../following::li[1]//span';
		$modules = $this->query($modules_selector)->all()->asText();
		$this->assertEquals($widget_modules, array_values($modules));

		$this->page->open('zabbix.php?action=module.list')->waitUntilReady();
		$this->query('button:Scan directory')->one()->click();
		$this->page->waitUntilReady();
		CMessageElement::find()->one()->close();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRows('Name', ['4th Module'])->select();
		$this->query('button:Enable')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Module enabled');

		foreach ([true, false] as $enable_modules) {
			$this->page->open('zabbix.php?action=user.edit&userid='.self::$admin_user)->waitUntilReady();
			$this->query('xpath://form[@name="user_form"]')->waitUntilPresent()->one()->asForm()->selectTab('Permissions');

			if ($enable_modules) {
				$this->assertEquals('status-green', $this->query($modules_selector.'[text()="4th Module"]')->one()
						->getAttribute('class')
				);
				$this->page->open('zabbix.php?action=userrole.edit&roleid='.self::$admin_roleid);
				$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
				$form->getField('4th Module')->uncheck();
				$form->submit();
			}
			else {
				$this->assertEquals('status-grey', $this->query($modules_selector.'[text()="4th Module"]')->one()
						->getAttribute('class')
				);
			}
		}
	}
}
