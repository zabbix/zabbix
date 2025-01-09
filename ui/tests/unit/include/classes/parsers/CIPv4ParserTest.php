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

class CIPv4ParserTest extends TestCase {

	/**
	 * An array of trigger functions and parsed results.
	 */
	public static function dataProvider() {
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
	 * @dataProvider dataProvider
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
