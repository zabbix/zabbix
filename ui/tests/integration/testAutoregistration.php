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
 * Test suite for autoregistration
 *
 * @required-components server, agent
 * @configurationDataProvider agentConfigurationProvider
 * @backup ids,hosts,items,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testAutoregistration extends CIntegrationTest {
	const HOST_METADATA1 = "autoreg 1";
	const HOST_METADATA2 = "autoreg 2";
	const AUTOREG_ACTION_NAME1 = 'Test autoregistration action 1';
	const AUTOREG_ACTION_NAME2 = 'Test autoregistration action 2';
	const ITEM_KEY = "item_key";
	const LLD_KEY = "lld_key";

	public static $HOST_METADATA = self::HOST_METADATA1;

	public static $items = [
			[
				'name' => self::ITEM_KEY,
				'key_' => self::ITEM_KEY,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		];

	public static $lldrules = [
			[
				'name' => self::LLD_KEY,
				'key_' => self::LLD_KEY,
				'type' => ITEM_TYPE_TRAPPER,
				'lifetime_type' => 0,
				'lifetime' => '1d',
				'enabled_lifetime_type' => 0,
				'enabled_lifetime' => '3h'
			]
		];

	private function waitForAutoreg($expectedTags) {
		$max_attempts = 5;
		$sleep_time = 2;

		for ($i = 0; $i < $max_attempts; $i++) {
			try {
				$response = $this->call('host.get', [
					'selectTags' => ['tag', 'value']
				]);

				$this->assertArrayHasKey('result', $response,
						'Failed to autoregister host before timeout');
				$this->assertCount(1, $response['result'],
						'Failed to autoregister host before timeout, response result: '. json_encode($response['result']));
				$this->assertArrayHasKey('tags', $response['result'][0],
						'Failed to autoregister host before timeout: response result: '. json_encode($response['result']));

				$autoregHost = $response['result'][0];
				$this->assertArrayHasKey('hostid', $autoregHost,
						'Failed to get host ID of the autoregistered host');

				$tags = $autoregHost['tags'];
				$this->assertCount(count($expectedTags), $tags, 'Unexpected tags count was detected');

				foreach ($expectedTags as $tag)
				{
					$this->assertContains($tag, $tags);
				}

				break;
			} catch (Exception $e) {
				if ($i == $max_attempts - 1)
					throw $e;
				else
					sleep($sleep_time);
			}
		}

		return $autoregHost['hostid'];
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::COMPONENT_AGENT,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HostMetadata' => self::$HOST_METADATA
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('host.get', []);

		$hostids = array();
		foreach ($response['result'] as $host) {
			$hostids[] = $host['hostid'];
		}

		$this->call('host.delete', $hostids);

		$response = $this->call('host.get', []);
		$this->assertArrayHasKey('result', $response, 'Failed to clear existing hosts during test setup');
		$this->assertCount(0, $response['result'], 'Failed to clear existing hosts during test setup');

		$response = $this->call('templategroup.get', [
			'filter' => [
				'name' => 'Templates'
			]
		]);
		$this->assertCount(1, $response['result']);
		$templategroupid = $response['result'][0]['groupid'];

		$response = $this->call('template.create', [
				'host' => 'test_template',
				'groups' => [
					'groupid' => $templategroupid
				]
		]);
		$this->assertCount(1, $response['result']['templateids']);
		$templateid = $response['result']['templateids'][0];

		$items = [];
		foreach (self::$items as $item) {
			$items[] = [
				'name' => $item['name'],
				'key_' => $item['key_'],
				'type' => $item['type'],
				'value_type' => $item['value_type'],
				'hostid' => $templateid
			];
		}
		$response = $this->call('item.create', $items);
		$this->assertCount(count($items), $response['result']['itemids']);

		$lldrules = [];
		foreach (self::$lldrules as $lldrule) {
			$lldrules[] = [
				'name' => $lldrule['name'],
				'key_' => $lldrule['key_'],
				'type' => $lldrule['type'],
				'lifetime_type' => $lldrule['lifetime_type'],
				'lifetime' => $lldrule['lifetime'],
				'enabled_lifetime_type' => $lldrule['enabled_lifetime_type'],
				'enabled_lifetime' => $lldrule['enabled_lifetime'],
				'hostid' => $templateid
			];
		}
		$response = $this->call('discoveryrule.create', $lldrules);
		$this->assertCount(count($lldrules), $response['result']['itemids']);

		$response = $this->call('action.create', [
		[
			'name' => self::AUTOREG_ACTION_NAME1,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::COMPONENT_AGENT
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
				/* OPERATION_TYPE_HOST_ADD is intentionally missing. It is expected to be run by */
				/* Zabbix server, because OPERATION_TYPE_HOST_TAGS_ADD is present.               */
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'a1',
							'value' => 'autoreg 1'
						],
						[
							'tag' => 'tag1',
							'value' => 'value 1'
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_REMOVE,
					'optag' => [
						[
							'tag' => 'a2',
							'value' => 'autoreg 2'
						],
						[
							'tag' => 'tag2',
							'value' => 'value 2'
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
					'optemplate' => [
						[
							'templateid' => $templateid
						]
					]
				]
			]
		],
		[
			'name' => self::AUTOREG_ACTION_NAME2,
			'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => self::COMPONENT_AGENT
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
				/* OPERATION_TYPE_HOST_ADD is intentionally missing. It is expected to be run by */
				/* Zabbix server, because OPERATION_TYPE_HOST_TAGS_ADD is present.               */
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'a2',
							'value' => 'autoreg 2'
						],
						[
							'tag' => 'tag2',
							'value' => 'value 2'
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_REMOVE,
					'optag' => [
						[
							'tag' => 'a1',
							'value' => 'autoreg 1'
						],
						[
							'tag' => 'tag1',
							'value' => 'value 1'
						]
					]
				]
			]
		]]);
		$this->assertArrayHasKey('result', $response, 'Failed to create an autoregistration action');
		$this->assertArrayHasKey('actionids', $response['result'],
				'Failed to create an autoregistration action');
		$actionids = $response['result']['actionids'];
		$this->assertCount(2, $actionids, 'Failed to create an autoregistration action');
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 */
	public function testAutoregistration_autoregHost1FirstTime()
	{
		$hostid = $this->waitForAutoreg([
			['tag' => 'a1', 'value' => 'autoreg 1'],
			['tag' => 'tag1', 'value' => 'value 1']
		]);

		$response = $this->call('item.get', [
			'hostids' => [ $hostid ],
			'output' => [
				'name',
				'key_',
				'type',
				'value_type'
			]
		]);
		$this->assertCount(count(self::$items), $response['result']);

		for ($i = 0; $i < count($response['result']); $i++) {
			unset($response['result'][$i]['itemid']);
			$this->assertContains($response['result'][$i], self::$items);
		}

		$response = $this->call('discoveryrule.get', [
			'hostids' => [ $hostid ],
			'output' => [
				'name',
				'key_',
				'type',
				'lifetime_type',
				'lifetime',
				'enabled_lifetime_type',
				'enabled_lifetime'
			]
		]);
		$this->assertCount(count(self::$lldrules), $response['result']);

		for ($i = 0; $i < count($response['result']); $i++) {
			unset($response['result'][$i]['itemid']);
			$this->assertContains($response['result'][$i], self::$lldrules);
		}

		self::$HOST_METADATA = self::HOST_METADATA2;
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 * @depends testAutoregistration_autoregHost1FirstTime
	 */
	public function testAutoregistration_autoregHost2()
	{
		$this->waitForAutoreg([
			['tag' => 'a2', 'value' => 'autoreg 2'],
			['tag' => 'tag2', 'value' => 'value 2']
		]);

		self::$HOST_METADATA = self::HOST_METADATA1;
	}

	/**
	 * @required-components agent
	 * @configurationDataProvider agentConfigurationProvider
	 * @depends testAutoregistration_autoregHost2
	 */
	public function testAutoregistration_autoregHost1SecondTime()
	{
		$this->waitForAutoreg([
			['tag' => 'a1', 'value' => 'autoreg 1'],
			['tag' => 'tag1', 'value' => 'value 1']
		]);
	}
}
