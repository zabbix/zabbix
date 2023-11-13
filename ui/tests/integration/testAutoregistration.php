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
 * Test suite for autoregistration
 *
 * @required-components server, agent
 * @configurationDataProvider agentConfigurationProvider
 * @backup hosts,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testAutoregistration extends CIntegrationTest {
	const HOST_METADATA1 = "autoreg 1";
	const HOST_METADATA2 = "autoreg 2";
	const AUTOREG_ACTION_NAME1 = 'Test autoregistration action 1';
	const AUTOREG_ACTION_NAME2 = 'Test autoregistration action 2';

	public static $HOST_METADATA = self::HOST_METADATA1;

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
						'Failed to autoregister host before timeout');
				$this->assertArrayHasKey('tags', $response['result'][0],
						'Failed to autoregister host before timeout');

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
		$this->waitForAutoreg([
			['tag' => 'a1', 'value' => 'autoreg 1'],
			['tag' => 'tag1', 'value' => 'value 1']
		]);

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
