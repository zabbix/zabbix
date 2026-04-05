<?php declare(strict_types = 1);
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * @required-components server
 * @onAfter clearData
 */
class testDiagInfo extends CIntegrationTest {

	private static $host_id;
	private static $item_ids = [];
	private static $trigger_ids = [];
	private static $action_ids = [];

	const NUM = 100;
	const TRAPPER_ITEM_NAME = 'diag_info_trap';
	const TRAPPER_ITEM_KEY = 'diag_info_trap';
	const HOST_NAME = 'diag_info_host';
	const TAG_NAME = 'diag_info_tag_name';
	const TAG_VALUE = 'diag_info_tag_value';
	const VALUE_TO_FIRE_TRIGGER = '0';

	const TRIGGER_PRIORITY = 3;
	const TRIGGER_STATUS_ENABLED = 0;
	const TRIGGER_TYPE = 1;
	const TRIGGER_RECOVERY_MODE = 0;

	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			]
		];
	}

	public function prepareData() {
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


		$response = $this->call('user.update', [
			'userid' => 1,
			'medias' => [
				[
					'mediatypeid' => 1,
					'sendto' => 'test@local.local'
				]
			]
		]);

		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$host_id = $response['result']['hostids'][0];

		$items = [];
		for ($i = 0; $i < self::NUM; $i++) {
			$items[] = [
				'hostid' => self::$host_id,
				'name' => self::TRAPPER_ITEM_NAME.$i,
				'key_' => self::TRAPPER_ITEM_KEY.$i,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			];
		}

		$response = $this->call('item.create', $items);
		self::$item_ids = $response['result']['itemids'];
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count(self::$item_ids), count($response['result']['itemids']));


		$triggers = [];
		for ($i = 0; $i < self::NUM; $i++) {
			$trigger_expression = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.$i.')='.
				self::VALUE_TO_FIRE_TRIGGER;

			$triggers[] = [
				'description'			=> 'trigger_trap',
				'expression'			=> $trigger_expression,
				'priority'				=> self::TRIGGER_PRIORITY,
				'status'				=> self::TRIGGER_STATUS_ENABLED,
				'type'					=> self::TRIGGER_TYPE,
				'recovery_mode'			=> self::TRIGGER_RECOVERY_MODE,
				'tags' => [
					[
						'tag' => self::TAG_NAME,
						'value' => self::TAG_VALUE
					]
				]
			];
		}

		$response = $this->call('trigger.create', $triggers);
		self::$trigger_ids = $response['result']['triggerids'];

		$actions = [];
		for ($i = 0; $i < self::NUM; $i++) {
			$actions[] = [
				'esc_period' => '1m',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'status' => 0,
				'filter' => [
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => self::$trigger_ids[$i]
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR
				],
				'name' => 'action_trigger_trap'.$i,
				'operations' => [
					[
						'esc_period' => 0,
						'esc_step_from' => 1,
						'esc_step_to' => 1,
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => 1
						],
						'opmessage_grp' => [
							['usrgrpid' => 7]
						]
					]
				]];
		}

		$response = $this->call('action.create', $actions);
		self::$action_ids = $response['result']['actionids'];
	}

	public function testDiagInfo() {
		sleep(20);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'1', self::TRAPPER_ITEM_NAME);

		$sender_values = [];
		for ($i = 0; $i < self::NUM; $i++)
		{
			$sender_values[] = ['host' => self::HOST_NAME, 'key' => self::TRAPPER_ITEM_KEY.$i, 'value' => 0];
		}

		$this->sendSenderValues($sender_values);

		$expectedDiagInfoLogEntries = [
			'diaginfo=alerting' => '== alerting diagnostic information ==',
			'diaginfo=valuecache' => '== value cache diagnostic information ==',
			'diaginfo=lld' => '== LLD diagnostic information ==',
			'diaginfo=historycache' => '== history cache diagnostic information ==',
			'diaginfo=preprocessing' => '== preprocessing diagnostic information ==',
			'diaginfo=locks' => '== locks diagnostic information ==',
			'diaginfo=connector' => '== connector diagnostic information =='
		];
		foreach ($expectedDiagInfoLogEntries as $cmd => $e) {
			$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, $cmd);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $e, true, 20, 3);
		}
	}

	public static function clearData(): void {
		CDataHelper::call('action.delete', self::$action_ids);
		CDataHelper::call('trigger.delete', self::$trigger_ids);
		CDataHelper::call('item.delete', self::$item_ids);
		CDataHelper::call('host.delete', [self::$host_id]);
	}
}
