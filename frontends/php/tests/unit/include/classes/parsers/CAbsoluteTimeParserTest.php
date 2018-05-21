<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CAbsoluteTimeParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of absolute times and parsed results.
	 */
	public static function testProvider() {
		return [
			[
				'2018-04-15 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45:34'
				],
				'datetime' => ['value' => '2018-04-15 12:45:34']
			],
			[
				'2018-04-15 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45'
				],
				'datetime' => ['value' => '2018-04-15 12:45:00']
			],
			[
				'2018-04-15 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12'
				],
				'datetime' => ['value' => '2018-04-15 12:00:00']
			],
			[
				'2018-04-15', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15'
				],
				'datetime' => ['value' => '2018-04-15 00:00:00']
			],
			[
				'2018-04 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12:45:34'
				],
				'datetime' => ['value' => '2018-04-01 12:45:34']
			],
			[
				'2018-04 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12:45'
				],
				'datetime' => ['value' => '2018-04-01 12:45:00']
			],
			[
				'2018-04 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12'
				],
				'datetime' => ['value' => '2018-04-01 12:00:00']
			],
			[
				'2018-04', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04'
				],
				'datetime' => ['value' => '2018-04-01 00:00:00']
			],
			[
				'2018 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12:45:34'
				],
				'datetime' => ['value' => '2018-01-01 12:45:34']
			],
			[
				'2018 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12:45'
				],
				'datetime' => ['value' => '2018-01-01 12:45:00']
			],
			[
				'2018 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12'
				],
				'datetime' => ['value' => '2018-01-01 12:00:00']
			],
			[
				'2018', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018'
				],
				'datetime' => ['value' => '2018-01-01 00:00:00']
			],
			[
				'2018-04-15 24:00:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['value' => null]
			],
			[
				'2018-04-15 00:60:00', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['value' => null]
			],
			[
				'2018-04-15 00:00:60', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				],
				'datetime' => ['value' => null]
			],
			[
				'2018-04-15 23:59:59', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 23:59:59'
				],
				'datetime' => ['value' => '2018-04-15 23:59:59']
			],
			[
				'2018-04-15 00:00:00', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 00:00:00'
				],
				'datetime' => ['value' => '2018-04-15 00:00:00']
			]
		];
	}

	/**
	 * @dataProvider testProvider
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

		$ts = $parser->getDateTime();
		$this->assertSame($datetime['value'], $ts !== null ? $ts->format('Y-m-d H:i:s') : null);
	}
}
