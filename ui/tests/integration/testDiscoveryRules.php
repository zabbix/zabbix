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
 * Test suite for discovery rules
 *
 * @required-components server
 * @backup hosts,drules,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testDiscoveryRules extends CIntegrationTest {
	const DRULE_NAME = 'Test discovery rule';
	const DACTION_NAME = 'Test discovery action';

	private static $discoveryRuleId;
	private static $discoveryActionId;
	private static $discoveredHostId;

	private function waitForDiscovery($expectedTags, $notExpectedTags = []) {
		$max_attempts = 10;
		$sleep_time = 2;

		for ($i = 0; $i < $max_attempts; $i++) {
			try {
				$response = $this->call('host.get', [
					'selectTags' => ['tag', 'value']
				]);

				$this->assertArrayHasKey('result', $response, 'Failed to discover host before timeout');
				$this->assertCount(1, $response['result'], 'Failed to discover host before timeout');
				$this->assertArrayHasKey('tags', $response['result'][0], 'Failed to discover host before timeout');

				$discoveredHost = $response['result'][0];
				$this->assertArrayHasKey('hostid', $discoveredHost, 'Failed to get host ID of the discovered host');
				self::$discoveredHostId = $discoveredHost['hostid'];

				$tags = $discoveredHost['tags'];
				$this->assertCount(count($expectedTags), $tags, 'Unexpected tags count was detected');

				foreach($expectedTags as $expectedTag) {
					$this->assertContains($expectedTag, $tags, 'Expected tag was not found after discovery');
				}

				foreach($notExpectedTags as $notExpectedTag) {
					$this->assertNotContains($notExpectedTag, $tags, 'Unexpected tag was found after discovery');
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

		$response = $this->call('drule.create', [
			'name' => self::DRULE_NAME,
			'delay' => '5s',
			'iprange' => '127.0.0.1',
			'dchecks' => [
				[
					'type' => SVC_HTTP,
					'ports' => '80'
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery rule');
		$this->assertArrayHasKey('druleids', $response['result'], 'Failed to create a discovery rule');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery rule');
		self::$discoveryRuleId = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			'name' => self::DACTION_NAME,
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$discoveryRuleId
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DSTATUS,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => DOBJECT_STATUS_UP
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
							'tag' => 'add_tag1',
							'value' => 'add_value1'
						],
						[
							'tag' => 'add_tag2',
							'value' => 'add_value2'
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery action');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create a discovery action');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery action');

		$response = $this->call('action.get', [
			'filter' => [
				'name' => self::DACTION_NAME
			]
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to retrieve the discovery action');
		$this->assertCount(1, $response['result'], 'Failed to retrieve the discovery action');
		$discoveryAction = $response['result'][0];
		$this->assertArrayHasKey('actionid', $discoveryAction, 'Failed to get actionid of the discovery action');
		self::$discoveryActionId = $discoveryAction['actionid'];
	}

	public function testDiscoveryRules_opAddHostTags()
	{
		$this->waitForDiscovery([
			['tag' => 'add_tag1', 'value' => 'add_value1'],
			['tag' => 'add_tag2', 'value' => 'add_value2']
		]);
	}

	/**
	 * @depends testDiscoveryRules_opAddHostTags
	 */
	public function testDiscoveryRules_opDelHostTags()
	{
		/* Replace tags at the discovered host */
		$response = $this->call('host.update', [
			'hostid' => self::$discoveredHostId,
			'tags' => [
				[
					'tag' => 'del_tag3',
					'value' => 'del_value3'
				],
				[
					'tag' => 'del_tag4',
					'value' => 'del_value4'
				]
			]
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertCount(1, $response['result']);

		$response = $this->call('action.update', [
			'actionid' => self::$discoveryActionId,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'add_tag1',
							'value' => 'add_value1'
						],
						[
							'tag' => 'add_tag2',
							'value' => 'add_value2'
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_REMOVE,
					'optag' => [

						[
							'tag' => 'del_tag3',
							'value' => 'del_value3'
						],
						[
							'tag' => 'del_tag4',
							'value' => 'del_value4'
						]
					]
				]
			]
		]);

		$this->waitForDiscovery([
			['tag' => 'add_tag1', 'value' => 'add_value1'],
			['tag' => 'add_tag2', 'value' => 'add_value2']
		],
		[
			['tag' => 'del_tag3', 'value' => 'del_value3'],
			['tag' => 'del_tag4', 'value' => 'del_value4']
		]);
	}
}
