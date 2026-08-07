<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class CAuditTest extends TestCase {

	public static function dataProviderHandleObjectDiffItem() {
		// CAudit::ACTION_ADD
		yield 'action ACTION_ADD' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_ADD,
			[
				'hostid' => '140021',
				'allow_traps' => 0,
				'follow_redirects' => 1,
				'output_format' => 0,
				'status' => 0,
				'verify_host' => 0,
				'verify_peer' => 0,
				'inventory_link' => 0,
				'authtype' => 0,
				'delay' => '1m',
				'description' => '',
				'headers' => [
					['sortorder' => 1, 'name' => 'header1', 'value' => 'value1']
				],
				'history' => '31d',
				'http_proxy' => '',
				'interfaceid' => '99008',
				'name' => 'http item',
				'post_type' => 0,
				'posts' => '',
				'preprocessing' => [],
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field1', 'value' => 'value1']
				],
				'request_method' => 0,
				'retrieve_mode' => 0,
				'ssl_cert_file' => '',
				'ssl_key_file' => '',
				'ssl_key_password' => '',
				'status_codes' => '200',
				'tags' => [],
				'timeout' => '',
				'trends' => '365d',
				'type' => 19,
				'units' => '',
				'url' => 'http://localhost',
				'value_type' => 3,
				'valuemapid' => '0',
				'key_' => 'item[http]',
				'flags' => 0,
				'itemid' => '400858'
			],
			[],
			[
				'item.hostid' => [CAudit::DETAILS_ACTION_ADD, '140021'],
				'item.delay' => [CAudit::DETAILS_ACTION_ADD, '1m'],
				'item.headers[1]' => [CAudit::DETAILS_ACTION_ADD],
				'item.headers[1].sortorder' => [CAudit::DETAILS_ACTION_ADD, '1'],
				'item.headers[1].name' => [CAudit::DETAILS_ACTION_ADD, 'header1'],
				'item.headers[1].value' => [CAudit::DETAILS_ACTION_ADD, 'value1'],
				'item.interfaceid' => [CAudit::DETAILS_ACTION_ADD, '99008'],
				'item.name' => [CAudit::DETAILS_ACTION_ADD, 'http item'],
				'item.query_fields[1]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query_fields[1].sortorder' => [CAudit::DETAILS_ACTION_ADD, '1'],
				'item.query_fields[1].name' => [CAudit::DETAILS_ACTION_ADD, 'field1'],
				'item.query_fields[1].value' => [CAudit::DETAILS_ACTION_ADD, 'value1'],
				'item.ssl_key_password' => [CAudit::DETAILS_ACTION_ADD, ZBX_SECRET_MASK],
				'item.type' => [CAudit::DETAILS_ACTION_ADD, '19'],
				'item.url' => [CAudit::DETAILS_ACTION_ADD, 'http://localhost'],
				'item.value_type' => [CAudit::DETAILS_ACTION_ADD, '3'],
				'item.key_' => [CAudit::DETAILS_ACTION_ADD, 'item[http]'],
				'item.itemid' => [CAudit::DETAILS_ACTION_ADD, '400858']
			]
		];
		yield 'action ACTION_ADD for telemetry query item' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_ADD,
			[
				'type' => ITEM_TYPE_TELEMETRY_QUERY,
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_METRICS_POINT_SUM,
					'columns' => [
						['column' => 'column 1', 'attribute_key' => ''],
						['column' => 'column 2', 'attribute_key' => 'attr']
					],
					'aggregated_columns' => [
						['column' => 'aggregated 1', 'function' => AGGREGATE_PCTILE, 'parameters' => [], 'alias' => ''],
						['column' => 'aggregated 2', 'function' => AGGREGATE_PCTILE, 'parameters' => ['10'], 'alias' => ''],
						['column' => 'aggregated 3', 'function' => AGGREGATE_PCTILE, 'parameters' => ['10'], 'alias' => 'aggregate alias']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => [
							['column' => 'filter 1', 'attribute_key' => '', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '']
						]
					]
				]
			],
			[],
			[
				'item.type' => [CAudit::DETAILS_ACTION_ADD, '24'],
				'item.query' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.signal_type' => [CAudit::DETAILS_ACTION_ADD, '0'],
				'item.query.metric_point_type' => [CAudit::DETAILS_ACTION_ADD, '0'],
				'item.query.columns[0]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.columns[0].column' => [CAudit::DETAILS_ACTION_ADD, 'column 1'],
				'item.query.columns[0].attribute_key' => [CAudit::DETAILS_ACTION_ADD, ''],
				'item.query.columns[1]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.columns[1].column' => [CAudit::DETAILS_ACTION_ADD, 'column 2'],
				'item.query.columns[1].attribute_key' => [CAudit::DETAILS_ACTION_ADD, 'attr'],
				'item.query.aggregated_columns[0]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.aggregated_columns[0].column' => [CAudit::DETAILS_ACTION_ADD, 'aggregated 1'],
				'item.query.aggregated_columns[0].function' => [CAudit::DETAILS_ACTION_ADD, '8'],
				'item.query.aggregated_columns[0].alias' => [CAudit::DETAILS_ACTION_ADD, ''],
				'item.query.aggregated_columns[1]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.aggregated_columns[1].column' => [CAudit::DETAILS_ACTION_ADD, 'aggregated 2'],
				'item.query.aggregated_columns[1].function' => [CAudit::DETAILS_ACTION_ADD, '8'],
				'item.query.aggregated_columns[1].parameters[0]' => [CAudit::DETAILS_ACTION_ADD, '10'],
				'item.query.aggregated_columns[1].alias' => [CAudit::DETAILS_ACTION_ADD, ''],
				'item.query.aggregated_columns[2]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.aggregated_columns[2].column' => [CAudit::DETAILS_ACTION_ADD, 'aggregated 3'],
				'item.query.aggregated_columns[2].function' => [CAudit::DETAILS_ACTION_ADD, '8'],
				'item.query.aggregated_columns[2].parameters[0]' => [CAudit::DETAILS_ACTION_ADD, '10'],
				'item.query.aggregated_columns[2].alias' => [CAudit::DETAILS_ACTION_ADD, 'aggregate alias'],
				'item.query.filter' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.filter.evaltype' => [CAudit::DETAILS_ACTION_ADD, '0'],
				'item.query.filter.formula' => [CAudit::DETAILS_ACTION_ADD, ''],
				'item.query.filter.conditions[0]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.filter.conditions[0].column' => [CAudit::DETAILS_ACTION_ADD, 'filter 1'],
				'item.query.filter.conditions[0].attribute_key' => [CAudit::DETAILS_ACTION_ADD, ''],
				'item.query.filter.conditions[0].operator' => [CAudit::DETAILS_ACTION_ADD, '0'],
				'item.query.filter.conditions[0].value' => [CAudit::DETAILS_ACTION_ADD, '']
			]
		];

		// CAudit::ACTION_UPDATE
		yield 'item preprocessing step new step added' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'preprocessing' => [
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					],
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135684',
						'step' => 2,
						'type' => ZBX_PREPROC_REGSUB,
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]
			],
			[
				'preprocessing' => [
					'135683' => [
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'item.preprocessing[135684]' => [CAudit::DETAILS_ACTION_ADD],
				'item.preprocessing[135684].params' => [CAudit::DETAILS_ACTION_ADD, "match\nreplace"],
				'item.preprocessing[135684].item_preprocid' => [CAudit::DETAILS_ACTION_ADD, '135684'],
				'item.preprocessing[135684].step' => [CAudit::DETAILS_ACTION_ADD, '2'],
				'item.preprocessing[135684].type' => [CAudit::DETAILS_ACTION_ADD, '5']
			]
		];
		yield 'item preprocessing step param value change from "replace\nreplace" to "match\nreplace"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'preprocessing' => [
					[
						'params' => "replace\nreplace",
						'item_preprocid' => '135683',
						'type' => ITEM_TYPE_INTERNAL,
						'step' => 1,
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]
			],
			[
				'preprocessing' => [
					'135683' => [
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'item.preprocessing[135683]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.preprocessing[135683].params' => [CAudit::DETAILS_ACTION_UPDATE, "replace\nreplace", "match\nreplace"]
			]
		];
		yield 'item preprocessing step deleted' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'preprocessing' => [
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'preprocessing' => [
					'135683' => [
						'params' => "match\nreplace",
						'item_preprocid' => '135683',
						'step' => '1',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					],
					[
						'params' => "match\nreplace",
						'item_preprocid' => '135684',
						'step' => '2',
						'type' => '5',
						'error_handler' => '0',
						'error_handler_params' => ''
					]
				]
			],
			[
				'item.preprocessing[135684]' => [CAudit::DETAILS_ACTION_DELETE]
			]
		];

		yield '"query_fields" add new query field' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2', 'value' => 'value-2'],
					['sortorder' => 3, 'name' => 'field-3', 'value' => 'value-3']
				]
			],
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2', 'value' => 'value-2']
				]
			],
			[
				'item.query_fields[3]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query_fields[3].sortorder' => [CAudit::DETAILS_ACTION_ADD, '3'],
				'item.query_fields[3].name' => [CAudit::DETAILS_ACTION_ADD, 'field-3'],
				'item.query_fields[3].value' => [CAudit::DETAILS_ACTION_ADD, 'value-3']
			]
		];
		yield '"query_fields" update value from "field-2" to "field-2-updated"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2', 'value' => 'value-2']
				]
			],
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2-updated', 'value' => 'value-2']
				]
			],
			[
				'item.query_fields[2]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.query_fields[2].name' => [CAudit::DETAILS_ACTION_UPDATE, 'field-2', 'field-2-updated']
			]
		];
		yield '"query_fields" query field deleted' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1']
				]
			],
			[
				'query_fields' => [
					['sortorder' => 1, 'name' => 'field-1', 'value' => 'value-1'],
					['sortorder' => 2, 'name' => 'field-2-updated', 'value' => 'value-2']
				]
			],
			[
				'item.query_fields[2]' => [CAudit::DETAILS_ACTION_DELETE]
			]
		];

		yield '"item.tags" add new tags' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'tags' => [
					['tag' => 'tag-1', 'value' => 'value-1', 'itemtagid' => '85234'],
					['tag' => 'tag-2', 'value' => 'value-2', 'itemtagid' => '85235']
				]
			],
			[
				'tags' => []
			],
			[
				'item.tags[85234]' => [CAudit::DETAILS_ACTION_ADD],
				'item.tags[85235]' => [CAudit::DETAILS_ACTION_ADD],
				'item.tags[85234].tag' => [CAudit::DETAILS_ACTION_ADD, 'tag-1'],
				'item.tags[85234].value' => [CAudit::DETAILS_ACTION_ADD, 'value-1'],
				'item.tags[85234].itemtagid' => [CAudit::DETAILS_ACTION_ADD, '85234'],
				'item.tags[85235].tag' => [CAudit::DETAILS_ACTION_ADD, 'tag-2'],
				'item.tags[85235].value' => [CAudit::DETAILS_ACTION_ADD, 'value-2'],
				'item.tags[85235].itemtagid' => [CAudit::DETAILS_ACTION_ADD, '85235']
			]
		];

		yield '"item.password" change from "123" to "passwordpassword"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'password' => 'passwordpassword',
				'type' => ITEM_TYPE_SIMPLE
			],
			[
				'type' => ITEM_TYPE_SIMPLE,
				'password' => '123'
			],
			[
				'item.password' => [CAudit::DETAILS_ACTION_UPDATE, ZBX_SECRET_MASK, ZBX_SECRET_MASK]
			]
		];
		yield '"item.password" change from empty value to "password"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'password' => 'password',
				'type' => ITEM_TYPE_SIMPLE
			],
			[
				'type' => ITEM_TYPE_SIMPLE,
				'password' => ''
			],
			[
				'item.password' => [CAudit::DETAILS_ACTION_UPDATE, ZBX_SECRET_MASK, ZBX_SECRET_MASK]
			]
		];
		yield '"item.password" change "password" to empty value' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'type' => ITEM_TYPE_SIMPLE,
				'password' => ''
			],
			[
				'password' => 'password',
				'type' => ITEM_TYPE_SIMPLE
			],
			[
				'item.password' => [CAudit::DETAILS_ACTION_UPDATE, ZBX_SECRET_MASK, ZBX_SECRET_MASK]
			]
		];

		yield '"item.query.columns" delete column' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'Timestamp', 'attribute_key' => '']
					],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_COUNT, 'alias' => 'total']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'Timestamp', 'attribute_key' => ''],
						['column' => 'SpanId', 'attribute_key' => '']
					],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_COUNT, 'alias' => 'total']
					],
					'filter' => [
						'evaltype' => 0,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'item.query.columns[1]' => [CAudit::DETAILS_ACTION_DELETE]
			]
		];
		yield '"item.query.aggregated_columns[1].alias" change from "min" to "minimum"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_COUNT, 'alias' => 'total'],
						['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'min']
					],
					'filter' => [
						'evaltype' => 0,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_COUNT, 'alias' => 'total'],
						['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'minimum']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'item.query.aggregated_columns[1]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.query.aggregated_columns[1].alias' => [CAudit::DETAILS_ACTION_UPDATE, 'min', 'minimum']
			]
		];
		yield '"item.query.aggregated_columns" add new column' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_COUNT, 'alias' => 'total'],
						['column' => '', 'function' => AGGREGATE_PCTILE, 'parameters' => ['10'], 'alias' => '']
					],
					'filter' => [
						'evaltype' => 0,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_COUNT, 'alias' => 'total']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'item.query.aggregated_columns[1]' => [CAudit::DETAILS_ACTION_ADD],
				'item.query.aggregated_columns[1].column' => [CAudit::DETAILS_ACTION_ADD, ''],
				'item.query.aggregated_columns[1].function' => [CAudit::DETAILS_ACTION_ADD, '8'],
				'item.query.aggregated_columns[1].parameters[0]' => [CAudit::DETAILS_ACTION_ADD, '10'],
				'item.query.aggregated_columns[1].alias' => [CAudit::DETAILS_ACTION_ADD, '']
			]
		];
		yield '"item.query.aggregated_columns[1].parameters[0]" change from "5" to "10"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_PCTILE, 'parameters' => ['10'], 'alias' => '']
					],
					'filter' => [
						'evaltype' => 0,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_PCTILE, 'parameters' => ['5'], 'alias' => '']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'item.query.aggregated_columns[0]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.query.aggregated_columns[0].parameters[0]' => [CAudit::DETAILS_ACTION_UPDATE, '10', '5']
			]
		];
		yield '"item.query.aggregated_columns[0].function" change from "8" to "1"' => [
			CAudit::RESOURCE_ITEM,
			CAudit::ACTION_UPDATE,
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_MIN, 'alias' => '']
					],
					'filter' => [
						'evaltype' => 0,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'metric_point_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => '', 'function' => AGGREGATE_PCTILE, 'parameters' => ['10'], 'alias' => '']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => []
					]
				]
			],
			[
				'item.query.aggregated_columns[0]' => [CAudit::DETAILS_ACTION_UPDATE],
				'item.query.aggregated_columns[0].function' => [CAudit::DETAILS_ACTION_UPDATE, '1', '8'],
				'item.query.aggregated_columns[0].parameters[0]' => [CAudit::DETAILS_ACTION_DELETE]
			]
		];
	}

	/**
	 * @dataProvider dataProviderHandleObjectDiffItem
	 */
	public function testHandleObjectDiff(int $resource, int $action, array $object, array $db_object, array $expected) {
		static $closure;

		if ($closure === null) {
			$closure = Closure::bind(
				fn(int $resource, int $action, array $object, array $db_object): array => self::handleObjectDiff(
					$resource, $action, $object, $db_object
				),
				new CAudit(),
				CAudit::class
			);
		}

		$this->assertEqualsCanonicalizing($expected, $closure($resource, $action, $object, $db_object));
	}
}
