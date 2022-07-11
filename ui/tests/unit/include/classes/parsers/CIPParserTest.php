<?php declare(strict_types = 0);
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


/**
 * Class containing tests for CIPParser class functionality.
 */
use PHPUnit\Framework\TestCase;

class CIPParserTest extends TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function dataProvider() {
		return [
			[
				'192.168.3.4', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.3.4'
				]
			],
			[
				'192.168.3.4,192.168.5.0', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.3.4'
				]
			],
			[
				'192.168.3.4,192.168.5.0', 12, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.5.0'
				]
			],
			[
				'192.168.3.4,192.168.5.0/24', 12, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.5.0'
				]
			],
			[
				'192.168.3.256', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'192.168.3.256', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'192.168..4', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'::', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::'
				]
			],
			[
				'::', 0, ['v6' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'::1', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::1'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:0000:0001', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:0000:0001'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFF:FFFF', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:FFFF:FFFF'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.1', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:127.0.0.1'
				]
			],
			[
				'::FFFF:127.0.0.1', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::FFFF:127.0.0.1'
				]
			],
			[
				'1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1'
				]
			],
			[
				'random text.....1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1....text', 16, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1'
				]
			],
			[
				'::127.0.0.1', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::127.0.0.1'
				]
			],
			[
				'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255'
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.256', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFG:FFFF', 0, ['v6' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'2001:db8::8a2e:370:7334', 0, ['v6' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$MACRO}', 0, ['v6' => true, 'usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO}'
				]
			],
			[
				'text{$MACRO}', 4, ['v6' => true, 'usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO}'
				]
			],
			[
				'{$MACRO}text', 0, ['v6' => true, 'usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$MACRO}'
				]
			],
			[
				'{$MACRO:"test"}', 0, ['v6' => true, 'usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO:"test"}'
				]
			],
			[
				'{#MACRO}', 0, ['v6' => true, 'usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{#MACRO}', 0, ['v6' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#MACRO}'
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}'
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'test{#MACRO}', 4, ['v6' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#MACRO}'
				]
			],
			[
				'{#MACRO}test', 0, ['v6' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#MACRO}'
				]
			],
			[
				'{HOST.HOST}', 0, ['v6' => true, 'usermacros' => true, 'lldmacros' => true, 'macros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}'
				]
			],
			[
				'{HOST.HOST}', 0, ['v6' => true, 'macros' => []],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{HOST.HOST}', 0, ['v6' => true, 'macros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{HOST.HOST}', 0, ['v6' => true, 'macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}'
				]
			],
			[
				'test{HOST.HOST}', 4, ['v6' => true, 'macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}'
				]
			],
			[
				'{HOST.HOST}test', 0, ['v6' => true, 'macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{HOST.HOST}'
				]
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $options, $expected) {
		$ip_parser = new CIPParser($options);

		$this->assertSame($expected, [
			'rc' => $ip_parser->parse($source, $pos),
			'match' => $ip_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $ip_parser->getLength());
	}

}
