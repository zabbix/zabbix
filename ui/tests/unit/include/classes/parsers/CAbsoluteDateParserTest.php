<?php
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

class CAbsoluteDateParserTest extends TestCase {

	/**
	 * An array of absolute times and parsed results.
	 */
	public static function dataProvider() {
		return [
			[
				'2025-04-15', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-04-15'
				]
			],
			[
				'2025-04-5', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-04-5'
				]
			],
			[
				'2025-04-05', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-04-05'
				]
			],
			[
				'2025-4-5', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-4-5'
				]
			],
			[
				'2025-01', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-01'
				]
			],
			[
				'2025-3', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-3'
				]
			],
			[
				'2025', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025'
				]
			],
			[
				'2025-04-15', 0, ['min' => 10, 'max' => ZBX_MAX_DATE],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2025-04-15'
				]
			],
			[
				'2025-04-15', 0, ['min' => 10, 'max' => 1744578000],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'2037-04-15', 0, ['min' => ZBX_MAX_DATE],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1950-04-15', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'text', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'2025-02-30 12:45:34', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'2025-11-01 aaa', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'2055-11-01', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'202', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'20', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'2', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testParse(string $source, int $pos, array $options, array $expected) {
		$parser = new CAbsoluteDateParser($options);

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
