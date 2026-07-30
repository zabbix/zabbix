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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testRole extends CAPITest {

	private static array $roleids = [];

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'roles' => [
				['name' => 'used-role', 'type' => USER_TYPE_ZABBIX_ADMIN],
				['name' => 'deletable-role', 'type' => USER_TYPE_ZABBIX_USER],
				['name' => 'first-role-for-update', 'type' => USER_TYPE_SUPER_ADMIN],
				['name' => 'second-role-for-update', 'type' => USER_TYPE_SUPER_ADMIN, 'rules' => [
					'ui' => [
						[
							'name' => 'administration.macros',
							'status' => '0'
						],
						[
							'name' => 'administration.housekeeping',
							'status' => '1'
						]
					],
					'ui.default_access' => '0'
				]],
				['name' => 'role-for-get', 'type' => USER_TYPE_SUPER_ADMIN, 'rules' => [
					'ui' => [
						[
							'name' => 'configuration.discovery_actions',
							'status' => '0'
						],
						[
							'name' => 'configuration.internal_actions',
							'status' => '1'
						]
					],
					'ui.default_access' => '0'
				]],
				['name' => 'zabbix-user-role', 'type' => USER_TYPE_ZABBIX_USER, 'rules' => [
					'ui' => [
						[
							'name' => 'monitoring.dashboard',
							'status' => '1'
						]
					]
				]],
				['name' => 'zabbix-admin-role', 'type' => USER_TYPE_ZABBIX_ADMIN, 'rules' => [
					'ui' => [
						[
							'name' => 'monitoring.dashboard',
							'status' => '1'
						]
					]
				]]
			],
			'user_groups' => [
				['name' => 'user-group-used-for-role.delete-tests']
			],
			'users' => [
				[
					'username' => 'user-used-for-role.delete-tests',
					'roleid' => ':role:used-role',
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => ':user_group:user-group-used-for-role.delete-tests']
					]
				]
			]
		]);
	}

	public static function role_create() {
		return [
			// Check successful create.
			[
				'role' => [
					'name' => 'role-with-all-ui-elements',
					'type' => '3', // USER_TYPE_SUPER_ADMIN
					'rules' => [
						'ui' => [
							[
								'name' => 'monitoring.dashboard',
								'status' => '1'
							],
							[
								'name' => 'monitoring.problems',
								'status' => '1'
							],
							[
								'name' => 'monitoring.hosts',
								'status' => '1'
							],
							[
								'name' => 'monitoring.latest_data',
								'status' => '1'
							],
							[
								'name' => 'monitoring.maps',
								'status' => '1'
							],
							[
								'name' => 'services.services',
								'status' => '1'
							],
							[
								'name' => 'services.sla_report',
								'status' => '1'
							],
							[
								'name' => 'inventory.overview',
								'status' => '1'
							],
							[
								'name' => 'inventory.hosts',
								'status' => '1'
							],
							[
								'name' => 'reports.availability_report',
								'status' => '1'
							],
							[
								'name' => 'reports.top_triggers',
								'status' => '1'
							],
							[
								'name' => 'monitoring.discovery',
								'status' => '1'
							],
							[
								'name' => 'services.sla',
								'status' => '1'
							],
							[
								'name' => 'reports.scheduled_reports',
								'status' => '1'
							],
							[
								'name' => 'reports.system_info',
								'status' => '1'
							],
							[
								'name' => 'reports.notifications',
								'status' => '1'
							],
							[
								'name' => 'configuration.template_groups',
								'status' => '1'
							],
							[
								'name' => 'configuration.host_groups',
								'status' => '1'
							],
							[
								'name' => 'configuration.templates',
								'status' => '1'
							],
							[
								'name' => 'configuration.hosts',
								'status' => '1'
							],
							[
								'name' => 'configuration.maintenance',
								'status' => '1'
							],
							[
								'name' => 'configuration.discovery',
								'status' => '1'
							],
							[
								'name' => 'configuration.trigger_actions',
								'status' => '1'
							],
							[
								'name' => 'configuration.service_actions',
								'status' => '1'
							],
							[
								'name' => 'configuration.discovery_actions',
								'status' => '1'
							],
							[
								'name' => 'configuration.autoregistration_actions',
								'status' => '1'
							],
							[
								'name' => 'configuration.internal_actions',
								'status' => '1'
							],
							[
								'name' => 'reports.audit',
								'status' => '1'
							],
							[
								'name' => 'reports.action_log',
								'status' => '1'
							],
							[
								'name' => 'configuration.event_correlation',
								'status' => '1'
							],
							[
								'name' => 'administration.media_types',
								'status' => '1'
							],
							[
								'name' => 'administration.scripts',
								'status' => '1'
							],
							[
								'name' => 'administration.user_groups',
								'status' => '1'
							],
							[
								'name' => 'administration.user_roles',
								'status' => '1'
							],
							[
								'name' => 'administration.users',
								'status' => '1'
							],
							[
								'name' => 'administration.authentication',
								'status' => '1'
							],
							[
								'name' => 'administration.linked_devices',
								'status' => '1'
							],
							[
								'name' => 'administration.general',
								'status' => '1'
							],
							[
								'name' => 'administration.housekeeping',
								'status' => '1'
							],
							[
								'name' => 'administration.proxy_groups',
								'status' => '1'
							],
							[
								'name' => 'administration.proxies',
								'status' => '1'
							],
							[
								'name' => 'administration.macros',
								'status' => '1'
							],
							[
								'name' => 'administration.queue',
								'status' => '1'
							]
						],
						'ui.default_access' => '0',
						'services.read.mode' => '1',
						'services.read.list' => [],
						'services.read.tag' => [
							'tag' => '',
							'value' => ''
						],
						'services.write.mode' => '0',
						'services.write.list' => [],
						'services.write.tag' => [
							'tag' => '',
							'value' => ''
						],
						'modules' => [],
						'modules.default_access' => '1',
						'api' => [],
						'api.access' => '1',
						'api.mode' => '0',
						'actions' => [
							[
								'name' => 'edit_dashboards',
								'status' => '1'
							],
							[
								'name' => 'edit_maps',
								'status' => '1'
							],
							[
								'name' => 'acknowledge_problems',
								'status' => '1'
							],
							[
								'name' => 'suppress_problems',
								'status' => '1'
							],
							[
								'name' => 'close_problems',
								'status' => '1'
							],
							[
								'name' => 'change_severity',
								'status' => '1'
							],
							[
								'name' => 'add_problem_comments',
								'status' => '1'
							],
							[
								'name' => 'execute_scripts',
								'status' => '1'
							],
							[
								'name' => 'manage_api_tokens',
								'status' => '1'
							],
							[
								'name' => 'edit_maintenance',
								'status' => '1'
							],
							[
								'name' => 'manage_scheduled_reports',
								'status' => '1'
							],
							[
								'name' => 'manage_sla',
								'status' => '1'
							],
							[
								'name' => 'invoke_execute_now',
								'status' => '1'
							]
						],
						'actions.default_access' => '1'
					]
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => 'zabbix-admin-allowed-systeminfo',
					'type' => '2', // USER_TYPE_ZABBIX_ADMIN
					'rules' => [
						'ui' => [
							[
								'name' => 'reports.system_info',
								'status' => '1'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => 'New role',
					'type' => '1' // USER_TYPE_ZABBIX_USER
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => '☺',
					'type' => '2' // USER_TYPE_ZABBIX_ADMIN
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => 'æų',
					'type' => '3'
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => 'Роль пользователя',
					'type' => '1' // USER_TYPE_ZABBIX_USER
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => 'New/Nested',
					'type' => '1' // USER_TYPE_ZABBIX_USER
				],
				'expected_error' => null
			],
			// Check invalid role type.
			[
				'role' => [
					'name' => 'non existent parameter',
					'type' => '4'
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of 1, 2, 3.'
			],
			// Check role name.
			[
				'role' => [
					'name' => '',
					'type' => '1' // USER_TYPE_ZABBIX_USER
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'role' => [
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256',
					'type' => '1' // USER_TYPE_ZABBIX_USER
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check for duplicated role names.
			[
				'role' => [
					'name' => 'Super admin role',
					'type' => '1' // USER_TYPE_ZABBIX_USER
				],
				'expected_error' => 'User role "Super admin role" already exists.'
			],
			// Check for removed ui elements
			[
				'role' => [
					'name' => 'role-with-invalid-ui-elements',
					'type' => '3',
					'rules' => [
						'ui' => [
							[
								'name' => 'services.actions',
								'status' => '1'
							]
						],
					'ui.default_access' => '0'
					]
				],
				'expected_error' =>
					'UI element "services.actions" is not available for user role "role-with-invalid-ui-elements".'
			],
			[
				'role' => [
					'name' => 'role-with-invalid-ui-elements',
					'type' => '3',
					'rules' => [
						'ui' => [
							[
								'name' => 'configuration.actions',
								'status' => '1'
							]
						],
					'ui.default_access' => '0'
					]
				],
				'expected_error' =>
					'UI element "configuration.actions" is not available for user role "role-with-invalid-ui-elements".'
			],
			[
				'role' => [
					'name' => 'zabbix-user-not-allowed-systeminfo',
					'type' => '1', // USER_TYPE_ZABBIX_USER
					'rules' => [
						'ui' => [
							[
								'name' => 'reports.system_info',
								'status' => '1'
							]
						]
					]
				],
				'expected_error' => 'UI element "reports.system_info" is not available for user role "zabbix-user-not-allowed-systeminfo".'
			]
		];
	}

	/**
	* @dataProvider role_create
	*/
	public function testRole_Create($role, $expected_error) {
		$result = $this->call('role.create', $role, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['roleids'] as $roleid) {
				self::$roleids[] = $roleid;

				$dbRow = CDBHelper::getRow('SELECT name,type FROM role WHERE roleid='.zbx_dbstr($roleid));
				$this->assertEquals($dbRow['name'], $role['name']);
				$this->assertEquals($dbRow['type'], $role['type']);

				if (isset($role['rules']['ui']) && isset($role['rules']['ui.default_access'])) {
					$dbDataUi = CDBHelper::getAll(
						'SELECT name,value_int FROM role_rule WHERE roleid='.zbx_dbstr($roleid)
					);
					foreach ($dbDataUi as $row) {
						foreach ($role['rules']['ui'] as $element) {
							if ($row['name'] === 'ui.'.$element['name']) {
								$this->assertEquals($row['value_int'], $element['status']);
							}
						}
					}
				}
			}
		}
	}

	public static function role_delete() {
		return [
			[
				'roleids' => [
					':role:deletable-role'
				],
				'expected_error' => null
			],
			[
				'roleids' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'roleids' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'roleids' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'roleids' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'roleids' => [
					':role:used-role'
				],
				'expected_error' => 'Cannot delete assigned user role "used-role".'
			]
		];
	}

	/**
	* @dataProvider role_delete
	*/
	public function testRole_Delete($roleids, $expected_error) {
		$converted_roleids = CTestDataHelper::getConvertedValueReferences($roleids);

		$result = $this->call('role.delete', $converted_roleids, $expected_error);

		if ($expected_error === null) {
			CTestDataHelper::unsetDeletedObjectIds(array_diff($roleids, $converted_roleids));

			foreach ($result['result']['roleids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM role WHERE roleid='.zbx_dbstr($id)));
			}
		}
	}

	public static function role_update() {
		return [
			// Check successful update.
			[
				'role' => [
					'roleid' => ':role:second-role-for-update',
					'name' => 'Successfully updated role',
					'type' => '2' // USER_TYPE_ZABBIX_ADMIN
				],
				'expected_error' => null
			],
			[
				'role' => [
					'roleid' => ':role:second-role-for-update',
					'name' => 'Successfully updated role',
					'type' => '3',
					'rules' => [
						'ui' => [
							[
								'name' => 'administration.macros',
								'status' => '1'
							],
							[
								'name' => 'administration.housekeeping',
								'status' => '1'
							]
						],
					'ui.default_access' => '0'
					]
				],
				'expected_error' => null
			],
			[
				'role' => [
					'roleid' => ':role:second-role-for-update',
					'name' => 'Successfully updated role',
					'type' => '3',
					'rules' => [
						'ui' => [
							[
								'name' => 'administration.macros',
								'status' => '1'
							],
							[
								'name' => 'administration.housekeeping',
								'status' => '1'
							]
						],
						'ui.default_access' => '0'
					]
				],
				'expected_error' => null
			],
			[
				'role' => [
					'roleid' => ':role:zabbix-admin-role',
					'name' => 'zabbix-admin-role',
					'type' => 2, // USER_TYPE_ZABBIX_ADMIN
					'rules' => [
						'ui' => [
							[
								'name' => 'reports.system_info',
								'status' => '1'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'role' => [
					'roleid' => ':role:first-role-for-update',
					'name' => 'non existent parameter',
					'type' => '4'
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of 1, 2, 3.'
			],
			// Check roleid.
			[
				'role' => [
					'name' => 'without roleid'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "roleid" is missing.'
			],
			[
				'role' => [
					'roleid' => '',
					'name' => 'empty roleid',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/roleid": a number is expected.'
			],
			[
				'role' => [
					'roleid' => '123456',
					'name' => 'roleid with not existing id',
					'type' => '3'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'role' => [
					'roleid' => 'abc',
					'name' => 'id is not a number',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/roleid": a number is expected.'
			],
			[
				'role' => [
					'roleid' => '57.57',
					'name' => 'id is not a number',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/roleid": a number is expected.'
			],
			// Check name.
			[
				'role' => [
					'roleid' => ':role:first-role-for-update',
					'name' => '',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'role' => [
					'roleid' => ':role:first-role-for-update',
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'role' => [
					'roleid' => ':role:first-role-for-update',
					'name' => 'Super admin role',
					'type' => '3'
				],
				'expected_error' => 'User role "Super admin role" already exists.'
			],
			// Check for removed ui elements
			[
				'role' => [
					'roleid' => ':role:first-role-for-update',
					'name' => 'Unknown ui element',
					'type' => '3',
					'rules' => [
						'ui' => [
							[
								'name' => 'services.actions',
								'status' => '1'
							],
							[
								'name' => 'administration.housekeeping',
								'status' => '1'
							]
						],
					'ui.default_access' => '0'
					]
				],
				'expected_error' =>
					'UI element "services.actions" is not available for user role "Unknown ui element".'
			],
			[
				'role' => [
					'roleid' => ':role:zabbix-user-role',
					'name' => 'zabbix-user-role',
					'type' => 1, // USER_TYPE_ZABBIX_USER
					'rules' => [
						'ui' => [
							[
								'name' => 'reports.system_info',
								'status' => '1'
							]
						]
					]
				],
				'expected_error' => 'UI element "reports.system_info" is not available for user role "zabbix-user-role".'
			]
		];
	}

	/**
	* @dataProvider role_update
	*/
	public function testRole_Update($role, $expected_error) {
		if (isset($role['roleid'])) {
			$role['roleid'] = CTestDataHelper::getConvertedValueReference($role['roleid']);
		}

		$result = $this->call('role.update', $role, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['roleids'] as $roleid) {
				$dbRow = CDBHelper::getRow('SELECT roleid,name,type FROM role WHERE roleid='.zbx_dbstr($roleid));
				$this->assertEquals($dbRow['roleid'], $role['roleid']);
				$this->assertEquals($dbRow['name'], $role['name']);
				$this->assertEquals($dbRow['type'], $role['type']);

				if (isset($role['rules']['ui']) && isset($role['rules']['ui.default_access'])) {
					$dbDataUi = CDBHelper::getAll(
						'SELECT name,value_int FROM role_rule WHERE roleid='.zbx_dbstr($roleid)
					);

					foreach ($dbDataUi as $row) {
						foreach ($role['rules']['ui'] as $element) {
							if ($row['name'] === 'ui.'.$element['name']) {
								$this->assertEquals($row['value_int'], $element['status']);
							}
						}
					}
				}
			}
		}
	}

	public static function role_get() {
		return [
			// Check successful get.
			[
				'params' => [
					'output' => ['roleid', 'name', 'type'],
					'roleids' => [':role:role-for-get']
				],
				'expected_result' => [
					'jsonrpc' => '2.0',
					'result' => [
						'roleid' => ':role:role-for-get',
						'name' => 'role-for-get',
						'type' => '3'
					],
					'id' => 3
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['roleid', 'name', 'type'],
					'selectRules' => ['ui', 'ui.default_access'],
					'roleids' => [':role:role-for-get']
				],
				'expected_result' => [
					'jsonrpc' => '2.0',
					'result' => [
						'roleid' => ':role:role-for-get',
						'name' => 'role-for-get',
						'type' => '3',
						'rules' => [
							'ui' => [
								[
									'name' => 'monitoring.dashboard',
									'status' => '1'
								],
								[
									'name' => 'monitoring.problems',
									'status' => '1'
								],
								[
									'name' => 'monitoring.hosts',
									'status' => '1'
								],
								[
									'name' => 'monitoring.latest_data',
									'status' => '1'
								],
								[
									'name' => 'monitoring.maps',
									'status' => '1'
								],
								[
									'name' => 'services.services',
									'status' => '1'
								],
								[
									'name' => 'services.sla_report',
									'status' => '1'
								],
								[
									'name' => 'inventory.overview',
									'status' => '1'
								],
								[
									'name' => 'inventory.hosts',
									'status' => '1'
								],
								[
									'name' => 'reports.availability_report',
									'status' => '1'
								],
								[
									'name' => 'reports.top_triggers',
									'status' => '1'
								],
								[
									'name' => 'monitoring.discovery',
									'status' => '1'
								],
								[
									'name' => 'services.sla',
									'status' => '1'
								],
								[
									'name' => 'reports.scheduled_reports',
									'status' => '1'
								],
								[
									'name' => 'reports.system_info',
									'status' => '1'
								],
								[
									'name' => 'reports.notifications',
									'status' => '1'
								],
								[
									'name' => 'configuration.template_groups',
									'status' => '1'
								],
								[
									'name' => 'configuration.host_groups',
									'status' => '1'
								],
								[
									'name' => 'configuration.templates',
									'status' => '1'
								],
								[
									'name' => 'configuration.hosts',
									'status' => '1'
								],
								[
									'name' => 'configuration.maintenance',
									'status' => '1'
								],
								[
									'name' => 'configuration.discovery',
									'status' => '1'
								],
								[
									'name' => 'configuration.trigger_actions',
									'status' => '1'
								],
								[
									'name' => 'configuration.service_actions',
									'status' => '1'
								],
								[
									'name' => 'configuration.autoregistration_actions',
									'status' => '1'
								],
								[
									'name' => 'configuration.internal_actions',
									'status' => '1'
								],
								[
									'name' => 'reports.audit',
									'status' => '1'
								],
								[
									'name' => 'reports.action_log',
									'status' => '1'
								],
								[
									'name' => 'configuration.event_correlation',
									'status' => '1'
								],
								[
									'name' => 'administration.media_types',
									'status' => '1'
								],
								[
									'name' => 'administration.scripts',
									'status' => '1'
								],
								[
									'name' => 'administration.user_groups',
									'status' => '1'
								],
								[
									'name' => 'administration.user_roles',
									'status' => '1'
								],
								[
									'name' => 'administration.users',
									'status' => '1'
								],
								[
									'name' => 'administration.api_tokens',
									'status' => '1'
								],
								[
									'name' => 'administration.authentication',
									'status' => '1'
								],
								[
									'name' => 'administration.linked_devices',
									'status' => '1'
								],
								[
									'name' => 'administration.general',
									'status' => '1'
								],
								[
									'name' => 'administration.audit_log',
									'status' => '1'
								],
								[
									'name' => 'administration.housekeeping',
									'status' => '1'
								],
								[
									'name' => 'administration.proxy_groups',
									'status' => '1'
								],
								[
									'name' => 'administration.proxies',
									'status' => '1'
								],
								[
									'name' => 'administration.macros',
									'status' => '1'
								],
								[
									'name' => 'administration.queue',
									'status' => '1'
								],
								[
									'name' => 'configuration.discovery_actions',
									'status' => '0'
								]
							],
							'ui.default_access' => '0'
						]
					],
					'id' => 3
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['roleid', 'name', 'type'],
					'roleids' => 'abc'
				],
				'expected_result' => false,
				'expected_error' => 'Invalid parameter "/roleids": an array is expected.'
			],
			[
				'params' => [
					'output' => ['roleid', 'name', 'type'],
					'roleids' => ['abc']
				],
				'expected_result' => false,
				'expected_error' => 'Invalid parameter "/roleids/1": a number is expected.'
			],
			[
				'params' => [
					'output' => ['roleid', 'name', 'type'],
					'roleids' => ['']
				],
				'expected_result' => false,
				'expected_error' => 'Invalid parameter "/roleids/1": a number is expected.'
			],
			[
				'params' => [
					'output' => ['flag'],
					'roleids' => ['3']
				],
				'expected_result' => false,
				'expected_error' =>
					'Invalid parameter "/output/1": value must be one of "roleid", "name", "type", "readonly".'
			]
		];
	}

	/**
	 * @dataProvider role_get
	 */
	public function testRole_Get($params, $expected_result, $expected_error) {
		if (isset($params['roleids']) && is_array($params['roleids'])) {
			$params['roleids'] = CTestDataHelper::getConvertedValueReferences($params['roleids']);
		}
		if (isset($expected_result['result']['roleid'])) {
			$expected_result['result']['roleid'] = CTestDataHelper::getConvertedValueReference(
				$expected_result['result']['roleid']
			);
		}

		$result = $this->call('role.get', $params, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result'] as $role) {
				foreach ($expected_result['result'] as $field => $expected_value){
					$this->assertArrayHasKey($field, $role, 'Field should be present.');
					$this->assertEquals($expected_value, $role[$field], 'Returned value should match.');
				}
			}
		}
	}

	public static function cleanTestData(): void {
		CDataHelper::call('role.delete', self::$roleids);

		CTestDataHelper::cleanUp();
	}
}
