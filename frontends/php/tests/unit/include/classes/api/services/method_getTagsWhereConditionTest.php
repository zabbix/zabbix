<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class method_getTagsWhereConditionTest extends PHPUnit_Framework_TestCase {

	public static function provider() {
		return [
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				],
				'EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag\' AND UPPER(et.value) LIKE \'%VALUE%\' ESCAPE \'!\')'
			],
			[
				[
					[
						['tag' => 'Tag']
					],
					TAG_EVAL_TYPE_AND_OR
				],
				'EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag\')'
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
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3'],
					],
					TAG_EVAL_TYPE_AND_OR
				],
				'EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag1\' AND (UPPER(et.value) LIKE \'%VALUE1%\' ESCAPE \'!\' OR UPPER(et.value) LIKE \'%VALUE2%\' ESCAPE \'!\' OR et.value=\'Value3\'))'.
				' AND EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag2\' AND (et.value=\'Value4\' OR UPPER(et.value) LIKE \'%VALUE5%\' ESCAPE \'!\'))'.
				' AND EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag3\')'
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
						['tag' => 'Tag3', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value3'],
					],
					TAG_EVAL_TYPE_OR
				],
				'(EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag1\' AND (UPPER(et.value) LIKE \'%VALUE1%\' ESCAPE \'!\' OR UPPER(et.value) LIKE \'%VALUE2%\' ESCAPE \'!\' OR et.value=\'Value3\'))'.
				' OR EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag2\' AND (et.value=\'Value4\' OR UPPER(et.value) LIKE \'%VALUE5%\' ESCAPE \'!\'))'.
				' OR EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag3\'))'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
					],
					TAG_EVAL_TYPE_AND_OR
				],
				'EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag\' AND et.value=\'\')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL]
					],
					TAG_EVAL_TYPE_AND_OR
				],
				'EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag\' AND et.value=\'\')'
			],
			[
				[
					[
						['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
					],
					TAG_EVAL_TYPE_AND_OR
				],
				'EXISTS (SELECT NULL FROM event_tag et WHERE e.eventid=et.eventid AND et.tag=\'Tag\' AND et.value=\'Value\')'
			],
		];
	}

	/**
	 * @dataProvider provider
	 */
	public function test(array $params, $expected) {
		global $DB;

		// zbx_dbstr() for ORACLE does not use DB specific functions
		$DB['TYPE'] = ZBX_DB_ORACLE;

		$this->assertSame($expected, call_user_func_array(['CEvent', 'getTagsWhereCondition'], $params));
	}
}
