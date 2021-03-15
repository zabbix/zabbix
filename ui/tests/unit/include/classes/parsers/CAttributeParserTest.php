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


class CAttributeParserTest extends PHPUnit_Framework_TestCase {

	public function testProvider() {
		return [
			['tag="name:value"', 0, CParser::PARSE_SUCCESS, 'tag', '"name:value"'],
			['group="group name"', 0, CParser::PARSE_SUCCESS, 'group', '"group name"'],

			[' tag="name" ', 1, CParser::PARSE_SUCCESS_CONT, 'tag', '"name"'],
			['group="name" ', 0, CParser::PARSE_SUCCESS_CONT, 'group',  '"name"'],
			['tag="name:va\\"lue"', 0, CParser::PARSE_SUCCESS_CONT, 'tag', '"name:va\\"'],

			['', 0, CParser::PARSE_FAIL],
			['unknown="value"', 0, CParser::PARSE_FAIL],
			['tag=', 0, CParser::PARSE_FAIL],
			['="value"', 0, CParser::PARSE_FAIL],
			['group=name ', 0, CParser::PARSE_FAIL],
			['tag=\'value\'', 0, CParser::PARSE_FAIL],
			['group"value"', 0, CParser::PARSE_FAIL]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param $string
	 * @param $pos
	 * @param $expected_rc
	 * @param $expected_match
	 */
	public function testParseValid($string, $pos, $expected_rc, $expected_name = '', $expected_value = '') {
		$parser = new CAttributeParser();
		$expected_match = ($expected_rc == CParser::PARSE_FAIL) ? '' : $expected_name.'='.$expected_value;

		$this->assertSame(
			[
				'rc' => $expected_rc,
				'match' => $expected_match,
				'length' => strlen($expected_match),
				'name' => $expected_name,
				'value' => $expected_value
			],
			[
				'rc' => $parser->parse($string, $pos),
				'match' => $parser->getMatch(),
				'length' => $parser->getLength(),
				'name' => $parser->name,
				'value' => $parser->value
			]
		);
	}
}
