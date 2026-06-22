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
require_once __DIR__.'/../../include/classes/helpers/CArrayHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testTriggerTags extends CAPITest {
	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'discovered.trigger.tags.tests.host.group']
			],
			'hosts' => [
				[
					'host' => 'discovered.trigger.tags',
					'tags' => [
						['tag' => 'source', 'value' => 'host']
					],
					'lld_rules' => [
						[
							'key_' => 'discovered.trigger.rule',
							'item_prototypes' => [
								[
									'key_' => 'item.prototype[{#LLD}]',
									'discovered_items' => [
										[
											'key_' => 'discovered.item[eth0]'
										]
									]
								]
							]
						]
					]
				]
			],
			'trigger_prototypes' => [
				[
					'description' => 'trigger.prototype[{#LLD}]',
					'expression' => 'last(/discovered.trigger.tags/item.prototype[{#LLD}])=0',
					'tags' => [
						['tag' => 'source', 'value' => 'trigger_prototype']
					],
					'discovered_triggers' => [
						[
							'description' => 'discovered.trigger[eth0]',
							'expression' => 'last(/discovered.trigger.tags/discovered.item[eth0])=0'
						]
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getTestCases() {
		return [
			'Update discovered trigger by adding tag' => [
				'method' => 'trigger.update',
				'params' => [
					'triggerid' => ':discovered_trigger:discovered.trigger[eth0]',
					'tags' => [
						['tag' => 'source', 'value' => 'trigger_prototype'],
						['tag' => 'discovered.own', 'value' => 'tag']
					]
				],
				'errors' => null,
				'expected' => [
					['tag' => 'source', 'value' => 'trigger_prototype', 'automatic' => ZBX_TAG_AUTOMATIC],
					['tag' => 'discovered.own', 'value' => 'tag', 'automatic' => ZBX_TAG_MANUAL]
				]
			],
			'Update discovered trigger by deleting automatic tag' => [
				'method' => 'trigger.update',
				'params' => [
					'triggerid' => ':discovered_trigger:discovered.trigger[eth0]',
					'tags' => [
						['tag' => 'discovered.own', 'value' => 'tag']
					]
				],
				'errors' => null,
				'expected' => [
					['tag' => 'source', 'value' => 'trigger_prototype', 'automatic' => ZBX_TAG_AUTOMATIC],
					['tag' => 'discovered.own', 'value' => 'tag', 'automatic' => ZBX_TAG_MANUAL]
				]
			],
			'Update discovered trigger by deleting all tags' => [
				'method' => 'trigger.update',
				'params' => [
					'triggerid' => ':discovered_trigger:discovered.trigger[eth0]',
					'tags' => []
				],
				'errors' => null,
				'expected' => [
					['tag' => 'source', 'value' => 'trigger_prototype', 'automatic' => ZBX_TAG_AUTOMATIC]
				]
			],
			'Update discovered trigger by adding new tag same as automatic tag' => [
				'method' => 'trigger.update',
				'params' => [
					'triggerid' => ':discovered_trigger:discovered.trigger[eth0]',
					'tags' => [
						['tag' => 'source', 'value' => 'trigger_prototype']
					]
				],
				'errors' => null,
				'expected' => [
					['tag' => 'source', 'value' => 'trigger_prototype', 'automatic' => ZBX_TAG_AUTOMATIC]
				]
			],
			'Update discovered trigger by adding tag with unexpected parameter "automatic"' => [
				'method' => 'trigger.update',
				'params' => [
					'triggerid' => ':discovered_trigger:discovered.trigger[eth0]',
					'tags' => [
						['tag' => 'discovered.own', 'value' => 'tag', 'automatic' => ZBX_TAG_MANUAL]
					]
				],
				'errors' => 'Invalid parameter "/1/tags/1": unexpected parameter "automatic".'
			],
			'Update trigger prototype by adding new tag' => [
				'method' => 'triggerprototype.update',
				'params' => [
					'triggerid' => ':trigger_prototype:trigger.prototype[{#LLD}]',
					'tags' => [
						['tag' => 'source', 'value' => 'trigger_prototype'],
						['tag' => 'source', 'value' => 'trigger_prototype#2']
					]
				],
				'errors' => null,
				'expected' => [
					['tag' => 'source', 'value' => 'trigger_prototype'],
					['tag' => 'source', 'value' => 'trigger_prototype#2']
				]
			],
			'Update trigger prototype by deleting last added tag' => [
				'method' => 'triggerprototype.update',
				'params' => [
					'triggerid' => ':trigger_prototype:trigger.prototype[{#LLD}]',
					'tags' => [
						['tag' => 'source', 'value' => 'trigger_prototype']
					]
				],
				'errors' => null,
				'expected' => [
					['tag' => 'source', 'value' => 'trigger_prototype']
				]
			]
		];
	}

	/**
	 * @dataProvider getTestCases
	 */
	public function testDiscoveredTriggerTags(string $method, array $params, ?string $error = null,
			?array $expected = []): void {
		$api_object = substr($method, 0, strpos($method, '.'));
		$params = array_key_exists(0, $params) ? $params : [$params];

		foreach ($params as &$param) {
			if ($api_object === 'trigger') {
				CTestDataHelper::convertTriggerReferences($param);
			}
			elseif ($api_object === 'triggerprototype') {
				CTestDataHelper::convertTriggerPrototypeReferences($param);
			}
		}
		unset($param);

		$response = $this->call($method, $params, $error);

		if ($error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('triggerids', $response['result']);

		if (!$expected) {
			return;
		}

		$options = [
			'output' => [],
			'triggerids' => $response['result']['triggerids'],
			'selectTags' => $api_object == 'trigger' ? ['tag', 'value', 'automatic'] : ['tag', 'value']
		];

		$response = $this->call($api_object . '.get', $options);

		$this->assertArrayHasKey('result', $response);

		$result = reset($response['result']);

		$this->assertArrayHasKey('tags', $result);

		CArrayHelper::sort($result['tags'], ['automatic', 'tag', 'value']);

		$this->assertEquals($expected, $result['tags']);
	}
}
