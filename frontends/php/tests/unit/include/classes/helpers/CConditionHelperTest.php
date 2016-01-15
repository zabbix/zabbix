<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CConditionHelperTest extends PHPUnit_Framework_TestCase {

	public function testGetFormulaProvider() {
		return [
			[
				[], CONDITION_EVAL_TYPE_AND, ''
			],

			// and
			[
				[
					1 => 'condition1'
				],
				CONDITION_EVAL_TYPE_AND, '{1}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3',
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2} and {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => 'condition1',
					2 => 'condition1',
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
				],
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and {3}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2',
				],
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and ({3} and {4})'
			],

			// or
			[
				[
					1 => 'condition1'
				],
				CONDITION_EVAL_TYPE_OR, '{1}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3',
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2} or {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => 'condition1',
					2 => 'condition1',
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
				],
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or {3}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2',
				],
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or ({3} or {4})'
			],

			// and/or
			[
				[
					1 => 'condition1'
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3',
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2} and {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => 'condition1',
					2 => 'condition1',
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} or {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
				],
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and {3}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2',
				],
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and ({3} or {4})'
			],
		];
	}

	/**
	 * @dataProvider testGetFormulaProvider
	 *
	 * @param array $conditions
	 * @param $evaltype
	 * @param $expectedFormula
	 */
	public function testGetFormula(array $conditions, $evaltype, $expectedFormula) {
		$formula = CConditionHelper::getFormula($conditions, $evaltype);

		$this->assertSame($expectedFormula, $formula);
	}

	public function testGetFormulaIdsProvider() {
		return [
			['', []],
			['1', [1 => 'A']],
			['1 and 2', [1 => 'A', 2 => 'B']],
			['1 and 2 and 1', [1 => 'A', 2 => 'B']],
			['(1 and 2) and 3', [1 => 'A', 2 => 'B', 3 => 'C']],
		];
	}

	/**
	 * @dataProvider testGetFormulaIdsProvider
	 *
	 * @param $formula
	 * @param array $expectedIds
	 */
	public function testGetFormulaIds($formula, array $expectedIds) {
		$ids = CConditionHelper::getFormulaIds($formula);

		$this->assertSame($ids, $expectedIds);
	}

	public function testReplaceNumericIdsProvider() {
		return [
			[
				'', [], ''
			],
			[
				'{1}', [1 => 'A'], 'A'
			],
			[
				'{1} and {2}', [1 => 'A', 2 => 'B'], 'A and B'
			],
			[
				'{1} and {2} or {3}', [1 => 'A', 2 => 'B', '3' => 'C'], 'A and B or C'
			],
			[
				'{1} and {2} or {1}', [1 => 'A', 2 => 'B'], 'A and B or A'
			],
		];
	}

	/**
	 * @dataProvider testReplaceNumericIdsProvider
	 *
	 * @param $formula
	 * @param array $ids
	 * @param $expectedFormula
	 */
	public function testReplaceNumericIds($formula, array $ids, $expectedFormula) {
		$generatedFormula = CConditionHelper::replaceNumericIds($formula, $ids);

		$this->assertSame($expectedFormula, $generatedFormula);
	}

	public function testReplaceLetterIdsProvider() {
		return [
			[
				'', [], ''
			],
			[
				'A', ['A' => 1], '{1}'
			],
			[
				'A and B', ['A' => 1, 'B' => 2], '{1} and {2}'
			],
			[
				'A and B or C', ['A' => 1, 'B' => 2, 'C' => 3], '{1} and {2} or {3}'
			],
			[
				'A and B or A', ['A' => 1, 'B' => 2], '{1} and {2} or {1}'
			],
			[
				'A and (B or AA)', ['A' => 1, 'B' => 2, 'AA' => 3], '{1} and ({2} or {3})'
			]
		];
	}

	/**
	 * @dataProvider testReplaceLetterIdsProvider
	 *
	 * @param $formula
	 * @param array $ids
	 * @param $expectedFormula
	 */
	public function testReplaceLetterIds($formula, array $ids, $expectedFormula) {
		$generatedFormula = CConditionHelper::replaceLetterIds($formula, $ids);

		$this->assertSame($expectedFormula, $generatedFormula);
	}

	/**
	 * @dataProvider testSortConditionsByFormulaIdProvider
	 *
	 * @param array $conditions
	 * @param array $expectedConditions
	 */
	public function testSortConditionsByFormulaId($conditions, $expectedConditions) {
		$sortedConditions = CConditionHelper::sortConditionsByFormulaId($conditions);

		$this->assertSame($expectedConditions, $sortedConditions);
	}

	/**
	 * @return array
	 */
	public function testSortConditionsByFormulaIdProvider() {
		return [
			[
				[0 => ['formulaid' => 'A'], 1 => ['formulaid' => 'B'], 2 => ['formulaid' => 'C']],
				[0 => ['formulaid' => 'A'], 1 => ['formulaid' => 'B'], 2 => ['formulaid' => 'C']]
			],
			[
				[2 => ['formulaid' => 'C'], 0 => ['formulaid' => 'A'], 1 => ['formulaid' => 'B']],
				[0 => ['formulaid' => 'A'], 1 => ['formulaid' => 'B'], 2 => ['formulaid' => 'C']]
			],
			[
				[2 => ['formulaid' => 'C'], 3 => ['formulaid' => 'D'], 0 => ['formulaid' => 'A']],
				[0 => ['formulaid' => 'A'], 2 => ['formulaid' => 'C'], 3 => ['formulaid' => 'D']]
			],
			[
				[2 => ['formulaid' => 'CC'], 3 => ['formulaid' => 'D'], 0 => ['formulaid' => 'AA']],
				[3 => ['formulaid' => 'D'], 0 => ['formulaid' => 'AA'], 2 => ['formulaid' => 'CC']]
			]
		];
	}

	/**
	 * @dataProvider testGetNextFormulaIdProvider
	 *
	 * @param array $formulaIds
	 * @param string $expectedFormulaId
	 */
	public function testGetNextFormulaId($formulaIds, $expectedFormulaId) {
		$nextFormulaId = CConditionHelper::getNextFormulaId($formulaIds);

		$this->assertSame($expectedFormulaId, $nextFormulaId);
	}

	/**
	 * @return array
	 */
	public function testGetNextFormulaIdProvider() {
		return [
			[
				[], 'A'
			],
			[
				['A', 'B', 'C'], 'D'
			],
			[
				['C', 'A', 'B'], 'D'
			],
			[
				['X', 'Y', 'Z'], 'AA'
			],
			[
				['AX', 'AY', 'AZ'], 'BA'
			],
			[
				['ZX', 'ZY', 'ZZ'], 'AAA'
			],
			[
				['AAX', 'AAY', 'AAZ'], 'ABA'
			],
			[
				['ZZZX', 'ZZZY', 'ZZZZ'], 'AAAAA'
			]
		];
	}
}
