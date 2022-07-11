<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

class CVaultSecretParserTest extends TestCase {

	/**
	 * An array of Vault secret tokens and parsed results.
	 */
	public function dataProvider() {
		return [
			// PARSE_SUCCESS
			['path/to/secret:key', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['path/to/secret', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['mount%2Fpoint/to/secret:key', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['mount%2Fpoint/secret:key', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['mount%2Fpoint/secret', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// Double slash in key is allowed.
			['namespace/secret:key/'.'/key', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// Multibyte support.
			['bā/āb:ā', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['zabbix/secret%3A/path:key', 0, [], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// PARSE_FAIL
			['pathtosecret/:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near ":key"'
			]],
			['/mount%2Fpoint/pathtosecret:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/mount%2Fpoint/pathtosecret:key"'
			]],
			['/pathtosecret:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/pathtosecret:key"'
			]],
			['pathtosecret:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "pathtosecret:key"'
			]],
			[':key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near ":key"'
			]],
			['/path/to/secret:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/path/to/secret:key"'
			]],
			// Path is empty.
			['namespace/', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "namespace/"'
			]],
			// Path has trailing slash.
			['namespace/path/', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path/"'
			]],
			['namespace/path/:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path/:key"'
			]],
			// Path begins with slash.
			['namespace/'.'/path', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/path"'
			]],
			// Path has empty segment.
			['namespace/path/'.'/to', 0, ['with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path//to"'
			]],
			['namespace/path/'.'/to:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path//to:key"'
			]],
			['zabbix/secret/:path:key', 0, [], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "secret/:path:key"'
			]]
		];
	}

	/**
	 * @dataProvider dataProvider
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
			'error' => $vault_secret_parser->getError()
		]);
	}
}
