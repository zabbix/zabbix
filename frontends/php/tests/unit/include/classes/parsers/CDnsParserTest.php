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


class CDnsParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function testProvider() {
		return [
			[
				'dns.name', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'dns.name'
				]
			],
			[
				'', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'www.zabbix.com-', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com-'
				]
			],
			[
				'.a', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'-a', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'_a', 0,
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'a', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'a'
				]
			],
			[
				'com.', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'com.'
				]
			],
			[
				'com..', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'com.'
				]
			],
			[
				'a.root-servers.net', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'a.root-servers.net'
				]
			],
			[
				'x--ample.example.net', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'x--ample.example.net'
				]
			],
			[
				'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890_', 0,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890_'
				]
			],
			[
				'abcdefghijklmnopqrstuvwxyz/.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890', 0,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abcdefghijklmnopqrstuvwxyz'
				]
			],
			[
				'127.0.0.1;www.zabbix.com.', 10,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com.'
				]
			],
			[
				'127.0.0.1;www..zabbix.com', 10,
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'www.'
				]
			],
			[
				'127.0.0.1;www.zabbix.com', 10,
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com'
				]
			]
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
		static $dns_parser = null;

		if ($dns_parser === null) {
			$dns_parser = new CDnsParser();
		}

		$this->assertSame($expected, [
			'rc' => $dns_parser->parse($source, $pos),
			'match' => $dns_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $dns_parser->getLength());
	}
}
