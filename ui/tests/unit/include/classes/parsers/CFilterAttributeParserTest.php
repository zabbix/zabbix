<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CFilterAttributeParserTest extends PHPUnit_Framework_TestCase {

	public function testProvider() {
		$tests = [
			['?[]', 0, CParser::PARSE_SUCCESS, [
			]],
			['?[      ]', 0, CParser::PARSE_SUCCESS, [
			]],
			['?[tag="name:[]"]', 0, CParser::PARSE_SUCCESS, [
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'tag="name:[]"']
			]],
			['?[tag="name:value"]', 0, CParser::PARSE_SUCCESS, [
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'tag="name:value"']
			]],
			['?[tag="name:value" and group="test"]', 0, CParser::PARSE_SUCCESS, [
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'tag="name:value"'],
				[CFilterAttributeParser::TOKEN_TYPE_OPERATOR, 'and'],
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'group="test"']
			]],
			['?[(tag="name:value" and group="test")]', 0, CParser::PARSE_SUCCESS, [
				[CFilterAttributeParser::TOKEN_TYPE_OPEN_BRACE, '('],
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'tag="name:value"'],
				[CFilterAttributeParser::TOKEN_TYPE_OPERATOR, 'and'],
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'group="test"'],
				[CFilterAttributeParser::TOKEN_TYPE_CLOSE_BRACE, ')']
			]],
			['?[(tag="name:value" or tag="value:name") and group="test"]', 0, CParser::PARSE_SUCCESS, [
				[CFilterAttributeParser::TOKEN_TYPE_OPEN_BRACE, '('],
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'tag="name:value"'],
				[CFilterAttributeParser::TOKEN_TYPE_OPERATOR, 'or'],
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'tag="value:name"'],
				[CFilterAttributeParser::TOKEN_TYPE_CLOSE_BRACE, ')'],
				[CFilterAttributeParser::TOKEN_TYPE_OPERATOR, 'and'],
				[CFilterAttributeParser::TOKEN_TYPE_KEYVALUE, 'group="test"'],
			]],

			['?[()]', 0, CParser::PARSE_FAIL, []],
			['?[(]', 0, CParser::PARSE_FAIL, []],
			['?[)]', 0, CParser::PARSE_FAIL, []],
			['?[(()]', 0, CParser::PARSE_FAIL, []],
			['?[unknown="attribute"]', 0, CParser::PARSE_FAIL, []],
			['?[tag=unquoted_value]', 0, CParser::PARSE_FAIL, []],
			['?[tag=]', 0, CParser::PARSE_FAIL, []],
			['?[group]', 0, CParser::PARSE_FAIL, []],
			['?[(tag="name:value" or tag="value:name" and group="test"]', 0, CParser::PARSE_FAIL, []],
			['?[(tag="name:value" or and group="test"]', 0, CParser::PARSE_FAIL, []],
			['?[(tag="name:value" or () and group="test"]', 0, CParser::PARSE_FAIL, []],
		];

		$result = [];

		foreach ($tests as $test) {
			list($expected_source, $expected_pos, $expected_rc, $tokens) = $test;
			$expected_match = ($expected_rc == CParser::PARSE_SUCCESS) ? $expected_source : '';
			$p = $expected_pos;
			$expected_tokens = [];

			foreach ($tokens as $token) {
				list($type, $match) = $token;
				$p = strpos($expected_source, $match, $p);
				$expected_tokens[] = [
					'type' => $type,
					'match' => $match,
					'pos' => $p,
					'length' => strlen($match)
				];
			}

			$result[] = [$expected_source, $expected_pos, $expected_rc, $expected_match, $expected_tokens];
		}

		return $result;
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expected_rc
	 * @param $expected_match
	 */
	public function testParseValid($string, $pos, $expected_rc, $expected_match, $expected_tokens) {
		$parser = new CFilterAttributeParser();

		$this->assertSame(
			[
				'rc' => $expected_rc,
				'match' => $expected_match,
				'length' => strlen($expected_match),
				'tokens' => $expected_tokens
			],
			[
				'rc' => $parser->parse($string, $pos),
				'match' => $parser->getMatch(),
				'length' => $parser->getLength(),
				'tokens' => $parser->getTokens()
			]
		);
	}
}
