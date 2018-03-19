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


/**
 * Class containing tests for CIPParser class functionality.
 */
class CIPParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function testProvider() {
		return [
			[
				'192.168.3.4', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.3.4'
				]
			],
			[
				'192.168.3.4,192.168.5.0', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.3.4'
				]
			],
			[
				'192.168.3.4,192.168.5.0', 12, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.5.0'
				]
			],
			[
				'192.168.3.4,192.168.5.0/24', 12, true,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.5.0'
				]
			],
			[
				'192.168.3.256', 0, true,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'192.168.3.256', 0, true,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'192.168..4', 0, true,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'', 0, true,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'::', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::'
				]
			],
			[
				'::', 0, false,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'::1', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::1'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:0000:0001', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:0000:0001'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFF:FFFF', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:FFFF:FFFF'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.1', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:127.0.0.1'
				]
			],
			[
				'::FFFF:127.0.0.1', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::FFFF:127.0.0.1'
				]
			],
			[
				'1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1'
				]
			],
			[
				'random text.....1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1....text', 16, true,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1'
				]
			],
			[
				'::127.0.0.1', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::127.0.0.1'
				]
			],
			[
				'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255', 0, true,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.256', 0, true,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFG:FFFF', 0, true,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param bool   $v6
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $v6, $expected) {
		$ipv6_parser = new CIPParser(['v6' => $v6]);

		$this->assertSame($expected, [
			'rc' => $ipv6_parser->parse($source, $pos),
			'match' => $ipv6_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $ipv6_parser->getLength());
	}

}
