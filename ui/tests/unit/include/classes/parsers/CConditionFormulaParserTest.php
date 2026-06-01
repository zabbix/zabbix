<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

class CConditionFormulaParserTest extends TestCase {

	protected CConditionFormulaParser $condition_formula_parser;

	protected function setUp(): void {
		$this->condition_formula_parser = new CConditionFormulaParser();
	}

	public function dataProvider() {
		return [
			['', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'expression is empty',
				'constants' => []
			]],
			['  )', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near " )"',
				'constants' => []
			]],
			['A or not ', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'A ',
				'length' => 2,
				'error' => 'incorrect syntax near " "',
				'constants' => [
					['value' => 'A', 'pos' => 0]
				]
			]],
			['A or (not C', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'A ',
				'length' => 2,
				'error' => 'incorrect syntax near "C"',
				'constants' => [
					['value' => 'A', 'pos' => 0]
				]
			]],
			[' #A', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near " #A"',
				'constants' => []
			]],
			['A', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => 'A',
				'length' => 1,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 0]
				]
			]],
			['A and B', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => 'A and B',
				'length' => 7,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 0],
					['value' => 'B', 'pos' => 6]
				]
			]],
			['A or B', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => 'A or B',
				'length' => 6,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 0],
					['value' => 'B', 'pos' => 5]
				]
			]],
			['   not A ', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => '   not A ',
				'length' => 9,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 7]
				]
			]],
			['(A)', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => '(A)',
				'length' => 3,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 1]
				]
			]],
			['((A))', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => '((A))',
				'length' => 5,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 2]
				]
			]],
			['  A and (not B or C  )and(  not D and not E)   ', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => '  A and (not B or C  )and(  not D and not E)   ',
				'length' => 47,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 2],
					['value' => 'B', 'pos' => 13],
					['value' => 'C', 'pos' => 18],
					['value' => 'D', 'pos' => 32],
					['value' => 'E', 'pos' => 42]
				]
			]],
			['   (  (   A    or   B   )   )   and not     C   ', 3, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => '(  (   A    or   B   )   )   and not     C   ',
				'length' => 45,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 10],
					['value' => 'B', 'pos' => 20],
					['value' => 'C', 'pos' => 44]
				]
			]],
			['   (  (   A    or   B   )   )   and not     C   ', 4, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => '  (   A    or   B   )   ',
				'length' => 24,
				'error' => 'incorrect syntax near " )   and not     C   "',
				'constants' => [
					['value' => 'A', 'pos' => 10],
					['value' => 'B', 'pos' => 20]
				]
			]],
			['((A or B))) and not C', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => '((A or B))',
				'length' => 10,
				'error' => 'incorrect syntax near ")) and not C"',
				'constants' => [
					['value' => 'A', 'pos' => 2],
					['value' => 'B', 'pos' => 7]
				]
			]],
			['C or A and AB or', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'C or A and AB ',
				'length' => 14,
				'error' => 'incorrect syntax near "r"',
				'constants' => [
					['value' => 'C', 'pos' => 0],
					['value' => 'A', 'pos' => 5],
					['value' => 'AB', 'pos' => 11]
				]
			]],
			['C or (A and AB or)', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'C ',
				'length' => 2,
				'error' => 'incorrect syntax near "r)"',
				'constants' => [
					['value' => 'C', 'pos' => 0]
				]
			]],
			['not(not(A) or O and nt(A))', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near " nt(A))"',
				'constants' => []
			]],
			['A and B or A', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => 'A and B or A',
				'length' => 12,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 0],
					['value' => 'B', 'pos' => 6],
					['value' => 'A', 'pos' => 11]
				]
			]],
			['A and not B or C', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => 'A and not B or C',
				'length' => 16,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 0],
					['value' => 'B', 'pos' => 10],
					['value' => 'C', 'pos' => 15]
				]
			]],
			['A and NOT and B', 0, [
				'result' => CParser::PARSE_SUCCESS,
				'match' => 'A and NOT and B',
				'length' => 15,
				'error' => '',
				'constants' => [
					['value' => 'A', 'pos' => 0],
					['value' => 'NOT', 'pos' => 6],
					['value' => 'B', 'pos' => 14]
				]
			]],
			['not(not(not(not(not(A) or O and nt(A)))))', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near " nt(A)))))"',
				'constants' => []
			]],
			['not(A or A or A or A or A or A or A or A or A or A or B2 and E and E)', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near "B2 and E and E)"',
				'constants' => []
			]],
			['(A', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near "A"',
				'constants' => []
			]],
			['((A)', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near ")"',
				'constants' => []
			]],
			['A andnot B', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'A ',
				'length' => 2,
				'error' => 'incorrect syntax near "dnot B"',
				'constants' => [
					['value' => 'A', 'pos' => 0]
				]
			]],
			['A ornot B', 0, [
				'result' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'A ',
				'length' => 2,
				'error' => 'incorrect syntax near "rnot B"',
				'constants' => [
					['value' => 'A', 'pos' => 0]
				]
			]],
			['notA and B', 0, [
				'result' => CParser::PARSE_FAIL,
				'match' => '',
				'length' => 0,
				'error' => 'incorrect syntax near "tA and B"',
				'constants' => []
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testParseConstants(string $formula, int $pos, array $expected) {
		$result = $this->condition_formula_parser->parse($formula, $pos);

		$this->assertSame($expected, [
			'result' => $result,
			'match' => $this->condition_formula_parser->getMatch(),
			'length' => $this->condition_formula_parser->getLength(),
			'error' => $this->condition_formula_parser->getError(),
			'constants' => $this->condition_formula_parser->getConstants()
		]);
	}
}
