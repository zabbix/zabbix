<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
			['{HOST.HOST}', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'macro' => 'HOST.HOST',
					'ref'	=> null
				]
			],
			['chunk{HOST.HOST}', 5, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'macro' => 'HOST.HOST',
					'ref'	=> null
				]
			],
			['chunk{HOST.HOST}chunk2', 5, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{HOST.HOST}',
					'macro' => 'HOST.HOST',
					'ref'	=> null
				]
			],
			['', 0, [], $fail],
			['{}', 0, [], $fail],
			['{', 0, [], $fail],
			['{{HOST.HOST}abc', 0, [], $fail],
			['{HOST.HOST', 0, [], $fail],
			['{EVENT.TAGS."Test test"}', 0, [], $fail],
			['{EVENT.TAGS.test}', 0, [], $fail],
			['{EVENT.TAGS.}', 0, [], $fail],
			['{EVENT.TAGS1}', 0, [], $fail],
			['{EVENT.TAG}', 0, [], $fail],

			['{HOST.HOST}', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> 0
			]],
			['{HOST.HOST2}', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST2}',
				'macro' => 'HOST.HOST',
				'ref'	=> 2
			]],
			['{HOST.HOST', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{HOST.HOST1', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{HOST.HOST0}', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{HOST.HOST.test0}', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{5}', 0, ['ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],

			['{EVENT.TAGS."Test test"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Test test"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Test test'
			]],
			['{EVENT.TAGS."Тест"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Тест"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Тест'
			]],
			['{EVENT.TAGS."Test\"\\\\ test"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Test\"\\\\ test"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Test"\\ test'
			]],
			['{EVENT.TAGS.""}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS.""}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> ''
			]],
			['{EVENT.TAGS.test}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS.test}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'test'
			]],
			['{EVENT.TAGS."{\"\\\\\"}"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."{\"\\\\\"}"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> '{"\\"}'
			]],
			['{EVENT.TAGS.$%%^}%&test}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS."Te\\st test"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS.test"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS.}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS."Test}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS"Test test"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS1."Test test"}', 0, ['ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $options, $expected) {
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
