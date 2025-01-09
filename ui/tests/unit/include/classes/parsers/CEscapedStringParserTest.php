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

class CEscapedStringParserTest extends TestCase {

	/**
	 * An array of properly and improperly escaped strings and parsed results.
	 */
	public static function dataProvider() {
		return [
			// CParser::PARSE_SUCCESS
			['', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '',
				'error' => ''
			]],
			['Simple text', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => 'Simple text',
				'error' => ''
			]],
			['\n\nZabbix', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\n\nZabbix',
				'error' => ''
			]],
			['\\\n', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\n',
				'error' => ''
			]],
			// The backslash must always be escaped, it can optionally be set in the parameters.
			['\\\n', 0, ['characters' => '\\nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\n',
				'error' => ''
			]],
			['\s', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\s',
				'error' => ''
			]],
			['\\\t', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\t',
				'error' => ''
			]],
			['\Here is another\n', 1, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => 'Here is another\n',
				'error' => ''
			]],
			['\\\\', 0, ['characters' => ''], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\\',
				'error' => ''
			]],
			['\n\n\\\\', 4, ['characters' => ''], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\\\\',
				'error' => ''
			]],
			['\n\n\n\n\n', 0, ['characters' => 'n'], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '\n\n\n\n\n',
				'error' => ''
			]],

			// CParser::PARSE_SUCCESS_CONT
			['\\\\\n', 0, ['characters' => ''], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '\\\\',
				'error' => 'value contains unescaped character at position 3'
			]],
			['\\\\valid\n\\\\till\\\\position 29\ and then failed', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '\\\\valid\n\\\\till\\\\position 29',
				'error' => 'value contains unescaped character at position 29'
			]],
			['\\\\valid\n\\\\in the middle\ ', 9, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '\\\\in the middle',
				'error' => 'value contains unescaped character at position 16'
			]],

			// CParser::PARSE_FAIL
			['\ ', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'value contains unescaped character at position 1'
			]],
			['\n\\', 0, ['characters' => ''], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'value contains unescaped character at position 1'
			]],
			['\\', 0, ['characters' => 'nrts'], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'value contains unescaped character at position 1'
			]],
			['\\', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'value contains unescaped character at position 1'
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $offset
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse(string $source, int $offset, array $options, array $expected) {
		$escaped_string_parser = new CEscapedStringParser($options);

		$this->assertSame($expected, [
			'rc' => $escaped_string_parser->parse($source, $offset),
			'match' => $escaped_string_parser->getMatch(),
			'error' => $escaped_string_parser->getError()
		]);
	}
}
