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
require_once __DIR__.'/../../include/classes/helpers/CCepRuleHelper.php';

/**
 * @onBefore	prepareData
 * @onAfter		clearData
 * @backup cep_rule
 */
class testCepRule extends CAPITest {
	static array $ruleids = [];

	public static function prepareData() {
		CTestDataHelper::createObjects([
			'ceprules' => [
				[
					'name' => 'update.success',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					]
				],
				[
					'name' => 'window_type=simple',
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'sortorder' => 2,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_ADDED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'event'
						]
					]
				],
				[
					'name' => 'window_type=pattern_match',
					'window_type' => CCepRuleHelper::WINDOW_PATTERN_MATCH,
					'window' => [
						'script' => 'return 0;'
					],
					'sortorder' => 3,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_PATTERN_MATCHED,
							'type' => CCepRuleHelper::OP_SET_SEVERITY,
							'severity' => TRIGGER_SEVERITY_NOT_CLASSIFIED
						]
					]
				],
				[
					'name' => 'update.fail',
					'sortorder' => 4,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					]
				]
			]
		]);
	}

	public static function clearData() {
		if (self::$ruleids) {
			CDataHelper::call('ceprule.delete', self::$ruleids);
		}

		CTestDataHelper::cleanUp();
	}

	public static function dataProviderCreateOperationFilter() {
		yield 'Operation filter formula cannot be empty with CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter": the parameter "formula" is missing.'
		];

		yield 'Operation filter formula must contain identifier' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'abc'
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/formula": incorrect syntax near "abc".'
		];

		yield 'Operation filter conditions required for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A'
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter": the parameter "conditions" is missing.'
		];

		yield 'Operation filter conditions required for CONDITION_EVAL_TYPE_AND_OR' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter": the parameter "conditions" is missing.'
		];

		yield 'Operation filter conditions cannot define non existing formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'type' => CCepRuleHelper::CONDITION_TAG,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'tag' => 'tag',
									'formulaid' => 'A'
								],
								[
									'type' => CCepRuleHelper::CONDITION_TAG,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'tag' => 'tag',
									'formulaid' => 'B'
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/2/formulaid": an identifier is not defined in the formula.'
		];

		yield 'Operation filter cannot reference non existing condition for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'type' => CCepRuleHelper::CONDITION_TAG,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'tag' => 'tag',
									'formulaid' => 'B'
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/formula": missing filter condition "A".'
		];

		yield 'Operation filter conditions must have formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'type' => CCepRuleHelper::CONDITION_EVENT_NAME
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1": the parameter "formulaid" is missing.'
		];

		yield 'Operation filter conditions formulaid must be string for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => 123
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1/formulaid": a character string is expected.'
		];

		yield 'Operation filter conditions formulaid cannot be empty' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'formulaid' => ''
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1/formulaid": cannot be empty.'
		];

		yield 'Operation filter conditions formulaid must be uppercase' => [
			[
				'name' => 'ceprule.operations.filter.create-'.__LINE__,
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'formulaid' => 'b'
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1/formulaid": uppercase identifier expected.'
		];

		yield 'Operation filter conditions can be empty unless CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'name' => 'ceprule.operations.filter.create.valid',
				'sortorder' => 1,
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => []
						]
					]
				]
			],
			null
		];
	}

	public static function cepRuleCreateValidationData(): array {
		return [
			'Non-empty object is required' => [
				'request' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Cannot have unexpected parameter' => [
				'request' => [
					'unexpected' => true
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "unexpected".'
			],
			'Name is required' => [
				'request' => [
					'sortorder' => 1,
					'operations' => []
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Name must be string' => [
				'request' => [
					'name' => [],
					'sortorder' => 1
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Name must be not empty' => [
				'request' => [
					'name' => '',
					'sortorder' => 1
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Operations are required unless WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "operations" is missing.'
			],
			'Operations must be array' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => 123
				],
				'expected_error' => 'Invalid parameter "/1/operations": an array is expected.'
			],
			'Operations must be non-empty' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => []
				],
				'expected_error' => 'Invalid parameter "/1/operations": cannot be empty.'
			],
			'Operation must contain execute_when' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'type' => CCepRuleHelper::OP_SET_NAME
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "execute_when" is missing.'
			],
			'Operation execute_when must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/execute_when": an integer is expected.'
			],
			'Operation execute_when without window must be WHEN_EVENT_OCCURRED(0)' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/execute_when": value must be '.CCepRuleHelper::WHEN_EVENT_OCCURRED.'.'
			],
			'Cannot have execute_when=WHEN_EVENT_EVICTED(1) without window' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_EVICTED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/execute_when": value must be '.CCepRuleHelper::WHEN_EVENT_OCCURRED.'.'
			],
			'Operation must contain sortorder' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "sortorder" is missing.'
			],
			'Both sortorder and execute_when are required' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "execute_when" is missing.'
			],
			'Operation sortorder must be int' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 'abc',
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/sortorder": an integer is expected.'
			],
			'Operation must contain type' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "type" is missing.'
			],
			'Operation type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": an integer is expected.'
			],
			'Operation type must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": value must be one of '.implode(', ', [
					CCepRuleHelper::OP_SET_NAME,
					CCepRuleHelper::OP_CLOSE_EVENT,
					CCepRuleHelper::OP_DISCARD,
					CCepRuleHelper::OP_SET_SEVERITY,
					CCepRuleHelper::OP_INCREASE_SEVERITY,
					CCepRuleHelper::OP_DECREASE_SEVERITY,
					CCepRuleHelper::OP_SUPPRESS,
					CCepRuleHelper::OP_UNSUPPRESS,
					CCepRuleHelper::OP_ADD_TAG,
					CCepRuleHelper::OP_SET_TAG,
					CCepRuleHelper::OP_SET_TAG_VALUE,
					CCepRuleHelper::OP_INCREASE_TAG_VALUE,
					CCepRuleHelper::OP_DECREASE_TAG_VALUE,
					CCepRuleHelper::OP_RENAME_TAG,
					CCepRuleHelper::OP_REMOVE_TAG
				]).'.'
			],
			'Operation type must be suited for WHEN_EVENT_OCCURRED' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_CLONE_LAST
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": value must be one of '.implode(', ', [
					CCepRuleHelper::OP_SET_NAME,
					CCepRuleHelper::OP_CLOSE_EVENT,
					CCepRuleHelper::OP_DISCARD,
					CCepRuleHelper::OP_SET_SEVERITY,
					CCepRuleHelper::OP_INCREASE_SEVERITY,
					CCepRuleHelper::OP_DECREASE_SEVERITY,
					CCepRuleHelper::OP_SUPPRESS,
					CCepRuleHelper::OP_UNSUPPRESS,
					CCepRuleHelper::OP_ADD_TAG,
					CCepRuleHelper::OP_SET_TAG,
					CCepRuleHelper::OP_SET_TAG_VALUE,
					CCepRuleHelper::OP_INCREASE_TAG_VALUE,
					CCepRuleHelper::OP_DECREASE_TAG_VALUE,
					CCepRuleHelper::OP_RENAME_TAG,
					CCepRuleHelper::OP_REMOVE_TAG
				]).'.'
			],
			'Operation suppress_duration is required' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SUPPRESS
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "suppress_duration" is missing.'
			],
			'Operation suppress_duration is in range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SUPPRESS,
							'suppress_duration' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/suppress_duration": value must be one of 0-157680000.'
			],
			'Need event_name for WHEN_EVENT_OCCURRED' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "event_name" is missing.'
			],
			'Event name parameter must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/event_name": a character string is expected.'
			],
			'Minimal ceprule without window succeeds' => [
				'request' => [
					'name' => 'ceprule.minimal',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					]
				],
				'expected_error' => null
			],
			'Can pass tags for operation' => [
				'request' => [
					'name' => 'ceprule.operation.tags',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'conditions' => []
							]
						]
					]
				],
				'expected_error' => null
			],
			'Operation tag must be set for operation type OP_SET_TAG' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_TAG
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "tag" is missing.'
			],
			'Operation tag cannot be empty' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_TAG,
							'tag' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag": cannot be empty.'
			],
			'Operation tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_TAG,
							'tag' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag": a character string is expected.'
			],
			'Cannot specify non-default tag for OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'tag' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag": value must be empty.'
			],
			'Operation  filter.evaltype must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'filter' => [
								'evaltype' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/filter/evaltype": an integer is expected.'
			],
			'Operation evaltype must be of known range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'filter' => [
								'evaltype' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/filter/evaltype": value must be one of '.
					implode(', ', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]).'.'
			],
			'Operation new_tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'new_tag' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/new_tag": a character string is expected.'
			],
			'Cannot have non-default operation new_tag for OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'new_tag' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/new_tag": value must be empty.'
			],
			'Operation tag_value must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'tag_value' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag_value": a character string is expected.'
			],
			'Cannot have non-default operation tag_value for OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'tag_value' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag_value": value must be empty.'
			],
			'Operation severity must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'severity' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/severity": an integer is expected.'
			],
			'Cannot have non-default operation severity for OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name',
							'severity' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/severity": value must be 0.'
			],
			'Filter must be object' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => 123
				],
				'expected_error' => 'Invalid parameter "/1/filter": an array is expected.'
			],
			'Filter can be reset' => [
				'request' => [
					'name' => 'ceprule.no.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => []
					]
				],
				'expected_error' => null
			],
			'Non-empty filter requires evaltype' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'conditions' => []
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter": the parameter "evaltype" is missing.'
			],
			'Filter evaltype must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/evaltype": an integer is expected.'
			],
			'Filter evaltype must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/evaltype": value must be one of '.implode(', ', [
					CONDITION_EVAL_TYPE_AND_OR,
					CONDITION_EVAL_TYPE_AND,
					CONDITION_EVAL_TYPE_OR,
					CONDITION_EVAL_TYPE_EXPRESSION
				]).'.'
			],
			'Filter formula must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": a character string is expected.'
			],
			'Filter formula must be empty unless CONDITION_EVAL_TYPE_AND_OR' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": value must be empty.'
			],
			'Filter conditions must be array' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions": an array is expected.'
			],
			'Filter conditions can be empty unless CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.empty',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => []
					]
				],
				'expected_error' => null
			],
			'Filter formula cannot be empty with CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter": the parameter "formula" is missing.'
			],
			'Filter formula must contain identifier' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": incorrect syntax near "abc".'
			],
			'Filter conditions must be present for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter": the parameter "conditions" is missing.'
			],
			'Filter cannot reference non existing condition for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => []
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": missing filter condition "A".'
			],
			'Filter conditions must have formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "formulaid" is missing.'
			],
			'Filter conditions formulaid must be string for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/formulaid": a character string is expected.'
			],
			'Filter conditions formulaid cannot be empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'formulaid' => ''
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/formulaid": cannot be empty.'
			],
			'Filter conditions formulaid must be uppercase' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'formulaid' => 'b'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/formulaid": uppercase identifier expected.'
			],
			'Filter conditions event_name must be specified' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'formulaid' => 'A',
								'operator' => CONDITION_OPERATOR_EQUAL
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "event_name" is missing.'
			],
			'Filter conditions event_name must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'formulaid' => 'A',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'event_name' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": a character string is expected.'
			],
			'Filter conditions event_name must be not empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'formulaid' => 'A',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'event_name' => ''
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": cannot be empty.'
			],
			'Filter conditions event_name can be empty if not CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter.event_name.empty',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_HOST,
								'formulaid' => 'A',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'event_name' => '',
								'host' => 'host name'
							]
						]
					]
				],
				'expected_error' => null
			],
			'Filter conditions formulaid must match expression' => [
				'request' => [
					'name' => 'ceprule.filter.formulaid',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'formulaid' => 'B',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'event_name' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": missing filter condition "A".'
			],
			'Filter conditions type must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/type": an integer is expected.'
			],
			'Filter conditions type must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/type": value must be one of '.implode(', ', [
					CCepRuleHelper::CONDITION_EVENT_NAME,
					CCepRuleHelper::CONDITION_TAG,
					CCepRuleHelper::CONDITION_TAG_VALUE,
					CCepRuleHelper::CONDITION_SEVERITY,
					CCepRuleHelper::CONDITION_HOST,
					CCepRuleHelper::CONDITION_HOST_GROUP,
					CCepRuleHelper::CONDITION_TIME_PERIOD
				]).'.'
			],
			'Filter conditions operator must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'operator' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/operator": an integer is expected.'
			],
			'Filter conditions operator must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'operator' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/operator": value must be one of '.implode(', ', [
					CONDITION_OPERATOR_EQUAL,
					CONDITION_OPERATOR_NOT_EQUAL,
					CONDITION_OPERATOR_LIKE,
					CONDITION_OPERATOR_NOT_LIKE
				]).'.'
			],
			'Filter conditions tag must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_TAG,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'tag' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": a character string is expected.'
			],
			'Filter conditions tag must be non-empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_TAG,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'tag' => ''
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": cannot be empty.'
			],
			'Filter conditions tag must be empty when type!=CONDITION_TAG_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'event_name' => 'abc',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'tag' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": value must be empty.'
			],
			'Filter conditions severity must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_SEVERITY,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'severity' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": an integer is expected.'
			],
			'Filter conditions severity must be empty when type=CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'event_name' => 'abc',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'severity' => TRIGGER_SEVERITY_INFORMATION
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": value must be 0.'
			],
			'Filter conditions host must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_HOST,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'host' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": a character string is expected.'
			],
			'Filter conditions host must be not empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_HOST,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'host' => ''
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": cannot be empty.'
			],
			'Filter conditions host must be empty when type!=CONDITION_HOST' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'event_name' => 'abc',
								'operator' => CONDITION_OPERATOR_EQUAL,
								'host' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": value must be empty.'
			],
			'Filter conditions host_group must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_HOST_GROUP,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'host_group' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": a character string is expected.'
			],
			'Filter conditions host_group must be not empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_HOST_GROUP,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'host_group' => ''
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": cannot be empty.'
			],
			'Filter conditions host_group must be empty when type=CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'event_name' => 'abc',
								'host_group' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": value must be empty.'
			],
			'Filter conditions time_period must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'type' => CCepRuleHelper::CONDITION_TIME_PERIOD,
								'operator' => CONDITION_OPERATOR_IN,
								'event_name' => '',
								'time_period' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": a character string is expected.'
			],
			'Filter conditions time_period must be empty when type!=CONDITION_TIME_PERIOD' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_EVENT_NAME,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'event_name' => 'abc',
							'time_period' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": value must be empty.'
			],
			'Filter conditions requires tag when type=CONDITION_TAG_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_TAG,
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "tag" is missing.'
			],
			'Filter conditions requires severity when type=CONDITION_SEVERITY' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_SEVERITY,
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "severity" is missing.'
			],
			'Filter conditions requires severity must be in range when type=CONDITION_SEVERITY' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_SEVERITY,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'severity' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": value must be one of '.
					implode(', ', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)).'.'
			],
			'Filter conditions requires host when type=CONDITION_HOST' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_HOST,
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "host" is missing.'
			],
			'Filter conditions requires host_group when type=CONDITION_HOST_GROUP' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_HOST_GROUP,
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "host_group" is missing.'
			],
			'Filter conditions requires time_period when type=CONDITION_TIME_PERIOD' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_IN
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "time_period" is missing.'
			],
			'Filter time_period invalid format check' => [
				'request' => [
					'name' => 'ceprule.filter',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_IN,
							'time_period' => '2h'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": a time period is expected.'
			],
			'Filter time_period check' => [
				'request' => [
					'name' => 'ceprule.filter.time_period',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'Event name'
						]
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => CCepRuleHelper::CONDITION_TIME_PERIOD,
							'operator' => CONDITION_OPERATOR_IN,
							'time_period' => '3-4,10:00-14:00'
						]
					]
				],
				'expected_error' => null
			],
			'Rule stop must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'stop' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/stop": an integer is expected.'
			],
			'Rule stop must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'stop' => -1
				],
				'expected_error' => 'Invalid parameter "/1/stop": value must be one of '.
					implode(', ', [CCepRuleHelper::EXECUTION_CONTINUE, CCepRuleHelper::EXECUTION_STOP]).'.'
			],
			'Rule sortorder must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'sortorder' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			'Rule description must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'description' => -1
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Rule status must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Rule status must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'status' => -1
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [CCepRuleHelper::STATUS_ENABLED, CCepRuleHelper::STATUS_DISABLED]).'.'
			],
			'Rule window_type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/window_type": an integer is expected.'
			],
			'Rule window_type must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => -1
				],
				'expected_error' => 'Invalid parameter "/1/window_type": value must be one of '.implode(', ', [
					CCepRuleHelper::WINDOW_NONE,
					CCepRuleHelper::WINDOW_SIMPLE,
					CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
					CCepRuleHelper::WINDOW_TAG_MATCH,
					CCepRuleHelper::WINDOW_PATTERN_MATCH
				]).'.'
			],
			'Rule window required for window_type=WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Rule window required for window_type=WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_PATTERN_MATCH
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Rule window must be object' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/window": an array is expected.'
			],
			'Unexpected fields for window are rejected' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'unexpected' => true
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": unexpected parameter "unexpected".'
			],
			'Window duration must be time unit' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": a time unit is expected.'
			],
			'Window duration must be greater than zero' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => 0
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value must be one of 1-'.SEC_PER_YEAR.'.'
			],
			'Window duration must be not more than 365d' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => SEC_PER_YEAR + 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value must be one of 1-'.SEC_PER_YEAR.'.'
			],
			'Window duration accepts time unit' => [
				'request' => [
					'name' => 'ceprule.window.duration',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '2h'
					]
				],
				'expected_error' => null
			],
			'Window duration does not accept "y"' => [
				'request' => [
					'name' => 'ceprule.window.duration.as.year',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1y'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": a time unit is expected.'
			],
			'Window duration accepts macro' => [
				'request' => [
					'name' => 'ceprule.window.duration.as.macro',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '{$DURATION}'
					]
				],
				'expected_error' => null
			],
			'Window duration rejects malformed macro' => [
				'request' => [
					'name' => 'ceprule.window.duration.as.macro.malformed',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '{$DURATIon}'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": a time unit is expected.'
			],
			'Window duration rejects too long macro' => [
				'request' => [
					'name' => 'ceprule.window.duration.as.macro.long',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '{$'.str_repeat('M', DB::getFieldLength('cep_rule_window', 'duration')).'}'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value is too long.'
			],
			'Window capacity must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'capacity' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": an integer is expected.'
			],
			'Window capacity must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'capacity' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": value must be one of 0-'.ZBX_MAX_INT32.'.'
			],
			'Window capacity accepts macro' => [
				'request' => [
					'name' => 'ceprule.window.capacity.as.macro',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '{$DURATION}',
						'capacity' => '{$CAPACITY}'
					]
				],
				'expected_error' => null
			],
			'Window capacity rejects malformed macro' => [
				'request' => [
					'name' => 'ceprule.window.capacity.as.macro.malformed',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '{$DURATION}',
						'capacity' => '{$CAPAcity}'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": an integer is expected.'
			],
			'Window capacity rejects too long macro' => [
				'request' => [
					'name' => 'ceprule.window.capacity.as.macro.long',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '{$DURATION}',
						'capacity' => '{$'.str_repeat('M', DB::getFieldLength('cep_rule_window', 'capacity')).'}'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": value is too long.'
			],
			'Window script must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'script' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": a character string is expected.'
			],
			'Window script must be empty for WINDOW_SIMPLE' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must be empty for WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must be empty for WINDOW_TAG_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must not be empty for WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_PATTERN_MATCH,
					'window' => [
						'duration' => '1h',
						'script' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": cannot be empty.'
			],
			'Window group_by_host_group must be int boolean' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_host_group' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_host_group": value must be one of 0, 1.'
			],
			'Window group_by_host must be int boolean' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_host' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_host": value must be one of 0, 1.'
			],
			'Window group_by_tags must be int boolean' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tags' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_tags": value must be one of 0, 1.'
			],
			'Window tag required if group_by_tags=true' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tags' => CCepRuleHelper::GROUP_BY_YES
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": the parameter "tags" is missing.'
			],
			'Window tags must be array of strings' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
						'tags' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tags": an array is expected.'
			],
			'Window tags must be not empty' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
						'tags' => []
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tags": cannot be empty.'
			],
			'Window tag not accepted if group_by_tags=no' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tags' => CCepRuleHelper::GROUP_BY_NO,
						'tags' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tags": value must be empty.'
			],
			'Properties group_by* are optional' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					]
				],
				'expected_error' => null
			],
			'Operations not required for window=WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule.window.symptom.no.operations',
					'window_type' => CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
					'sortorder' => 1,
					'window' => [
						'duration' => '1h',
						'group_by_tags' => CCepRuleHelper::GROUP_BY_YES,
						'tags' => ['abc']
					]
				],
				'expected_error' => null
			],
			'Window event_count_tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'event_count_tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/event_count_tag": a character string is expected.'
			],
			'Window event_count_tag must can be empty' => [
				'request' => [
					'name' => 'ceprule.window.event_count_tag',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'event_count_tag' => ''
					]
				],
				'expected_error' => null
			],
			'Window should be set to database default for WINDOW_NONE' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_NONE,
					'window' => [
						'duration' => '1h',
						'event_count_tag' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value must be "10m".'
			],
			'Non-empty window filter prohibited for WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'sortorder' => 1,
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'bla'
						]
					],
					'window_type' => CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": unexpected parameter "filter".'
			]
		];
	}

	/**
	 * @dataProvider cepRuleCreateValidationData
	 * @dataProvider dataProviderCreateOperationFilter
	 */
	public function testCepRule_CreateValidation(array $request, ?string $expected_error = null) {
		$result = $this->call('ceprule.create', $request, $expected_error);

		if ($expected_error === null) {
			self::$ruleids = array_merge(self::$ruleids, $result['result']['cep_ruleids']);
		}
	}

	public static function dataProviderUpdateOperationFilter() {
		yield 'Operation filter formula cannot be empty with CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter": the parameter "formula" is missing.'
		];

		yield 'Operation filter formula must contain identifier' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'abc'
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/formula": incorrect syntax near "abc".'
		];

		yield 'Operation filter conditions required for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A'
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter": the parameter "conditions" is missing.'
		];

		yield 'Operation filter conditions required for CONDITION_EVAL_TYPE_AND_OR' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter": the parameter "conditions" is missing.'
		];

		yield 'Operation filter conditions cannot define non existing formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'type' => CCepRuleHelper::CONDITION_TAG,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'tag' => 'tag',
									'formulaid' => 'A'
								],
								[
									'type' => CCepRuleHelper::CONDITION_TAG,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'tag' => 'tag',
									'formulaid' => 'B'
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/2/formulaid": an identifier is not defined in the formula.'
		];

		yield 'Operation filter cannot reference non existing condition for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'type' => CCepRuleHelper::CONDITION_TAG,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'tag' => 'tag',
									'formulaid' => 'B'
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/formula": missing filter condition "A".'
		];

		yield 'Operation filter conditions must have formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'type' => CCepRuleHelper::CONDITION_EVENT_NAME
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1": the parameter "formulaid" is missing.'
		];

		yield 'Operation filter conditions formulaid must be string for CONDITION_EVAL_TYPE_EXPRESSION' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => 123
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1/formulaid": a character string is expected.'
		];

		yield 'Operation filter conditions formulaid cannot be empty' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'formulaid' => ''
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1/formulaid": cannot be empty.'
		];

		yield 'Operation filter conditions formulaid must be uppercase' => [
			[
				'cep_ruleid' => ':ceprule:update.fail',
				'operations' => [
					[
						'sortorder' => 1,
						'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
						'type' => CCepRuleHelper::OP_SET_NAME,
						'event_name' => 'Event name',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								[
									'formulaid' => 'b'
								]
							]
						]
					]
				]
			],
			'Invalid parameter "/1/operations/1/filter/conditions/1/formulaid": uppercase identifier expected.'
		];
	}

	public static function cepRuleUpdateValidation(): array {
		return [
			'Ruleid(s) required' => [
				'request' => [],
				'expected_error' => 'Invalid parameter "/1": the parameter "cep_ruleid" is missing.'
			],
			'Cannot send unexpected fields' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'unexpected' => 'unexpected'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "unexpected".'
			],
			'Cannot set empty name' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Cannot send duplicate names' => [
				'request' => [
					[
						'cep_ruleid' => ':ceprule:update.fail',
						'name' => 'abc'
					],
					[
						'cep_ruleid' => ':ceprule:update.success',
						'name' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(abc) already exists.'
			],
			'Cannot set name existing in DB' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'name' => 'update.success'
				],
				'expected_error' => 'CEP rule "update.success" already exists.'
			],
			'Can reset filter' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => []
					]
				],
				'expected_error' => null
			],
			'Cannot send unexpected fields in filter' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'filter' => [
						'unexpected' => 'unexpected'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter": unexpected parameter "unexpected".'
			],
			'Required fields checked for non-empty filter' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'filter' => [
						'conditions' => []
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter": the parameter "evaltype" is missing.'
			],
			'Empty window accepted for WINDOW_NONE' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window' => []
				],
				'expected_error' => null
			],
			'Unexpected fields in window are rejected' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'window_type' => CCepRuleHelper::WINDOW_SIMPLE,
					'window' => [
						'unexpected' => true
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": unexpected parameter "unexpected".'
			],
			'Cannot change window_type from WINDOW_SIMPLE to WINDOW_NONE when operations[].execute_when is not supported' => [
				'request' => [
					'cep_ruleid' => ':ceprule:window_type=simple',
					'window_type' => CCepRuleHelper::WINDOW_NONE
				],
				'Invalid parameter "/1/operations/1/execute_when": value must be 0.'
			],
			'Cannot change window_type from WINDOW_PATTERN_MATCH to WINDOW_NONE when operations[].execute_when is not supported' => [
				'request' => [
					'cep_ruleid' => ':ceprule:window_type=pattern_match',
					'window_type' => CCepRuleHelper::WINDOW_NONE
				],
				'Invalid parameter "/1/operations/1/execute_when": value must be 0.'
			],
			'Switch to group_by_tags requires tags' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'window_type' => CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_tags' => CCepRuleHelper::GROUP_BY_YES
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tags": cannot be empty.'
			],
			'Script is required for WINDOW_TAG_MATCH' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'name' => 'pattern',
					'window_type' => CCepRuleHelper::WINDOW_PATTERN_MATCH,
					'window' => [
						'duration' => '1h'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": cannot be empty.'
			],
			'Can switch to WINDOW_TAG_MATCH' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'name' => 'pattern',
					'window_type' => CCepRuleHelper::WINDOW_PATTERN_MATCH,
					'window' => [
						'duration' => '1h',
						'script' => 'return false;'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations": at least one operation must execute when pattern matched.'
			],
			'Cannot pass non database default duration for switch to WINDOW_NONE' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'name' => 'pattern',
					'window_type' => CCepRuleHelper::WINDOW_NONE,
					'window' => [
						'duration' => '1h'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value must be "10m".'
			],
			'Can reset window' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window_type' => CCepRuleHelper::WINDOW_NONE
				],
				'expected_error' => null
			],
			'Requires execute_when for operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'operations' => [
						[
							'sortorder' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "execute_when" is missing.'
			],
			'Cannot send unexpected field in operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'unexpected' => 'unexpected'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": unexpected parameter "unexpected".'
			],
			'Can update operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'operations' => [
						[
							'sortorder' => 1,
							'execute_when' => CCepRuleHelper::WHEN_EVENT_OCCURRED,
							'type' => CCepRuleHelper::OP_SET_NAME,
							'event_name' => 'update.operation'
						]
					]
				],
				'expected_error' => null
			],
			'Cannot reset operations unless WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'operations' => []
				],
				'expected_error' => 'Invalid parameter "/1/operations": cannot be empty.'
			],
			'Can switch to WINDOW_CAUSE_SYMPTOM and reset operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window_type' => CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'group_by_host_group' => CCepRuleHelper::GROUP_BY_YES
					],
					'operations' => []
				],
				'expected_error' => null
			],
			'Can update stop' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'stop' => CCepRuleHelper::EXECUTION_STOP
				],
				'expected_error' => null
			],
			'Can update sortorder' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'sortorder' => 123
				],
				'expected_error' => null
			],
			'Can update description' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'description' => 'abc'
				],
				'expected_error' => null
			],
			'Can update status' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'status' => CCepRuleHelper::STATUS_DISABLED
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider cepRuleUpdateValidation
	 * @dataProvider dataProviderUpdateOperationFilter
	 */
	public function testCepRule_UpdateValidation(array $request, ?string $expected_error = null) {
		if (!is_numeric(key($request))) {
			$request = [$request];
		}

		CTestDataHelper::convertCepRuleReferences($request);

		$this->call('ceprule.update', $request, $expected_error);
	}
}

