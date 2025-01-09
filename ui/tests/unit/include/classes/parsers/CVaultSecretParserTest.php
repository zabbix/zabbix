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

class CVaultSecretParserTest extends TestCase {

	/**
	 * An array of Vault secret tokens and parsed results.
	 */
	public function dataProvider() {
		return [
			// HashiCorp
			// PARSE_SUCCESS
			['path/to/secret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['path/to/secret', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true, 'with_key' => false], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['mount%2Fpoint/to/secret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['mount%2Fpoint/secret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['mount%2Fpoint/secret', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true, 'with_key' => false], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// Double slash in key is allowed.
			['namespace/secret:key/'.'/key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// Multibyte support.
			['bā/āb:ā', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['zabbix/secret%3A/path:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// Without with_namespace option
			['pathtosecret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['pathtosecret:key/:/key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['pathtosecret:key/', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['pathtosecret:/key/', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// PARSE_FAIL
			['pathtosecret/:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near ":key"'
			]],
			['/mount%2Fpoint/pathtosecret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/mount%2Fpoint/pathtosecret:key"'
			]],
			['/pathtosecret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/pathtosecret:key"'
			]],
			['pathtosecret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "pathtosecret:key"'
			]],
			[':key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near ":key"'
			]],
			['/path/to/secret:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/path/to/secret:key"'
			]],
			// Path is empty.
			['namespace/', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true, 'with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'unexpected end of string'
			]],
			// Path has trailing slash.
			['namespace/path/', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true, 'with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path/"'
			]],
			['namespace/path/:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path/:key"'
			]],
			// Path begins with slash.
			['namespace/'.'/path', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true, 'with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "/path"'
			]],
			// Path has empty segment.
			['namespace/path/'.'/to', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true, 'with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path//to"'
			]],
			['namespace/path/'.'/to:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "path//to:key"'
			]],
			['zabbix/secret/:path:key', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "secret/:path:key"'
			]],
			// Key is empty
			['path/to/secret:', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near ":"'
			]],
			['path/to/secret', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "to/secret"'
			]],
			['', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_namespace' => true], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'string is empty'
			]],
			['', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'string is empty'
			]],
			['', 0, ['provider' => ZBX_VAULT_TYPE_HASHICORP, 'with_key' => false], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'string is empty'
			]],

			// CyberArk
			// PARSE_SUCCESS
			['AppID=&Query=Safe=Object=buzz:key', 0, ['provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			['AppID=&Query=Safe=Object=buzz', 0, ['with_key' => false, 'provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_SUCCESS,
				'error' => ''
			]],
			// PARSE_FAIL
			['', 0, ['provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'string is empty'
			]],
			['@AppID=&Query=Safe=Object=buzz', 0, ['with_key' => false, 'provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "@AppID=&Query=Safe=Object=buzz"'
			]],
			['AppID=&Qu#ery=Safe=Object=buzz', 0, ['with_key' => false, 'provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near "Qu#ery=Safe=Object=buzz"'
			]],
			['AppID=&Query=Safe=Object=buzz', 0, ['provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'mandatory key is missing'
			]],
			['AppId=&Query=Safe=Object=buzz:key', 0, ['provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'mandatory parameter "AppID" is missing'
			]],
			['AppID=&Query=Safe=Object=buzz:key', 0, ['with_key' => false, 'provider' => ZBX_VAULT_TYPE_CYBERARK], [
				'rc' => CParser::PARSE_FAIL,
				'error' => 'incorrect syntax near ":key"'
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
