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


class CVaultSecretParserTest extends PHPUnit_Framework_TestCase {

	/**
	 * An array of Vault secret tokens and parsed results.
	 */
	public function testProvider() {
		return [
			// PARSE_SUCCESS
			['path/to/secret:key', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => 'path/to/secret:key',
				'error' => ''
			]],
			['path/to/secret', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_SUCCESS,
				'match' => 'path/to/secret',
				'error' => ''
			]],

			// PARSE_SUCCESS_CONT
			['path/to/secret:key', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'path/to/secret',
				'error' => 'incorrect syntax near ":key"'
			]],
			['path/to/secret:key something unrelated', 0, [], [
				'rc' => CParser::PARSE_SUCCESS_CONT,
				'match' => 'path/to/secret:key',
				'error' => 'incorrect syntax near " something unrelated"'
			]],

			// PARSE_FAIL
			['pathtosecret:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'incorrect syntax near ":key"'
			]],
			[':key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'incorrect syntax near ":key"'
			]],
			['path/to/{$MACRO}:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'incorrect syntax near "{$MACRO}:key"'
			]],
			['/path/to/secret:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'match' => '',
				'error' => 'incorrect syntax near "/path/to/secret:key"'
			]]
		];
	}

	/**
	 * @dataProvider testProvider
	 *
	 * @param string $source
	 * @param int    $pos
	 * @param array  $options
	 * @param array  $expected
	 */
	public function testParse($source, $pos, $options, $expected) {

		$vault_secret_parser = new CVaultSecretParser($options);

		$this->assertSame($expected, [
			'rc' => $vault_secret_parser->parse($source, $pos),
			'match' => $vault_secret_parser->getMatch(),
			'error' => $vault_secret_parser->getError()
		]);
		$this->assertSame(strlen($expected['match']), $vault_secret_parser->getLength());
	}
}
