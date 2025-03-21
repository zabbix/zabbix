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
 * @backup hstgrp
 * @backup hosts
 */
class testTemplate extends CAPITest {

	private static $data = [
		'templateids' => [
			'api_vendor_test' => null
		]
	];

	public function prepareTestData() {
		$templates_data = [
			[
				'host' => 'api_vendor_test',
				'groups' => ['groupid' => 1],
				'vendor_name' => 'Zabbix',
				'vendor_version' => '6.4-0'
			]
		];
		$templates = CDataHelper::call('template.create', $templates_data);
		$this->assertArrayHasKey('templateids', $templates);
		self::$data['templateids']['api_vendor_test'] = $templates['templateids'][0];
	}

	public function dataProviderCreate() {
		return [
			[
				'request' => [
					'host' => 'test-template-01',
					'groups' => ['groupid' => 1]
				]
			],
			[
				'request' => [
					'host' => 'test-template-02',
					'groups' => []
				],
				'expected_error' => 'Invalid parameter "/1/groups": cannot be empty.'
			],
			[
				'request' => [
					'host' => 'test-template-03',
					'groups' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/groups/1": the parameter "groupid" is missing.'
			],
			[
				'request' => [
					'host' => 'test-template-04',
					'groups' => ['groupid' => 9999]
				],
				'expected_error' => 'Invalid parameter "/1/groups/1": object does not exist, or you have no permissions to it.'
			],
			[
				'request' => [
					'host' => 'test-template-05',
					'groups' => ['groupid' => 1]
				],
				'expected_error' => 'Invalid parameter "/1/groups/1": object does not exist, or you have no permissions to it.',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix']
			],
			[
				'request' => [
					[
						'host' => 'test-template-06',
						'groups' => ['groupid' => 1]
					],
					[
						'host' => 'test-template-06',
						'groups' => ['groupid' => 1]
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (host)=(test-template-06) already exists.'
			],
			[
				'request' => [
					[
						'host' => 'test-template-07',
						'name' => 'test-template-07',
						'groups' => ['groupid' => 1]
					],
					[
						'host' => 'test-template-08',
						'name' => 'test-template-07',
						'groups' => ['groupid' => 1]
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(test-template-07) already exists.'
			],
			[
				'request' => [
					[
						'host' => 'test-template-09',
						'groups' => ['groupid' => 1]
					]
				]
			],
			// The next two test cases depends on the previous one.
			[
				'request' => [
					[
						'host' => 'test-template-09',
						'groups' => ['groupid' => 1]
					]
				],
				'expected_error' => 'Template with host name "test-template-09" already exists.'
			],
			[
				'request' => [
					[
						'host' => 'test-template-10',
						'name' => 'test-template-09',
						'groups' => ['groupid' => 1]
					]
				],
				'expected_error' => 'Template with visible name "test-template-09" already exists.'
			],
			// The next two test cases depends on the existing host "Zabbix server".
			[
				'request' => [
					[
						'host' => 'Zabbix server',
						'groups' => ['groupid' => 1]
					]
				],
				'expected_error' => 'Host with host name "Zabbix server" already exists.'
			],
			[
				'request' => [
					[
						'host' => 'test-template-11',
						'name' => 'Zabbix server',
						'groups' => ['groupid' => 1]
					]
				],
				'expected_error' => 'Host with visible name "Zabbix server" already exists.'
			],
			[
				'request' => [
					[
						'host' => 'test-template-12',
						'groups' => ['groupid' => 1],
						'templates' => ['templateid' => 10047 /* "Zabbix server health" */]
					]
				]
			],
			[
				'request' => [
					[
						'host' => 'test-template-13',
						'groups' => ['groupid' => 1],
						'templates' => ['templateid' => 99999]
					]
				],
				'expected_error' => 'Invalid parameter "/1/templates/1": object does not exist, or you have no permissions to it.'
			],
			[
				'request' => [
					[
						'host' => 'test-template-14',
						'groups' => ['groupid' => 1],
						'templates' => ['templateid' => 10047 /* "Zabbix server health" */]
					]
				],
				'expected_error' => 'Invalid parameter "/1/groups/1": object does not exist, or you have no permissions to it.',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix']
			],
			[
				'request' => [
					[
						'host' => 'vendor-template-error',
						'groups' => ['groupid' => 1],
						'vendor_name' => 'Zabbix'
					]
				],
				'expected_error' => 'Invalid parameter "/1": both vendor_name and vendor_version should be either present or empty.'
			],
			[
				'request' => [
					[
						'host' => 'vendor-template-error',
						'groups' => ['groupid' => 1],
						'vendor_name' => '',
						'vendor_version' => '6.4-0'
					]
				],
				'expected_error' => 'Invalid parameter "/1": both vendor_name and vendor_version should be either present or empty.'
			]
		];
	}

	public function dataProviderUpdate() {
		return [
			[
				'request' => [
					[
						'templateid' => 'api_vendor_test',
						'vendor_name' => '',
						'vendor_version' => ''
					]
				]
			],
			[
				'request' => [
					[
						'templateid' => 'api_vendor_test',
						'vendor_name' => 'ZABBIX'
					]
				],
				'expected_error' => 'Invalid parameter "/1": both vendor_name and vendor_version should be either present or empty.'
			],
			[
				'request' => [
					[
						'templateid' => 'api_vendor_test',
						'vendor_name' => 'Zabbix',
						'vendor_version' => '6.4-0'
					]
				]
			],
			// The next two test cases depends on the previous one.
			[
				'request' => [
					[
						'templateid' => 'api_vendor_test',
						'vendor_name' => 'ZABBIX'
					]
				]
			],
			[
				'request' => [
					[
						'templateid' => 'api_vendor_test',
						'vendor_version' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1": both vendor_name and vendor_version should be either present or empty.'
			]
		];
	}

	/**
	 * @dataProvider dataProviderCreate
	 */
	public function testTemplate_Create(array $request, ?string $expected_error = null, ?array $user = null) {
		if ($user !== null) {
			$this->authorize($user['user'], $user['password']);
		}
		$this->call('template.create', $request, $expected_error);
	}

	public function testTemplate_CreateUUID() {
		$result = $this->call('template.create', [
			[
				'host' => 'test-template-uuid-01',
				'groups' => ['groupid' => 1]
			],
			[
				'host' => 'test-template-uuid-02',
				'groups' => ['groupid' => 1]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$db_templates = $this->call('template.get', [
			'output' => ['uuid'],
			'templateids' => $result['templateids']
		])['result'];

		$this->assertTrue(preg_match('/[0-9a-f]{32}/', $db_templates[0]['uuid']) === 1);
		$this->assertTrue(preg_match('/[0-9a-f]{32}/', $db_templates[1]['uuid']) === 1);
		$this->assertNotSame($db_templates[0]['uuid'], $db_templates[1]['uuid']);
	}

	public function testTemplate_CreateDoubleLink() {
		/*        +-------------+
		 *        | template-01 |
		 *        +-------------+
		 *          /         \
		 *         /           \
		 *        V            |
		 * +-------------+     |
		 * | template-02 |     |
		 * +-------------+     |
		 *        |            |
		 *        V            |
		 * +-------------+     |
		 * | template-03 |     |
		 * +-------------+     |
		 *        \           /
		 *         \         /
		 *          V       V
		 *        +-------------+    \
		 *        | template-04 |    |  new template
		 *        +-------------+    /
		 *                   fail
		 */

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-double-link-01',
				'groups' => ['groupid' => 1]
			],
			[
				'host' => 'test-template-double-link-02',
				'groups' => ['groupid' => 1]
			],
			[
				'host' => 'test-template-double-link-03',
				'groups' => ['groupid' => 1]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$templateids = $result['templateids'];

		$result = $this->call('template.update', [
			[
				'templateid' => $templateids[1],
				'templates' => ['templateid' => $templateids[0]]
			],
			[
				'templateid' => $templateids[2],
				'templates' => ['templateid' => $templateids[1]]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$this->call('template.create', [
			[
				'host' => 'test-template-double-link-04',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[0]],
					['templateid' => $templateids[2]]
				]
			]
		], 'Cannot link template "test-template-double-link-03" to template "test-template-double-link-04", because its parent template "test-template-double-link-01" would be linked twice.');
	}

	public function testTemplate_CreateTriggerDependency() {
		/*
		 *                     +---------------------------+
		 *                     V                           |  "trigger-02" depends on "trigger-01"
		 * +-------------+------------+ +-------------+------------+
		 * | template-01 | trigger-01 | | template-02 | trigger-02 |
		 * +-------------+------------+ +-------------+------------+
		 *        |\                          /|
		 *        | \    +-------------+     / |           \
		 *        |  \-->| template-03 |<---/  |           |
		 *        |      +-------------+       |           |
		 *        V              success       V           |  new templates
		 * +-------------+              +-------------+    |
		 * | template-04 |              | template-05 |    |
		 * +-------------+              +-------------+    /
		 *         success                         fail
		 */

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-dependency-01',
				'groups' => ['groupid' => 1]
			],
			[
				'host' => 'test-template-trigger-dependency-02',
				'groups' => ['groupid' => 1]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$templateids = $result['templateids'];

		$result = $this->call('item.create', [
			[
				'hostid' => $templateids[0],
				'name' => 'item-01',
				'key_' => 'item-01',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'hostid' => $templateids[1],
				'name' => 'item-02',
				'key_' => 'item-02',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		])['result'];

		$this->assertArrayHasKey('itemids', $result);

		$result = $this->call('trigger.create', [
			[
				'description' => 'trigger-01',
				'expression' => 'last(/test-template-trigger-dependency-01/item-01)'
			],
			[
				'description' => 'trigger-02',
				'expression' => 'last(/test-template-trigger-dependency-02/item-02)'
			]
		])['result'];

		$this->assertArrayHasKey('triggerids', $result);

		$triggerids = $result['triggerids'];

		$result = $this->call('trigger.update', [
			[
				'triggerid' => $triggerids[1],
				'dependencies' => [
					['triggerid' => $triggerids[0]]
				]
			]
		])['result'];

		$this->assertArrayHasKey('triggerids', $result);

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-dependency-03',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[0]],
					['templateid' => $templateids[1]]
				]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-dependency-04',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[0]]
				]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-dependency-05',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[1]]
				]
			]
		], 'Cannot link template "test-template-trigger-dependency-02" without template "test-template-trigger-dependency-01" to template "test-template-trigger-dependency-05" due to dependency of trigger "trigger-02".');
	}

	public function testTemplate_CreateTriggerExpression() {
		/*
		 * +-------------+---------+-------------+
		 * | template-01 | trigger | template-02 |  The "trigger" contains items from both templates.
		 * +-------------+---------+-------------+
		 *       |\                       /|
		 *       | \   +-------------+   / |          \
		 *       |  \->| template-03 |<-/  |          |
		 *       |     +-------------+     |          |
		 *       V             success     V          |  new templates
		 * +-------------+         +-------------+    |
		 * | template-04 |         | template-05 |    |
		 * +-------------+         +-------------+    /
		 *           fail                     fail
		 */

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-expression-01',
				'groups' => ['groupid' => 1]
			],
			[
				'host' => 'test-template-trigger-expression-02',
				'groups' => ['groupid' => 1]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$templateids = $result['templateids'];

		$result = $this->call('item.create', [
			[
				'hostid' => $templateids[0],
				'name' => 'item-01',
				'key_' => 'item-01',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'hostid' => $templateids[1],
				'name' => 'item-02',
				'key_' => 'item-02',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		])['result'];

		$this->assertArrayHasKey('itemids', $result);

		$result = $this->call('trigger.create', [
			[
				'description' => 'trigger',
				'expression' => 'last(/test-template-trigger-expression-01/item-01) or last(/test-template-trigger-expression-02/item-02)'
			]
		])['result'];

		$this->assertArrayHasKey('triggerids', $result);

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-expression-03',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[0]],
					['templateid' => $templateids[1]]
				]
			]
		])['result'];

		$this->assertArrayHasKey('templateids', $result);

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-expression-04',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[0]]
				]
			]
		], 'Cannot link template "test-template-trigger-expression-01" without template "test-template-trigger-expression-02" to template "test-template-trigger-expression-04" due to expression of trigger "trigger".');

		$result = $this->call('template.create', [
			[
				'host' => 'test-template-trigger-expression-05',
				'groups' => ['groupid' => 1],
				'templates' => [
					['templateid' => $templateids[1]]
				]
			]
		], 'Cannot link template "test-template-trigger-expression-02" without template "test-template-trigger-expression-01" to template "test-template-trigger-expression-05" due to expression of trigger "trigger".');
	}

	/**
	 * @dataProvider dataProviderUpdate
	 */
	public function testTemplate_Update(array $request, ?string $expected_error = null, ?array $user = null) {
		if ($user !== null) {
			$this->authorize($user['user'], $user['password']);
		}

		foreach ($request as &$request_item) {
			$request_item['templateid'] = self::$data['templateids'][$request_item['templateid']];
		}
		unset($request_item);

		$this->call('template.update', $request, $expected_error);

		if ($expected_error === null) {
			$fields = array_reduce($request, 'array_replace', []);
			$db_templates = $this->call('template.get', [
				'output' => array_keys($fields),
				'templateids' => array_column($request, 'templateid'),
				'preservekeys' => true
			])['result'];
			$templates = [];

			foreach ($request as &$request_item) {
				$templateid = $request_item['templateid'];

				if (array_key_exists($templateid, $db_templates)) {
					$templates[$templateid] = array_intersect_key($db_templates[$templateid], $request_item);
				}
			}
			unset($request_item);

			$this->assertEquals($templates, $db_templates);
		}
	}
}
