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
 * @dataSource EntitiesTags, Services
 *
 * @backup role, module, users
 *
 * @onBefore prepareRoleData, prepareUserData
 */
class testFormUserRoles extends CWebTest {

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

	const ROLE_SQL = 'SELECT * FROM role r INNER JOIN role_rule rr ON rr.roleid = r.roleid ORDER BY r.roleid, rr.role_ruleid';

	/**
	 * Id of role that created for future update.
	 *
	 * @var integer
	 */
	protected static $roleid;

	/**
	 * Id of role that created for future role change for Super admin.
	 *
	 * @var integer
	 */
	protected static $super_roleid;

	/**
	 * Id of role that created for future role delete and type update.
	 *
	 * @var integer
	 */
	protected static $delete_roleid;

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
			],
			[
				'name' => 'super_role',
				'type' => 3
			],
			[
				'name' => 'role_for_delete',
				'type' => 3
			]
		]);
		$this->assertArrayHasKey('roleids', $response);
		self::$roleid = $response['roleids'][0];
		self::$super_roleid = $response['roleids'][1];
		self::$delete_roleid = $response['roleids'][2];
	}

	public function prepareUserData() {
		CDataHelper::call('user.create', [
			[
				'username' => 'super_role_check',
				'passwd' => 'test5678',
				'roleid' => self::$super_roleid,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
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
					'message_details' => 'User role "User role" already exists.'
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
					'message_details' => 'User role "Admin role" already exists.'
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
					'message_details' => 'User role "Super admin role" already exists.'
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
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => []
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be enabled for user role "user_ui_checked_out".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'admin_ui_checked_out',
						'User type' => 'Admin',
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => [],
						'Data collection' => [],
						'Alerts' => []
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be enabled for user role "admin_ui_checked_out".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'super_admin_ui_checked_out',
						'User type' => 'Super admin',
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => [],
						'Data collection' => [],
						'Alerts' => [],
						'Users' => [],
						'Administration' => []
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be enabled for user role "super_admin_ui_checked_out".'
				]
			],
			// Remove everything.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'user_everything_removed',
						'User type' => 'User',
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => [],
						'Default access to new UI elements' => false,
						'Default access to new modules' => false,
						'Enabled' => false,
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be enabled for user role "user_everything_removed".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'admin_everything_removed',
						'User type' => 'Admin',
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => [],
						'Data collection' => [],
						'Alerts' => [],
						'Default access to new UI elements' => false,
						'Default access to new modules' => false,
						'Enabled' => false,
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Manage scheduled reports' => false,
						'Manage SLA' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be enabled for user role "admin_everything_removed".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'super_admin_everything_removed',
						'User type' => 'Super admin',
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => [],
						'Data collection' => [],
						'Alerts' => [],
						'Users' => [],
						'Administration' => [],
						'Default access to new UI elements' => false,
						'Default access to new modules' => false,
						'Enabled' => false,
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Manage scheduled reports' => false,
						'Manage SLA' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'At least one UI element must be enabled for user role "super_admin_everything_removed".'
				]
			],
			// Read-write service tag name not specified.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Missing Read-write service tag name',
						'User type' => 'Admin',
						'Read-write access to services' => 'Service list'
					],
					'write_services' => [
						'Read-write access to services with tag' => [
							'service_write_tag_value' => 'value'
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Cannot have non-empty tag value while having empty tag in rule "services.write.tag"'
							.' for user role "Missing Read-write service tag name".'
				]
			],
			// Read-only service tag name not specified.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Missing Read-write service tag name',
						'User type' => 'Admin',
						'Read-only access to services' => 'Service list'
					],
					'write_services' => [
						'Read-only access to services with tag' => [
							'service_read_tag_value' => 'value'
						]
					],
					'message_header' => 'Cannot create user role',
					'message_details' => 'Cannot have non-empty tag value while having empty tag in rule "services.read.tag"'
							.' for user role "Missing Read-write service tag name".'
				]
			],
			// Special symbols in the name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '!@#$%^^&*()_+',
						'User type' => 'User'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '!@#$%^^&*()_=',
						'User type' => 'Admin'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '!@#$%^^&*()_?',
						'User type' => 'Super admin'
					],
					'message_header' => 'User role created'
				]
			],
			// A lot of spaces in the name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'user          test          name',
						'User type' => 'User'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'admin          test          name',
						'User type' => 'Admin'
					],
					'message_header' => 'User role created'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'super          admin          test          name',
						'User type' => 'Super admin'
					],
					'message_header' => 'User role created'
				]
			],
			// Trailing, leading space in name.
			[
				[
					'expected' => TEST_GOOD,
					'space' => true,
					'fields' => [
						'Name' => ' user_leading_trailing ',
						'User type' => 'User'
					],
					'message_header' => 'User role created'
				]
			],
			// All UI elements unchecked except one.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'user_ui_one_left',
						'User type' => 'User',
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => ['Services'],
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
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => ['Services'],
						'Inventory' => [],
						'Reports' => [],
						'Data collection' => [],
						'Alerts' => []
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
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => ['Services'],
						'Inventory' => [],
						'Reports' => [],
						'Data collection' => [],
						'Alerts' => [],
						'Users' => [],
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
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Invoke "Execute now" on read-only hosts' => false,
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
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Manage scheduled reports' => false,
						'Manage SLA' => false,
						'Invoke "Execute now" on read-only hosts' => false,
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
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Create and edit maintenance' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Manage scheduled reports' => false,
						'Manage SLA' => false,
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
						'Monitoring' => ['Maps'],
						'Reports' => [],
						'Create and edit dashboards' => false
					],
					'message_header' => 'User role created'
				]
			],
			// Access to services set to None.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'No access to services',
						'User type' => 'Admin',
						'Read-write access to services' => 'None',
						'Read-write access to services' => 'None'
					],
					'message_header' => 'User role created'
				]
			],
			// Access to services set to All in both cases.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'All access to services',
						'User type' => 'Admin',
						'Read-write access to services' => 'All',
						'Read-write access to services' => 'All'
					],
					'message_header' => 'User role created'
				]
			],
			// Access to services set to Service list.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Access to specific services',
						'User type' => 'Admin',
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'Service list'
					],
					'write_services' => [
						'xpath:(//div[@class="multiselect-control"])[1]' => 'Update service',
						'Read-write access to services with tag' => [
							'service-write-tag-tag' => 'tag-write',
							'service_write_tag_value' => 'value-write'
						]
					],
					'read_services' => [
						'xpath:(//div[@class="multiselect-control"])[2]' => ['Update service', 'Service for delete 2'],
						'Read-only access to services with tag' => [
							'service-read-tag-tag' => 'tag-read',
							'service_read_tag_value' => 'value-read'
						]
					],
					'message_header' => 'User role created'
				]
			],
			// Access to services set to Service list.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Only read-only services',
						'User type' => 'Super admin',
						'Read-only access to services' => 'Service list',
						// added element 'API methods' with default value for page scroll
						'API methods' => 'Deny list'
					],
					'read_services' => [
						'xpath:(//div[@class="multiselect-control"])[2]' => ['Update service', 'Service for delete 2']
					],
					'message_header' => 'User role created'
				]
			],
			// Access to services set to Service list but the list is empty.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Services access with empty list and tags',
						'User type' => 'User',
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'Service list'
					],
					'override_service_access' => 'None',
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
		$this->checkRoleAction($data, 'create');
	}

	public function testFormUserRoles_Layout() {
		$roles = ['User', 'Admin', 'Super admin'];
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid=1');
		$this->page->assertTitle('Configuration of user roles');
		$this->page->assertHeader('User roles');
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
		$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));
		$this->assertEquals($roles, $this->query('id:user-type')->one()->asDropdown()->getOptions()->asText());

		// Unchecking API, button and radio button becomes disabled.
		$form->fill(['Enabled' => false]);
		$this->assertFalse($form->getField('API methods')->isEnabled());
		$this->assertFalse($this->query('button:Select')->one()->isClickable());
		$this->assertTrue($this->query('xpath://div[@id="api_methods_" and @aria-disabled="true"]')->exists());
		$this->page->refresh()->waitUntilReady();
		$this->assertEquals(4, $form->query('button', ['Update', 'Clone', 'Delete', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count());

		// New role check with screenshots.
		$this->page->open('zabbix.php?action=userrole.edit')->waitUntilReady();
		$this->page->removeFocus();

		$screenshot_area = $this->query('id:user_role_tab')->one();
		foreach ($roles as $role) {
			$this->query('id:user-type')->one()->asDropdown()->select($role);

			if ($role === 'Super admin') {
				$form->invalidate();
				foreach (['Read-write access to services', 'Read-only access to services'] as $field) {
					$form->getField($field)->fill('Service list');
				}

				// Scroll up after filling the form to take the correct screenshot.
				$this->page->scrollToTop();
			}

			$this->assertScreenshotExcept($screenshot_area, ['query' => 'xpath://input[@id="name"]'], $role);
		}

		// Screen for the existing super admin role.
		$this->page->open('zabbix.php?action=userrole.edit&roleid=3');
		$this->assertScreenshotExcept($screenshot_area, ['query' => 'xpath://input[@id="name"]']);
		foreach (['Clone' => true, 'Cancel' => true, 'Update' => false, 'Delete' => false] as $button => $clickable) {
			$this->assertEquals($clickable, $this->query('button', $button)->one()->isClickable());
		}
	}

	public static function getApiListData() {
		return [
			// User role.
			[
				[
					'fields' => [
						'Name' => 'user_api',
						'User type' => 'User'
					],
					'api_list' => [
						'action.get', 'alert.get', 'configuration.export', 'configuration.import', 'configuration.importcompare',
						'correlation.get', 'dashboard.create', 'dashboard.delete', 'dashboard.get', 'dashboard.update',
						'dcheck.get', 'dhost.get', 'discoveryrule.get', 'drule.get', 'dservice.get', 'event.acknowledge',
						'event.get', 'graph.get', 'graphitem.get', 'graphprototype.get', 'hanode.get', 'history.get', 'history.push',
						'host.get', 'hostgroup.get', 'hostinterface.get', 'hostprototype.get', 'housekeeping.get', 'httptest.get',
						'iconmap.get', 'image.get', 'item.get', 'itemprototype.get', 'maintenance.get', 'map.create',
						'map.delete', 'map.get', 'map.update', 'mediatype.get', 'module.get', 'problem.get', 'proxy.get',
						'proxygroup.get', 'role.get', 'script.execute', 'script.get', 'script.getscriptsbyevents',
						'script.getscriptsbyhosts', 'service.create', 'service.delete', 'service.get', 'service.update',
						'settings.get', 'sla.get', 'sla.getsli', 'template.get', 'templatedashboard.get', 'templategroup.get',
						'token.create', 'token.delete', 'token.generate', 'token.get', 'token.update', 'trend.get', 'trigger.get',
						'triggerprototype.get', 'user.get', 'user.logout', 'user.update', 'usergroup.get', 'usermacro.get',
						'valuemap.get'
					]
				]
			],
			// Admin role.
			[
				[
					'fields' => [
						'Name' => 'admin_api',
						'User type' => 'Admin'
					],
					'api_list' => [
						'action.create', 'action.delete', 'action.get', 'action.update', 'alert.get', 'configuration.export',
						'configuration.import', 'configuration.importcompare', 'correlation.get', 'dashboard.create',
						'dashboard.delete', 'dashboard.get', 'dashboard.update', 'dcheck.get', 'dhost.get',
						'discoveryrule.create', 'discoveryrule.delete', 'discoveryrule.get', 'discoveryrule.update',
						'drule.create', 'drule.delete', 'drule.get', 'drule.update', 'dservice.get', 'event.acknowledge',
						'event.get', 'graph.create', 'graph.delete', 'graph.get', 'graph.update', 'graphitem.get',
						'graphprototype.create', 'graphprototype.delete', 'graphprototype.get', 'graphprototype.update',
						'hanode.get', 'history.clear', 'history.get', 'history.push', 'host.create', 'host.delete',
						'host.get', 'host.massadd',	'host.massremove', 'host.massupdate', 'host.update', 'hostgroup.delete',
						'hostgroup.get', 'hostgroup.massadd', 'hostgroup.massremove', 'hostgroup.massupdate',
						'hostgroup.update', 'hostinterface.create', 'hostinterface.delete', 'hostinterface.get',
						'hostinterface.massadd', 'hostinterface.massremove', 'hostinterface.replacehostinterfaces',
						'hostinterface.update', 'hostprototype.create', 'hostprototype.delete', 'hostprototype.get',
						'hostprototype.update', 'housekeeping.get', 'httptest.create', 'httptest.delete', 'httptest.get',
						'httptest.update', 'iconmap.get', 'image.get', 'item.create', 'item.delete', 'item.get',
						'item.update', 'itemprototype.create', 'itemprototype.delete', 'itemprototype.get',
						'itemprototype.update', 'maintenance.create', 'maintenance.delete', 'maintenance.get',
						'maintenance.update', 'map.create', 'map.delete', 'map.get', 'map.update', 'mediatype.get',
						'module.get', 'problem.get', 'proxy.get', 'proxygroup.get', 'report.create', 'report.delete',
						'report.get', 'report.update', 'role.get', 'script.execute', 'script.get',
						'script.getscriptsbyevents', 'script.getscriptsbyhosts', 'service.create', 'service.delete',
						'service.get', 'service.update', 'settings.get', 'sla.create', 'sla.delete', 'sla.get',
						'sla.getsli', 'sla.update', 'template.create', 'template.delete', 'template.get',
						'template.massadd', 'template.massremove', 'template.massupdate', 'template.update',
						'templatedashboard.create', 'templatedashboard.delete', 'templatedashboard.get',
						'templatedashboard.update', 'templategroup.delete', 'templategroup.get', 'templategroup.massadd',
						'templategroup.massremove', 'templategroup.massupdate', 'templategroup.update', 'token.create',
						'token.delete', 'token.generate', 'token.get', 'token.update', 'trend.get', 'trigger.create',
						'trigger.delete', 'trigger.get', 'trigger.update', 'triggerprototype.create',
						'triggerprototype.delete', 'triggerprototype.get', 'triggerprototype.update', 'user.get',
						'user.logout', 'user.update', 'usergroup.get', 'usermacro.create', 'usermacro.delete',
						'usermacro.get', 'usermacro.update', 'valuemap.create', 'valuemap.delete', 'valuemap.get',
						'valuemap.update'
					]
				]
			],
			// Super Admin role.
			[
				[
					'fields' => [
						'Name' => 'super_admin_api',
						'User type' => 'Super admin'
					],
					'api_list' => [
						'action.create', 'action.delete', 'action.get', 'action.update', 'alert.get', 'auditlog.get',
						'authentication.get', 'authentication.update', 'autoregistration.get', 'autoregistration.update',
						'configuration.export', 'configuration.import', 'configuration.importcompare', 'connector.create',
						'connector.delete', 'connector.get', 'connector.update', 'correlation.create', 'correlation.delete',
						'correlation.get', 'correlation.update', 'dashboard.create', 'dashboard.delete',
						'dashboard.get', 'dashboard.update', 'dcheck.get', 'dhost.get', 'discoveryrule.create',
						'discoveryrule.delete', 'discoveryrule.get', 'discoveryrule.update', 'drule.create', 'drule.delete',
						'drule.get', 'drule.update', 'dservice.get', 'event.acknowledge', 'event.get', 'graph.create',
						'graph.delete', 'graph.get', 'graph.update', 'graphitem.get', 'graphprototype.create',
						'graphprototype.delete', 'graphprototype.get', 'graphprototype.update', 'hanode.get',
						'history.clear', 'history.get', 'history.push', 'host.create', 'host.delete', 'host.get',
						'host.massadd', 'host.massremove', 'host.massupdate', 'host.update', 'hostgroup.create',
						'hostgroup.delete', 'hostgroup.get', 'hostgroup.massadd', 'hostgroup.massremove',
						'hostgroup.massupdate', 'hostgroup.propagate', 'hostgroup.update', 'hostinterface.create',
						'hostinterface.delete', 'hostinterface.get', 'hostinterface.massadd', 'hostinterface.massremove',
						'hostinterface.replacehostinterfaces', 'hostinterface.update', 'hostprototype.create',
						'hostprototype.delete', 'hostprototype.get', 'hostprototype.update', 'housekeeping.get',
						'housekeeping.update', 'httptest.create', 'httptest.delete', 'httptest.get', 'httptest.update',
						'iconmap.create', 'iconmap.delete', 'iconmap.get', 'iconmap.update',
						'image.create', 'image.delete', 'image.get', 'image.update', 'item.create', 'item.delete',
						'item.get', 'item.update', 'itemprototype.create', 'itemprototype.delete', 'itemprototype.get',
						'itemprototype.update', 'maintenance.create', 'maintenance.delete', 'maintenance.get',
						'maintenance.update', 'map.create', 'map.delete', 'map.get', 'map.update', 'mediatype.create',
						'mediatype.delete', 'mediatype.get', 'mediatype.update', 'mfa.create', 'mfa.delete', 'mfa.get',
						'mfa.update', 'module.create', 'module.delete', 'module.get', 'module.update', 'problem.get',
						'proxy.create', 'proxy.delete', 'proxy.get', 'proxy.update', 'proxygroup.create',
						'proxygroup.delete', 'proxygroup.get', 'proxygroup.update', 'regexp.create', 'regexp.delete',
						'regexp.get', 'regexp.update', 'report.create', 'report.delete', 'report.get', 'report.update',
						'role.create', 'role.delete', 'role.get', 'role.update', 'script.create', 'script.delete',
						'script.execute', 'script.get', 'script.getscriptsbyevents', 'script.getscriptsbyhosts',
						'script.update', 'service.create', 'service.delete', 'service.get', 'service.update',
						'settings.get', 'settings.update', 'sla.create', 'sla.delete', 'sla.get', 'sla.getsli',
						'sla.update', 'task.create', 'task.get', 'template.create', 'template.delete', 'template.get',
						'template.massadd', 'template.massremove', 'template.massupdate', 'template.update',
						'templatedashboard.create', 'templatedashboard.delete', 'templatedashboard.get',
						'templatedashboard.update', 'templategroup.create', 'templategroup.delete', 'templategroup.get',
						'templategroup.massadd', 'templategroup.massremove', 'templategroup.massupdate',
						'templategroup.propagate', 'templategroup.update', 'token.create', 'token.delete',
						'token.generate', 'token.get', 'token.update', 'trend.get', 'trigger.create', 'trigger.delete',
						'trigger.get', 'trigger.update', 'triggerprototype.create', 'triggerprototype.delete',
						'triggerprototype.get', 'triggerprototype.update', 'user.create', 'user.delete', 'user.get',
						'user.logout', 'user.provision', 'user.resettotp', 'user.unblock', 'user.update',
						'userdirectory.create', 'userdirectory.delete', 'userdirectory.get', 'userdirectory.test',
						'userdirectory.update', 'usergroup.create', 'usergroup.delete', 'usergroup.get',
						'usergroup.update', 'usermacro.create', 'usermacro.createglobal', 'usermacro.delete',
						'usermacro.deleteglobal', 'usermacro.get', 'usermacro.update', 'usermacro.updateglobal',
						'valuemap.create', 'valuemap.delete', 'valuemap.get', 'valuemap.update'
					]
				]
			]
		];
	}

	/**
	 * Check available API requests for each role type.
	 *
	 * @dataProvider getApiListData
	 */
	public function testFormUserRoles_ApiList($data) {
		$this->page->login()->open('zabbix.php?action=userrole.edit');
		$selector = 'xpath://div[@id="api_methods_"]/following::button[text()="Select"]';
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asGridForm()->one();
		$form->fill($data['fields']);
		$this->query($selector)->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertTableDataColumn($data['api_list']);
		$overlay->query('id:all_records')->asCheckbox()->one()->check();
		$overlay->query('button:Select')->one()->click();

		// Open the list of API methods and check that random method is selected and disabled.
		$this->query($selector)->one()->click();
		$overlay->waitUntilReady();
		$method = $data['api_list'][array_rand($data['api_list'])];
		$this->assertTrue($overlay->query('name:item['.$method.']')->one()->isAttributePresent(['checked', 'disabled']));
		$overlay->close();

		$form->submit();
		$sql_api = 'SELECT * FROM role_rule WHERE type=1 and roleid in (SELECT roleid FROM role WHERE name='
				.zbx_dbstr($data['fields']['Name']).')'.' ORDER BY value_str ASC';
		$role_rules = CDBHelper::getColumn($sql_api, 'value_str');
		sort($role_rules);
		$this->assertEquals($data['api_list'], $role_rules);
	}

	public function testFormUserRoles_SimpleUpdate() {
		$hash_before = CDBHelper::getHash(self::ROLE_SQL);
		$this->page->login()->open('zabbix.php?action=userrole.list');
		$this->query('link', 'Admin role')->one()->click();
		$this->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'User role updated');
		$this->assertEquals($hash_before, CDBHelper::getHash(self::ROLE_SQL));
	}

	public static function getUpdateData() {
		return [
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
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
					'fields' => [
						'Name' => 'User role '
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'User role "User role" already exists.'
				]
			],
			// All UI elements disabled.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Dashboards' => false,
						'Monitoring' => [],
						'Services' => [],
						'Inventory' => [],
						'Reports' => []
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'At least one UI element must be enabled for user role "role_for_update".'
				]
			],
			// Read-write service tag name not specified.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Read-write access to services' => 'Service list'
					],
					'write_services' => [
						'Read-write access to services with tag' => [
							'service_write_tag_value' => 'value'
						]
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'Cannot have non-empty tag value while having empty tag in rule "services.write.tag"'
							.' for user role "role_for_update".'
				]
			],
			// Read-only service tag name not specified.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Read-only access to services' => 'Service list'
					],
					'write_services' => [
						'Read-only access to services with tag' => [
							'service_read_tag_value' => 'value'
						]
					],
					'message_header' => 'Cannot update user role',
					'message_details' => 'Cannot have non-empty tag value while having empty tag in rule "services.read.tag"'
							.' for user role "role_for_update".'
				]
			],
			// Change name.
			[
				[
					'expected' => TEST_GOOD,
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
					'to_user' => true,
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
					'fields' => [],
					'api_methods' => '',
					'message_header' => 'User role updated'
				]
			],
			// Allow API list.
			[
				[
					'expected' => TEST_GOOD,
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
					'fields' => [
						'API methods' => 'Deny list',
						'Create and edit dashboards' => false,
						'Create and edit maps' => false,
						'Add problem comments' => false,
						'Change severity' => false,
						'Acknowledge problems' => false,
						'Close problems' => false,
						'Execute scripts' => false,
						'Manage API tokens' => false,
						'Default access to new actions' => false
					],
					'message_header' => 'User role updated'
				]
			],
			// Access to services set to None.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Read-write access to services' => 'None',
						'Read-only access to services' => 'None'
					],
					'message_header' => 'User role updated'
				]
			],
			// Access to services set to All in both cases.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Read-write access to services' => 'All',
						'Read-only access to services' => 'All'
					],
					'message_header' => 'User role updated'
				]
			],
			// Access to services set to Service list but the list is empty.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'Service list'
					],
					'override_service_access' => 'None',
					'message_header' => 'User role updated'
				]
			],
			// Access to services set to Service list.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Read-only access to services' => 'Service list',
						// added element 'API methods' with default value for page scroll
						'API methods' => 'Deny list'
					],
					'read_services' => [
						'xpath:(//div[@class="multiselect-control"])[2]' => ['Update service', 'Service for delete 2']
					],
					'message_header' => 'User role updated'
				]
			],
			// Access to services set to Service list.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Read-write access to services' => 'Service list',
						'Read-only access to services' => 'Service list',
						// added element 'API methods' with default value for page scroll
						'API methods' => 'Deny list'
					],
					'write_services' => [
						'xpath:(//div[@class="multiselect-control"])[1]' => ['Update service', 'Service for delete 2'],
						'Read-write access to services with tag' => [
							'service-write-tag-tag' => 'tag-write',
							'service_write_tag_value' => 'value-write'
						]
					],
					'read_services' => [
						'xpath:(//div[@class="multiselect-control"])[2]' => 'Update service',
						'Read-only access to services with tag' => [
							'service-read-tag-tag' => 'tag-read',
							'service_read_tag_value' => 'value-read'
						]
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
		$id = (array_key_exists('to_user', $data)) ? self::$delete_roleid : self::$roleid;
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid='.$id);
		$this->checkRoleAction($data, 'update');
	}

	public function testFormUserRoles_Clone() {
		$this->page->login()->open('zabbix.php?action=userrole.edit&roleid=2');
		$form = $this->query('id:userrole-form')->waitUntilReady()->asForm()->one();
		$values = $form->getFields()->asValues();
		$role_name = $values['Name'];
		$this->query('button:Clone')->one()->click();
		$this->page->waitUntilReady();
		$form->invalidate();
		$form->fill(['Name' => 'Cloned_'.$role_name]);
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'User role created');
		foreach([$role_name, 'Cloned_'.$role_name] as $role) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM role WHERE name='.zbx_dbstr($role)));
		}

		$this->query('link', 'Cloned_'.$role_name)->one()->click();
		$cloned_values = $form->getFields()->asValues();
		$this->assertEquals('Cloned_'.$role_name, $cloned_values['Name']);

		// Field Name removed from arrays.
		unset($cloned_values['Name']);
		unset($values['Name']);
		$this->assertEquals($values, $cloned_values);
	}

	public function testFormUserRoles_Delete() {
		$this->page->login()->open('zabbix.php?action=userrole.list');
		foreach (['Admin role', 'role_for_delete'] as $role) {
			if ($role === 'Admin role') {
				$hash_before = CDBHelper::getHash(self::ROLE_SQL);
			}
			$this->query('link', $role)->one()->click();
			$this->query('button:Delete')->one()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			if ($role === 'Admin role') {
				$this->assertMessage(TEST_BAD, 'Cannot delete user role', 'Cannot delete assigned user role "Admin role".');
				$this->assertEquals($hash_before, CDBHelper::getHash(self::ROLE_SQL));
			}
			else {
				$this->assertMessage(TEST_GOOD, 'User role deleted');
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM role WHERE name='. zbx_dbstr('role_for_delete')));
			}
		}
	}

	public function testFormUserRoles_Cancellation() {
		foreach(['userrole.edit', 'userrole.edit&roleid=2'] as $link) {
			$hash_before = CDBHelper::getHash(self::ROLE_SQL);
			$this->page->login()->open('zabbix.php?action='.$link);
			$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
			$form->fill(['Name' => 'cancellation_name_user']);
			$this->query('button:Cancel')->one()->click();
			$this->assertEquals($hash_before, CDBHelper::getHash(self::ROLE_SQL));
		}
	}

	/**
	 * Checking, that created super admin can't change it's own role.
	 */
	public function testFormUserRoles_SuperAdmin() {
		$this->page->userLogin('super_role_check', 'test5678');
		$this->page->open('zabbix.php?action=userrole.list')->waitUntilReady();
		$this->query('link:super_role')->one()->click();
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
		$this->assertEquals('User cannot change the user type of own role.',
				$this->query('xpath://input[@id="type"]/following::span')->one()->getText()
		);
		$this->assertEquals('true', $form->getField('User type')->getAttribute('readonly'));
	}

	/**
	 *  Checking layout after enabling modules.
	 */
	public function testFormUserRoles_Modules() {
		$this->page->login();

		foreach ([true, false] as $enable_modules) {
			$modules = ['4th Module', '5th Module'];
			$this->page->open('zabbix.php?action=userrole.edit&roleid=2')->waitUntilReady();
			$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();

			if ($enable_modules === true) {
				foreach ($modules as $module) {
					$this->assertFalse($form->query("xpath:.//label[text()=".CXPathHelper::escapeQuotes($module)."]")
							->one(false)->isValid()
					);
				}

				$this->page->open('zabbix.php?action=module.list')->waitUntilReady();
				$this->query('button:Scan directory')->one()->click();
				$this->page->waitUntilReady();
				CMessageElement::find()->one()->close();
				$table = $this->query('class:list-table')->asTable()->one();
				$table->findRows('Name', $modules)->select();
				$this->query('button:Enable')->one()->click();
				$this->page->acceptAlert();
				$this->page->waitUntilReady();

				$this->assertMessage(TEST_GOOD, 'Modules enabled');
				CMessageElement::find()->one()->close();
			}
			else {
				foreach ($modules as $module) {
					$form->getField($module)->isChecked();
				}
			}
		}
	}

	/**
	 *  Checking layout with opened Add services dialog.
	 */
	public function testFormUserRoles_ServicesLayout() {
		$services_table = [
			[
				'Name' => 'Service for delete by checkbox',
				'Tags' => '',
				'Problem tags' => ''
			],
			[
				'Name' => 'Service for delete',
				'Tags' => ['remove_tag_2: remove_value_2'],
				'Problem tags' => ''
			],
			[
				'Name' => 'Service for delete 2',
				'Tags' => ['3rd_tag: 3rd_value', '4th_tag: 4th_value', 'remove_tag_1: remove_value_1', 'remove_tag_2: remove_value_2'],
				'Problem tags' => ['tag1: value1', 'tag2: value2', 'tag3: value3', 'tag4: value4']
			]
		];

		$this->page->login();
		$this->page->open('zabbix.php?action=userrole.edit&roleid=2')->waitUntilReady();
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();

		foreach (['Read-write' => '[1]', 'Read-only' => '[2]'] as $field => $i) {
			$form->getField($field.' access to services')->fill('Service list');
			$multiselect = $this->query('xpath:(//div[@class="multiselect-control"])'.$i)->asMultiselect()->one();
			$this->assertTrue($multiselect->isVisible());
			$multiselect->edit();

			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$this->assertEquals('Add services', $dialog->getTitle());

			// Check filter form.
			$filter_form = $dialog->query('name:services_filter_form')->one();
			$this->assertEquals('Name', $filter_form->query('xpath:.//label')->one()->getText());
			$filter_input = $filter_form->query('name:filter_name')->one();
			$this->assertEquals(255, $filter_input->getAttribute('maxlength'));
			$this->assertEquals(4, $dialog->query('button', ['Filter', 'Reset', 'Select', 'Cancel'])->all()
					->filter(new CElementFilter(CElementFilter::CLICKABLE))->count());

			$table = $dialog->query('class:list-table')->asTable()->one();
			$this->assertEquals(['', 'Name', 'Tags', 'Problem tags'], $table->getHeadersText());

			// Check problem and service tags in hint if there are more than 3 tags for service.
			foreach ($services_table as &$service) {
				foreach (['Problem tags' => &$service['Problem tags'], 'Tags' => &$service['Tags']] as $tag_type => &$tags) {
					if (is_array($tags)) {
						if (count($tags) > 3) {
							$table->findRow('Name', $service['Name'])->getColumn($tag_type)
									->query('class:zi-more')->one()->click();
							$popup = $this->query('xpath://div[@data-hintboxid]')->one()->waitUntilReady();
							foreach ($tags as $tag) {
								$this->assertTrue($popup->query("xpath:.//span[text()=".CXPathHelper::escapeQuotes($tag)."]")
										->one(false)->isValid()
								);
							}
							$popup->query('class:btn-overlay-close')->one()->click();

							// Leave only 3 tags in array as it is the maximal number of tags displayed in table per row.
							array_splice($tags, 3);
						}
						// Combine all tags into a single string so that it would be valid for comparison.
						$tags = implode('',$tags);
					}
				}
				unset($tags);
			}

			// Filter out all unwanted services before checking table content.
			$filter_input->fill('Service for delete');
			$filter_button = $dialog->query('button:Filter')->one();
			$filter_button->click();
			$dialog->waitUntilReady();

			// Check the content of the services list with modified expected value in tags column.
			$this->assertTableData($services_table);

			// Check filtering of services by name.
			$searches = [
				'child 1' => ['Child 1', 'Clone child 1'],
				' 2 ' => ['Parent for 2 levels of child services'],
				'empty result' => null
			];

			foreach ($searches as $string => $result) {
				$filter_form->query('name:filter_name')->one()->fill($string);
				$filter_button->click();
				$dialog->waitUntilReady();

				/**
				 * After the filter is submitted, check that the expected services are returned in the list,
				 * or that 'No data found' message is returned.
				 */
				if ($result !== null) {
					$this->assertTableDataColumn($result);
				}
				else {
					$this->assertTableData();
				}
			}

			// Select one of the Services and make sure its not displayed in the list anymore.
			$filter_form->query('button:Reset')->one()->click();
			$dialog->invalidate();
			$dialog->query('link:Service for delete by checkbox')->waitUntilClickable()->one()->click();
			$dialog->ensureNotPresent();

			$multiselect->edit();
			$dialog->invalidate();

			// Filter out all unwanted services before checking table content.
			$dialog->query('name:filter_name')->one()->fill('Service for delete');
			$dialog->query('button:Filter')->one()->click();
			$dialog->waitUntilReady();

			$this->assertTableDataColumn(['Service for delete', 'Service for delete 2']);
			$dialog->close();

			// Check the layout of tag related fields in Service section of user role config form.
			$tag_field = $form->getField($field.' access to services with tag');
			foreach (['tag', 'value'] as $input_type) {
				$input = $tag_field->query('xpath:.//input[contains(@name, '.CXPathHelper::escapeQuotes('tag_'.$input_type).')]')->one();
				$this->assertEquals($input_type, $input->getAttribute('placeholder'));
				$this->assertEquals(255, $input->getAttribute('maxlength'));
			}
		}
	}

	/**
	 * Create or update role.
	 *
	 * @param array $data		given data provider
	 * @param string $action	create or update
	 */
	private function checkRoleAction($data, $action) {
		// TODO: remove if ($action === 'create'), after ZBX-19246 fix
		if ($action === 'create') {
			if ($data['expected'] === TEST_BAD) {
				$hash_before = CDBHelper::getHash(self::ROLE_SQL);
			}
		}
		$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);

		if (array_key_exists('write_services', $data) || array_key_exists('read_services', $data)) {
			$services = array_merge(CTestArrayHelper::get($data, 'write_services', []),
					CTestArrayHelper::get($data,'read_services', [])
			);
			$form->fill($services);
		}

		if (array_key_exists('api_methods', $data)) {
			$this->query('xpath:(//div[@class="multiselect-control"])[3]')->asMultiselect()->one()->fill($data['api_methods']);
		}
		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['message_header'], $data['message_details']);

			// TODO: remove if ($action === 'create'), after ZBX-19246 fix
			if ($action === 'create') {
				$this->assertEquals($hash_before, CDBHelper::getHash(self::ROLE_SQL));
			}
		}
		else {
			$this->assertMessage(TEST_GOOD, $data['message_header']);

			if ($action === 'create') {
				$created_roleid = CDBHelper::getValue('SELECT roleid FROM role WHERE name='.zbx_dbstr(trim($data['fields']['Name'])));
				$this->assertNotEquals(null, $created_roleid);
				$this->page->open('zabbix.php?action=userrole.edit&roleid='.$created_roleid);
			}
			else {
				$id = (array_key_exists('to_user', $data)) ? self::$delete_roleid : self::$roleid;
				$this->page->login()->open('zabbix.php?action=userrole.edit&roleid='.$id);
			}

			$form = $this->query('id:userrole-form')->waitUntilPresent()->asForm()->one();

			if (array_key_exists('space', $data)) {
				$data['fields']['Name'] = trim($data['fields']['Name']);
			}

			if (array_key_exists('override_service_access', $data)) {
				foreach (['Read-write', 'Read-only'] as $field) {
					$data['fields'][$field.' access to services'] = $data['override_service_access'];
				}
			}

			$form->checkValue($data['fields']);

			if (array_key_exists('write_services', $data) || array_key_exists('read_services', $data)) {
				$form->checkValue($services);
			}

			if (array_key_exists('api_methods', $data)) {
				$api_methods = $this->query('xpath:(//div[@class="multiselect-control"])[3]')->asMultiselect()->one()->getValue();
				if (is_array($api_methods)) {
					rsort($api_methods);
				}
				$this->assertEquals($data['api_methods'], $api_methods);
			}
		}
	}
}
