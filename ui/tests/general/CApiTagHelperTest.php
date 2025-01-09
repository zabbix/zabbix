<?php declare(strict_types = 0);
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


require_once __DIR__.'/../include/CTest.php';
require_once __DIR__.'/../../include/classes/core/CRegistryFactory.php';
require_once __DIR__.'/../../include/classes/api/API.php';
require_once __DIR__.'/../../include/classes/api/CApiServiceFactory.php';
require_once __DIR__.'/../../include/classes/api/helpers/CApiTagHelper.php';
require_once __DIR__.'/../../include/classes/api/services/CItemGeneral.php';
require_once __DIR__.'/../../include/classes/api/services/CItem.php';

class CApiTagHelperTest extends CTest {

	public static function provider(): array {
		$sql_args = [2 => 'e', 'event_tag', 'eventid'];

		return [
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND UPPER(event_tag.value)'.
						' LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value4'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value5'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],	// duplicate
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag1\''.
						' AND (UPPER(event_tag.value)'.
							' LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(event_tag.value)'.
							' LIKE \'%VALUE2%\' ESCAPE \'!\''.
							' OR event_tag.value=\'Value3\''.
						')'.
				')'.
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag2\''.
						' AND (event_tag.value=\'Value4\''.
							' OR UPPER(event_tag.value)'.
							' LIKE \'%VALUE5%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag3\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2'],
						['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value4'],
						['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value5'],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3']
					],
					TAG_EVAL_TYPE_OR
				] + $sql_args,
				'('.
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Tag1\''.
							' AND (UPPER(event_tag.value)'.
								' LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(event_tag.value)'.
								' LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR event_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Tag2\''.
							' AND (event_tag.value=\'Value4\''.
								' OR UPPER(event_tag.value)'.
								' LIKE \'%VALUE5%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Tag3\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND event_tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND event_tag.value=\'\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
						' AND event_tag.value=\'Value\''.
				')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''],
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Tag\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
						' AND event_tag.value=\'Chrome\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
						' AND ('.
							'event_tag.value=\'Chrome\''.
							' OR event_tag.value=\'Firefox\''.
						')'.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
				')'
			],
			[
				[
					[
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Chrome'],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Firefox']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'Browser\''.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $sql_args,
				'('.
					'NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
					')'.
					' OR EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
							' AND event_tag.value=\'Android\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'Browser', 'operator' => TAG_OPERATOR_NOT_EXISTS],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Android']
					],
					TAG_EVAL_TYPE_OR
				] + $sql_args,
				'('.
					'NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
					')'.
					' OR EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'OS\''.
							' AND event_tag.value=\'Android\''.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'Browser\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'OS\''.
						' AND UPPER(event_tag.value)'.
						' LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'OS', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'win']
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'tag1\''.
						' AND UPPER(event_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'OS\''.
						' AND UPPER(event_tag.value) LIKE \'%WIN%\' ESCAPE \'!\''.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'val'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'value'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'('.
					'NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
							' AND (event_tag.value=\'val\''.
								' OR UPPER(event_tag.value)'.
								' LIKE \'%VALUE%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
					')'.
				')'
			],
			[
				[
					[
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'val'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'value'],
						['tag' => 'tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS]
					],
					TAG_EVAL_TYPE_AND_OR
				] + $sql_args,
				'('.
					'EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
							' AND (event_tag.value=\'val\''.
								' OR UPPER(event_tag.value)'.
								' LIKE \'%VALUE%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						'SELECT NULL'.
						' FROM event_tag'.
						' WHERE'.
							' e.eventid=event_tag.eventid'.
							' AND event_tag.tag=\'tag1\''.
					')'.
				')'
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, string $expected): void {
		$this->assertSame($expected, call_user_func_array(['CApiTagHelper', 'addWhereCondition'], $params));
	}
}
