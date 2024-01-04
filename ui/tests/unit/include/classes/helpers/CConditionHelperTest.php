<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

class CConditionHelperTest extends TestCase {

	public function dataProviderGetFormula() {
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
					2 => 'condition2'
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3'
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2} and {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => 'condition1',
					2 => 'condition1'
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2'
				],
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and {3}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2'
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
					2 => 'condition2'
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3'
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2} or {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => 'condition1',
					2 => 'condition1'
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2'
				],
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or {3}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2'
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
					2 => 'condition2'
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3'
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2} and {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => 'condition1',
					2 => 'condition1'
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} or {2}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2'
				],
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and {3}'
			],
			[
				[
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2'
				],
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and ({3} or {4})'
			]
		];
	}

	/**
	 * @dataProvider dataProviderGetFormula
	 *
	 * @param array $conditions
	 * @param $evaltype
	 * @param $expectedFormula
	 */
	public function testGetFormula(array $conditions, $evaltype, $expectedFormula) {
		$formula = CConditionHelper::getFormula($conditions, $evaltype);

		$this->assertSame($expectedFormula, $formula);
	}

	public function dataProviderGetFormulaIds() {
		return [
			['', []],
			['1', [1 => 'A']],
			['1 and 2', [1 => 'A', 2 => 'B']],
			['1 and 2 and 1', [1 => 'A', 2 => 'B']],
			['(1 and 2) and 3', [1 => 'A', 2 => 'B', 3 => 'C']]
		];
	}

	/**
	 * @dataProvider dataProviderGetFormulaIds
	 *
	 * @param $formula
	 * @param array $expectedIds
	 */
	public function testGetFormulaIds($formula, array $expectedIds) {
		$ids = CConditionHelper::getFormulaIds($formula);

		$this->assertSame($ids, $expectedIds);
	}

	public function dataProviderReplaceNumericIds() {
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
			]
		];
	}

	/**
	 * @dataProvider dataProviderReplaceNumericIds
	 *
	 * @param $formula
	 * @param array $ids
	 * @param $expectedFormula
	 */
	public function testReplaceNumericIds($formula, array $ids, $expectedFormula) {
		$generatedFormula = CConditionHelper::replaceNumericIds($formula, $ids);

		$this->assertSame($expectedFormula, $generatedFormula);
	}

	public function dataProviderReplaceLetterIds() {
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
	 * @dataProvider dataProviderReplaceLetterIds
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
	 * @dataProvider dataProviderSortConditionsByFormulaId
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
	public function dataProviderSortConditionsByFormulaId() {
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
	 * @dataProvider dataProviderGetNextFormulaId
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
	public function dataProviderGetNextFormulaId() {
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

	/**
	 * Test if conditions are correctly sorted based on given formula.
	 *
	 * @dataProvider dataProviderSortConditionsByFormula
	 *
	 * @param array $filter
	 * @param array $expected_conditions
	 */
	public function testSortConditionsByFormula(array $filter, array $expected_conditions): void {
		$sorted_conditions = CConditionHelper::sortConditionsByFormula($filter);

		$this->assertSame($expected_conditions, $sorted_conditions);
	}

	/**
	 * Data provider to test condition sorting.
	 *
	 * @return array
	 */
	public function dataProviderSortConditionsByFormula(): array {
		return [
			[
				[
					'formula' => 'B or A',
					'conditions' => [
						['formulaid' => 'A'],
						['formulaid' => 'B']
					]
				],
				[
					['formulaid' => 'B'],
					['formulaid' => 'A']
				]
			],
			[
				[
					'formula' => 'A and B and C',
					'conditions' => [
						['formulaid' => 'A'],
						['formulaid' => 'B'],
						['formulaid' => 'C']
					]
				],
				[
					['formulaid' => 'A'],
					['formulaid' => 'B'],
					['formulaid' => 'C']
				]
			],
			[
				[
					'formula' => 'C and B and A',
					'conditions' => [
						['formulaid' => 'A'],
						['formulaid' => 'B'],
						['formulaid' => 'C']
					]
				],
				[
					['formulaid' => 'C'],
					['formulaid' => 'B'],
					['formulaid' => 'A']
				]
			],
			[
				[
					'formula' => '(D or E or F) and (C or A or B) and (G and H)',
					'conditions' => [
						['formulaid' => 'A'],
						['formulaid' => 'B'],
						['formulaid' => 'C'],
						['formulaid' => 'D'],
						['formulaid' => 'E'],
						['formulaid' => 'F'],
						['formulaid' => 'G'],
						['formulaid' => 'H']
					]
				],
				[
					['formulaid' => 'D'],
					['formulaid' => 'E'],
					['formulaid' => 'F'],
					['formulaid' => 'C'],
					['formulaid' => 'A'],
					['formulaid' => 'B'],
					['formulaid' => 'G'],
					['formulaid' => 'H']
				]
			],
			[
				[
					'formula' => '(G or D or B or E or C or H or A or F or I or J or O) and (AA or T or W or Z or Q or'.
						' M or Y or X or K or U or V or S or AC or P or R or L or N) or (AB and AD)',
					'conditions' => [
						['formulaid' => 'A'],
						['formulaid' => 'B'],
						['formulaid' => 'C'],
						['formulaid' => 'D'],
						['formulaid' => 'E'],
						['formulaid' => 'F'],
						['formulaid' => 'G'],
						['formulaid' => 'H'],
						['formulaid' => 'I'],
						['formulaid' => 'J'],
						['formulaid' => 'K'],
						['formulaid' => 'L'],
						['formulaid' => 'M'],
						['formulaid' => 'N'],
						['formulaid' => 'O'],
						['formulaid' => 'P'],
						['formulaid' => 'Q'],
						['formulaid' => 'R'],
						['formulaid' => 'S'],
						['formulaid' => 'T'],
						['formulaid' => 'U'],
						['formulaid' => 'V'],
						['formulaid' => 'W'],
						['formulaid' => 'X'],
						['formulaid' => 'Y'],
						['formulaid' => 'Z'],
						['formulaid' => 'AA'],
						['formulaid' => 'AB'],
						['formulaid' => 'AC'],
						['formulaid' => 'AD']
					]
				],
				[
					['formulaid' => 'G'],
					['formulaid' => 'D'],
					['formulaid' => 'B'],
					['formulaid' => 'E'],
					['formulaid' => 'C'],
					['formulaid' => 'H'],
					['formulaid' => 'A'],
					['formulaid' => 'F'],
					['formulaid' => 'I'],
					['formulaid' => 'J'],
					['formulaid' => 'O'],
					['formulaid' => 'AA'],
					['formulaid' => 'T'],
					['formulaid' => 'W'],
					['formulaid' => 'Z'],
					['formulaid' => 'Q'],
					['formulaid' => 'M'],
					['formulaid' => 'Y'],
					['formulaid' => 'X'],
					['formulaid' => 'K'],
					['formulaid' => 'U'],
					['formulaid' => 'V'],
					['formulaid' => 'S'],
					['formulaid' => 'AC'],
					['formulaid' => 'P'],
					['formulaid' => 'R'],
					['formulaid' => 'L'],
					['formulaid' => 'N'],
					['formulaid' => 'AB'],
					['formulaid' => 'AD']
				]
			]
		];
	}
}
