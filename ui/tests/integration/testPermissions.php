<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for permissions
 *
 * @required-components server, agent
 * @configurationDataProvider serverConfigurationProvider
 * @backup hosts,actions,permission,alerts
 * @backup auditlog,changelog,config
 */
class testPermissions extends CIntegrationTest {
	const HOST_NAME_01 = 'h01';
	const HOST_NAME_02 = 'h02';
	const HOST_NAME_03 = 'h03';

	const HOSTGROUP_NAME_01 = 'hg01';
	const HOSTGROUP_NAME_02 = 'hg02';
	const HOSTGROUP_NAME_03 = 'hg03';

	const USER_NAME_01 = 'u01';
	const USER_NAME_02 = 'u02';
	const USER_NAME_03 = 'u03';

	const USERGROUP_NAME_01 = 'ug01';
	const USERGROUP_NAME_02 = 'ug02';
	const USERGROUP_NAME_03 = 'ug03';

	const HOST_METADATA1 = 'host metadata autoreg host';
	const HOST_METADATA2 = 'host metadata add group';
	const HOST_METADATA3 = 'host metadata remove group';

	const AUTOREG_ACTION_HOST_ADD = 'Test autoregistration action: add host';
	const AUTOREG_ACTION_HOSTGROUP_ADD = 'Test autoregistration action: add host group';
	const AUTOREG_ACTION_HOSTGROUP_REMOVE = 'Test autoregistration action: remove host group';
	const TRIGGER_ACTION_NAME = 'Test trigger action';

	const TEMPLATE_NAME = 'Template with item and trigger';
	const ITEM_NAME = 'trap_001';
	const TRIGGER_NAME = 'Trigger to test permissions';
	const TRIGGER_EXPRESSION = 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_NAME.')='.TRIGGER_VALUE_TRUE;

	private static $discovered_hostgroupid;
	private static $triggerid;
	private static $ts = 0;

	private static $userids = [];
	private static $hostids = [];
	private static $hostgroupids = [];
	private static $usergroupids = [];
	private static $templateids = [];

	public static $HOST_METADATA = self::HOST_METADATA1;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Get media type id
		$response = $this->call('mediatype.get', [
			'filter' => [
				'name' => 'Email'
			]
		]);

		$this->assertCount(1, $response['result']);
		$mediatypeid = $response['result'][0]['mediatypeid'];

		// Enable media type
		$response = $this->call('mediatype.update', [
			'mediatypeid' => $mediatypeid,
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		]);

		$this->assertCount(1, $response['result']['mediatypeids']);
		$this->assertEquals($mediatypeid, $response['result']['mediatypeids'][0]);

		// Delete hosts
		$response = $this->call('host.get', []);

		$hostids = [];
		foreach ($response['result'] as $host) {
			$hostids[] = $host['hostid'];
		}

		$this->call('host.delete', $hostids);
		$response = $this->call('host.get', []);
		$this->assertCount(0, $response['result']);

		// Get template group ID
		$response = $this->call('templategroup.get', [
			'filter' => [
				'name' => 'Templates'
			]
		]);

		$this->assertCount(1, $response['result']);
			$templategroupid = $response['result'][0]['groupid'];

		// Create templates
		$templates = [
			[
				'host' => self::TEMPLATE_NAME,
				'groups' => [
					'groupid' => $templategroupid
				]
			]
		];

		$response = $this->call('template.create', $templates);

		// Get template IDs
		$templateids = $response['result']['templateids'];

		foreach ($templates as $i => $template)
			self::$templateids[$template['host']] = $templateids[$i];

		// Create item
		$items = [
			[
				'name' => self::ITEM_NAME,
				'key_' => self::ITEM_NAME,
				'hostid' => self::$templateids[self::TEMPLATE_NAME],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		];

		$response = $this->call('item.create', $items);
		$this->assertCount(1, $response['result']['itemids']);

		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => self::TRIGGER_NAME,
			'priority' => TRIGGER_SEVERITY_HIGH,
			'status' => TRIGGER_STATUS_ENABLED,
			'type' => 0,
			'recovery_mode' => 0,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'expression' => self::TRIGGER_EXPRESSION
		]);

		$this->assertCount(1, $response['result']['triggerids']);
		self::$triggerid = $response['result']['triggerids'][0];

		// Create host groups
		$hostgroups = [
			[
				'name' => self::HOSTGROUP_NAME_01
			],
			[
				'name' => self::HOSTGROUP_NAME_02
			],
			[
				'name' => self::HOSTGROUP_NAME_03
			]
		];
		$response = $this->call('hostgroup.create', $hostgroups);
		$this->assertCount(count($hostgroups), $response['result']['groupids']);

		// Get host group IDs
		$hostgroupids = $response['result']['groupids'];

		foreach ($hostgroups as $i => $hostgroup)
			self::$hostgroupids[$hostgroup['name']] = $hostgroupids[$i];

		// Create hosts
		$hosts = [
			[
				'host' => self::HOST_NAME_02,
				'groups' => [
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_01]
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateids[self::TEMPLATE_NAME]
					]
				]
			],
			[
				'host' => self::HOST_NAME_03,
				'groups' => [
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_01]
					],
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_02]
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateids[self::TEMPLATE_NAME]
					]
				]
			]
		];

		$response = $this->call('host.create', $hosts);
		$this->assertCount(count($hosts), $response['result']['hostids']);

		// Get host IDs
		$hostids = $response['result']['hostids'];

		foreach ($hosts as $i => $host)
			self::$hostids[$host['host']] = $hostids[$i];

		// Get discovered hosts hostgroup ID
		$response = $this->call('settings.get', [
			'output' => [ 'discovery_groupid' ]
		]);

		$this->assertCount(1, $response['result']);
			self::$discovered_hostgroupid = $response['result']['discovery_groupid'];

		// Create users
		$users = [
			[
				'username' => self::USER_NAME_01,
				'passwd' => '123QWErty!',
				'roleid' => USER_TYPE_ZABBIX_ADMIN,
				'medias' => [
					[
						'mediatypeid' => $mediatypeid,
						'sendto' => 'example@example.com'
					]
				]
			],
			[
				'username' => self::USER_NAME_02,
				'passwd' => '123QWErty!',
				'roleid' => USER_TYPE_ZABBIX_ADMIN,
				'medias' => [
					[
						'mediatypeid' => $mediatypeid,
						'sendto' => 'example@example.com'
					]
				]
			],
			[
				'username' => self::USER_NAME_03,
				'passwd' => '123QWErty!',
				'roleid' => USER_TYPE_ZABBIX_ADMIN,
				'medias' => [
					[
						'mediatypeid' => $mediatypeid,
						'sendto' => 'example@example.com'
					]
				]
			]
		];

		$response = $this->call('user.create', $users);
		$this->assertCount(count($users), $response['result']['userids']);

		// Get user IDs
		$userids = $response['result']['userids'];

		foreach ($users as $i => $user)
			self::$userids[$user['username']] = $userids[$i];

		// Create user groups
		$usergroups = [
			[
				'name' => self::USERGROUP_NAME_01,
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_01],
						'permission' => PERM_READ_WRITE
					],
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_03],
						'permission' => PERM_DENY
					]
				],
				'users' => [
					[
						'userid' => self::$userids[self::USER_NAME_01]
					]
				]
			],
			[
				'name' => self::USERGROUP_NAME_02,
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_02],
						'permission' => PERM_READ
					]
				],
				'users' => [
					[
						'userid' => self::$userids[self::USER_NAME_02]
					]
				]
			],
			[
				'name' => self::USERGROUP_NAME_03,
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_01],
						'permission' => PERM_READ_WRITE
					],
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_02],
						'permission' => PERM_DENY
					]
				],
				'users' => [
					[
						'userid' => self::$userids[self::USER_NAME_03]
					]
				]
			]
		];

		$response = $this->call('usergroup.create', $usergroups);
		$this->assertCount(count($usergroups), $response['result']['usrgrpids']);

		// Get user group IDs
		$usergroupids = $response['result']['usrgrpids'];

		foreach ($usergroups as $i => $usergroup)
			self::$usergroupids[$usergroup['name']] = $usergroupids[$i];

		// Create actions
		$response = $this->call('action.create', [
		[
			'name' => self::AUTOREG_ACTION_HOST_ADD,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_NAME_01
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_METADATA1
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
					'optemplate' => [
						[
							'templateid' => self::$templateids[self::TEMPLATE_NAME]
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_GROUP_REMOVE,
					'opgroup' => [
						[
							'groupid' => self::$discovered_hostgroupid
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_GROUP_ADD,
					'opgroup' => [
						[
							'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_01]
						]
					]
				]
			]
		],
		[
			'name' => self::AUTOREG_ACTION_HOSTGROUP_ADD,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_NAME_01
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_METADATA2
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_GROUP_ADD,
					'opgroup' => [
						[
							'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_03]
						]
					]
				]
			]
		],
		[
			'name' => self::AUTOREG_ACTION_HOSTGROUP_REMOVE,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_NAME_01
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_METADATA3
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_GROUP_REMOVE,
					'opgroup' => [
						[
							'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_03]
						]
					]
				]
			]
		],
		[
			'name' => self::TRIGGER_ACTION_NAME,
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'subject' => '{HOST.NAME}',
						'message' => '{HOST.NAME}',
						'mediatypeid' => $mediatypeid
					],
					'opmessage_usr' => [
						[
							'userid' => self::$userids[self::USER_NAME_01]
						],
						[
							'userid' => self::$userids[self::USER_NAME_02]
						],
						[
							'userid' => self::$userids[self::USER_NAME_03]
						]
					]
				]
			]
		]
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('actionids', $response['result']);
		$actionids = $response['result']['actionids'];
		$this->assertCount(4, $actionids);
	}

	private function waitForAutoregHost($hostname, $expected_hostgroups = null) {
		$max_attempts = 5;
		$sleep_time = 2;

		$request = [
			'filter' => [
				'host' => [
						$hostname
				]
			]
		];

		if ($expected_hostgroups != null) {
			$err_msg = 'Failed to update groups for autoregistered host before timeout';
			$request['selectHostGroups'] = ['name'];
		}
		else
			$err_msg = 'Failed to autoregister host before timeout';

		for ($i = 0; $i < $max_attempts; $i++) {
			try {
				$response = $this->call('host.get', $request);
				$this->assertCount(1, $response['result'], $err_msg);
				$autoreg_host = $response['result'][0];
				$this->assertArrayHasKey('hostid', $autoreg_host,
						'Failed to get host ID of the autoregistered host');

				if ($expected_hostgroups != null) {
					$this->assertArrayHasKey('hostgroups', $response['result'][0], $err_msg);
					$hostgroups = $autoreg_host['hostgroups'];
					$this->assertCount(count($expected_hostgroups), $hostgroups, $err_msg);

					foreach ($expected_hostgroups as $hostgroup)
						$this->assertContains($hostgroup, $expected_hostgroups, $err_msg);
				}

				break;
			} catch (Exception $e) {
				if ($i == $max_attempts - 1)
					throw $e;
				else
					sleep($sleep_time);
			}
		}

		return $autoreg_host['hostid'];
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME_01,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HostMetadata' => self::$HOST_METADATA
			]
		];
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 50
			]
		];
	}

	private function getTriggerId($hostid) {
		$response = $this->call('trigger.get', [
			'hostids' => [$hostid]
		]);

		$this->assertArrayHasKey(0, $response['result']);
		return $response['result'][0]['triggerid'];
	}

	private function validateTriggerStatus($triggerid, $expected_state, $expected_value) {
		$response = $this->call('trigger.get', [
			'triggerids' => [$triggerid]
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($expected_state, $response['result'][0]['state']);
		$this->assertEquals($expected_value, $response['result'][0]['value']);
	}

	private function checkAlert($userid, $hostid, $count, $ts = 0) {
		$response = $this->call('alert.get', [
			'hostids' => [
				$hostid
			],
			'userids' => [
				$userid
			],
			'time_from' => $ts
		]);

		$this->assertCount($count, $response['result']);
	}

	private function setTriggers($hosts, $fire = true, $triggerids = null) {
		if ($fire === true)
			$value = TRIGGER_VALUE_TRUE;
		else
			$value = TRIGGER_VALUE_FALSE;

		foreach ($hosts as $host) {
			$this->sendSenderValue($host, self::ITEM_NAME, $value);

			if ($triggerids === null)
				$triggerids[] = $this->getTriggerId(self::$hostids[$host]);
		}

		sleep(5);

		foreach ($triggerids as $triggerid)
			$this->validateTriggerStatus($triggerid, TRIGGER_STATE_NORMAL, $value);

		return $triggerids;
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 */
	public function testPermissions_initial()
	{
		self::$hostids[self::HOST_NAME_01] = $this->waitForAutoregHost(self::HOST_NAME_01);
		$this->reloadConfigurationCache();
		sleep(3);

		$hosts = [
			self::HOST_NAME_01,
			self::HOST_NAME_02,
			self::HOST_NAME_03
		];

		$triggerids = $this->setTriggers($hosts);

		$response = $this->call('alert.get', []);
		$this->assertCount(6, $response['result']);

		foreach ($response['result'] as $alert) {
			if ($alert['clock'] > self::$ts)
				self::$ts = $alert['clock'];
		}
		self::$ts++;

		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_01], 1);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_02], 1);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_03], 1);

		$this->checkAlert(self::$userids[self::USER_NAME_02], self::$hostids[self::HOST_NAME_03], 1);

		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_01], 1);
		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_02], 1);

		$this->setTriggers($hosts, false, $triggerids);

		self::$HOST_METADATA = self::HOST_METADATA2;
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 * @depends testPermissions_initial
	 */
	public function testPermissions_addGroup()
	{
		$hostid = $this->waitForAutoregHost(self::HOST_NAME_01, [
			['name' => self::HOSTGROUP_NAME_01],
			['name' => self::HOSTGROUP_NAME_03]
		]);

		$this->reloadConfigurationCache();
		sleep(3);

		$hosts = [
			self::HOST_NAME_01,
			self::HOST_NAME_02,
			self::HOST_NAME_03
		];

		$triggerids = $this->setTriggers($hosts);

		$response = $this->call('alert.get', [
			'time_from' => self::$ts
		]);
		$this->assertCount(5, $response['result']);

		$ts = self::$ts;

		foreach ($response['result'] as $alert) {
			if ($alert['clock'] > self::$ts)
				self::$ts = $alert['clock'];
		}

		self::$ts++;

		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_02], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_03], 1, $ts);

		$this->checkAlert(self::$userids[self::USER_NAME_02], self::$hostids[self::HOST_NAME_03], 1, $ts);

		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_01], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_02], 1, $ts);

		$this->setTriggers($hosts, false, $triggerids);

		self::$HOST_METADATA = self::HOST_METADATA3;
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 * @depends testPermissions_addGroup
	 */
	public function testPermissions_removeGroup()
	{
		$hostid = $this->waitForAutoregHost(self::HOST_NAME_01, [
			['name' => self::HOSTGROUP_NAME_01]
		]);

		$this->reloadConfigurationCache();
		sleep(3);

		$hosts = [
			self::HOST_NAME_01,
			self::HOST_NAME_02,
			self::HOST_NAME_03
		];

		$triggerids = $this->setTriggers($hosts);

		$response = $this->call('alert.get', [
			'time_from' => self::$ts
		]);
		$this->assertCount(6, $response['result']);

		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_01], 1, self::$ts);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_02], 1, self::$ts);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_03], 1, self::$ts);

		$this->checkAlert(self::$userids[self::USER_NAME_02], self::$hostids[self::HOST_NAME_03], 1, self::$ts);

		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_01], 1, self::$ts);
		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_02], 1, self::$ts);

		$this->setTriggers($hosts, false, $triggerids);
	}
}
