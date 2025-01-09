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
	const HOST_NAME_11 = 'h11';
	const HOST_NAME_12 = 'h12';
	const HOST_NAME_13 = 'h13';
	const HOST_NAME_14 = 'h14';

	const HOSTGROUP_NAME_01 = 'hg01';
	const HOSTGROUP_NAME_02 = 'hg02';
	const HOSTGROUP_NAME_03 = 'hg03';
	const HOSTGROUP_NAME_11 = 'hg11';
	const HOSTGROUP_NAME_12 = 'hg12';
	const HOSTGROUP_NAME_13 = 'hg13';
	const HOSTGROUP_NAME_14 = 'hg14';

	const USER_NAME_01 = 'u01';
	const USER_NAME_02 = 'u02';
	const USER_NAME_03 = 'u03';
	const USER_NAME_11 = 'u11';
	const USER_NAME_12 = 'u12';

	const USERGROUP_NAME_01 = 'ug01';
	const USERGROUP_NAME_02 = 'ug02';
	const USERGROUP_NAME_03 = 'ug03';
	const USERGROUP_NAME_11 = 'ug11';
	const USERGROUP_NAME_12 = 'ug12';

	const HOST_METADATA1 = 'host metadata autoreg host';
	const HOST_METADATA2 = 'host metadata add group';
	const HOST_METADATA3 = 'host metadata remove group';
	const HOST_METADATA4 = 'host metadata autoreg host for lld';
	const HOST_METADATA5 = 'host metadata delete autoreg host for lld';

	const AUTOREG_ACTION_01_HOST_ADD = 'Test autoregistration action: add host';
	const AUTOREG_ACTION_01_HOSTGROUP_ADD = 'Test autoregistration action: add host group';
	const AUTOREG_ACTION_01_HOSTGROUP_REMOVE = 'Test autoregistration action: remove host group';
	const TRIGGER_ACTION_NAME = 'Test trigger action';

	const AUTOREG_ACTION_02_HOST_ADD = 'Test autoregistration action: add host with lld';
	const AUTOREG_ACTION_02_HOST_DEL = 'Test autoregistration action: remove host with lld';

	const TEMPLATE_NAME_01 = 'Template with item and trigger';
	const ITEM_NAME = 'trap_001';
	const TRIGGER_NAME = 'Trigger to test permissions';
	const TRIGGER_EXPRESSION = 'last(/'.self::TEMPLATE_NAME_01.'/'.self::ITEM_NAME.')='.TRIGGER_VALUE_TRUE;

	const TEMPLATE_NAME_02 = 'Template with lld';
	const LLD_NAME = 'lld_001';
	const LLD_MACRO_HP = '{#LLD.HP}';
	const LLD_MACRO_GP = '{#LLD.GP}';
	const LLD_HP_PREFIX_01 = 'hp01';
	const LLD_GP_PREFIX_01 = 'gp01';
	const LLD_GP_PREFIX_02 = 'gp02';
	const HP01_NAME = self::LLD_HP_PREFIX_01.self::LLD_MACRO_HP;
	const HP01_HOST_NAME_01 = self::LLD_HP_PREFIX_01.self::HOST_NAME_01;
	const HP01_HOSTGROUP_NAME_01 = self::LLD_GP_PREFIX_01.self::HOSTGROUP_NAME_01;
	const HP01_HOSTGROUP_NAME_02 = self::LLD_GP_PREFIX_02.self::HOSTGROUP_NAME_01;

	private static $discovered_hostgroupid;
	private static $discovered_hostgroup;
	private static $triggerid;
	private static $lldid;
	private static $ts = 0;

	private static $userids = [];
	private static $hostids = [];
	private static $hostgroupids = [];
	private static $usergroupids = [];
	private static $templateids = [];

	public static $HOST_METADATA = self::HOST_METADATA1;
	public static $LLD_HOST_METADATA = self::HOST_METADATA4;

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

		// Get discovered hosts hostgroup ID
		$response = $this->call('settings.get', [
			'output' => [ 'discovery_groupid' ]
		]);

		$this->assertCount(1, $response['result']);
		self::$discovered_hostgroupid = $response['result']['discovery_groupid'];
		self::$discovered_hostgroup = $this->getHostGroupName(self::$discovered_hostgroupid);

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
				'host' => self::TEMPLATE_NAME_01,
				'groups' => [
					'groupid' => $templategroupid
				]
			],
			[
				'host' => self::TEMPLATE_NAME_02,
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
				'hostid' => self::$templateids[self::TEMPLATE_NAME_01],
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

		// Create lld with host prototype and group prototypes
		$response = $this->call('discoveryrule.create', [
			'name' => self::LLD_NAME,
			'key_' => self::LLD_NAME,
			'hostid' => self::$templateids[self::TEMPLATE_NAME_02],
			'type' => ITEM_TYPE_TRAPPER
		]);

		$this->assertCount(1, $response['result']['itemids']);
		self::$lldid = $response['result']['itemids'][0];

		// Create host prototype.
		$response = $this->call('hostprototype.create', [
			'ruleid' => self::$lldid,
			'host' => self::HP01_NAME,
			'groupLinks' => [
				[
					'groupid' => self::$discovered_hostgroupid
				]
			],
			'groupPrototypes' => [
				[
					'name' => self::LLD_GP_PREFIX_01.self::LLD_MACRO_GP
				],
				[
					'name' => self::LLD_GP_PREFIX_02.self::LLD_MACRO_GP
				]
			],
			'templates' => [
				[
					'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
				]
			]
		]);

		$this->assertCount(1, $response['result']['hostids']);
		self::$hostids[self::HP01_NAME] = $response['result']['hostids'][0];

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
			],
			[
				'name' => self::HOSTGROUP_NAME_11
			],
			[
				'name' => self::HOSTGROUP_NAME_12
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
						'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
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
						'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
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
			],
			[
				'username' => self::USER_NAME_11,
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
				'username' => self::USER_NAME_12,
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
			'name' => self::AUTOREG_ACTION_01_HOST_ADD,
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
							'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
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
			'name' => self::AUTOREG_ACTION_01_HOSTGROUP_ADD,
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
			'name' => self::AUTOREG_ACTION_01_HOSTGROUP_REMOVE,
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
						],
						[
							'userid' => self::$userids[self::USER_NAME_11]
						],
						[
							'userid' => self::$userids[self::USER_NAME_12]
						]
					]
				]
			]
		],
[
			'name' => self::AUTOREG_ACTION_02_HOST_ADD,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_NAME_11
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_METADATA4
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
					'optemplate' => [
						[
							'templateid' => self::$templateids[self::TEMPLATE_NAME_02]
						]
					]
				]
			]
		],
		[
			'name' => self::AUTOREG_ACTION_02_HOST_DEL,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_NAME_11
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::HOST_METADATA5
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_REMOVE
				]
			]
		]
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('actionids', $response['result']);
		$actionids = $response['result']['actionids'];
		$this->assertCount(6, $actionids);
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
	public function agentConfigurationProviderLld() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME_11,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HostMetadata' => self::$LLD_HOST_METADATA
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

	private function waitForHost($hostname, $expected_hostgroups = null) {
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
						$this->assertContains($hostgroup, $hostgroups, $err_msg);
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

	private function waitForHostRemoved($hostname) {
		$max_attempts = 5;
		$sleep_time = 2;

		$request = [
			'filter' => [
				'host' => [
						$hostname
				]
			]
		];

		for ($i = 0; $i < $max_attempts; $i++) {
			try {
				$response = $this->call('host.get', $request);
				$this->assertCount(0, $response['result'], 'Failed to remove host before timeout');

				break;
			} catch (Exception $e) {
				if ($i == $max_attempts - 1)
					throw $e;
				else
					sleep($sleep_time);
			}
		}
	}

	private function getTriggerId($hostid) {
		$response = $this->call('trigger.get', [
			'hostids' => [$hostid]
		]);

		$this->assertArrayHasKey(0, $response['result']);
		return $response['result'][0]['triggerid'];
	}

	private function waitForTriggersStatus($triggerids, $expected_state, $expected_value) {
		$max_attempts = 10;
		$sleep_time = 1;
		$err_msg = 'Failed to set trigger';

		for ($i = 0; $i < $max_attempts; $i++) {
			try {
				$response = $this->call('trigger.get', [
					'triggerids' => $triggerids
				]);

				$this->assertCount(count($triggerids), $response['result'], $err_msg);

				foreach ($triggerids as $i)
				{
					$this->assertEquals($expected_state, $response['result'][$i]['state']);
					$this->assertEquals($expected_value, $response['result'][$i]['value']);
				}

				break;
			} catch (Exception $e) {
				if ($i == $max_attempts - 1)
					throw $e;
				else
					sleep($sleep_time);
			}
		}
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

		$this->waitForTriggersStatus($triggerids, TRIGGER_STATE_NORMAL, $value);

		return $triggerids;
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

	private function getHostGroupId($hostGroupName) {
		$response = $this->call('hostgroup.get', [
			'filter' => [
				'name' => [
					$hostGroupName
				]
			]
		]);

		$this->assertCount(1, $response['result']);

		return $response['result'][0]['groupid'];
	}

	private function getHostGroupName($hostGroupId) {
		$response = $this->call('hostgroup.get', [
			'filter' => [
				'groupid' => [
					$hostGroupId
				]
			]
		]);

		$this->assertCount(1, $response['result']);

		return $response['result'][0]['name'];
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 */
	public function testPermissions_initial()
	{
		self::$hostids[self::HOST_NAME_01] = $this->waitForHost(self::HOST_NAME_01);
		$this->reloadConfigurationCache();

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
		self::$hostids[self::HOST_NAME_01] = $this->waitForHost(self::HOST_NAME_01, [
			['name' => self::HOSTGROUP_NAME_01],
			['name' => self::HOSTGROUP_NAME_03]
		]);

		$this->reloadConfigurationCache();

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
		$this->waitForHost(self::HOST_NAME_01, [
			['name' => self::HOSTGROUP_NAME_01]
		]);

		$this->reloadConfigurationCache();

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

		$ts = self::$ts;

		foreach ($response['result'] as $alert) {
			if ($alert['clock'] > self::$ts)
				self::$ts = $alert['clock'];
		}

		self::$ts++;

		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_01], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_02], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_01], self::$hostids[self::HOST_NAME_03], 1, $ts);

		$this->checkAlert(self::$userids[self::USER_NAME_02], self::$hostids[self::HOST_NAME_03], 1, $ts);

		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_01], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_03], self::$hostids[self::HOST_NAME_02], 1, $ts);

		$this->setTriggers($hosts, false, $triggerids);
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProviderLld
	 * @depends testPermissions_removeGroup
	 */
	public function testPermissions_addLldHost()
	{
		self::$hostids[self::HOST_NAME_11] = $this->waitForHost(self::HOST_NAME_11);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME_11, self::LLD_NAME,
			['data' => [[self::LLD_MACRO_HP => self::HOST_NAME_01, self::LLD_MACRO_GP => self::HOSTGROUP_NAME_01]]]
		);

		self::$hostids[self::HP01_HOST_NAME_01] = $this->waitForHost(self::HP01_HOST_NAME_01);

		self::$hostgroupids[self::HP01_HOSTGROUP_NAME_01] = $this->getHostGroupId(self::HP01_HOSTGROUP_NAME_01);
		self::$hostgroupids[self::HP01_HOSTGROUP_NAME_02] = $this->getHostGroupId(self::HP01_HOSTGROUP_NAME_02);

		$hosts = [
			[
				'host' => self::HOST_NAME_12,
				'groups' => [
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_11]
					],
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_12]
					],
					[
						'groupid' => self::$hostgroupids[self::HP01_HOSTGROUP_NAME_01]
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
					]
				]
			],
			[
				'host' => self::HOST_NAME_13,
				'groups' => [
					[
						'groupid' => self::$hostgroupids[self::HP01_HOSTGROUP_NAME_01]
					],
					[
						'groupid' => self::$hostgroupids[self::HP01_HOSTGROUP_NAME_02]
					],
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_11]
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
					]
				]
			],
			[
				'host' => self::HOST_NAME_14,
				'groups' => [
					[
						'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_11]
					]
				],
				'templates' => [
					[
						'templateid' => self::$templateids[self::TEMPLATE_NAME_01]
					]
				]
			]
		];

		$response = $this->call('host.create', $hosts);
		$this->assertCount(count($hosts), $response['result']['hostids']);
		$hostids = $response['result']['hostids'];

		foreach ($hosts as $i => $host)
			self::$hostids[$host['host']] = $hostids[$i];

		$usergroups = [
			[
				'name' => self::USERGROUP_NAME_11,
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_11],
						'permission' => PERM_READ_WRITE
					],
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_12],
						'permission' => PERM_DENY
					],
					[
						'id' => self::$hostgroupids[self::HP01_HOSTGROUP_NAME_01],
						'permission' => PERM_READ_WRITE
					]
				],
				'users' => [
					[
						'userid' => self::$userids[self::USER_NAME_11]
					]
				]
			],
			[
				'name' => self::USERGROUP_NAME_12,
				'hostgroup_rights' => [
					[
						'id' => self::$hostgroupids[self::HOSTGROUP_NAME_12],
						'permission' => PERM_READ
					],
					[
						'id' => self::$hostgroupids[self::HP01_HOSTGROUP_NAME_01],
						'permission' => PERM_DENY
					],
					[
						'id' => self::$hostgroupids[self::HP01_HOSTGROUP_NAME_02],
						'permission' => PERM_READ
					]
				],
				'users' => [
					[
						'userid' => self::$userids[self::USER_NAME_12]
					]
				]
			]
		];

		$response = $this->call('usergroup.create', $usergroups);
		$this->assertCount(count($usergroups), $response['result']['usrgrpids']);
		$usergroupids = $response['result']['usrgrpids'];

		foreach ($usergroups as $i => $usergroup)
			self::$usergroupids[$usergroup['name']] = $usergroupids[$i];

		$this->reloadConfigurationCache();

		$hosts = [
			self::HOST_NAME_12,
			self::HOST_NAME_13,
			self::HOST_NAME_14,
			self::HP01_HOST_NAME_01
		];

		$triggerids = $this->setTriggers($hosts);

		$response = $this->call('alert.get', [
			'time_from' => self::$ts
		]);
		$this->assertCount(3, $response['result']);

		$ts = self::$ts;

		foreach ($response['result'] as $alert) {
			if ($alert['clock'] > self::$ts)
				self::$ts = $alert['clock'];
		}

		self::$ts++;

		$this->checkAlert(self::$userids[self::USER_NAME_11], self::$hostids[self::HOST_NAME_13], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_11], self::$hostids[self::HOST_NAME_14], 1, $ts);
		$this->checkAlert(self::$userids[self::USER_NAME_11], self::$hostids[self::HP01_HOST_NAME_01], 1, $ts);

		$this->setTriggers($hosts, false, $triggerids);
	}

	/**
	 * @depends testPermissions_addLldHost
	 */
	public function testPermissions_updateLldHostGroupAdd()
	{
		$response = $this->call('hostprototype.update', [
			'hostid' => self::$hostids[self::HP01_NAME],
			'groupLinks' => [
				[
					'groupid' => self::$discovered_hostgroupid
				],
				[
					'groupid' => self::$hostgroupids[self::HOSTGROUP_NAME_12]
				]
			]
		]);

		$this->assertCount(1, $response['result']['hostids']);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME_11, self::LLD_NAME,
			['data' => [[self::LLD_MACRO_HP => self::HOST_NAME_01, self::LLD_MACRO_GP => self::HOSTGROUP_NAME_01]]]
		);

		$this->waitForHost(self::HP01_HOST_NAME_01, [
			['name' => self::HOSTGROUP_NAME_12],
			['name' => self::HP01_HOSTGROUP_NAME_01],
			['name' => self::HP01_HOSTGROUP_NAME_02],
			['name' => self::$discovered_hostgroup]
		]);

		$triggerids = $this->setTriggers([self::HP01_HOST_NAME_01]);

		$response = $this->call('alert.get', [
			'time_from' => self::$ts
		]);
		$this->assertCount(0, $response['result']);

		$this->setTriggers([self::HP01_HOST_NAME_01], false, $triggerids);
	}

	/**
	 * @depends testPermissions_updateLldHostGroupAdd
	 */
	public function testPermissions_updateLldHostGroupRemove()
	{
		$response = $this->call('hostprototype.update', [
			'hostid' => self::$hostids[self::HP01_NAME],
			'groupLinks' => [
				[
					'groupid' => self::$discovered_hostgroupid
				]
			]
		]);

		$this->assertCount(1, $response['result']['hostids']);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME_11, self::LLD_NAME,
			['data' => [[self::LLD_MACRO_HP => self::HOST_NAME_01, self::LLD_MACRO_GP => self::HOSTGROUP_NAME_01]]]
		);

		$this->waitForHost(self::HP01_HOST_NAME_01, [
			['name' => self::HP01_HOSTGROUP_NAME_01],
			['name' => self::HP01_HOSTGROUP_NAME_02],
			['name' => self::$discovered_hostgroup]
		]);

		$triggerids = $this->setTriggers([self::HP01_HOST_NAME_01]);

		$response = $this->call('alert.get', [
			'time_from' => self::$ts
		]);
		$this->assertCount(1, $response['result']);

		$ts = self::$ts;

		foreach ($response['result'] as $alert) {
			if ($alert['clock'] > self::$ts)
				self::$ts = $alert['clock'];
		}

		self::$ts++;

		$this->checkAlert(self::$userids[self::USER_NAME_11], self::$hostids[self::HP01_HOST_NAME_01], 1, $ts);

		$this->setTriggers([self::HP01_HOST_NAME_01], false, $triggerids);

		self::$LLD_HOST_METADATA = self::HOST_METADATA5;
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProviderLld
	 * @depends testPermissions_updateLldHostGroupRemove
	 */
	public function testPermissions_removeLldHost()
	{
		$this->waitForHostRemoved(self::HOST_NAME_11);
		$this->reloadConfigurationCache();

		$hosts = [
			self::HOST_NAME_12,
			self::HOST_NAME_13,
			self::HOST_NAME_14
		];

		$triggerids = $this->setTriggers($hosts);

		$response = $this->call('alert.get', [
			'time_from' => self::$ts
		]);
		$this->assertCount(3, $response['result']);

		$this->checkAlert(self::$userids[self::USER_NAME_11], self::$hostids[self::HOST_NAME_13], 1, self::$ts);
		$this->checkAlert(self::$userids[self::USER_NAME_11], self::$hostids[self::HOST_NAME_14], 1, self::$ts);

		$this->checkAlert(self::$userids[self::USER_NAME_12], self::$hostids[self::HOST_NAME_12], 1, self::$ts);

		$this->setTriggers($hosts, false, $triggerids);
	}
}
