<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		return array(
			array(
				array(), CONDITION_EVAL_TYPE_AND, ''
			),

			// and
			array(
				array(
					1 => 'condition1'
				),
				CONDITION_EVAL_TYPE_AND, '{1}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition2',
				),
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3',
				),
				CONDITION_EVAL_TYPE_AND, '{1} and {2} and {3}'
			),
			// same conditions shouldn't have parentheses
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
				),
				CONDITION_EVAL_TYPE_AND, '{1} and {2}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
				),
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and {3}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2',
				),
				CONDITION_EVAL_TYPE_AND, '({1} and {2}) and ({3} and {4})'
			),

			// or
			array(
				array(
					1 => 'condition1'
				),
				CONDITION_EVAL_TYPE_OR, '{1}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition2',
				),
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3',
				),
				CONDITION_EVAL_TYPE_OR, '{1} or {2} or {3}'
			),
			// same conditions shouldn't have parentheses
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
				),
				CONDITION_EVAL_TYPE_OR, '{1} or {2}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
				),
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or {3}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2',
				),
				CONDITION_EVAL_TYPE_OR, '({1} or {2}) or ({3} or {4})'
			),

			// and/or
			array(
				array(
					1 => 'condition1'
				),
				CONDITION_EVAL_TYPE_AND_OR, '{1}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition2',
				),
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition2',
					3 => 'condition3',
				),
				CONDITION_EVAL_TYPE_AND_OR, '{1} and {2} and {3}'
			),
			// same conditions shouldn't have parentheses
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
				),
				CONDITION_EVAL_TYPE_AND_OR, '{1} or {2}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
				),
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and {3}'
			),
			array(
				array(
					1 => 'condition1',
					2 => 'condition1',
					3 => 'condition2',
					4 => 'condition2',
				),
				CONDITION_EVAL_TYPE_AND_OR, '({1} or {2}) and ({3} or {4})'
			),
		);
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
		return array(
			array('', array()),
			array('1', array(1 => 'A')),
			array('1 and 2', array(1 => 'A', 2 => 'B')),
			array('1 and 2 and 1', array(1 => 'A', 2 => 'B')),
			array('(1 and 2) and 3', array(1 => 'A', 2 => 'B', 3 => 'C')),
		);
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
		return array(
			array(
				'', array(), ''
			),
			array(
				'{1}', array(1 => 'A'), 'A'
			),
			array(
				'{1} and {2}', array(1 => 'A', 2 => 'B'), 'A and B'
			),
			array(
				'{1} and {2} or {3}', array(1 => 'A', 2 => 'B', '3' => 'C'), 'A and B or C'
			),
			array(
				'{1} and {2} or {1}', array(1 => 'A', 2 => 'B'), 'A and B or A'
			),
		);
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
		return array(
			array(
				'', array(), ''
			),
			array(
				'A', array('A' => 1), '{1}'
			),
			array(
				'A and B', array('A' => 1, 'B' => 2), '{1} and {2}'
			),
			array(
				'A and B or C', array('A' => 1, 'B' => 2, 'C' => 3), '{1} and {2} or {3}'
			),
			array(
				'A and B or A', array('A' => 1, 'B' => 2), '{1} and {2} or {1}'
			),
		);
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
		return array(
			array(
				array(0 => array('formulaid' => 'A'), 1 => array('formulaid' => 'B'), 2 => array('formulaid' => 'C')),
				array(0 => array('formulaid' => 'A'), 1 => array('formulaid' => 'B'), 2 => array('formulaid' => 'C'))
			),
			array(
				array(2 => array('formulaid' => 'C'), 0 => array('formulaid' => 'A'), 1 => array('formulaid' => 'B')),
				array(0 => array('formulaid' => 'A'), 1 => array('formulaid' => 'B'), 2 => array('formulaid' => 'C'))
			),
			array(
				array(2 => array('formulaid' => 'C'), 3 => array('formulaid' => 'D'), 0 => array('formulaid' => 'A')),
				array(0 => array('formulaid' => 'A'), 2 => array('formulaid' => 'C'), 3 => array('formulaid' => 'D'))
			),
			array(
				array(2 => array('formulaid' => 'CC'), 3 => array('formulaid' => 'D'), 0 => array('formulaid' => 'AA')),
				array(3 => array('formulaid' => 'D'), 0 => array('formulaid' => 'AA'), 2 => array('formulaid' => 'CC'))
			)
		);
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
		return array(
			array(
				array(), 'A'
			),
			array(
				array('A', 'B', 'C'), 'D'
			),
			array(
				array('C', 'A', 'B'), 'D'
			),
			array(
				array('X', 'Y', 'Z'), 'AA'
			),
			array(
				array('AX', 'AY', 'AZ'), 'BA'
			),
			array(
				array('ZX', 'ZY', 'ZZ'), 'AAA'
			),
			array(
				array('AAX', 'AAY', 'AAZ'), 'ABA'
			),
			array(
				array('ZZZX', 'ZZZY', 'ZZZZ'), 'AAAAA'
			)
		);
	}
}
