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

	public static function dataProviderConvertKeysToPaths() {
		yield 'Flatten keys of telemetry item' => [
			'item',
			[
				'key_' => 'apm_metric',
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'ServiceName', 'attribute_key' => ''],
						['column' => 'ResourceAttributes', 'attribute_key' => 'field']
					],
					'aggregate_columns' => [
						['column' => 'Duration', 'function' => AGGREGATE_SUM, 'parameters' => [], 'alias' => 'duration_sum'],
						['column' => 'Duration', 'function' => AGGREGATE_PERCENTILE, 'parameters' => ['90.0'], 'alias' => 'duration_p90']
					],
					'filter' => [
						'evaltype' => 3,
						'formula' => '{0} or ({1} and {2} and {3})',
						'conditions' => [
							['column' => 'ResourceAttributes', 'attribute_key' => 'field', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'val1'],
							['column' => 'Events.Attributes', 'attribute_key' => 'attr1', 'operator' => CONDITION_OPERATOR_NOT_EQUAL, 'value' => 'v2'],
							['column' => 'Events.Attributes', 'attribute_key' => 'attr2', 'operator' => CONDITION_OPERATOR_EXISTS, 'value' => ''],
							['column' => 'ServiceName', 'attribute_key' => '', 'operator' => CONDITION_OPERATOR_LIKE, 'value' => 'b']
						]
					]
				]
			],
			[
				'item.key_' => 'apm_metric',
				'item.query.signal_type' => '0',
				'item.query.columns.0.column' => 'ServiceName',
				'item.query.columns.0.attribute_key' => '',
				'item.query.columns.1.column' => 'ResourceAttributes',
				'item.query.columns.1.attribute_key' => 'field',
				'item.query.aggregate_columns.0.column' => 'Duration',
				'item.query.aggregate_columns.0.function' => '5',
				'item.query.aggregate_columns.0.alias' => 'duration_sum',
				'item.query.aggregate_columns.1.column' => 'Duration',
				'item.query.aggregate_columns.1.function' => '8',
				'item.query.aggregate_columns.1.parameters.0' => '90.0',
				'item.query.aggregate_columns.1.alias' => 'duration_p90',
				'item.query.filter.evaltype' => '3',
				'item.query.filter.formula' => '{0} or ({1} and {2} and {3})',
				'item.query.filter.conditions.0.column' => 'ResourceAttributes',
				'item.query.filter.conditions.0.attribute_key' => 'field',
				'item.query.filter.conditions.0.operator' => '0',
				'item.query.filter.conditions.0.value' => 'val1',
				'item.query.filter.conditions.1.column' => 'Events.Attributes',
				'item.query.filter.conditions.1.attribute_key' => 'attr1',
				'item.query.filter.conditions.1.operator' => '1',
				'item.query.filter.conditions.1.value' => 'v2',
				'item.query.filter.conditions.2.column' => 'Events.Attributes',
				'item.query.filter.conditions.2.attribute_key' => 'attr2',
				'item.query.filter.conditions.2.operator' => '12',
				'item.query.filter.conditions.2.value' => '',
				'item.query.filter.conditions.3.column' => 'ServiceName',
				'item.query.filter.conditions.3.attribute_key' => '',
				'item.query.filter.conditions.3.operator' => '2',
				'item.query.filter.conditions.3.value' => 'b'
			]
		];
	}

	/**
	 * @dataProvider dataProviderConvertKeysToPaths
	 */
	public function testConvertKeysToPaths(string $path, array $object, array $expected) {
		static $closure = Closure::bind(
			fn(string $path, array $object): array => self::convertKeysToPaths($path, $object),
			new CAudit(),
			CAudit::class
		);

		$this->assertSame($expected, $closure($path, $object));
	}
}
