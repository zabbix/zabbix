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

class CSlaSchedulePeriodParserTest extends TestCase {

	/**
	 * An array of time periods and parsed results.
	 */
	public static function dataProvider() {
		return [
			// success
			[
				'00:00-23:00', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '00:00-23:00'
				]
			],
			[
				'07:23-23:23', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '07:23-23:23'
				]
			],
			[
				'23:59-24:00', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '23:59-24:00'
				]
			],
			[
				'8:00-17:00, 7:00-18:00', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '8:00-17:00, 7:00-18:00'
				]
			],
			[
				'8:00-17:00,7:00-18:00', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '8:00-17:00,7:00-18:00'
				]
			],
			[
				'8:00-17:00,   7:00-18:00', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '8:00-17:00,   7:00-18:00'
				]
			],
			[
				'  8:00-17:00  ,   7:00-18:00  ', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '8:00-17:00  ,   7:00-18:00'
				]
			],
			[
				'8:00-17:00, 7:00-18:00, 6:15-18:15, 5:30-9:47', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '8:00-17:00, 7:00-18:00, 6:15-18:15, 5:30-9:47'
				]
			],
			[
				'08:00-17:00, 07:00-18:00, 06:15-18:15, 5:30-19:47', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '08:00-17:00, 07:00-18:00, 06:15-18:15, 5:30-19:47'
				]
			],
			// fail
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'a', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1a', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1-', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:a', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:1', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11-', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11 ', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11- ', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11--', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11-1', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11-11', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11-11:', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11-11:1', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'11:11-11:10', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'00:00-24:01', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'12:00-11:59', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'00:00-24:0', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'00:0-24:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1, 00:0-24:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'7:00-9:00, 7:00-9:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'7:00-9:00, 07:00-09:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'23:00-25:61, 07:00-09:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
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
	public function testParse(string $source, int $pos, array $expected) {
		$parser = new CSlaSchedulePeriodParser();

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
