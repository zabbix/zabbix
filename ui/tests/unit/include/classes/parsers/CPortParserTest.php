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

class CPortParserTest extends TestCase {

	public function dataProvider() {
		return [
			[
				'', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123456', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'12345', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '12345'
				]
			],
			[
				'a', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'1 2', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				' 123', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'123 ', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$MACRO}', [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				'{$MACRO}', ['usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO}'
				]
			],
			[
				'{{$M}.regsub("^([0-9]+)", \1)}', ['usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M}.regsub("^([0-9]+)", \1)}'
				]
			],
			[
				'{#MACRO}', ['lldmacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#MACRO}'
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", \1)}', ['lldmacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", \1)}'
				]
			],
			[
				'{$MACRO}asd', ['usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$MACRO}'
				]
			],
			[
				123, [], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '123'
				]
			],
			[
				'{$ASD:  regex:   asd"}', ['usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$ASD:  regex:   asd"}'
				]
			],
			[
				sprintf('%s', ZBX_MIN_PORT_NUMBER), [], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => sprintf('%s', ZBX_MIN_PORT_NUMBER)
				]
			],
			[
				sprintf('%s', ZBX_MIN_PORT_NUMBER - 1), [], [
					'rc' => CParser::PARSE_FAIL,
					'match' => ''
				]
			],
			[
				sprintf('%s', ZBX_MAX_PORT_NUMBER), [], [
					'rc' => CParser::PARSE_SUCCESS,
					'match' => sprintf('%s', ZBX_MAX_PORT_NUMBER)
				]
			],
			[
				sprintf('%s', ZBX_MAX_PORT_NUMBER + 1), [], [
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
	public function testParse($source, $options, $expected) {
		$port_parser = new CPortParser($options);

		$this->assertSame($expected, [
			'rc' => $port_parser->parse($source),
			'match' => $port_parser->getMatch()
		]);
	}

}
