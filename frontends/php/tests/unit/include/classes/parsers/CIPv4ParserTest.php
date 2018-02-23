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


class CIPv4ParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function testProvider() {
		return [
			[
				'192.168.3.4', 0, [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.3.4'
				]
			],
			[
				'192.168.3.4,192.168.5.0', 0, [
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.3.4'
				]
			],
			[
				'192.168.3.4,192.168.5.0', 12, [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.5.0'
				]
			],
			[
				'192.168.3.4,192.168.5.0/24', 12, [
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.5.0'
				]
			],
			[
				'192.168.3.256', 0, [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'192.168.3.256', 0, [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'192.168..4', 0, [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'321.654.987.456', 0, [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'', 0, [
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
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $expected) {
		static $ipv4_parser = null;

		if ($ipv4_parser === null) {
			$ipv4_parser = new CIPv4Parser();
		}

		$this->assertSame($expected, [
			'rc' => $ipv4_parser->parse($source, $pos),
			'match' => $ipv4_parser->getMatch()
		]);
		$this->assertSame(strlen($expected['match']), $ipv4_parser->getLength());
	}
}
