<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

class CConditionValidatorTest extends TestCase {

	public function invalidCompareSeveralTriggersWithAndProvider()
	{
		return [
			[
				[
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
							'formulaid' => 'A'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
							'formulaid' => 'B'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'C',
							'operator' => CONDITION_OPERATOR_EQUAL
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'D',
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND
				]
			]
		];
	}

	public function validCompareSeveralTriggersProvider()
	{
		return [
			[
				[
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'A',
							'operator' => CONDITION_OPERATOR_EQUAL
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'B',
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
					'formula' => 'A or B'
				]
			],
			[
				[
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
							'formulaid' => 'A'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
							'formulaid' => 'B'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'C',
							'operator' => CONDITION_OPERATOR_EQUAL
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'D',
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_OR
				]
			],
			[
				[
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
							'formulaid' => 'A'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
							'formulaid' => 'B'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST,
							'formulaid' => 'C'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'D',
							'operator' => CONDITION_OPERATOR_EQUAL
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND
				]
			],
			[
				[
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_SUPPRESSED,
							'formulaid' => 'A'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
							'formulaid' => 'B'
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'C',
							'operator' => CONDITION_OPERATOR_EQUAL
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'formulaid' => 'D',
							'operator' => CONDITION_OPERATOR_NOT_EQUAL
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND
				]
			]
		];
	}

	/**
	 * @dataProvider invalidCompareSeveralTriggersWithAndProvider
	 */
	public function testInvalidComparingSeveralTriggersWithAnd($object) {
		$conditionValidator = new CConditionValidator();

		$result = $conditionValidator->validate($object);

		$this->assertSame($result, false);
	}

	/**
	 * @dataProvider validCompareSeveralTriggersProvider
	 */
	public function testValidComparingSeveralTriggersWithAnd($object) {
		$conditionValidator = new CConditionValidator();

		$result = $conditionValidator->validate($object);

		$this->assertSame($result, true);
	}
}
