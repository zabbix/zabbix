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


/**
 * Class containing methods to test CPortParser class functionality.
 */
use PHPUnit\Framework\TestCase;

class CPortRangeParserTest extends TestCase {

	public function dataProvider() {
		return [
			[
				'', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123456', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'12345', [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '12345'
				]
			],
			[
				'a', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1 2', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				' 123', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123 ', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$MACRO}', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123-123', [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123-123'
				]
			],
			[
				'123-123,123-123', [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123-123,123-123'
				]
			],
			[
				'123-123,123-', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123-123,123', [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123-123,123'
				]
			],
			[
				'123-123,', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123-abc', [
				'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123,123', [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123,123'
				]
			],
			[
				'123,123,abc', [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				sprintf('%s', ZBX_MIN_PORT_NUMBER), [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => sprintf('%s', ZBX_MIN_PORT_NUMBER)
				]
			],
			[
				sprintf('%s', ZBX_MIN_PORT_NUMBER - 1), [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				sprintf('%s-%s', ZBX_MIN_PORT_NUMBER, ZBX_MAX_PORT_NUMBER), [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => sprintf('%s-%s', ZBX_MIN_PORT_NUMBER, ZBX_MAX_PORT_NUMBER)
				]
			],
			[
				sprintf('%s', ZBX_MAX_PORT_NUMBER), [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => sprintf('%s', ZBX_MAX_PORT_NUMBER)
				]
			],
			[
				sprintf('%s', ZBX_MAX_PORT_NUMBER + 1), [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				sprintf('%s-%s', ZBX_MIN_PORT_NUMBER - 1, ZBX_MAX_PORT_NUMBER), [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				sprintf('%s-%s', ZBX_MIN_PORT_NUMBER, ZBX_MAX_PORT_NUMBER + 1), [
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
	 * @param array  $options
	 * @param array  $expected
	*/
	public function testParse($source, $expected) {
		$port_range_parser = new CPortRangeParser();

		$this->assertSame($expected, [
			'rc' => $port_range_parser->parse($source),
			'match' => $port_range_parser->getMatch()
		]);
	}

}
