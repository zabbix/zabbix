<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CMacroParserTest extends PHPUnit_Framework_TestCase {

	public function testProvider() {
		return [
			[[], '{HOST.HOST}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'n' => 0
			]],
			[[], 'chunk{HOST.HOST}', 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'n' => 0
			]],
			[[], 'chunk{HOST.HOST}chunk2', 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'n' => 0
			]],
			[['allow_reference' => true], '{HOST.HOST}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'n' => 0
			]],
			[['allow_reference' => true], '{HOST.HOST2}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST2}',
				'macro' => 'HOST.HOST',
				'n' => 2
			]],

			[[], '', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[[], '{}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[[], '{', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[[], '{{HOST.HOST}abc', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[[], '{HOST.HOST', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[['allow_reference' => true], '{HOST.HOST', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[['allow_reference' => true], '{HOST.HOST1', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[['allow_reference' => true], '{HOST.HOST0}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]],
			[['allow_reference' => true], '{5}', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'macro' => '',
				'n' => 0
			]]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	*/
	public function testParse($options, $source, $pos, $expected) {
		$macro_parser = new CMacroParser(['{HOST.HOST}', '{HOST.IP}', '{ITEM.VALUE}'], $options);

		$this->assertSame($expected, [
			'rc' => $macro_parser->parse($source, $pos),
			'match' => $macro_parser->getMatch(),
			'macro' => $macro_parser->getMacro(),
			'n' => $macro_parser->getN()
		]);
		$this->assertSame(strlen($expected['match']), $macro_parser->getLength());
	}
}
