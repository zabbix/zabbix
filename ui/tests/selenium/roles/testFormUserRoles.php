<?php /*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup role
 * @on-before prepareRoleData
 */
class testFormUserRoles extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Id of role that created for future update.
	 *
	 * @var integer
	 */
	protected static $roleid;

	/**
	 * Function used to create roles.
	 */
	public function prepareRoleData() {
		$response = CDataHelper::call('role.create', [
			[
				'name' => 'role_for_update',
				'type' => 1,
				'rules' => [
					'api' => [
						'*.create',
						'host.*',
						'*.*'
					]
				]
			]
		]);
		$this->assertArrayHasKey('roleids', $response);
		self::$roleid = $response['roleids'][0];

		CDataHelper::call('role.create', [
			[
				'name' => 'role_for_delete',
				'type' => 1
			]
		]);
	}

	public static function getCreateData() {
		return [
			// Same name for 3 types of roles.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'User role',
						'User type' => 'User'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'User role with name "User role" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Admin role',
						'User type' => 'Admin'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'User role with name "Admin role" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Super admin role',
						'User type' => 'Super admin'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'User role with name "Super admin role" already exists.'
				]
			],
			// Empty name field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'User type' => 'User'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'User type' => 'Admin'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'User type' => 'Super admin'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty space in name field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ' ',
						'User type' => 'User'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ' ',
						'User type' => 'Admin'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ' ',
						'User type' => 'Super admin'
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			// All UI elements unchecked.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'user_ui_checked_out',
						'User type' => 'User',
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => []
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'admin_ui_checked_out',
						'User type' => 'Admin',
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => [],
						'Configuration' => []
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'super_admin_ui_checked_out',
						'User type' => 'Super admin',
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => [],
						'Configuration' => [],
						'Administration' => []
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			// Remove everything.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'user_everything_removed',
						'User type' => 'User',
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => [],
						'Default access to new UI elements' => false,
						'Default access to new modules' => false,
						'Enabled' => false,
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'admin_everything_removed',
						'User type' => 'Admin',
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => [],
						'Configuration' => [],
						'Default access to new UI elements' => false,
						'Default access to new modules' => false,
						'Enabled' => false,
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'super_admin_everything_removed',
						'User type' => 'Super admin',
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => [],
						'Configuration' => [],
						'Administration' => [],
						'Default access to new UI elements' => false,
						'Default access to new modules' => false,
						'Enabled' => false,
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			// All UI elements checked out except one.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'user_ui_one_left',
						'User type' => 'User',
						'Monitoring' => ['Services'],
						'Inventory' => [],
						'Reports' => []
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'admin_ui_one_left',
						'User type' => 'Admin',
						'Monitoring' => ['Services'],
						'Inventory' => [],
						'Reports' => [],
						'Configuration' => []
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'super_admin_ui_one_left',
						'User type' => 'Super admin',
						'Monitoring' => ['Services'],
						'Inventory' => [],
						'Reports' => [],
						'Configuration' => [],
						'Administration' => []
					],
					'message_header' => 'User role created'
				]
			],
			// Remove all Access to actions.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'user_ui_no_actions',
						'User type' => 'User',
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'admin_ui_no_actions',
						'User type' => 'Admin',
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'super_admin_ui_no_Actions',
						'User type' => 'Super admin',
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'User role created'
				]
			],
			// API methods deny list.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'user_ui_api_deny',
						'User type' => 'User'
					],
					'api_methods' => [
							'dashboard.create',
							'dashboard.*',
							'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'admin_ui_api_deny',
						'User type' => 'Admin'
					],
					'api_methods' => [
							'dashboard.create',
							'dashboard.*',
							'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'super_admin_ui_api_deny',
						'User type' => 'Super admin'
					],
					'api_methods' => [
							'dashboard.create',
							'dashboard.*',
							'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			// API methods allow list.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'user_ui_api_allow',
						'User type' => 'User',
						'API methods' => 'Allow list'
					],
					'api_methods' => [
							'dashboard.create',
							'dashboard.*',
							'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'admin_ui_api_allow',
						'User type' => 'Admin',
						'API methods' => 'Allow list'
					],
					'api_methods' => [
							'dashboard.create',
							'dashboard.*',
							'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'super_admin_ui_api_allow',
						'User type' => 'Super admin',
						'API methods' => 'Allow list'
					],
					'api_methods' => [
							'dashboard.create',
							'dashboard.*',
							'*.create'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'super_admin_role',
						'User type' => 'Super admin',
						'Default access to new modules' => false,
						'API methods' => 'Deny list',
						'Monitoring' => ['Overview', 'Maps'],
						'Reports' => [],
						'Create and edit dashboards and screens' => false
					],
					'message_header' => 'User role created'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormUserRoles_Create($data) {
		$this->page->login()->open('zabbix.php?action=userrole.edit');
		$this->CreateUpdate($data, 'create');
	}

	public function testFormUserRoles_Layout() {
		// Checking buttons for already created role.
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid=1');
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asFluidForm()->one();

		// Unchecking API, button and radio button becomes disabled.
		$form->fill(['Enabled' => false]);
		foreach (['api_mode_0', 'api_mode_1'] as $id) {
			$this->assertFalse($form->query('id', $id)->one()->isEnabled());
		}
		$this->assertFalse($this->query('button:Select')->one()->isClickable());
		$this->assertTrue($this->query('xpath://div[@id="api_methods_" and @aria-disabled="true"]')->exists());
		$this->page->refresh()->waitUntilReady();
		$this->assertEquals(4, $form->query('button', ['Update', 'Clone', 'Delete', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count());

		// New role check with screenshots.
		$this->page->open('zabbix.php?action=userrole.edit');
		$screenshot_area = $this->query('id:user_role_tab')->one();
		foreach (['User', 'Admin', 'Super admin'] as $role) {
			$this->query('class:js-userrole-usertype')->one()->asZDropdown()->select($role);
			$this->page->removeFocus();
			$this->assertScreenshotExcept($screenshot_area, ['query' => 'xpath://input[@id="name"]'], $role);
		}

		// Screens for super admin.
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid=3');
		$this->page->removeFocus();
		$this->assertScreenshotExcept($screenshot_area, ['query' => 'xpath://input[@id="name"]']);
		foreach (['Clone' => true, 'Cancel' => true, 'Update' => false, 'Delete' => false] as $button => $clickable) {
			$this->assertEquals($clickable, $this->query('button', $button)->one()->isClickable());
		}
	}

	public function testFormUserRoles_SimpleUpdate() {
		$hash_before = CDBHelper::getHash('SELECT * FROM role_rule where roleid=2');
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->query('link', 'Admin role')->one()->click();
		$this->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'User role updated');
		$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM role_rule where roleid=2'));
	}

	public static function getUpdateData() {
		return [
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'link' => 'role_for_update',
					'fields' => [
						'Name' => ''
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty space.
			[
				[
					'expected' => TEST_BAD,
					'link' => 'role_for_update',
					'fields' => [
						'Name' => ' '
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			// Existing name.
			[
				[
					'expected' => TEST_BAD,
					'link' => 'role_for_update',
					'fields' => [
						'Name' => 'User role '
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'User role with name "User role" already exists.'
				]
			],
			// All UI elements disabled.
			[
				[
					'expected' => TEST_BAD,
					'link' => 'role_for_update',
					'fields' => [
						'Monitoring' => [],
						'Inventory' => [],
						'Reports' => []
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'At least one UI element must be checked.'
				]
			],
			// Change name.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'role_for_update',
					'fields' => [
						'Name' => 'user_changed_name',
						'User type' => 'User'
					],
					'message_header' => 'User role updated'
				]
			],
			// Change type to admin.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [
						'User type' => 'Admin'
					],
					'message_header' => 'User role updated'
				]
			],
			// Change type to super admin.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [
						'User type' => 'Super admin'
					],
					'message_header' => 'User role updated'
				]
			],
			// Change type to user.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [
						'User type' => 'User'
					],
					'message_header' => 'User role updated'
				]
			],
			// Remove all API methods.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [],
					'api_methods' => [],
					'message_header' => 'User role updated'
				]
			],
			// Allow API list.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [
						'API methods' => 'Allow list'
					],
					'api_methods' => ['*.create'],
					'message_header' => 'User role updated'
				]
			],
			// Deny API list.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [
						'API methods' => 'Deny list'
					],
					'message_header' => 'User role updated'
				]
			],
			// Access to actions removed.
			[
				[
					'expected' => TEST_GOOD,
					'link' => 'user_changed_name',
					'fields' => [
						'API methods' => 'Deny list',
						'Create and edit dashboards and screens' => false,
						'Create and edit maps' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'User role updated'
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 */
	public function testFormUserRoles_Update($data) {
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->query('link', $data['link'])->one()->click();
		$this->CreateUpdate($data, 'update');
	}

	public function testFormUserRoles_Clone() {
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid=2');
		$form = $this->query('id:userrole-form')->waitUntilReady()->asFluidForm()->one();
		$values = $form->getFields()->asValues();
		$role_name = $values['Name'];
		$this->query('button:Clone')->waitUntilReady()->one()->click();
		$this->page->waitUntilReady();

		$form->invalidate();
		$form->fill(['Name' => 'Cloned_'.$role_name]);
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'User role created');
		foreach([$role_name, 'Cloned_'.$role_name] as $role) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM role WHERE name='.zbx_dbstr($role)));
		}

		$id = CDBHelper::getValue('SELECT roleid FROM role WHERE name='.zbx_dbstr('Cloned_'.$role_name));
		$this->page->open('zabbix.php?action=userrole.edit&roleid='.$id);
		$cloned_values = $form->getFields()->asValues();
		$this->assertEquals('Cloned_'.$role_name, $cloned_values['Name']);

		// Field Name removed from arrays.
		unset($cloned_values['Name']);
		unset($values['Name']);
		$this->assertEquals($values, $cloned_values);
	}

	public function testFormUserRoles_Delete() {
		$hash_before = CDBHelper::getHash('SELECT * FROM role');
		$this->page->login()->open('zabbix.php?action=userrole.list');
		foreach (['Admin role', 'role_for_delete'] as $role) {
			$this->query('link', $role)->one()->click();
			$this->query('button:Delete')->one()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			if ($role === 'Admin role') {
				$this->assertMessage(TEST_BAD, 'Cannot delete user role', 'The role "Admin role" is assigned to'.
						' at least one user and cannot be deleted.');
				$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM role'));
			}
			else {
				$this->assertMessage(TEST_GOOD, 'User role deleted');
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM role WHERE name=\'role_for_delete\''));
			}
		}
	}

	// Fill multiselect field.
	private function fillMultiselect($methods) {
		$api_field = $this->query('class:multiselect-control')->asMultiselect()->one();
		$api_field->fill($methods);
	}

	/**
	 * Create or update role.
	 *
	 * @param array $data		given data provider
	 * @param string $action	create or update
	 */
	private function CreateUpdate($data, $action) {
		if ($action === 'create') {
			if ($data['expected'] === TEST_BAD) {
				$hash_before = CDBHelper::getHash('SELECT * FROM role');
			}
		}
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asFluidForm()->one();
		$form->fill($data['fields']);

		if (array_key_exists('api_methods', $data)) {
			$this->fillMultiselect($data['api_methods']);
		}
		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);

			if ($action === 'create') {
				$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM role'));
			}
		}
		else {
			$this->assertMessage(TEST_GOOD, $data['message_header']);

			if ($action === 'create') {
				$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM role WHERE name='.zbx_dbstr($data['fields']['Name'])));
				$this->query('link', $data['fields']['Name'])->one()->click();
			}
			else {
				$this->page->open('zabbix.php?action=userrole.edit&roleid='.self::$roleid);
			}

			$form = $this->query('id:userrole-form')->waitUntilPresent()->asFluidForm()->one();
			$form->checkValue($data['fields']);
		}
	}
}
