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
require_once __DIR__.'/../../include/classes/api/helpers/CApiTagHelper.php';

class CApiTagHelperTest extends CTest {

	private static function ownTagConditionProvider(): Generator {
		yield from self::ownTagConditions('e', 'event_tag', 'eventid');
		yield from self::ownTagConditions('h', 'host_tag', 'hostid');
		yield from self::ownTagConditions('ht', 'httptest_tag', 'httptestid');
		yield from self::ownTagConditions('i', 'item_tag', 'itemid');
		yield from self::ownTagConditions('t', 'trigger_tag', 'triggerid');
		yield from self::ownTagConditions('p', 'problem_tag', 'eventid');
		yield from self::ownTagConditions('s', 'service_tag', 'serviceid');
		yield from self::ownTagConditions('s', 'service_problem_tag', 'serviceid');
		yield from self::ownTagConditions('sla', 'sla_service_tag', 'slaid');
	}

	private static function ownTagConditions(string $parent_alias, string $tag_table, string $field): Generator {
		$test_case_name = 'Test own tag condition for "'.$tag_table.'"';
		$params = [[$parent_alias], $tag_table, $field];
		$select_tags_where =
			'SELECT NULL'.
			' FROM '.$tag_table.
			' WHERE '.$parent_alias.'.'.$field.'='.$tag_table.'.'.$field;

		// Single tag-value pairs.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		// Merge duplicate tags.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		// Convert one operator to another.

		yield $test_case_name.': AND_OR ["Tag" LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		// Use the EXISTS operator to override other operators with the same tag.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
				')'
		];

		// A tag equals an empty value.
		yield $test_case_name.': AND_OR ["Tag" EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value=\'\''.
				')'
		];

		// A tag does not equal an empty value.
		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value=\'\''.
				')'
		];

		// Two tags with the same name and operator but with different values.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value1", "Tag" LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND ('.
							'UPPER('.$tag_table.'.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER('.$tag_table.'.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value1", "Tag" EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value1", "Tag" NOT_LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND ('.
							'UPPER('.$tag_table.'.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER('.$tag_table.'.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value1", "Tag" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag\''.
						' AND '.$tag_table.'.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		// Two tags with the same name but different operators and values.

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
					')'.
				')'
		];

		// Two tags with the same operator (and value) but with different names.

		yield $test_case_name.': AND_OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
							' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'.
				' AND EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
						' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
							' AND UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
						' AND '.$tag_table.'.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
				')'.
				' AND EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
					')'.
				')'
		];

		// Custom cases.

		yield $test_case_name.': AND_OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag1\''.
						' AND ('.
							'UPPER('.$tag_table.'.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER('.$tag_table.'.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							' OR '.$tag_table.'.value=\'Value3\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
						' AND ('.
							'UPPER('.$tag_table.'.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							' OR '.$tag_table.'.value=\'Value4\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag3\''.
				')'
		];

		yield $test_case_name.': OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND ('.
								'UPPER('.$tag_table.'.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER('.$tag_table.'.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR '.$tag_table.'.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
							' AND ('.
								'UPPER('.$tag_table.'.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR '.$tag_table.'.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Val", "Tag" LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
							' AND ('.
								'UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR '.$tag_table.'.value=\'Val\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Val", "Tag" NOT_LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag\''.
							' AND ('.
								'UPPER('.$tag_table.'.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR '.$tag_table.'.value=\'Val\''.
							')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS, "Tag1" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND '.$tag_table.'.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag1" NOT_EQUAL "Value3", "Tag2" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value3"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND '.$tag_table.'.value=\'Value1\''.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND '.$tag_table.'.value IN (\'Value2\',\'Value3\')'.
					')'.
				')'.
				' AND NOT EXISTS ('.
					$select_tags_where.
						' AND '.$tag_table.'.tag=\'Tag2\''.
						' AND '.$tag_table.'.value IN (\'Value2\',\'Value3\')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value1", "Tag2" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag1\''.
							' AND '.$tag_table.'.value IN (\'Value1\',\'Value2\')'.
					')'.
					' OR NOT EXISTS ('.
						$select_tags_where.
							' AND '.$tag_table.'.tag=\'Tag2\''.
							' AND '.$tag_table.'.value IN (\'Value1\',\'Value2\')'.
					')'.
				')'
		];
	}

	private static function ownAndInheritedTagConditionProvider(): Generator {
		yield from self::ownAndInheritedTagConditionsForHost();
		yield from self::ownAndInheritedTagConditionsForHttptest();
		yield from self::ownAndInheritedTagConditionsForItem();
		yield from self::ownAndInheritedTagConditionsForTrigger();
	}

	private static function ownAndInheritedTagConditionsForHost(): Generator {
		$test_case_name = 'Test host and inherited tag condition';
		$params = [['h'], 'host_tag', 'hostid', true];
		$select_host_inherited_tags_where =
			'SELECT NULL'.
			' FROM host_template_cache htc'.
			' JOIN host_tag ON htc.link_hostid=host_tag.hostid'.
			' WHERE h.hostid=htc.hostid';

		// Single tag-value pairs.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Merge duplicate tags.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Convert one operator to another.

		yield $test_case_name.': AND_OR ["Tag" LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Use the EXISTS operator to override other operators with the same tag.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// A tag equals an empty value.
		yield $test_case_name.': AND_OR ["Tag" EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'\''.
				')'
		];

		// A tag does not equal an empty value.
		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'\''.
				')'
		];

		// Two tags with the same name and operator but with different values.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value1", "Tag" LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value1", "Tag" EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value1", "Tag" NOT_LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value1", "Tag" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		// Two tags with the same name but different operators and values.

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		// Two tags with the same operator (and value) but with different names.

		yield $test_case_name.': AND_OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND host_tag.value=\'Value\''.
				')'.
				' AND EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND host_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
				')'.
				' AND EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		// Custom cases.

		yield $test_case_name.': AND_OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							' OR host_tag.value=\'Value3\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
							' OR host_tag.value=\'Value4\''.
						')'.
				')'.
				' AND EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag3\''.
				')'
		];

		yield $test_case_name.': OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Val", "Tag" LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Val\''.
							')'.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Val", "Tag" NOT_LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Val\''.
							')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS, "Tag1" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag1" NOT_EQUAL "Value3", "Tag2" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value3"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value1\''.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value IN (\'Value2\',\'Value3\')'.
					')'.
				')'.
				' AND NOT EXISTS ('.
					$select_host_inherited_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value IN (\'Value2\',\'Value3\')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value1", "Tag2" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
					' OR NOT EXISTS ('.
						$select_host_inherited_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
				')'
		];
	}

	private static function ownAndInheritedTagConditionsForHttptest(): Generator {
		$test_case_name = 'Test httptest and inherited tag condition';
		$params = [['ht', 'hti'], 'httptest_tag', 'httptestid', true];
		$select_httptest_tags_where =
			'SELECT NULL'.
			' FROM httptest_tag'.
			' WHERE ht.httptestid=httptest_tag.httptestid';
		$select_httptest_inherited_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE hti.itemid=itc.itemid';
		$select_httptest_inherited_host_tags_where_not =
			'SELECT NULL'.
			' FROM httptestitem'.
			' JOIN item_template_cache itc ON httptestitem.itemid=itc.itemid'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE ht.httptestid=httptestitem.httptestid';

		// Single tag-value pairs.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND httptest_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Merge duplicate tags.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND httptest_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Convert one operator to another.

		yield $test_case_name.': AND_OR ["Tag" LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Use the EXISTS operator to override other operators with the same tag.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		// A tag equals an empty value.
		yield $test_case_name.': AND_OR ["Tag" EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND httptest_tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'\''.
					')'.
				')'
		];

		// A tag does not equal an empty value.
		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND httptest_tag.value=\'\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'\''.
				')'
		];

		// Two tags with the same name and operator but with different values.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value1", "Tag" LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(httptest_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(httptest_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value1", "Tag" EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND httptest_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value1", "Tag" NOT_LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(httptest_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(httptest_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value1", "Tag" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag\''.
						' AND httptest_tag.value IN (\'Value1\',\'Value2\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		// Two tags with the same name but different operators and values.

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		// Two tags with the same operator (and value) but with different names.

		yield $test_case_name.': AND_OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
							' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
							' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag1\''.
						' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag1\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag2\''.
						' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag1\''.
								' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag2\''.
								' AND UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
								' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag1\''.
						' AND httptest_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag1\''.
						' AND host_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag2\''.
						' AND httptest_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag1\''.
								' AND httptest_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value=\'Value\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag2\''.
								' AND httptest_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
								' AND host_tag.value=\'Value\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag2\''.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
						')'.
					')'.
				')'
		];

		// Custom cases.

		yield $test_case_name.': AND_OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(httptest_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(httptest_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR httptest_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(httptest_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR httptest_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(httptest_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(httptest_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR httptest_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(httptest_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR httptest_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Val", "Tag" LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR httptest_tag.value=\'Val\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Val\''.
							')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Val", "Tag" NOT_LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(httptest_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR httptest_tag.value=\'Val\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR host_tag.value=\'Val\''.
								')'.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS, "Tag1" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND httptest_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag1" NOT_EQUAL "Value3", "Tag2" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value3"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_httptest_tags_where.
							' AND httptest_tag.tag=\'Tag1\''.
							' AND httptest_tag.value=\'Value1\''.
					')'.
					' OR EXISTS ('.
						$select_httptest_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value1\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag1\''.
								' AND httptest_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
					')'.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_tags_where.
						' AND httptest_tag.tag=\'Tag2\''.
						' AND httptest_tag.value IN (\'Value2\',\'Value3\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_httptest_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value IN (\'Value2\',\'Value3\')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value1", "Tag2" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag1\''.
								' AND httptest_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_httptest_tags_where.
								' AND httptest_tag.tag=\'Tag2\''.
								' AND httptest_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_httptest_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
								' AND host_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
					')'.
				')'
		];
	}

	private static function ownAndInheritedTagConditionsForItem(): Generator {
		$test_case_name = 'Test item and inherited tag condition';
		$params = [['i'], 'item_tag', 'itemid', true];
		$select_item_tags_where =
			'SELECT NULL'.
			' FROM item_tag'.
			' WHERE i.itemid=item_tag.itemid';
		$select_item_inherited_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE i.itemid=itc.itemid';

		// Single tag-value pairs.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Merge duplicate tags.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Convert one operator to another.

		yield $test_case_name.': AND_OR ["Tag" LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Use the EXISTS operator to override other operators with the same tag.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		// A tag equals an empty value.
		yield $test_case_name.': AND_OR ["Tag" EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'\''.
					')'.
				')'
		];

		// A tag does not equal an empty value.
		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value=\'\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'\''.
				')'
		];

		// Two tags with the same name and operator but with different values.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value1", "Tag" LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value1", "Tag" EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value1", "Tag" NOT_LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value1", "Tag" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value IN (\'Value1\',\'Value2\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		// Two tags with the same name but different operators and values.

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		// Two tags with the same operator (and value) but with different names.

		yield $test_case_name.': AND_OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag1\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag2\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag1\''.
								' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag1\''.
								' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag2\''.
								' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag2\''.
								' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag1\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag1\''.
						' AND host_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag2\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag1\''.
								' AND item_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value=\'Value\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag2\''.
								' AND item_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag2\''.
								' AND host_tag.value=\'Value\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag2\''.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag1\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag2\''.
						')'.
					')'.
				')'
		];

		// Custom cases.

		yield $test_case_name.': AND_OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Val", "Tag" LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Val\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Val\''.
							')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Val", "Tag" NOT_LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR item_tag.value=\'Val\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR host_tag.value=\'Val\''.
								')'.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS, "Tag1" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag1\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag2\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag1" NOT_EQUAL "Value3", "Tag2" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value3"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value1\''.
					')'.
					' OR EXISTS ('.
						$select_item_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value1\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag1\''.
								' AND item_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
					')'.
				')'.
				' AND NOT EXISTS ('.
					$select_item_tags_where.
						' AND item_tag.tag=\'Tag2\''.
						' AND item_tag.value IN (\'Value2\',\'Value3\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_item_inherited_host_tags_where.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value IN (\'Value2\',\'Value3\')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value1", "Tag2" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag1\''.
								' AND item_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_item_tags_where.
								' AND item_tag.tag=\'Tag2\''.
								' AND item_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_item_inherited_host_tags_where.
								' AND host_tag.tag=\'Tag2\''.
								' AND host_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
					')'.
				')'
		];
	}

	private static function ownAndInheritedTagConditionsForTrigger(): Generator {
		$test_case_name = 'Test trigger and inherited tag condition';
		$params = [['t', 'f'], 'trigger_tag', 'triggerid', true];
		$select_trigger_tags_where =
			'SELECT NULL'.
			' FROM trigger_tag'.
			' WHERE t.triggerid=trigger_tag.triggerid';
		$select_trigger_inherited_item_tags_where =
			'SELECT NULL'.
			' FROM item_tag'.
			' WHERE f.itemid=item_tag.itemid';
		$select_trigger_inherited_item_tags_where_not =
			'SELECT NULL'.
			' FROM functions'.
			' JOIN item_tag ON functions.itemid=item_tag.itemid'.
			' WHERE t.triggerid=functions.triggerid';
		$select_trigger_inherited_host_tags_where =
			'SELECT NULL'.
			' FROM item_template_cache itc'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE f.itemid=itc.itemid';
		$select_trigger_inherited_host_tags_where_not =
			'SELECT NULL'.
			' FROM functions'.
			' JOIN item_template_cache itc ON functions.itemid=itc.itemid'.
			' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
			' WHERE t.triggerid=functions.triggerid';

		// Single tag-value pairs.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND trigger_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Merge duplicate tags.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND trigger_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Convert one operator to another.

		yield $test_case_name.': AND_OR ["Tag" LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
				')'
		];

		// Use the EXISTS operator to override other operators with the same tag.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value", "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EXISTS, "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
					')'.
				')'
		];

		// A tag equals an empty value.
		yield $test_case_name.': AND_OR ["Tag" EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND trigger_tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'\''.
					')'.
				')'
		];

		// A tag does not equal an empty value.
		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL ""].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => '']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND trigger_tag.value=\'\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value=\'\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value=\'\''.
				')'
		];

		// Two tags with the same name and operator but with different values.

		yield $test_case_name.': AND_OR ["Tag" LIKE "Value1", "Tag" LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(trigger_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(trigger_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
							')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Value1", "Tag" EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND trigger_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value IN (\'Value1\',\'Value2\')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_LIKE "Value1", "Tag" NOT_LIKE "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(trigger_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(trigger_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND ('.
							'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
							' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
						')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Value1", "Tag" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag\''.
						' AND trigger_tag.value IN (\'Value1\',\'Value2\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag\''.
						' AND item_tag.value IN (\'Value1\',\'Value2\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag\''.
						' AND host_tag.value IN (\'Value1\',\'Value2\')'.
				')'
		];

		// Two tags with the same name but different operators and values.

		yield $test_case_name.': AND_OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag" NOT_EXISTS, "Tag" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		// Two tags with the same operator (and value) but with different names.

		yield $test_case_name.': AND_OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
							' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" LIKE "Value", "Tag2" LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
							' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EQUAL "Value", "Tag2" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND host_tag.value=\'Value\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag1\''.
						' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag1\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag1\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag2\''.
						' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag2\''.
						' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_LIKE "Value", "Tag2" NOT_LIKE "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag1\''.
								' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag1\''.
								' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag2\''.
								' AND UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag2\''.
								' AND UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
								' AND UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag1\''.
						' AND trigger_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag1\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag1\''.
						' AND host_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag2\''.
						' AND trigger_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag2\''.
						' AND item_tag.value=\'Value\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value=\'Value\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value", "Tag2" NOT_EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag1\''.
								' AND trigger_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag1\''.
								' AND item_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value=\'Value\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag2\''.
								' AND trigger_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag2\''.
								' AND item_tag.value=\'Value\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
								' AND host_tag.value=\'Value\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" EXISTS, "Tag2" EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag1\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag2\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag2\''.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
						')'.
					')'.
				')'
		];

		// Custom cases.

		yield $test_case_name.': AND_OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(trigger_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(trigger_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR trigger_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(trigger_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR trigger_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
				')'.
				' AND ('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': OR [3 tags LIKE/EQUAL different values].' => [
			'params' => [
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
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(trigger_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(trigger_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR trigger_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(item_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE1%\' ESCAPE \'!\''.
								' OR UPPER(host_tag.value) LIKE \'%VALUE2%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value3\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(trigger_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR trigger_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag2\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE5%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Value4\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag3\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag3\''.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" EQUAL "Val", "Tag" LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR trigger_tag.value=\'Val\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR item_tag.value=\'Val\''.
							')'.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag\''.
							' AND ('.
								'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
								' OR host_tag.value=\'Val\''.
							')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag" NOT_EQUAL "Val", "Tag" NOT_LIKE "Value", "Tag" NOT_EXISTS].' => [
			'params' => [
				[
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Val'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_LIKE, 'value' => 'Value'],
					['tag' => 'Tag', 'operator' => TAG_OPERATOR_NOT_EXISTS]
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(trigger_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR trigger_tag.value=\'Val\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(item_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR item_tag.value=\'Val\''.
								')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag\''.
								' AND ('.
									'UPPER(host_tag.value) LIKE \'%VALUE%\' ESCAPE \'!\''.
									' OR host_tag.value=\'Val\''.
								')'.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EXISTS, "Tag2" NOT_EXISTS, "Tag1" EQUAL "Value"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EXISTS],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND trigger_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag1\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag2\''.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
						')'.
					')'.
				')'
		];

		yield $test_case_name.': AND_OR ["Tag1" EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag1" NOT_EQUAL "Value3", "Tag2" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value3"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value3']
				],
				TAG_EVAL_TYPE_AND_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'EXISTS ('.
						$select_trigger_tags_where.
							' AND trigger_tag.tag=\'Tag1\''.
							' AND trigger_tag.value=\'Value1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_item_tags_where.
							' AND item_tag.tag=\'Tag1\''.
							' AND item_tag.value=\'Value1\''.
					')'.
					' OR EXISTS ('.
						$select_trigger_inherited_host_tags_where.
							' AND host_tag.tag=\'Tag1\''.
							' AND host_tag.value=\'Value1\''.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag1\''.
								' AND trigger_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag1\''.
								' AND item_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value IN (\'Value2\',\'Value3\')'.
						')'.
					')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_tags_where.
						' AND trigger_tag.tag=\'Tag2\''.
						' AND trigger_tag.value IN (\'Value2\',\'Value3\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_item_tags_where_not.
						' AND item_tag.tag=\'Tag2\''.
						' AND item_tag.value IN (\'Value2\',\'Value3\')'.
				')'.
				' AND NOT EXISTS ('.
					$select_trigger_inherited_host_tags_where_not.
						' AND host_tag.tag=\'Tag2\''.
						' AND host_tag.value IN (\'Value2\',\'Value3\')'.
				')'
		];

		yield $test_case_name.': OR ["Tag1" NOT_EQUAL "Value1", "Tag1" NOT_EQUAL "Value2", "Tag2" NOT_EQUAL "Value1", "Tag2" NOT_EQUAL "Value2"].' => [
			'params' => [
				[
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag1', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value1'],
					['tag' => 'Tag2', 'operator' => TAG_OPERATOR_NOT_EQUAL, 'value' => 'Value2']
				],
				TAG_EVAL_TYPE_OR,
				...$params
			],
			'expected_result' =>
				'('.
					'('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag1\''.
								' AND trigger_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag1\''.
								' AND item_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag1\''.
								' AND host_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
					')'.
					' OR ('.
						'NOT EXISTS ('.
							$select_trigger_tags_where.
								' AND trigger_tag.tag=\'Tag2\''.
								' AND trigger_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_item_tags_where_not.
								' AND item_tag.tag=\'Tag2\''.
								' AND item_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
						' AND NOT EXISTS ('.
							$select_trigger_inherited_host_tags_where_not.
								' AND host_tag.tag=\'Tag2\''.
								' AND host_tag.value IN (\'Value1\',\'Value2\')'.
						')'.
					')'.
				')'
		];
	}

	/**
	 * @dataProvider ownTagConditionProvider
	 * @dataProvider ownAndInheritedTagConditionProvider
	 */
	public function test(array $params, string $expected): void {
		$this->assertSame($expected, call_user_func_array(['CApiTagHelper', 'getTagCondition'], $params));
	}
}
