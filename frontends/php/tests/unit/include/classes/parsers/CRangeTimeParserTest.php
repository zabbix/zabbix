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


class CRangeTimeParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of relative times and parsed results.
	 */
	public static function testProvider() {
		return [
			// Absolute time.
			[
				'2018-04-15 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45:34'
				]
			],
			[
				'2018-04-15 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12:45'
				]
			],
			[
				'2018-04-15 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15 12'
				]
			],
			[
				'2018-04-15', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04-15'
				]
			],
			[
				'2018-04 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12:45:34'
				]
			],
			[
				'2018-04 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12:45'
				]
			],
			[
				'2018-04 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04 12'
				]
			],
			[
				'2018-04', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018-04'
				]
			],
			[
				'2018 12:45:34', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12:45:34'
				]
			],
			[
				'2018 12:45', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12:45'
				]
			],
			[
				'2018 12', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018 12'
				]
			],
			[
				'2018', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '2018'
				]
			],
			// Relative time.
			[
				'now', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now'
				]
			],
			[
				'now/y', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/y'
				]
			],
			[
				'now/M', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M'
				]
			],
			[
				'now/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/w'
				]
			],
			[
				'now/d', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/d'
				]
			],
			[
				'now/h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/h'
				]
			],
			[
				'now/m', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/m'
				]
			],
			[
				'now/s', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'now'
				]
			],
			[
				'now-1y', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now-1y'
				]
			],
			[
				'now-1M', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now-1M'
				]
			],
			[
				'now-1w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now-1w'
				]
			],
			[
				'now-1h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now-1h'
				]
			],
			[
				'now-1m', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now-1m'
				]
			],
			[
				'now-1s', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now-1s'
				]
			],
			[
				'now-1x', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'now'
				]
			],
			[
				'now/M-1y', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1y'
				]
			],
			[
				'now/M-1M', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1M'
				]
			],
			[
				'now/M-1w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1w'
				]
			],
			[
				'now/M-1h', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1h'
				]
			],
			[
				'now/M-1m', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1m'
				]
			],
			[
				'now/M-1s', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1s'
				]
			],
			[
				'now/M-1x', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'now/M'
				]
			],
			[
				'now/M-1y/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1y/w'
				]
			],
			[
				'now/M-1M/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1M/w'
				]
			],
			[
				'now/M-1w/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1w/w'
				]
			],
			[
				'now/M-1h/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1h/w'
				]
			],
			[
				'now/M-1m/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1m/w'
				]
			],
			[
				'now/M-1s/w', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'now/M-1s/w'
				]
			],
			[
				'now/M-1s/x', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'now/M-1s'
				]
			],
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $expected) {
		$parser = new CRangeTimeParser();

		$this->assertSame($expected, [
			'rc' => $parser->parse($source, $pos),
			'match' => $parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $parser->getLength());
	}
}
