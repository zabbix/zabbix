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

/**
 * Test distinguished names parser.
 */
class CDNParserTest extends TestCase {

	public function dataProvider() {
		return [
			// Empty values.
			['', CParser::PARSE_SUCCESS],
			['=', CParser::PARSE_SUCCESS, [
				['name' => '', 'value' => '']
			]],
			['=org', CParser::PARSE_SUCCESS, [
				['name' => '', 'value' => 'org']
			]],
			['dc=', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => '']
			]],

			// Simple cases.
			['dc=org', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => 'org']
			]],
			['dc=org,cn=org', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => 'org'],
				['name' => 'cn', 'value' => 'org']
			]],

			// Trimming.
			['dc = org', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => 'org']
			]],
			[' dc = org , dc = org , cn = john ', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => 'org'],
				['name' => 'dc', 'value' => 'org'],
				['name' => 'cn', 'value' => 'john']
			]],

			// Escaping.
			['cn=doe\\, john', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'doe, john']
			]],
			['cn=doe\\=john', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'doe=john']
			]],
			['cn=\\ john\\ ', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => ' john ']
			]],

			// Repeated names.
			['dc=example,dc=org', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => 'example'],
				['name' => 'dc', 'value' => 'org']
			]],

			// Multivalue RDN's
			['cn=doe\, john+uid=123', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'doe, john'],
				['name' => 'uid', 'value' => '123']
			]],

			// Failing cases.
			['cn=john,ou', CParser::PARSE_FAIL],
			['john', CParser::PARSE_FAIL]
		];
	}

	/**
	 * @dataProvider dataProvider
	 */
	public function testParse(string $dn, int $expect_result, array $expect_objects = []) {
		$dn_parser = new CDNParser();

		$this->assertSame($dn_parser->parse($dn), $expect_result);
		$this->assertSame($dn_parser->result, $expect_objects);
	}
}
