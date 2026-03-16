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

class CNumberParserTest extends TestCase {

	public function dataProvider() {
		$fail = [
			'rc' => CParser::PARSE_FAIL,
			'match' => '',
			'suffix' => null,
			'value' => 0.0
		];

		return [
			// Valid numbers.
			['11', 0, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '11', 'suffix' => null, 'value' => 11.0]],
			['11', 1, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '1', 'suffix' => null, 'value' => 1.0]],
			['11.1', 0, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '11.1', 'suffix' => null, 'value' => 11.1]],
			['-11', 0, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '-11', 'suffix' => null, 'value' => -11.0]],
			['.1', 0, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '.1', 'suffix' => null, 'value' => 0.1]],
			['3e10', 0, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '3e10', 'suffix' => null, 'value' => 30000000000.0]],
			['-3e10', 0, [],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '-3e10', 'suffix' => null, 'value' => -30000000000.0]
			],
			['3e-10', 0, [], ['rc' => CParser::PARSE_SUCCESS, 'match' => '3e-10', 'suffix' => null, 'value' => 3e-10]],
			['-3e-10', 0, [],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '-3e-10', 'suffix' => null, 'value' => -3e-10]
			],
			['.1e-10', 0, [],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '.1e-10', 'suffix' => null, 'value' => 1.0E-11]
			],

			// Number with size suffix.
			['11K', 0, ['with_size_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11K', 'suffix' => 'K', 'value' => 11264.0]
			],
			['11M', 0, ['with_size_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11M', 'suffix' => 'M', 'value' =>  11534336.0]
			],
			['11G', 0, ['with_size_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11G', 'suffix' => 'G', 'value' => 11811160064.0]
			],
			['11T', 0, ['with_size_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11T', 'suffix' => 'T', 'value' => 12094627905536.0]
			],
			['11K', 0, ['with_size_suffix' => true, 'is_binary_size' => false],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11K', 'suffix' => 'K', 'value' => 11000.0]
			],
			['11M', 0, ['with_size_suffix' => true, 'is_binary_size' => false],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11M', 'suffix' => 'M', 'value' =>  11000000.0]
			],
			['11G', 0, ['with_size_suffix' => true, 'is_binary_size' => false],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11G', 'suffix' => 'G', 'value' => 11000000000.0]
			],
			['11T', 0, ['with_size_suffix' => true, 'is_binary_size' => false],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11T', 'suffix' => 'T', 'value' => 11000000000000.0]
			],

			// Number with time suffix.
			['11s', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11s', 'suffix' => 's', 'value' => 11.0]
			],
			['11m', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11m', 'suffix' => 'm', 'value' => 660.0]
			],
			['11h', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11h', 'suffix' => 'h', 'value' => 39600.0]
			],
			['11d', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11d', 'suffix' => 'd', 'value' => 950400.0]
			],
			['11w', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11w', 'suffix' => 'w', 'value' => 6652800.0]
			],
			['11M', 0, ['with_time_suffix' => true, 'with_year' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11M', 'suffix' => 'M', 'value' => 28512000.0]
			],
			['11y', 0, ['with_time_suffix' => true, 'with_year' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '11y', 'suffix' => 'y', 'value' => 346896000.0]
			],

			// Macros.
			['{$MACRO}', 0, ['usermacros' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '{$MACRO}', 'suffix' => null, 'value' => 0.0]
			],
			['{#MACRO}', 0, ['lldmacros' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '{#MACRO}', 'suffix' => null, 'value' => 0.0]
			],
			['3{#MACRO}', 1, ['lldmacros' => true],
				['rc' => CParser::PARSE_SUCCESS, 'match' => '{#MACRO}', 'suffix' => null, 'value' => 0.0]
			],

			// Invalid number format.
			['-11', 0, ['with_minus' => false], $fail],
			['11.0', 0, ['with_float' => false],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '11', 'suffix' => null, 'value' => 11.0]
			],
			['1.0', 1, ['with_float' => false], $fail],

			// Invalid size suffix.
			['11Z', 0, [],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '11', 'suffix' => null, 'value' => 11.0]
			],
			['11M', 0, ['with_size_suffix' => false],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '11', 'suffix' => null, 'value' => 11.0]
			],

			// Invalid time suffix.
			['11s', 0, [],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '11', 'suffix' => null, 'value' => 11.0]
			],
			['11M', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '11', 'suffix' => null, 'value' => 11.0]
			],
			['11y', 0, ['with_time_suffix' => true],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '11', 'suffix' => null, 'value' => 11.0]
			],

			// Invalid or unsupported macro.
			['{HOST}', 0, [], $fail],
			['{#MACRO}', 0, [], $fail],
			['{#MACRO', 0, ['usermacros' => true], $fail],
			['{#MACRO}', 0, ['usermacros' => true], $fail],
			['{$MACRO}', 0, ['lldmacros' => true], $fail],
			['{$MACRO}}', 0, ['usermacros' => true],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '{$MACRO}', 'suffix' => null, 'value' => 0.0]
			],
			['{#MACRO}}', 0, ['lldmacros' => true],
				['rc' => CParser::PARSE_SUCCESS_CONT, 'match' => '{#MACRO}', 'suffix' => null, 'value' => 0.0]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $options, $expected) {
		$number_parser = new CNumberParser($options);

		$this->assertSame($expected, [
			'rc' => $number_parser->parse($source, $pos),
			'match' => $number_parser->getMatch(),
			'suffix' => $number_parser->getSuffix(),
			'value' => $number_parser->calcValue()
		]);
		$this->assertSame(strlen($expected['match']), $number_parser->getLength());
	}
}
