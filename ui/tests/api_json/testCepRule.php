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
 * @onBefore prepareData
 * @onAfter clearData
 *
 * @backup cep_rule,cep_condition,cep_window,cep_window_condition,cep_group,cep_operation,cep_operation_tag
 */
class testCepRule extends CAPITest {
	static array $ids = [];

	public static function prepareData() {

	}

	public function clearData() {
		if (self::$ids) {
			$this->call('ceprule.delete', self::$ids);
		}

		CTestDataHelper::cleanUp();
	}

	public function cepRuleCreateDataProvider(): array {
		return [
			'Object is required' => [
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
			'Operations are required' => [
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
						'sortorder' => 1
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
			'Operation must contain type' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1": the parameter "type" is missing.'
			],
			'Operation type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/type": an integer is expected.'
			],
			'Operation type must be non-negative' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'severity' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/severity": value must be 0.'
			],
			'Operation sortorder must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name',
						'sortorder' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/operations/1/sortorder": an integer is expected.'
			],
			'Ceprule filter must be object' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => 123
				],
				'expected_error' => 'Invalid parameter "/1/filter": an array is expected.'
			],
			'Ceprule filter can be empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => []
				],
				'expected_error' => null
			],
			'Ceprule filter evaltype must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter evaltype must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter formula must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'formula' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": a character string is expected.'
			],
			'Ceprule filter formula must be empty unless CONDITION_EVAL_TYPE_AND_OR' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'formula' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/formula": value must be empty.'
			],
			'Ceprule filter conditions must be array' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions": an array is expected.'
			],
			'Ceprule filter conditions can be empty unless CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter.conditions.empty',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => []
					]
				],
				'expected_error' => null
			],
			'Ceprule filter formula cannot be empty with CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter formula must contain identifier' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions must be present for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions cannot be empty for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions must have formulaid for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions formulaid must be string for CONDITION_EVAL_TYPE_EXPRESSION' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions formulaid cannot be empty' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions formulaid must be uppercase' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
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
			'Ceprule filter conditions event_name must be specified' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => 'A'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "event_name" is missing.'
			],
			'Ceprule filter conditions event_name must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => 'A',
							'event_name' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/event_name": a character string is expected.'
			],
			'Ceprule filter conditions event_name can be empty' => [
				'request' => [
					'name' => 'ceprule.filter.event_name.empty',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => 'A',
							'event_name' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Ceprule filter conditions formulaid must match expression' => [
				'request' => [
					'name' => 'ceprule.filter.formulaid',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A',
						'conditions' => [
							'formulaid' => 'B',
							'event_name' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/formulaid": an identifier is not defined in the formula.'
			],
			'Ceprule filter conditions type must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/type": an integer is expected.'
			],
			'Ceprule filter conditions type must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
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
			'Ceprule filter conditions operator must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'operator' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/operator": an integer is expected.'
			],
			'Ceprule filter conditions operator must be in range' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
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
			'Ceprule filter conditions tag must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'tag' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": a character string is expected.'
			],
			'Ceprule filter conditions tag must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'tag' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag": value must be empty.'
			],
			'Ceprule filter conditions tag_value must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'tag_value' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag_value": a character string is expected.'
			],
			'Ceprule filter conditions tag_value must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'tag_value' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/tag_value": value must be empty.'
			],
			'Ceprule filter conditions severity must be integer' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'severity' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": an integer is expected.'
			],
			'Ceprule filter conditions severity must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'severity' => TRIGGER_SEVERITY_INFORMATION
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": value must be 0.'
			],
			'Ceprule filter conditions host must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'host' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": a character string is expected.'
			],
			'Ceprule filter conditions host must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'host' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host": value must be empty.'
			],
			'Ceprule filter conditions host_group must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'host_group' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": a character string is expected.'
			],
			'Ceprule filter conditions host_group must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'host_group' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/host_group": value must be empty.'
			],
			'Ceprule filter conditions time_period must be string' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'time_period' => 123
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": a character string is expected.'
			],
			'Ceprule filter conditions time_period must be empty when type=ZBX_CEP_CONDITION_EVENT_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'event_name' => '',
							'time_period' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": value must be empty.'
			],
			'Ceprule filter conditions requires tag when type=ZBX_CEP_CONDITION_TAG_NAME' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_NAME
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "tag" is missing.'
			],
			'Ceprule filter conditions requires tag_value when type=ZBX_CEP_CONDITION_TAG_VALUE' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TAG_VALUE
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "tag_value" is missing.'
			],
			'Ceprule filter conditions requires severity when type=ZBX_CEP_CONDITION_SEVERITY' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_SEVERITY
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "severity" is missing.'
			],
			'Ceprule filter conditions requires severity must be in range when type=ZBX_CEP_CONDITION_SEVERITY' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_SEVERITY,
							'severity' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/severity": value must be one of '.
					implode(', ', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)).'.'
			],
			'Ceprule filter conditions requires host when type=ZBX_CEP_CONDITION_HOST' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "host" is missing.'
			],
			'Ceprule filter conditions requires host_group when type=ZBX_CEP_CONDITION_HOST_GROUP' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_HOST_GROUP
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "host_group" is missing.'
			],
			'Ceprule filter conditions requires time_period when type=ZBX_CEP_CONDITION_TIME_PERIOD' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1": the parameter "time_period" is missing.'
			],
			'Ceprule filter time_period invalid format check' => [
				'request' => [
					'name' => 'ceprule.filter',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD,
							'time_period' => '2h'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/time_period": a time period is expected.'
			],
			'Ceprule filter time_period check' => [
				'request' => [
					'name' => 'ceprule.filter.time_period',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'Event name'
					],
					'filter' => [
						'conditions' => [
							'type' => ZBX_CEP_CONDITION_TIME_PERIOD,
							'time_period' => '3-4,10:00-14:00'
						]
					]
				],
				'expected_error' => null
			],
			'Ceprule stop must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'stop' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/stop": an integer is expected.'
			],
			'Ceprule stop must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'stop' => -1
				],
				'expected_error' => 'Invalid parameter "/1/stop": value must be one of '.
					implode(', ', [ZBX_CEP_EXECUTION_CONTINUE, ZBX_CEP_EXECUTION_STOP]).'.'
			],
			'Ceprule sortorder must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'sortorder' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/sortorder": an integer is expected.'
			],
			'Ceprule description must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'description' => -1
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Ceprule status must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Ceprule status must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'status' => -1
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED]).'.'
			],
			'Ceprule window_type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/window_type": an integer is expected.'
			],
			'Ceprule window_type must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
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
			'Ceprule window required for window_type=ZBX_CEP_WINDOW_SIMPLE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Ceprule window required for window_type=ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Ceprule window required for window_type=ZBX_CEP_WINDOW_TAG_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Ceprule window required for window_type=ZBX_CEP_WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "window" is missing.'
			],
			'Ceprule window must be object' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/window": an array is expected.'
			],
			'Defaults (duration) are applied to window=ZBX_CEP_WINDOW_SIMPLE' => [
				'request' => [
					'name' => 'ceprule.window.simple',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => []
				],
				'expected_error' => null
			],
			'Unexpected fields for window are rejected' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
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
			'Window duration must be time unit' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
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
			'Window duration accepts 1y' => [
				'request' => [
					'name' => 'ceprule.window.duration.as.year',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'duration' => '1y'
					]
				],
				'expected_error' => null
			],
			'Window capacity must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'capacity' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": an integer is expected.'
			],
			'Window capacity must be in range' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'capacity' => -1
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/capacity": value must be one of 0-'.ZBX_MAX_INT64.'.'
			],
			'Window script must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'script' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": a character string is expected.'
			],
			'Window script must be empty for ZBX_CEP_WINDOW_SIMPLE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must be empty for ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must be empty for ZBX_CEP_WINDOW_TAG_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_TAG_MATCH,
					'window' => [
						'script' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": value must be empty.'
			],
			'Window script must not be empty for ZBX_CEP_WINDOW_PATTERN_MATCH' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_PATTERN_MATCH,
					'window' => [
						'script' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/script": cannot be empty.'
			],
			'Window group_by_host_group must be boolean' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'group_by_host_group' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_host_group": a boolean is expected.'
			],
			'Window group_by_host must be boolean' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'group_by_host' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_host": a boolean is expected.'
			],
			'Window group_by_tag must be boolean' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'group_by_tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/group_by_tag": a boolean is expected.'
			],
			'Window tag required if group_by_tag=true' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'group_by_tag' => true
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": the parameter "tag" is missing.'
			],
			'Window tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'group_by_tag' => true,
						'tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tag": a character string is expected.'
			],
			'Window tag must be not empty' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'group_by_tag' => true,
						'tag' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/tag": cannot be empty.'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM' => [
				'request' => [
					'name' => 'ceprule.window.symptom',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => []
				],
				'expected_error' => 'Invalid parameter "/1/window": grouping criteria is missing.'
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_host_group' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by_host_group',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_host_group' => true
					]
				],
				'expected_error' => null
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_host' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by_host',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_host' => true
					]
				],
				'expected_error' => null
			],
			'At least one group_by* required for window=ZBX_CEP_WINDOW_CAUSE_SYMPTOM, test with group_by_tag' => [
				'request' => [
					'name' => 'ceprule.window.symptom.group_by_tag',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
					'window' => [
						'group_by_tag' => true,
						'tag' => 'abc'
					]
				],
				'expected_error' => null
			],
			'Window event_count_tag must be string' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'event_count_tag' => 123
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/event_count_tag": a character string is expected.'
			],
			'Window event_count_tag must can be empty' => [
				'request' => [
					'name' => 'ceprule.window.event_count_tag',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'event_count_tag' => ''
					]
				],
				'expected_error' => null
			],
			'Window should be empty for ZBX_CEP_WINDOW_NONE' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_NONE,
					'window' => [
						'event_count_tag' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/window": should be empty.'
			],
			'Window filter prohibits unexpected fields' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
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
			'Window filter evaltype must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
							'formula' => 'A',
							'conditions' => [
								'formulaid' => 'B',
								'past_tag' => 'abc',
								'tag' => 'abc'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/window/filter/conditions/1/formulaid": an identifier is not defined in the formula.'
			],
			'Window filter conditions type must be integer' => [
				'request' => [
					'name' => 'ceprule',
					'operations' => [
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
							'conditions' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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
						'execute_when' => ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
						'type' => ZBX_CEP_OP_SET_NAME,
						'event_name' => 'bla'
					],
					'window_type' => ZBX_CEP_WINDOW_SIMPLE,
					'window' => [
						'filter' => [
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



























		];
	}

	/**
	 * @dataProvider cepRuleCreateDataProvider
	 */
	public function testCepRule_Create(array $request, ?string $expected_error = null) {
		$result = $this->call('ceprule.create', $request, $expected_error);

		if ($expected_error === null) {
			self::$ids = array_merge(self::$ids, $result['result']['cep_ruleids']);
		}
	}
}
