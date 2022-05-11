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

class CIPv6ParserTest extends TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function dataProvider() {
		return [
			// valid keys
			[
				'::', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::'
				]
			],
			[
				'::1', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::1'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:0000:0001', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:0000:0001'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFF:FFFF', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:FFFF:FFFF'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.1', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:127.0.0.1'
				]
			],
			[
				'::FFFF:127.0.0.1', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::FFFF:127.0.0.1'
				]
			],
			[
				'1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1'
				]
			],
			[
				'random text.....1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1....text', 16,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1'
				]
			],
			[
				'::127.0.0.1', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::127.0.0.1'
				]
			],
			[
				'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.256', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFG:FFFF', 0,
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
	public function testParse($source, $pos, $expected) {
		static $ipv6_parser = null;

		if ($ipv6_parser === null) {
			$ipv6_parser = new CIPv6Parser();
		}

		$this->assertSame($expected, [
			'rc' => $ipv6_parser->parse($source, $pos),
			'match' => $ipv6_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $ipv6_parser->getLength());
	}
}
