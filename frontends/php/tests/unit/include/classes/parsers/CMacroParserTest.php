<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		$fail = [
			'rc' => CParser::PARSE_FAIL,
			'match' => '',
			'macro' => '',
			'ref' => null
		];

		return [
			[CMacroParser::REFERENCE_NONE, '{HOST.HOST}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> null
			]],
			[CMacroParser::REFERENCE_NONE, 'chunk{HOST.HOST}', 5, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> null
			]],
			[CMacroParser::REFERENCE_NONE, 'chunk{HOST.HOST}chunk2', 5, [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> null
			]],
			[CMacroParser::REFERENCE_NUMERIC, '{HOST.HOST}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> null
			]],
			[CMacroParser::REFERENCE_NUMERIC, '{HOST.HOST2}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST2}',
				'macro' => 'HOST.HOST',
				'ref'	=> 2
			]],
			[CMacroParser::REFERENCE_NONE, '', 0, $fail],
			[CMacroParser::REFERENCE_NONE, '{}', 0, $fail],
			[CMacroParser::REFERENCE_NONE, '{', 0, $fail],
			[CMacroParser::REFERENCE_NONE, '{{HOST.HOST}abc', 0, $fail],
			[CMacroParser::REFERENCE_NONE, '{HOST.HOST', 0, $fail],
			[CMacroParser::REFERENCE_NUMERIC, '{HOST.HOST', 0, $fail],
			[CMacroParser::REFERENCE_NUMERIC, '{HOST.HOST1', 0, $fail],
			[CMacroParser::REFERENCE_NUMERIC, '{HOST.HOST0}', 0, $fail],
			[CMacroParser::REFERENCE_NUMERIC, '{5}', 0, $fail],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS."Test test"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Test test"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Test test'
			]],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS."Test\"\\\\ test"}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Test\"\\\\ test"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Test\"\\\\ test'
			]],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS.test}', 0, [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS.test}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'test'
			]],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS."Te\\st test"}', 0, $fail],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS.test"}', 0, $fail],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS."Test}', 0, $fail],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS"Test test"}', 0, $fail],
			[CMacroParser::REFERENCE_NONE, '{EVENT.TAGS."Test test"}', 0, $fail],
			[CMacroParser::REFERENCE_ALPHANUMERIC, '{EVENT.TAGS1."Test test"}', 0, $fail]
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
		$macro_parser = new CMacroParser(['{HOST.HOST}', '{HOST.IP}', '{ITEM.VALUE}', '{EVENT.TAGS}'], $options);

		$this->assertSame($expected, [
			'rc' => $macro_parser->parse($source, $pos),
			'match' => $macro_parser->getMatch(),
			'macro' => $macro_parser->getMacro(),
			'ref' => $macro_parser->getReference()
		]);
		$this->assertSame(strlen($expected['match']), $macro_parser->getLength());
	}
}
