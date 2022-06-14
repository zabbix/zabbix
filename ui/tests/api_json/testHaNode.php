<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../../include/classes/helpers/CCuid.php';

/**
 * @backup ha_node
 */
class testHaNode extends CAPITest {

	public static function hanode_get_cases() {
		return [
			'No params' => [
				'request' => [],
				'expected_error' => null
			],
			'Too short ha_nodeid' => [
				'request' => [
					'ha_nodeids' => 'æų'
				],
				'expected_error' => 'Invalid parameter "/ha_nodeids": an array is expected.'
			],
			'Integer ha_nodeids' => [
				'request' => [
					'ha_nodeids' => 7
				],
				'expected_error' => 'Invalid parameter "/ha_nodeids": an array is expected.'
			],
			'Empty ha_nodeids' => [
				'request' => [
					'ha_nodeids' => ''
				],
				'expected_error' => 'Invalid parameter "/ha_nodeids": an array is expected.'
			],
			'Null ha_nodeids' => [
				'request' => [
					'ha_nodeids' => null
				],
				'expected_error' => null
			],
			'Unexpected param' => [
				'request' => [
					'flag' => true
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "flag".'
			],
			'Non-existing status' => [
				'request' => [
					'filter' => [
						'status' => 111
					]
				],
				'expected_error' => 'Invalid parameter "/filter/status/1": value must be one of 0, 1, 2, 3.'
			],
			'Empty filter' => [
				'request' => [
					'filter' => []
				],
				'expected_error' => null
			],
			'Non-array filter' => [
				'request' => [
					'filter' => 1
				],
				'expected_error' => 'Invalid parameter "/filter": an array is expected.'
			],
			'Non-expected filter' => [
				'request' => [
					'filter' => [
						'unexpected' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "unexpected".'
			],
			'Empty node name' => [
				'request' => [
					'filter' => [
						'name' => ['']
					]
				],
				'expected_error' => null
			],
			'Existing node by address' => [
				'request' => [
					'filter' => [
						'address' => ['192.168.1.9']
					]
				],
				'expected_error' => null
			],
			'Multiple nodes by address' => [
				'request' => [
					'filter' => [
						'address' => ['192.168.1.9', '192.168.1.10']
					]
				],
				'expected_error' => null
			],
			'Existing node by name' => [
				'request' => [
					'filter' => [
						'name' => 'node2'
					]
				],
				'expected_error' => null
			],
			'Multiple nodes by name' => [
				'request' => [
					'filter' => [
						'name' => ['node1', 'node3']
					]
				],
				'expected_error' => null
			],
			'Existing node by status' => [
				'request' => [
					'filter' => [
						'status' => ZBX_NODE_STATUS_STANDBY
					]
				],
				'expected_error' => null
			],
			'Existing nodes by status' => [
				'request' => [
					'filter' => [
						'status' => [ZBX_NODE_STATUS_STOPPED, ZBX_NODE_STATUS_UNAVAILABLE]
					]
				],
				'expected_error' => null
			],
			'Limit param gets accepted' => [
				'request' => [
					'limit' => 1
				],
				'expected_error' => null
			],
			'Limit should not be empty' => [
				'request' => [
					'limit' => ''
				],
				'expected_error' => 'Invalid parameter "/limit": an integer is expected.'
			],
			'Limit should not be string' => [
				'request' => [
					'limit' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/limit": an integer is expected.'
			],
			'Negative ha_nodeids' => [
				'request' => [
					'ha_nodeids' => [-1, -3]
				],
				'expected_error' => 'Invalid parameter "/ha_nodeids/1": a character string is expected.'
			],
			'Too short ha_nodeids' => [
				'request' => [
					'ha_nodeids' => ['cuid', 'expected']
				],
				'expected_error' => 'Invalid parameter "/ha_nodeids/1": must be 25 characters long.'
			],
			'Non-cuid ha_nodeids' => [
				'request' => [
					'ha_nodeids' => [str_repeat('a', 25), str_repeat('b', 25)]
				],
				'expected_error' => 'Invalid parameter "/ha_nodeids/1": CUID is expected.'
			],
			'Accepts output parameter' => [
				'request' => [
					'output' => API_OUTPUT_EXTEND
				],
				'expected_error' => null
			],
			'Rejects empty output parameter' => [
				'request' => [
					'output' => ''
				],
				'expected_error' => 'Invalid parameter "/output": value must be "extend".'
			],
			'Accepts output as array' => [
				'request' => [
					'output' => ['name', 'lastaccess']
				],
				'expected_error' => null
			],
			'Filters output fields' => [
				'request' => [
					'output' => ['name', 'status', 'unexpected_field']
				],
				'expected_error' => 'Invalid parameter "/output/3": value must be one of "ha_nodeid", "name", "address", "port", "lastaccess", "status".'
			],
			'Accepts preservekeys' => [
				'request' => [
					'preservekeys' => true
				],
				'expected_error' => null
			],
			'Accepts sortfield' => [
				'request' => [
					'sortfield' => 'name'
				],
				'expected_error' => null
			],
			'Rejects non-allowed sortfield' => [
				'request' => [
					'sortfield' => 'port'
				],
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of "name", "lastaccess", "status".'
			],
			'Accepts sortorder' => [
				'request' => [
					'sortfield' => 'name',
					'sortorder' => 'DESC'
				],
				'expected_error' => null
			],
			'Rejects non-ASC/DESC sortorder' => [
				'request' => [
					'sortfield' => 'name',
					'sortorder' => 'RAND()'
				],
				'expected_error' => 'Invalid parameter "/sortorder": value must be one of "ASC", "DESC".'
			],
			'Accepts countOutput' => [
				'request' => [
					'countOutput' => true
				],
				'expected_error' => null
			],
			'Accepts countOutput as false' => [
				'request' => [
					'countOutput' => false
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider hanode_get_cases
	 */
	public function testHaNode_get($request, $expected_error) {
		$this->call('hanode.get', $request, $expected_error);
	}

	/**
	 * Verify technical fields like ha_sessionid are not returned, ha_nodeid can be excluded from result.
	 */
	public static function hanode_field_map() {
		return [
			'Check fields' => [
				'request' => [
					'ha_nodeids' => 'ckuo7i1nv000d0sajd95y1b6x',
					'output' => 'extend'
				],
				'expected_result' => [
					'ha_nodeid' => 'ckuo7i1nv000d0sajd95y1b6x',
					'name' => 'node5',
					'address' => '192.168.1.9',
					'port' => '10053',
					'status' => 1,
					'ha_sessionid' => null
				]
			],
			'Check absence of fields via output limitation, and sorting with limit' => [
				'request' => [
					'output' => ['name', 'address'],
					'countOutput' => false,
					'sortfield' => 'name',
					'sortorder' => 'DESC',
					'limit' => 1
				],
				'expected_result' => [
					'ha_nodeid' => null,
					'name' => 'node8',
					'address' => '192.168.1.12',
					'port' => null,
					'status' => null,
					'ha_sessionid' => null
				]
			],
			'Check countOutput' => [
				'request' => [
					'countOutput' => true
				],
				'expected_result' => 9
			],
			'Check countOutput with filter' => [
				'request' => [
					'filter' => ['status' => ZBX_NODE_STATUS_STOPPED],
					'countOutput' => true
				],
				'expected_result' => 3
			]

		];
	}

	/**
	 * @dataProvider hanode_field_map
	 */
	public function testHaNode_FieldPresenceAndExclusion($params, $expected_result) {
		$result = $this->call('hanode.get', $params);

		if (!is_array($expected_result)) {
			$this->assertEquals($result['result'], $expected_result, 'countCoutput rows should match');
			return;
		}

		foreach ($result['result'] as $node) {
			foreach ($expected_result as $field => $expected_value){
				if ($expected_value === null) {
					$this->assertArrayNotHasKey($field, $node, 'Field '.$field.' should not be present');
					continue;
				}

				$this->assertArrayHasKey($field, $node, 'Field '.$field.' should be present.');
				$this->assertEquals($node[$field], $expected_value, 'Returned value should match.');
			}
		}
	}
}
