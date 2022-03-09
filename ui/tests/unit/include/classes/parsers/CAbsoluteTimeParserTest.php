<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

class CAbsoluteTimeParserTest extends TestCase {

	/**
	 * An array of absolute times and parsed results.
	 */
	public static function dataProvider() {
		return [
			[
				'2018-04-15 12:0', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:0'
				],
				'datetime' => ['values' => ['2018-04-15 12:00:00', '2018-04-15 12:00:59']]
			],
			[
				'2018-04-15 12:0:0', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:0:0'
				],
				'datetime' => ['values' => ['2018-04-15 12:00:00', '2018-04-15 12:00:00']]
			],
			[
				'2018-04-15 0:0:0', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 0:0:0'
				],
				'datetime' => ['values' => ['2018-04-15 00:00:00', '2018-04-15 00:00:00']]
			],
			[
				'texttexttext2018-04-15 0:0:0', 12,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 0:0:0'
				],
				'datetime' => ['values' => ['2018-04-15 00:00:00', '2018-04-15 00:00:00']]
			],
			[
				'2018-04-15 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45:34'
				],
				'datetime' => ['values' => ['2018-04-15 12:45:34', '2018-04-15 12:45:34']]
			],
			[
				'2018-04-15 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45'
				],
				'datetime' => ['values' => ['2018-04-15 12:45:00', '2018-04-15 12:45:59']]
			],
			[
				'2018-04-15 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12'
				],
				'datetime' => ['values' => ['2018-04-15 12:00:00', '2018-04-15 12:59:59']]
			],
			[
				'2018-04-15', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15'
				],
				'datetime' => ['values' => ['2018-04-15 00:00:00', '2018-04-15 23:59:59']]
			],
			[
				'2018-04-9', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-9'
				],
				'datetime' => ['values' => ['2018-04-09 00:00:00', '2018-04-09 23:59:59']]
			],
			[
				'2018-04', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04'
				],
				'datetime' => ['values' => ['2018-04-01 00:00:00', '2018-04-30 23:59:59']]
			],
			[
				'2018-4', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-4'
				],
				'datetime' => ['values' => ['2018-04-01 00:00:00', '2018-04-30 23:59:59']]
			],
			[
				'2018', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018'
				],
				'datetime' => ['values' => ['2018-01-01 00:00:00', '2018-12-31 23:59:59']]
			],
			[
				'2018-04 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '2018-04'
				],
				'datetime' => ['values' => ['2018-04-01 00:00:00', '2018-04-30 23:59:59']]
			],
			[
				'2018-04-15 12:45:34text', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '2018-04-15 12:45:34'
				],
				'datetime' => ['values' => ['2018-04-15 12:45:34', '2018-04-15 12:45:34']]
			],
			[
				'2018-', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '2018'
				],
				'datetime' => ['values' => ['2018-01-01 00:00:00', '2018-12-31 23:59:59']]
			],
			[
				'2018-04-', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '2018-04'
				],
				'datetime' => ['values' => ['2018-04-01 00:00:00', '2018-04-30 23:59:59']]
			],
			[
				'2018-04-15 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45'
				],
				'datetime' => ['values' => ['2018-04-15 12:45:00', '2018-04-15 12:45:59'], 'tz' => 'UTC']
			],
			[
				'text', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'2018-02-30 12:45:34', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'2018-11-31', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'2018-11-01 23:59:61', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'2018-11-01 23:72:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'2018-11-01 24:00:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'201', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'20', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'2', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			],
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['values' => [null, null]]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 * @param array  $datetime
	 */
	public function testParse($source, $pos, array $expected, array $datetime) {
		$parser = new CAbsoluteTimeParser();

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());

		$tz = array_key_exists('tz', $datetime) ? new DateTimeZone($datetime['tz']) : null;
		$ts_from = $parser->getDateTime(true, $tz);
		$ts_to = $parser->getDateTime(false, $tz);
		$this->assertSame($datetime['values'][0], $ts_from !== null ? $ts_from->format('Y-m-d H:i:s') : null);
		$this->assertSame($datetime['values'][1], $ts_to !== null ? $ts_to->format('Y-m-d H:i:s') : null);
		if ($tz !== null) {
			$this->assertSame($datetime['tz'], $ts_from->getTimeZone()->getName());
			$this->assertSame($datetime['tz'], $ts_to->getTimeZone()->getName());
		}
	}
}
