<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

class CEscapedStringParserTest extends TestCase {

	/**
	 * An array of properly and improperly escaped strings and parsed results.
	 */
	public static function dataProvider() {
		return [
			['', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '',
				'error' => ''
			]],
			['Simple text', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => 'Simple text',
				'error' => ''
			]],
			['\n\nZabbix', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\n\nZabbix',
				'error' => ''
			]],
			['\\\n', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\n',
				'error' => ''
			]],
			['\s', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\s',
				'error' => ''
			]],
			['\\\t', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\t',
				'error' => ''
			]],
			['\\\\t', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\\t',
				'error' => ''
			]],
			['\Here is another\n', 1, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => 'Here is another\n',
				'error' => ''
			]],
			['\ ', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'value contains unescaped character at position 1'
			]],
			['\\\\\\\\\n\\\\\n\n\t\somewhere\\\\\n\nat the end\n\ ', 0, [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'value contains unescaped character at position 43'
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	 */
	public function testParse(string $source, int $pos, array $expected) {
		$escaped_string_parser = new CEscapedStringParser();

		$this->assertSame($expected, [
			'rc' => $escaped_string_parser->parse($source, $pos),
			'match' => $escaped_string_parser->getMatch(),
			'error' => $escaped_string_parser->getError()
		]);
	}
}
