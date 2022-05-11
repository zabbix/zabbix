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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup triggers
 */
class testTriggerValidation extends CAPITest {

	const TEMPLATE_TRIGGERID = 50178;
	const UPDATE_TRIGGER_1 = 50176;
	const UPDATE_TRIGGER_2 = 50177;

	public static function triggers_to_update_data() {
		return [
			// Successful cases.
			'disable trigger' => [
				'triggers' => [
					[
						'status' => 1,
						'triggerid' => self::UPDATE_TRIGGER_1
					]
				],
				'expected_error' => null
			],
			'add dependent trigger' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'dependencies' => [
							[
								'triggerid' => self::UPDATE_TRIGGER_2
							]
						]
					]
				],
				'expected_error' => null
			],
			'delete dependent trigger' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'dependencies' => []
					]
				],
				'expected_error' => null
			],
			'change recovery mode to "none"' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'recovery_mode' => 2
					]
				],
				'expected_error' => null
			],

			// Failed cases.
			'swap names between 2 triggers' => [
				'triggers' => [
					[
						'description' => 'test-trigger-2',
						'triggerid' => self::UPDATE_TRIGGER_1
					],
					[
						'description' => 'test-trigger-1',
						'triggerid' => self::UPDATE_TRIGGER_2
					]
				],
				'expected_error' => 'Trigger "test-trigger-2" already exists on "Trigger validation test host".'
			],
			'check circular dependencies' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'dependencies' => [
							[
								'triggerid' => self::UPDATE_TRIGGER_2
							]
						]
					],
					[
						'triggerid' => self::UPDATE_TRIGGER_2,
						'dependencies' => [
							[
								'triggerid' => self::UPDATE_TRIGGER_1
							]
						]
					]
				],
				'expected_error' => 'Cannot create circular dependencies.'
			],
			'delete trigger name' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'description' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/description": cannot be empty.'
			],
			'make trigger dependent on itself' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'dependencies' => [
							[
								'triggerid' => self::UPDATE_TRIGGER_1
							]
						]
					]
				],
				'expected_error' => 'Cannot create dependency on trigger itself.'
			],
			'update read-only properties' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'flags' => 4
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			'add unexisting dependent trigger' => [
				'triggers' => [
					[
						'triggerid' => self::UPDATE_TRIGGER_1,
						'dependencies' => [
							[
								'triggerid' => 0
							]
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	public static function triggers_to_create_data() {
		return [
			// Successful cases.
			'2 triggers with different names' => [
				'triggers' => [
					[
						'description' => 'Trigger with unique name 1',
						'expression' => 'last(/Trigger validation test host/item)=0'
					],
					[
						'description' => 'Trigger with unique name 2',
						'expression' => 'last(/Trigger validation test host/item)=0'
					]
				],
				'expected_error' => null
			],
			'trigger with empty array values for tags and dependencies' => [
				'triggers' => [
					[
						'description' => 'Trigger with null values for array properties',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [],
						'dependencies' => []
					]
				],
				'expected_error' => null
			],
			'trigger with recovery expression' => [
				'triggers' => [
					[
						'description' => 'Trigger with recovery expression',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_mode' => 1,
						'recovery_expression' => 'last(/Trigger validation test host/item)=1'
					]
				],
				'expected_error' => null
			],
			'trigger with correlation tag' => [
				'triggers' => [
					[
						'description' => 'Trigger with correlation tag',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_mode' => 0,
						'correlation_mode' => 1,
						'correlation_tag' => 'tag'
					]
				],
				'expected_error' => null
			],
			'trigger with tags' => [
				'triggers' => [
					[
						'description' => 'Trigger with tags',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [
							[
								'tag' => 'tag1'
							],
							[
								'tag' => 'tag2'
							]
						]
					]
				],
				'expected_error' => null
			],
			'trigger with dependencies' => [
				'triggers' => [
					[
						'description' => 'Trigger with dependencies',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'dependencies' => [
							[
								'triggerid' => self::UPDATE_TRIGGER_1
							]
						]
					]
				],
				'expected_error' => null
			],

			// Failed cases.
			'2 triggers with similar names' => [
				'triggers' => [
					[
						'description' => 'Duplicate trigger name',
						'expression' => 'last(/Trigger validation test host/item)=0'
					],
					[
						'description' => 'Duplicate trigger name',
						'expression' => 'last(/Trigger validation test host/item)=0'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (description, expression)=(Duplicate trigger name, last(/Trigger validation test host/item)=0) already exists.'
			],
			'Trigger with invalid severity #1' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid severity',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'priority' => null
					]
				],
				'expected_error' => 'Invalid parameter "/1/priority": an integer is expected.'
			],
			'Trigger with invalid severity #2' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid severity',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'priority' => 9
					]
				],
				'expected_error' => 'Invalid parameter "/1/priority": value must be one of 0, 1, 2, 3, 4, 5.'
			],
			'Trigger with unexpected recovery exporession' => [
				'triggers' => [
					[
						'description' => 'Trigger with unexpected recovery exporession',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_expression' => 'last(/Trigger validation test host/item)=1'
					]
				],
				'expected_error' => 'Incorrect value for field "recovery_expression": should be empty.'
			],
			'Trigger with unspecified recovery exporession' => [
				'triggers' => [
					[
						'description' => 'Trigger with unspecified recovery exporession',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_mode' => 1
					]
				],
				'expected_error' => 'Incorrect value for field "recovery_expression": cannot be empty.'
			],
			'Trigger with invalid recovery expression #1' => [
				'triggers' => [
					[
						'description' => 'Trigger with expected recovery exporession',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_mode' => 1,
						'recovery_expression' => ['last(/Trigger validation test host/item)=1']
					]
				],
				'expected_error' => 'Invalid parameter "/1/recovery_expression": a character string is expected.'
			],
			'Trigger with invalid recovery expression #2' => [
				'triggers' => [
					[
						'description' => 'Trigger with expected recovery exporession',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_mode' => 1,
						'recovery_expression' => '1+1'
					]
				],
				'expected_error' => 'Invalid parameter "/1/recovery_expression": trigger expression must contain at least one /host/key reference.'
			],
			'Trigger with unexpected correlation tag #1' => [
				'triggers' => [
					[
						'description' => 'Trigger with unexpected correlation tag',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'correlation_mode' => 0,
						'correlation_tag' => 'tag'
					]
				],
				'expected_error' => 'Incorrect value for field "correlation_tag": should be empty.'
			],
			'Trigger with expected correlation tag #2' => [
				'triggers' => [
					[
						'description' => 'Trigger with unexpected correlation tag',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'recovery_mode' => 2,
						'correlation_tag' => 'tag'
					]
				],
				'expected_error' => 'Incorrect value for field "correlation_tag": should be empty.'
			],
			'Trigger with expected correlation tag' => [
				'triggers' => [
					[
						'description' => 'Trigger with unexpected correlation tag',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'correlation_mode' => 1,
						'correlation_tag' => ''
					]
				],
				'expected_error' => 'Incorrect value for field "correlation_tag": cannot be empty.'
			],
			'Trigger with invalid tags #1' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid tags',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [[]]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": the parameter "tag" is missing.'
			],
			'Trigger with invalid tags #2' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid tags',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [[
							'tag' => '',
							'value' => 'value'
						]]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
			],
			'Trigger with invalid dependencies #1' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid dependencies',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'dependencies' => [[
							'triggerid' => ''
						]]
					]
				],
				'expected_error' => 'Invalid parameter "/1/dependencies/1/triggerid": a number is expected.'
			],
			'Trigger with invalid dependencies #2' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid dependencies',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'dependencies' => [[
							'triggerid' => self::TEMPLATE_TRIGGERID
						]]
					]
				],
				'expected_error' => 'Cannot add dependency from a host to a template.'
			],
			'Trigger with invalid dependencies #3' => [
				'triggers' => [
					[
						'description' => 'Trigger with invalid dependencies',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'dependencies' => [[
							'triggerid' => 0
						]]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'trigger with non-unique tags #1' => [
				'triggers' => [
					[
						'description' => 'Trigger with non-unique tags',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [
							[
								'tag' => 'tag'
							],
							[
								'tag' => 'tag'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, ) already exists.'
			],
			'trigger with non-unique tags #2' => [
				'triggers' => [
					[
						'description' => 'Trigger with non-unique tags',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [
							[
								'tag' => 'tag',
								'value' => 'value'
							],
							[
								'tag' => 'tag',
								'value' => 'value'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, value)=(tag, value) already exists.'
			],
			'trigger with null in tags property' => [
				'triggers' => [
					[
						'description' => 'Trigger with null values for array properties',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => null
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			'trigger with null in dependencies property' => [
				'triggers' => [
					[
						'description' => 'Trigger with null values for array properties',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'dependencies' => null
					]
				],
				'expected_error' => 'Invalid parameter "/1/dependencies": an array is expected.'
			],
			'trigger with incorrectly formatted tags' => [
				'triggers' => [
					[
						'description' => 'Trigger with tags',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'tags' => [
							'tag' => 'tag1',
							'tag' => 'tag2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			'trigger with incorrectly formatted dependencies' => [
				'triggers' => [
					[
						'description' => 'Trigger with dependencies',
						'expression' => 'last(/Trigger validation test host/item)=0',
						'dependencies' => [
							'triggerid' => self::UPDATE_TRIGGER_1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/dependencies/1": an array is expected.'
			]
		];
	}

	/**
	 * Test trigger.create API.
	 *
	 * @dataProvider triggers_to_create_data
	 */
	public function testTriggerCreate($triggers, $expected_error) {
		$this->call('trigger.create', $triggers, $expected_error);
	}

	/**
	 * Test trigger.update API.
	 *
	 * @dataProvider triggers_to_update_data
	 */
	public function testTriggerUpdate($triggers, $expected_error) {
		$this->call('trigger.update', $triggers, $expected_error);
	}
}
