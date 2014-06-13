<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

}
