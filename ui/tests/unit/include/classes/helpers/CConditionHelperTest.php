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
		$sorted_conditions = CConditionHelper::sortConditionsByFormula($filter['conditions'], $filter['formula'],
			'conditionid'
		);

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
					'formula' => '{102} or {101}',
					'conditions' => [
						['conditionid' => '101'],
						['conditionid' => '102']
					]
				],
				[
					['conditionid' => '102'],
					['conditionid' => '101']
				]
			],
			[
				[
					'formula' => '{101} or {102} or {101}',
					'conditions' => [
						['conditionid' => '102'],
						['conditionid' => '101']
					]
				],
				[
					['conditionid' => '101'],
					['conditionid' => '102']
				]
			],
			[
				[
					'formula' => '{101} and {102} and {103}',
					'conditions' => [
						['conditionid' => '101'],
						['conditionid' => '102'],
						['conditionid' => '103']
					]
				],
				[
					['conditionid' => '101'],
					['conditionid' => '102'],
					['conditionid' => '103']
				]
			],
			[
				[
					'formula' => '{103} and {102} and {101}',
					'conditions' => [
						['conditionid' => '101'],
						['conditionid' => '102'],
						['conditionid' => '103']
					]
				],
				[
					['conditionid' => '103'],
					['conditionid' => '102'],
					['conditionid' => '101']
				]
			],
			[
				[
					'formula' => '({104} or {105} or {106}) and ({103} or {101} or {102}) and ({107} and {108})',
					'conditions' => [
						['conditionid' => '101'],
						['conditionid' => '102'],
						['conditionid' => '103'],
						['conditionid' => '104'],
						['conditionid' => '105'],
						['conditionid' => '106'],
						['conditionid' => '107'],
						['conditionid' => '108']
					]
				],
				[
					['conditionid' => '104'],
					['conditionid' => '105'],
					['conditionid' => '106'],
					['conditionid' => '103'],
					['conditionid' => '101'],
					['conditionid' => '102'],
					['conditionid' => '107'],
					['conditionid' => '108']
				]
			],
			[
				[
					'formula' => '({107} or {104} or {102} or {105} or {103} or {108} or {101} or {106} or {109} or '.
						'{110} or {115}) and ({127} or {120} or {123} or {126} or {117} or {113} or {125} or {124} or '.
						'{111} or {121} or {122} or {119} or {129} or {116} or {118} or {112} or {114}) or ({128} and '.
						'{130})',
					'conditions' => [
						['conditionid' => '101'],
						['conditionid' => '102'],
						['conditionid' => '103'],
						['conditionid' => '104'],
						['conditionid' => '105'],
						['conditionid' => '106'],
						['conditionid' => '107'],
						['conditionid' => '108'],
						['conditionid' => '109'],
						['conditionid' => '110'],
						['conditionid' => '111'],
						['conditionid' => '112'],
						['conditionid' => '113'],
						['conditionid' => '114'],
						['conditionid' => '115'],
						['conditionid' => '116'],
						['conditionid' => '117'],
						['conditionid' => '118'],
						['conditionid' => '119'],
						['conditionid' => '120'],
						['conditionid' => '121'],
						['conditionid' => '122'],
						['conditionid' => '123'],
						['conditionid' => '124'],
						['conditionid' => '125'],
						['conditionid' => '126'],
						['conditionid' => '127'],
						['conditionid' => '128'],
						['conditionid' => '129'],
						['conditionid' => '130']
					]
				],
				[
					['conditionid' => '107'],
					['conditionid' => '104'],
					['conditionid' => '102'],
					['conditionid' => '105'],
					['conditionid' => '103'],
					['conditionid' => '108'],
					['conditionid' => '101'],
					['conditionid' => '106'],
					['conditionid' => '109'],
					['conditionid' => '110'],
					['conditionid' => '115'],
					['conditionid' => '127'],
					['conditionid' => '120'],
					['conditionid' => '123'],
					['conditionid' => '126'],
					['conditionid' => '117'],
					['conditionid' => '113'],
					['conditionid' => '125'],
					['conditionid' => '124'],
					['conditionid' => '111'],
					['conditionid' => '121'],
					['conditionid' => '122'],
					['conditionid' => '119'],
					['conditionid' => '129'],
					['conditionid' => '116'],
					['conditionid' => '118'],
					['conditionid' => '112'],
					['conditionid' => '114'],
					['conditionid' => '128'],
					['conditionid' => '130']
				]
			]
		];
	}
}
