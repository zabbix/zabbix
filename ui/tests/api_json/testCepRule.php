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

/**
 * @onBefore	prepareData
 * @onAfter		clearData
 */
class testCepRule extends CAPITest {
	static array $ruleids = [];

	public static function prepareData() {
		CTestDataHelper::createObjects([
			'ceprules' => [
				[
					'name' => 'update.success',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					]
				],
				[
					'name' => 'update.fail',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
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

	public function cepRuleCreateValidationData(): array {
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
					'operations' => []
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Name must be string' => [
				'request' => [
					'name' => []
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Name must be not empty' => [
				'request' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Operations are required unless ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "operations" is missing.'
			],
			'Operations must be array' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => 123
				],
				'expected_error' => 'Invalid parameter "/1/operations": an array is expected.'
			],
			'Operations must be non-empty' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => []
				],
				'expected_error' => 'Invalid parameter "/1/operations": cannot be empty.'
			],
			'Operation must contain execute_when' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'type' => ZBX_CEP_OP_SET_NAME
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "execute_when" is missing.'
			],
			'Operation execute_when must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/execute_when": an integer is expected.'
			],
			'Operation execute_when without window must be ZBX_CEP_OP_WHEN_EVENT_OCCURRED(0)' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/execute_when": value must be '.ZBX_CEP_OP_WHEN_EVENT_OCCURRED.'.'
			],
			'Cannot have execute_when=ZBX_CEP_OP_WHEN_EVENT_EVICTED(1) without window' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_EVICTED
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/execute_when": value must be '.ZBX_CEP_OP_WHEN_EVENT_OCCURRED.'.'
			],
			'Operation must contain step' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "step" is missing.'
			],
			'Both step and execute_when are required' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "execute_when" is missing.'
			],
			'Operation step must be int' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 'abc',
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/step": an integer is expected.'
			],
			'Operation must contain type' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "type" is missing.'
			],
			'Operation type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": an integer is expected.'
			],
			'Operation type must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": value must be one of '.implode(', ', [
					ZBX_CEP_OP_SET_NAME,
					ZBX_CEP_OP_CLOSE,
					ZBX_CEP_OP_DISCARD,
					ZBX_CEP_OP_SET_SEVERITY,
					ZBX_CEP_OP_INCREASE_SEVERITY,
					ZBX_CEP_OP_DECREASE_SEVERITY,
					ZBX_CEP_OP_SUPPRESS,
					ZBX_CEP_OP_ADD_TAG,
					ZBX_CEP_OP_SET_TAG,
					ZBX_CEP_OP_SET_TAG_VALUE,
					ZBX_CEP_OP_INCREASE_TAG_VALUE,
					ZBX_CEP_OP_DECREASE_TAG_VALUE,
					ZBX_CEP_OP_RENAME_TAG,
					ZBX_CEP_OP_REMOVE_TAG
				]).'.'
			],
			'Operation type must be suited for ZBX_CEP_OP_WHEN_EVENT_OCCURRED' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_COPY_LAST
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": value must be one of '.implode(', ', [
					ZBX_CEP_OP_SET_NAME,
					ZBX_CEP_OP_CLOSE,
					ZBX_CEP_OP_DISCARD,
					ZBX_CEP_OP_SET_SEVERITY,
					ZBX_CEP_OP_INCREASE_SEVERITY,
					ZBX_CEP_OP_DECREASE_SEVERITY,
					ZBX_CEP_OP_SUPPRESS,
					ZBX_CEP_OP_ADD_TAG,
					ZBX_CEP_OP_SET_TAG,
					ZBX_CEP_OP_SET_TAG_VALUE,
					ZBX_CEP_OP_INCREASE_TAG_VALUE,
					ZBX_CEP_OP_DECREASE_TAG_VALUE,
					ZBX_CEP_OP_RENAME_TAG,
					ZBX_CEP_OP_REMOVE_TAG
				]).'.'
			],
			'Need event_name for ZBX_CEP_OP_WHEN_EVENT_OCCURRED' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "event_name" is missing.'
			],
			'Event name parameter must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/event_name": a character string is expected.'
			],
			'Minimal ceprule without window succeeds' => [
				'request' => [
					'name' => 'ceprule.minimal',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					]
				],
				'expected_error' => null
			],
			'Cannot have non-0 event_type without window' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'event_type' => ZBX_CEP_EXECUTE_EVENT_TYPE_CAUSE
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/event_type": value must be 0.'
			],
			'Event_type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'event_type' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/event_type": an integer is expected.'
			],
			'Can only have default eviction_cause when execute_when is ZBX_CEP_OP_WHEN_EVENT_OCCURRED' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'eviction_cause' => ZBX_CEP_EVICTION_CAUSE_DURATION
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/eviction_cause": value must be 0.'
			],
			'Eviction_cause must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'eviction_cause' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/eviction_cause": an integer is expected.'
			],
			'Can pass tags for operation' => [
				'request' => [
					'name' => 'ceprule.operation.tags',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => []
					]
				],
				'expected_error' => null
			],
			'Operation tags must be array' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags": an array is expected.'
			],
			'Operation tags cannot have unexpected fields' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'unexpected' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1": unexpected parameter "unexpected".'
			],
			'Operation tags must have tag name' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'value' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1": the parameter "tag" is missing.'
			],
			'Operation tags name must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'tag' => 1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1/tag": a character string is expected.'
			],
			'Operation tags name cannot be empty' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'tag' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1/tag": cannot be empty.'
			],
			'Operation tags operator must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'step' => 1,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'tag' => 'tag',
							'operator' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1/operator": an integer is expected.'
			],
			'Operation tags operator must be of known range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'tag' => 'tag',
							'operator' => 99
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1/operator": value must be one of '.
					implode(', ', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL]).'.'
			],
			'Operation tags value must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							'tag' => 'tag',
							'value' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/1/value": a character string is expected.'
			],
			'Multiple operation tags accepted' => [
				'request' => [
					'name' => 'ceprule.operation.tags.multiple',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							['tag' => 'tag1'],
							['tag' => 'tag2']
						]
					]
				],
				'expected_error' => null
			],
			'Operation tags must be unique' => [
				'request' => [
					'name' => 'ceprule.operation.tags.multiple.unique',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tags' => [
							['tag' => 'tag1', 'value' => ''],
							['tag' => 'tag1', 'value' => '']
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tags/2": value (tag, value)=(tag1, ) already exists.'
			],
			'Operation tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag": a character string is expected.'
			],
			'Cannot specify non-default tag for ZBX_CEP_OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tag' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag": value must be empty.'
			],
			'Operation evaltype must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'evaltype' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/evaltype": an integer is expected.'
			],
			'Operation evaltype must be of known range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'evaltype' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/evaltype": value must be one of '.
					implode(', ', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND]).'.'
			],
			'Operation new_tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'new_tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/new_tag": a character string is expected.'
			],
			'Cannot have non-default operation new_tag for ZBX_CEP_OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'new_tag' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/new_tag": value must be empty.'
			],
			'Operation tag_value must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tag_value' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag_value": a character string is expected.'
			],
			'Cannot have non-default operation tag_value for ZBX_CEP_OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'tag_value' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/tag_value": value must be empty.'
			],
			'Operation severity must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'severity' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/severity": an integer is expected.'
			],
			'Cannot have non-default operation severity for ZBX_CEP_OP_SET_NAME' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'severity' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/severity": value must be 0.'
			],
			'Filter must be object' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => 123
				],
				'expected_error' => 'Invalid parameter "/1/filter": an array is expected.'
			],
			'Filter can be reset' => [
				'request' => [
					'name' => 'ceprule.no.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => []
				],
				'expected_error' => null
			],
			'Non-empty filter requires evaltype' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter": the parameter "conditions" is missing.'
			],
			'Filter conditions cannot be empty for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => []
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions": cannot be empty.'
			],
			'Filter conditions must have formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "formulaid" is missing.'
			],
			'Filter conditions formulaid must be string for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/formulaid": cannot be empty.'
			],
			'Filter conditions formulaid must be uppercase' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => 'b'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/formulaid": uppercase identifier expected.'
			],
			'Filter conditions event_name must be specified' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'formulaid' => 'A'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "event_name" is missing.'
			],
			'Filter conditions event_name must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'formulaid' => 'A',
							'event_name' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": a character string is expected.'
			],
			'Filter conditions event_name must be not empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'formulaid' => 'A',
							'event_name' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": cannot be empty.'
			],
			'Filter conditions event_name can be empty if not ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter.event_name.empty',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST,
							'formulaid' => 'A',
							'event_name' => '',
							'host' => 'host name'
						]
					]
				],
				'expected_error' => null
			],
			'Filter conditions formulaid must match expression' => [
				'request' => [
					'name' => 'ceprule.filter.formulaid',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'formulaid' => 'B',
							'event_name' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": missing filter condition "A".'
			],
			'Filter conditions type must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/type": an integer is expected.'
			],
			'Filter conditions type must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/type": value must be one of '.implode(', ', [
					ZBX_CEP_CONDITION_EVENT_NAME,
					ZBX_CEP_CONDITION_TAG_NAME,
					ZBX_CEP_CONDITION_TAG_VALUE,
					ZBX_CEP_CONDITION_SEVERITY,
					ZBX_CEP_CONDITION_HOST,
					ZBX_CEP_CONDITION_HOST_GROUP,
					ZBX_CEP_CONDITION_TIME_PERIOD
				]).'.'
			],
			'Filter conditions operator must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'operator' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/operator": an integer is expected.'
			],
			'Filter conditions operator must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'operator' => -1
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME,
							'tag' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": a character string is expected.'
			],
			'Filter conditions tag must be non-empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME,
							'tag' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": cannot be empty.'
			],
			'Filter conditions tag must be empty when type!=ZBX_CEP_CONDITION_TAG_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'event_name' => 'abc',
							'tag' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": value must be empty.'
			],
			'Filter conditions tag_value must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_VALUE,
							'tag_value' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag_value": a character string is expected.'
			],
			'Filter conditions tag_value can be empty' => [
				'request' => [
					'name' => 'ceprule.filter.tag.value.empty',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_VALUE,
							'tag_value' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Filter conditions tag_value must be empty when type!=ZBX_CEP_CONDITION_TAG_VALUE' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME,
							'tag' => 'abc',
							'tag_value' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag_value": value must be empty.'
			],
			'Filter conditions severity must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_SEVERITY,
							'severity' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": an integer is expected.'
			],
			'Filter conditions severity must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'event_name' => 'abc',
							'severity' => TRIGGER_SEVERITY_INFORMATION
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": value must be 0.'
			],
			'Filter conditions host must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST,
							'host' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": a character string is expected.'
			],
			'Filter conditions host must be not empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST,
							'host' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": cannot be empty.'
			],
			'Filter conditions host must be empty when type!=ZBX_CEP_CONDITION_HOST' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'event_name' => 'abc',
							'host' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": value must be empty.'
			],
			'Filter conditions host_group must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST_GROUP,
							'host_group' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": a character string is expected.'
			],
			'Filter conditions host_group must be not empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST_GROUP,
							'host_group' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": cannot be empty.'
			],
			'Filter conditions host_group must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'event_name' => 'abc',
							'host_group' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": value must be empty.'
			],
			'Filter conditions time_period must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD,
							'event_name' => '',
							'time_period' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": a character string is expected.'
			],
			'Filter conditions time_period must be empty when type!=ZBX_CEP_CONDITION_TIME_PERIOD' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'event_name' => 'abc',
							'time_period' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": value must be empty.'
			],
			'Filter conditions requires tag when type=ZBX_CEP_CONDITION_TAG_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "tag" is missing.'
			],
			'Filter conditions requires tag_value when type=ZBX_CEP_CONDITION_TAG_VALUE' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_VALUE
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "tag_value" is missing.'
			],
			'Filter conditions requires severity when type=ZBX_CEP_CONDITION_SEVERITY' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_SEVERITY
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "severity" is missing.'
			],
			'Filter conditions requires severity must be in range when type=ZBX_CEP_CONDITION_SEVERITY' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_SEVERITY,
							'severity' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": value must be one of '.
					implode(', ', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)).'.'
			],
			'Filter conditions requires host when type=ZBX_CEP_CONDITION_HOST' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "host" is missing.'
			],
			'Filter conditions requires host_group when type=ZBX_CEP_CONDITION_HOST_GROUP' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST_GROUP
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "host_group" is missing.'
			],
			'Filter conditions requires time_period when type=ZBX_CEP_CONDITION_TIME_PERIOD' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "time_period" is missing.'
			],
			'Filter time_period invalid format check' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD,
							'time_period' => '2h'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": a time period is expected.'
			],
			'Filter time_period check' => [
				'request' => [
					'name' => 'ceprule.filter.time_period',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD,
							'time_period' => '3-4,10:00-14:00'
						]
					]
				],
				'expected_error' => null
			],
			'Rule stop must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'stop' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/stop": an integer is expected.'
			],
			'Rule stop must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'stop' => -1
				],
				'expected_error' => 'Invalid parameter "/1/stop": value must be one of '.
					implode(', ', [ZBX_CEP_EXECUTION_CONTINUE, ZBX_CEP_EXECUTION_STOP]).'.'
			],
			'Rule sortorder must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'sortorder' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			'Rule description must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'description' => -1
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Rule status must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Rule status must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'status' => -1
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED]).'.'
			],
			'Rule window_type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/window_type": an integer is expected.'
			],
			'Rule window_type must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => -1
				],
				'expected_error' => 'Invalid parameter "/1/window_type": value must be one of '.implode(', ', [
					ZBX_CEP_WINDOW_NONE,
					ZBX_CEP_WINDOW_SIMPLE,
					ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					ZBX_CEP_WINDOW_TAG_MATCH,
					ZBX_CEP_WINDOW_PATTERN_MATCH
				]).'.'
			],
			'Rule window required for window_type=ZBX_CEP_WINDOW_SIMPLE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Rule window required for window_type=ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Rule window required for window_type=ZBX_CEP_WINDOW_TAG_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Rule window required for window_type=ZBX_CEP_WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Rule window must be object' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/window": an array is expected.'
			],
			'Unexpected fields for window are rejected' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'unexpected' => true
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": unexpected parameter "unexpected".'
			],
			'Duration is required for window!=ZBX_CEP_WINDOW_NONE' => [
				'request' => [
					'name' => 'ceprule.window.simple',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => []
				],
				'expected_error' => 'Invalid parameter "/1/window": the parameter "duration" is missing.'
			],
			'Window duration must be time unit' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": a time unit is expected.'
			],
			'Window duration must be not empty' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => 0
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value must be one of 1-'.SEC_PER_YEAR.'.'
			],
			'Window duration must be not more than 365d' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => SEC_PER_YEAR + 1
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": value must be one of 1-'.SEC_PER_YEAR.'.'
			],
			'Window duration accepts time unit' => [
				'request' => [
					'name' => 'ceprule.window.duration',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '2h'
					]
				],
				'expected_error' => null
			],
			'Window duration does not accept "y"' => [
				'request' => [
					'name' => 'ceprule.window.duration.as.year',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1y'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/duration": a time unit is expected.'
			],
			'Window capacity must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'capacity' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": value must be one of 0-'.ZBX_MAX_INT64.'.'
			],
			'Window script must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'script' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": a character string is expected.'
			],
			'Window script must be empty for ZBX_CEP_WINDOW_SIMPLE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must be empty for ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must be empty for ZBX_CEP_WINDOW_TAG_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must not be empty for ZBX_CEP_WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH,
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_host' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_host": value must be one of 0, 1.'
			],
			'Window group_by_tag must be int boolean' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_tag": value must be one of 0, 1.'
			],
			'Window tag required if group_by_tag=true' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": the parameter "tag" is missing.'
			],
			'Window tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES,
						'tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tag": a character string is expected.'
			],
			'Window tag must be not empty' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES,
						'tag' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tag": cannot be empty.'
			],
			'Window tag not accepted if group_by_tag=no' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => ZBX_CEP_GROUP_BY_NO,
						'tag' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tag": value must be empty.'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule.window.symptom',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'event_type' => ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": at least one of "group_by_host_group", "group_by_host" or "group_by_tag" parameters must be enabled.'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_host_group' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by_host_group',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'event_type' => ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'group_by_host_group' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => null
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_host' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by_host',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'event_type' => ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'group_by_host' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => null
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_tag' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by_tag',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'event_type' => ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES,
						'tag' => 'abc'
					]
				],
				'expected_error' => null
			],
			'Operations not required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule.window.symptom.no.operations',
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES,
						'tag' => 'abc'
					]
				],
				'expected_error' => null
			],
			'Window event_count_tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
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
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h',
						'event_count_tag' => ''
					]
				],
				'expected_error' => null
			],
			'Window should be empty for ZBX_CEP_WINDOW_NONE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_NONE,
					'window' => [
						'duration' => '1h',
						'event_count_tag' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": should be empty.'
			],
			'Non-empty window filter prohibited for ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter": should be empty.'
			],
			'Empty window filter allowed for ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule.symptom.filter.empty',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'event_type' => ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'duration' => '1h',
						'filter' => [],
						'group_by_host' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => null
			],
			'Non-empty window filter prohibited for ZBX_CEP_WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter": should be empty.'
			],
			'Empty window filter allowed for ZBX_CEP_WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule.pattern.filter.empty',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [],
						'script' => 'abc'
					]
				],
				'expected_error' => null
			],
			'Window filter prohibits unexpected fields' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'unexpected' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter": unexpected parameter "unexpected".'
			],
			'Window filter evaltype must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/evaltype": an integer is expected.'
			],
			'Window filter evaltype must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/evaltype": value must be one of '.implode(', ', [
					CONDITION_EVAL_TYPE_AND_OR,
					CONDITION_EVAL_TYPE_AND,
					CONDITION_EVAL_TYPE_OR,
					CONDITION_EVAL_TYPE_EXPRESSION
				]).'.'
			],
			'Window filter formula must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/formula": a character string is expected.'
			],
			'Window filter formula must be empty unless evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/formula": value must be empty.'
			],
			'Window filter formula must be non-empty if evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/formula": cannot be empty.'
			],
			'Window filter formula required if evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/formula": incorrect syntax near "abc".'
			],
			'Window filter conditions prohibit unexpected fields' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'unexpected' => true
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1": unexpected parameter "unexpected".'
			],
			'Window filter conditions required if evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter": the parameter "conditions" is missing.'
			],
			'Window filter conditions cannot be empty if evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions": cannot be empty.'
			],
			'Window filter conditions formulaid required for evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1": the parameter "formulaid" is missing.'
			],
			'Window filter conditions formulaid must be string for evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => 123,
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/formulaid": a character string is expected.'
			],
			'Window filter conditions formulaid must be non-empty for evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => '',
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/formulaid": cannot be empty.'
			],
			'Window filter conditions formulaid must be identifier for evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => 'a',
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/formulaid": uppercase identifier expected.'
			],
			'Window filter conditions formulaid must match identifier for evaltype=CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'type' => ZBX_CEP_CONDITION_TAG_NAME,
								'formulaid' => 'B',
								'past_tag' => 'abc',
								'tag_value' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/formula": missing filter condition "A".'
			],
			'Window filter conditions type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => 'A',
								'type' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/type": an integer is expected.'
			],
			'Window filter conditions type must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/type": value must be one of '.implode(', ', [
					ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
					ZBX_CEP_WINDOW_CONDITION_OLD_TAG,
					ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
				]).'.'
			],
			'Window filter conditions requires past_tag' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1": the parameter "past_tag" is missing.'
			],
			'Window filter conditions past_tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/past_tag": a character string is expected.'
			],
			'Window filter conditions past_tag cannot be empty' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => ''
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/past_tag": cannot be empty.'
			],
			'Window filter conditions operator must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => 'abc',
								'operator' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/operator": an integer is expected.'
			],
			'Window filter conditions operator range for ZBX_CEP_WINDOW_CONDITION_TAG_PAIR' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => 'abc',
								'operator' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/operator": value must be '.
					implode(', ', [CONDITION_OPERATOR_EQUAL]).'.'
			],
			'Window filter conditions operator range for ZBX_CEP_WINDOW_CONDITION_OLD_TAG' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_OLD_TAG,
								'past_tag' => 'abc',
								'operator' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/operator": value must be '.
					implode(', ', [CONDITION_OPERATOR_EQUAL]).'.'
			],
			'Window filter conditions operator range for ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE,
								'past_tag' => 'abc',
								'operator' => -1
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/operator": value must be one of '.
					implode(', ', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL]).'.'
			],
			'Window filter conditions tag required for ZBX_CEP_WINDOW_CONDITION_TAG_PAIR' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1": the parameter "tag" is missing.'
			],
			'Window filter conditions tag must be string for ZBX_CEP_WINDOW_CONDITION_TAG_PAIR' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => 'abc',
								'tag' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/tag": a character string is expected.'
			],
			'Window filter conditions tag can be empty for ZBX_CEP_WINDOW_CONDITION_TAG_PAIR' => [
				'request' => [
					'name' => 'ceprule.window.filter.conditions.tag.empty',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								'past_tag' => 'abc',
								'tag' => ''
							]
						]
					]
				],
				'expected_error' => null
			],
			'Window filter conditions tag_value required for ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE,
								'past_tag' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1": the parameter "tag_value" is missing.'
			],
			'Window filter conditions tag_value must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE,
								'past_tag' => 'abc',
								'tag_value' => 123
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/tag_value": a character string is expected.'
			],
			'Window filter conditions tag_value can be empty' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.tag_value.empty',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE,
								'past_tag' => 'abc',
								'tag_value' => ''
							]
						]
					]
				],
				'expected_error' => null
			],
			'Window filter conditions event_name cannot be empty with CONDITION_OPERATOR_LIKE' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.like',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'event_name' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": cannot be empty.'
			],
			'Window filter conditions event_name cannot be empty with CONDITION_OPERATOR_NOT_LIKE' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.like',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_EVENT_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'event_name' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": cannot be empty.'
			],
			'Window filter conditions tag cannot be empty with CONDITION_OPERATOR_LIKE' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.like',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'tag' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": cannot be empty.'
			],
			'Window filter conditions tag cannot be empty with CONDITION_OPERATOR_NOT_LIKE' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.like',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1h'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME,
							'operator' => CONDITION_OPERATOR_NOT_LIKE,
							'tag' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": cannot be empty.'
			]
		];
	}

	/**
	 * @dataProvider cepRuleCreateValidationData
	 */
	public function testCepRule_CreateValidation(array $request, ?string $expected_error = null) {
		$result = $this->call('ceprule.create', $request, $expected_error);

		if ($expected_error === null) {
			self::$ruleids = array_merge(self::$ruleids, $result['result']['cep_ruleids']);
		}
	}

	public function cepRuleUpdateValidation(): array {
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
					'cep_ruleid' => ':ceprule:update.fail',
					'filter' => []
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
			'Empty window accepted for ZBX_CEP_WINDOW_NONE' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window' => []
				],
				'expected_error' => null
			],
			'Window required on window type switch' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'window_type' => ZBX_CEP_WINDOW_SIMPLE
				],
				'expected_error' => 'Invalid parameter "/1/window": an array is expected.'
			],
			'Unexpected fields in window are rejected' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'unexpected' => true
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": unexpected parameter "unexpected".'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => []
				],
				'expected_error' => 'Invalid parameter "/1/window": at least one of "group_by_host_group", "group_by_host" or "group_by_tag" parameters must be enabled.'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_host_group' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_host_group' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => null
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, switch to group_by_host' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_host_group' => ZBX_CEP_GROUP_BY_NO,
						'group_by_host' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => null
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, switch to group_by_tag requires tag' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_host_group' => ZBX_CEP_GROUP_BY_NO,
						'group_by_host' => ZBX_CEP_GROUP_BY_NO,
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": the parameter "tag" is missing.'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, switch to group_by_tag' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_host_group' => ZBX_CEP_GROUP_BY_NO,
						'group_by_host' => ZBX_CEP_GROUP_BY_NO,
						'group_by_tag' => ZBX_CEP_GROUP_BY_YES,
						'tag' => 'abc'
					]
				],
				'expected_error' => null
			],
			'Can switch to ZBX_CEP_WINDOW_TAG_MATCH' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'name' => 'pattern',
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH,
					'window' => [
						'duration' => '1h'
					]
				],
				'expected_error' => null
			],
			'Cannot pass non-empty window for switch to ZBX_CEP_WINDOW_NONE' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'name' => 'pattern',
					'window_type' => ZBX_CEP_WINDOW_NONE,
					'window' => [
						'duration' => '1h'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": should be empty.'
			],

			'Can provide filter for switch to ZBX_CEP_WINDOW_TAG_MATCH' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'name' => 'pattern',
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'duration' => '1h',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								'type' => ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE,
								'operator' => CONDITION_OPERATOR_NOT_EQUAL,
								'past_tag' => 'abc',
								'tag_value' => ''
							]
						]
					]
				],
				'expected_error' => null
			],
			'Can reset window' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'window_type' => ZBX_CEP_WINDOW_NONE
				],
				'expected_error' => null
			],
			'Requires execute_when for operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'operations' => [
						'unexpected' => 'unexpected'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "execute_when" is missing.'
			],
			'Cannot send unexpected field in operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.fail',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'unexpected' => 'unexpected'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": unexpected parameter "unexpected".'
			],
			'Can update operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'operations' => [
						'step' => 1,
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'update.operation'
					]
				],
				'expected_error' => null
			],
			'Can reset operations' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'operations' => []
				],
				'expected_error' => null
			],
			'Can update stop' => [
				'request' => [
					'cep_ruleid' => ':ceprule:update.success',
					'stop' => ZBX_CEP_EXECUTION_STOP
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
					'status' => ZBX_CEP_STATUS_DISABLED
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider cepRuleUpdateValidation
	 */
	public function testCepRule_UpdateValidation(array $request, ?string $expected_error = null) {
		if (!is_numeric(key($request))) {
			$request = [$request];
		}

		CTestDataHelper::convertCepRuleReferences($request);

		$this->call('ceprule.update', $request, $expected_error);
	}
}

