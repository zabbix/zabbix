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
					1 => ['type' => 'type1']
				],
				CONDITION_EVAL_TYPE_AND, '{1}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type2'],
					3 => ['type' => 'type3']
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2} and {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1']
				],
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1'],
					3 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and {3}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1'],
					3 => ['type' => 'type2'],
					4 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and ({3} and {4})'
			],

			// or
			[
				[
					1 => ['type' => 'type1']
				],
				CONDITION_EVAL_TYPE_OR, '{1}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type2'],
					3 => ['type' => 'type3']
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2} or {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1']
				],
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1'],
					3 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or {3}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1'],
					3 => ['type' => 'type2'],
					4 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or ({3} or {4})'
			],

			// and/or
			[
				[
					1 => ['type' => 'type1']
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type2'],
					3 => ['type' => 'type3']
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2} and {3}'
			],
			// same conditions shouldn't have parentheses
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1']
				],
				CONDITION_EVAL_TYPE_AND_OR, '{1} or {2}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1'],
					3 => ['type' => 'type2']
				],
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and {3}'
			],
			[
				[
					1 => ['type' => 'type1'],
					2 => ['type' => 'type1'],
					3 => ['type' => 'type2'],
					4 => ['type' => 'type2']
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
		$formula = CConditionHelper::getEvalFormula($conditions, 'type', $evaltype);

		$this->assertSame($expectedFormula, $formula);
	}

	public function dataProviderAddFormulaIds() {
		return [
			['', []],
			['1', [1 => ['formulaid' => 'A']]],
			['1 and 2', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B']]],
			['1 and 2 and 1', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B']]],
			['(1 and 2) and 3', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B'], 3 => ['formulaid' => 'C']]]
		];
	}

	/**
	 * @dataProvider dataProviderAddFormulaIds
	 *
	 * @param $formula
	 * @param array $expectedIds
	 */
	public function testAddFormulaIds($formula, array $expected_conditions) {
		$conditions = [];
		CConditionHelper::addFormulaIds($conditions, $formula);

		$this->assertSame($expected_conditions, $conditions);
	}

	public function dataProviderReplaceNumericIds() {
		return [
			[
				'', [], ''
			],
			[
				'{1}', [1 => ['formulaid' => 'A']], 'A'
			],
			[
				'{1} and {2}', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B']], 'A and B'
			],
			[
				'{1} and {2} or {3}', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B'], '3' => ['formulaid' => 'C']], 'A and B or C'
			],
			[
				'{1} and {2} or {1}', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B']], 'A and B or A'
			]
		];
	}

	/**
	 * @dataProvider dataProviderReplaceNumericIds
	 *
	 * @param string $formula
	 * @param array  $conditions
	 * @param string $expected_formula
	 */
	public function testReplaceConditionIds(string $formula, array $conditions, string $expected_formula): void {
		CConditionHelper::replaceConditionIds($formula, $conditions);

		$this->assertSame($expected_formula, $formula);
	}

	public function dataProviderReplaceFormulaIds() {
		return [
			[
				'', [], ''
			],
			[
				'A', [1 => ['formulaid' => 'A']], '{1}'
			],
			[
				'A and B', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B']], '{1} and {2}'
			],
			[
				'A and B or C', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B'], 3 => ['formulaid' => 'C']], '{1} and {2} or {3}'
			],
			[
				'A and B or A', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B']], '{1} and {2} or {1}'
			],
			[
				'A and (B or AA)', [1 => ['formulaid' => 'A'], 2 => ['formulaid' => 'B'], 3 => ['formulaid' => 'AA']], '{1} and ({2} or {3})'
			]
		];
	}

	/**
	 * @dataProvider dataProviderReplaceFormulaIds
	 *
	 * @param $formula
	 * @param array $conditions
	 * @param $expectedFormula
	 */
	public function testReplaceFormulaIds($formula, array $conditions, $expectedFormula) {
		CConditionHelper::replaceFormulaIds($formula, $conditions);

		$this->assertSame($expectedFormula, $formula);
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
	 * @param string $formula
	 * @param array  $condtions
	 * @param array  $expected_conditions
	 */
	public function testSortConditionsByFormula($formula, array $conditions, array $expected_conditions): void {
		CConditionHelper::sortConditionsByFormula($conditions, $formula);

		$this->assertSame($expected_conditions, $conditions);
	}

	/**
	 * Data provider to test condition sorting.
	 *
	 * @return array
	 */
	public function dataProviderSortConditionsByFormula(): array {
		return [
			[
				'{102} or {101}',
				[
					101 => ['type' => '1'],
					102 => ['type' => '2']
				],
				[
					102 => ['type' => '2'],
					101 => ['type' => '1']
				]
			],
			[
				'{101} or {102} or {101}',
				[
					102 => ['type' => '2'],
					101 => ['type' => '1']
				],
				[
					101 => ['type' => '1'],
					102 => ['type' => '2']
				]
			],
			[
				'{101} and {102} and {103}',
				[
					101 => ['type' => '1'],
					102 => ['type' => '2'],
					103 => ['type' => '3']
				],
				[
					101 => ['type' => '1'],
					102 => ['type' => '2'],
					103 => ['type' => '3']
				]
			],
			[
				'{103} and {102} and {101}',
				[
					101 => ['type' => '1'],
					102 => ['type' => '2'],
					103 => ['type' => '3']
				],
				[
					103 => ['type' => '3'],
					102 => ['type' => '2'],
					101 => ['type' => '1']
				]
			],
			[
				'({104} or {105} or {106}) and ({103} or {101} or {102}) and ({107} and {108})',
				[
					101 => ['type' => '1'],
					102 => ['type' => '2'],
					103 => ['type' => '3'],
					104 => ['type' => '4'],
					105 => ['type' => '5'],
					106 => ['type' => '6'],
					107 => ['type' => '7'],
					108 => ['type' => '8']
				],
				[
					104 => ['type' => '4'],
					105 => ['type' => '5'],
					106 => ['type' => '6'],
					103 => ['type' => '3'],
					101 => ['type' => '1'],
					102 => ['type' => '2'],
					107 => ['type' => '7'],
					108 => ['type' => '8']
				]
			],
			[
				'({107} or {104} or {102} or {105} or {103} or {108} or {101} or {106} or {109} or '.
					'{110} or {115}) and ({127} or {120} or {123} or {126} or {117} or {113} or {125} or {124} or '.
					'{111} or {121} or {122} or {119} or {129} or {116} or {118} or {112} or {114}) or ({128} and '.
					'{130})',
				[
					101 => ['type' => '1'],
					102 => ['type' => '2'],
					103 => ['type' => '3'],
					104 => ['type' => '4'],
					105 => ['type' => '5'],
					106 => ['type' => '6'],
					107 => ['type' => '7'],
					108 => ['type' => '8'],
					109 => ['type' => '9'],
					110 => ['type' => '10'],
					111 => ['type' => '11'],
					112 => ['type' => '12'],
					113 => ['type' => '13'],
					114 => ['type' => '14'],
					115 => ['type' => '15'],
					116 => ['type' => '16'],
					117 => ['type' => '17'],
					118 => ['type' => '18'],
					119 => ['type' => '19'],
					120 => ['type' => '20'],
					121 => ['type' => '21'],
					122 => ['type' => '22'],
					123 => ['type' => '23'],
					124 => ['type' => '24'],
					125 => ['type' => '25'],
					126 => ['type' => '26'],
					127 => ['type' => '27'],
					128 => ['type' => '28'],
					129 => ['type' => '29'],
					130 => ['type' => '30']
				],
				[
					107 => ['type' => '7'],
					104 => ['type' => '4'],
					102 => ['type' => '2'],
					105 => ['type' => '5'],
					103 => ['type' => '3'],
					108 => ['type' => '8'],
					101 => ['type' => '1'],
					106 => ['type' => '6'],
					109 => ['type' => '9'],
					110 => ['type' => '10'],
					115 => ['type' => '15'],
					127 => ['type' => '27'],
					120 => ['type' => '20'],
					123 => ['type' => '23'],
					126 => ['type' => '26'],
					117 => ['type' => '17'],
					113 => ['type' => '13'],
					125 => ['type' => '25'],
					124 => ['type' => '24'],
					111 => ['type' => '11'],
					121 => ['type' => '21'],
					122 => ['type' => '22'],
					119 => ['type' => '19'],
					129 => ['type' => '29'],
					116 => ['type' => '16'],
					118 => ['type' => '18'],
					112 => ['type' => '12'],
					114 => ['type' => '14'],
					128 => ['type' => '28'],
					130 => ['type' => '30']
				]
			]
		];
	}
}
