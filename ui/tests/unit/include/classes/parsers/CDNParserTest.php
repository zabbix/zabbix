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
			['cn=john\\\\, dc=org', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'john\\'],
				['name' => 'dc', 'value' => 'org']
			]],
			['cn=john\\\\, dc=\\\\org', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'john\\'],
				['name' => 'dc', 'value' => '\\org']
			]],
			['cn=\\"doe john, dc=\\"org\\"', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => '"doe john'],
				['name' => 'dc', 'value' => '"org"']
			]],

			// Double quoted.
			['cn="john doe", dc=john doe', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'john doe'],
				['name' => 'dc', 'value' => 'john doe']
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

	/**
	 * @dataProvider dataProvider
	 */
	public function testParserMatchesLdapExplode(string $dn) {
		$dn_parser = new CDNParser();

		$parser_success = $dn_parser->parse($dn) == CParser::PARSE_SUCCESS;

		$library_result = ldap_explode_dn($dn, 0);
		$library_success = $library_result !== false;

		$message = $library_success ? "Library succeeded in parsing '$dn'." : "Library failed in parsing '$dn'.";
		$this->assertSame($parser_success, $library_success, $message);

		if (is_array($library_result)) {
			$this->assertSame(count($dn_parser->result), $library_result['count']);
			$library_result_objects = [];
			unset($library_result['count']);
			foreach ($library_result as $rdn) {
				[$name, $value] = explode('=', $rdn, 2);
				$value = self::replaceHexChars($value);
				$name = self::replaceHexChars($name);
				$library_result_objects[] = ['name' => $name, 'value' => $value];
			}

			$this->assertSame($dn_parser->result, $library_result_objects);
		}
	}

	private static function replaceHexChars(string $input) {
		return preg_replace_callback(
			pattern: '/\\\\([0-9A-Fa-f]{2})/',
			callback: static fn (array $matches) => chr(hexdec($matches[1])),
			subject: $input
		);
	}

}
