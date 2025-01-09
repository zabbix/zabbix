<?php declare(strict_types = 0);
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

class CMacroParserTest extends TestCase {

	public function dataProvider() {
		$fail = [
			'rc' => CParser::PARSE_FAIL,
			'match' => '',
			'macro' => '',
			'ref' => null
		];

		return [
			['{HOST.HOST}', 0, ['macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'macro' => 'HOST.HOST',
					'ref'	=> null
				]
			],
			['chunk{HOST.HOST}', 5, ['macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'macro' => 'HOST.HOST',
					'ref'	=> null
				]
			],
			['chunk{ITEM.VALUE}chunk2', 5, ['macros' => ['{HOST.HOST}', '{HOST.IP}', '{ITEM.VALUE}', '{EVENT.TAGS}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{ITEM.VALUE}',
					'macro' => 'ITEM.VALUE',
					'ref'	=> null
				]
			],
			['', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{}', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{{HOST.HOST}abc', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{HOST.HOST', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{EVENT.TAGS."Test test"}', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{EVENT.TAGS.test}', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{EVENT.TAGS.}', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{EVENT.TAGS1}', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{EVENT.TAG}', 0, ['macros' => ['{HOST.HOST}']], $fail],
			['{EVENT.TAG}', 0, [], $fail],
			['{HOST.HOST}', 0, ['macros' => true, 'ref_type' => CMacroParser::REFERENCE_NUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> 0
			]],
			['{HOST.HOST}', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST}',
				'macro' => 'HOST.HOST',
				'ref'	=> 0
			]],
			['{HOST.HOST2}', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{HOST.HOST2}',
				'macro' => 'HOST.HOST',
				'ref'	=> 2
			]],
			['{HOST.HOST', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{HOST.HOST1', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{HOST.HOST0}', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{HOST.HOST.test0}', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{5}', 0, ['macros' => ['{HOST.HOST}'], 'ref_type' => CMacroParser::REFERENCE_NUMERIC], $fail],
			['{EVENT.TAGS}', 0, ['macros' => []], $fail],
			['{EVENT.TAGS}', 0, ['macros' => false], $fail],
			['{EVENT.TAGS."Test test"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Test test"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Test test'
			]],
			['{EVENT.TAGS."Тест"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Тест"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Тест'
			]],
			['{EVENT.TAGS."Test\"\\\\ test"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."Test\"\\\\ test"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'Test"\\ test'
			]],
			['{EVENT.TAGS.""}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS.""}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> ''
			]],
			['{EVENT.TAGS.TAG}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS.TAG}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'TAG'
			]],
			['{EVENT.TAGS.test}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS.test}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> 'test'
			]],
			['{EVENT.TAGS."{\"\\\\\"}"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => '{EVENT.TAGS."{\"\\\\\"}"}',
				'macro' => 'EVENT.TAGS',
				'ref'	=> '{"\\"}'
			]],
			['{EVENT.TAGS.$%%^}%&test}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS."Te\\st test"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS.test"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS.}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS."Test}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS"Test test"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail],
			['{EVENT.TAGS1."Test test"}', 0, ['macros' => ['{EVENT.TAGS}'], 'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC], $fail]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $expected
	*/
	public function testParse($source, $pos, $options, $expected) {
		$macro_parser = new CMacroParser($options);

		$this->assertSame($expected, [
			'rc' => $macro_parser->parse($source, $pos),
			'match' => $macro_parser->getMatch(),
			'macro' => $macro_parser->getMacro(),
			'ref' => $macro_parser->getReference()
		]);
		$this->assertSame(strlen($expected['match']), $macro_parser->getLength());
	}
}
