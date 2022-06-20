<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class CApiTagHelperTest extends TestCase {

	public static function provider() {
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
				'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM event_tag'.
					' WHERE'.
						' e.eventid=event_tag.eventid'.
						' AND event_tag.tag=\'OS\''.
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
			]
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, $expected) {
		global $DB;

		// zbx_dbstr() for ORACLE does not use DB specific functions
		$DB['TYPE'] = ZBX_DB_ORACLE;

		$this->assertSame($expected, call_user_func_array(['CApiTagHelper', 'addWhereCondition'], $params));
	}
}
