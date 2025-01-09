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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @onBefore prepareTestData
 *
 * @backup role
 */
class testRole extends CAPITest {

	public static function role_create() {
		return [
			// Check successful create.
			[
				'role' => [
					'name' => 'role-with-all-ui-elements',
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
								'name' => 'reports.system_info',
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
					'name' => 'New role',
					'type' => '1'
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => '☺',
					'type' => '2'
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
					'type' => '1'
				],
				'expected_error' => null
			],
			[
				'role' => [
					'name' => 'New/Nested',
					'type' => '1'
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
					'type' => '1'
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'role' => [
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256',
					'type' => '1'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check for duplicated role names.
			[
				'role' => [
					'name' => 'Super admin role',
					'type' => '1'
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
				'role' => [
					'roleid_2'
				],
				'expected_error' => null
			],
			[
				'role' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'role' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'role' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'role' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'role' => [
					'roleid_1'
				],
				'expected_error' => 'Cannot delete assigned user role "used-role".'
			]
		];
	}

	/**
	* @dataProvider role_delete
	*/
	public function testRole_Delete($role, $expected_error) {

		if ($role[0] === 'roleid_1' || $role[0] === 'roleid_2') {
			$role = [self::$data['roleids'][$role[0]]];
		}

		$result = $this->call('role.delete', $role, $expected_error);

		if ($expected_error === null) {
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
					'roleid' => 'roleid_4',
					'name' => 'Successfully updated role',
					'type' => '2'
				],
				'expected_error' => null
			],
			[
				'role' => [
					'roleid' => 'roleid_4',
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
					'roleid' => 'roleid_3',
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
					'roleid' => 'roleid_3',
					'name' => '',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'role' => [
					'roleid' => 'roleid_3',
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256',
					'type' => '3'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'role' => [
					'roleid' => 'roleid_3',
					'name' => 'Super admin role',
					'type' => '3'
				],
				'expected_error' => 'User role "Super admin role" already exists.'
			],
			// Check for removed ui elements
			[
				'role' => [
					'roleid' => 'roleid_3',
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
			]
		];
	}

	/**
	* @dataProvider role_update
	*/
	public function testRole_Update($role, $expected_error) {
		if (isset($role['roleid'])) {
			if (isset($role['roleid']) && $role['roleid'] === 'roleid_3' ||
				isset($role['roleid']) && $role['roleid'] === 'roleid_4') {
				$role['roleid'] = (int) self::$data['roleids'][$role['roleid']];
			}
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
					'roleids' => ['roleid_5']
				],
				'expected_result' => [
					'jsonrpc' => '2.0',
					'result' => [
						'roleid' => 'roleid_5',
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
					'roleids' => ['roleid_5']
				],
				'expected_result' => [
					'jsonrpc' => '2.0',
					'result' => [
						'roleid' => 'roleid_5',
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
									'name' => 'reports.system_info',
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
		if (isset($params['roleids']) && $params['roleids'] === ['roleid_5']) {
			$params['roleids'] = [(int) self::$data['roleids'][$params['roleids'][0]]];
		}
		if (isset($expected_result['result']['roleid']) && $expected_result['result']['roleid'] === 'roleid_5') {
			$expected_result['result']['roleid'] = self::$data['roleids'][$expected_result['result']['roleid']];
		}

		$result = $this->call('role.get', $params, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result'] as $role) {
				foreach ($expected_result['result'] as $field => $expected_value){
					$this->assertArrayHasKey($field, $role, 'Field should be present.');
					$this->assertEquals($role[$field], $expected_value, 'Returned value should match.');
				}
			}
		}
	}

	/**
	 * Test data used by tests.
	 */
	protected static $data = [
		'roleids' => ['roleid_1', 'roleid_2', 'roleid_3', 'roleid_4', 'roleid_5'],
		'usergroupids' => ['usergroupid_1']
	];

	/**
	 * Prepare data for tests.
	 */
	public function prepareTestData() {

		$response = CDataHelper::call('role.create', [
			[
				'name' => 'used-role',
				'type' => 2
			],
			[
				'name' => 'deletable-role',
				'type' => 1
			],
			[
				'name' => 'first-role-for-update',
				'type' => 3
			],
			[
				'name' => 'second-role-for-update',
				'type' => 3,
				'rules' => [
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
				]
			],
						[
				'name' => 'role-for-get',
				'type' => 3,
				'rules' => [
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
				]
			]
		]);

		$this->assertArrayHasKey('roleids', $response);
		self::$data['roleids'] = array_combine(self::$data['roleids'], $response['roleids']);

		$response = CDataHelper::call('usergroup.create', [
			[
				'name' => 'user-group-used-for-role.delete-tests'
			]
		]);

		$userGroupId = (int) $response['usrgrpids'][0];

		CDataHelper::call('user.create', [
			[
				'username' => 'user-used-for-role.delete-tests',
				'roleid' => self::$data['roleids']['roleid_1'],
				'passwd' => 'Z@bb1x1234',
				'usrgrps' => [
						['usrgrpid' => $userGroupId]
					]
			]
		]);
	}
}
