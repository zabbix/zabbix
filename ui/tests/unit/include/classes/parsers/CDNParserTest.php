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
			['cn="john ,=+<>#; doe", dc=john doe', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'john ,=+<>#; doe'],
				['name' => 'dc', 'value' => 'john doe']
			]],
			['cn="john\\, doe", dc=john doe', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'john, doe'],
				['name' => 'dc', 'value' => 'john doe']
			]],

			// Repeated names.
			['dc=example,dc=org', CParser::PARSE_SUCCESS, [
				['name' => 'dc', 'value' => 'example'],
				['name' => 'dc', 'value' => 'org']
			]],

			['CN=Test\\\\,O=Org', CParser::PARSE_SUCCESS, [
				['name' => 'CN', 'value' => 'Test\\'],
				['name' => 'O', 'value' => 'Org']
			]],

			// Multivalue RDN's
			['cn=doe\, john+uid=123', CParser::PARSE_SUCCESS, [
				['name' => 'cn', 'value' => 'doe, john'],
				['name' => 'uid', 'value' => '123']
			]],

			// Failing cases.
			['cn=john,ou', CParser::PARSE_FAIL],
			['john', CParser::PARSE_FAIL],
			['=org', CParser::PARSE_FAIL],
			['=', CParser::PARSE_FAIL]
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

	public function dataProviderExtensive() {
		return [
			// Basic case
			['CN=John Doe,OU=Users,DC=example,DC=com'],

			// Multiple AttributeType=Value in RDN
			['CN=John Doe+UID=12345,OU=Users,DC=example,DC=com'],

			// Escaped characters
			['CN=Joh\\,n Doe,OU=Users,DC=example,DC=com'],         // Comma inside value
			['CN=Joh\\+n Doe,OU=Users,DC=example,DC=com'],         // Plus inside value
			['CN=Joh\\n Doe,OU=Users,DC=example,DC=com'],          // Backslash inside value
			['CN=Joh\\;n Doe,OU=Users,DC=example,DC=com'],         // Semicolon inside value
			['CN=Joh\\=n Doe,OU=Users,DC=example,DC=com'],         // Equals inside value

			// Hex encoding (control and non-printable chars)
			['CN=Hello \\2C World,OU=Test,DC=example,DC=com'],     // \2C = ','
			['CN=Tabbed\\09Name,OU=Test,DC=example,DC=com'],       // \09 = Tab
			['CN=Newline\\0AName,OU=Test,DC=example,DC=com'],      // \0A = Line Feed
			['CN=Null\\00Char,OU=Test,DC=example,DC=com'],         // Null byte escape

			// Leading/Trailing whitespace
			[' CN = John Doe , OU = Users , DC = example , DC = com '],  // Spaces around =
			['CN=John Doe , OU=Users,DC=example , DC=com'],              // Spaces after commas
			['CN=John Doe   ,OU=Users  ,DC=example,DC=com'],             // Extra spaces before comma

			// Empty RDN components (permitted but unusual)
			['CN=John Doe,,OU=Users,DC=example,DC=com'],           // Empty component
			['CN=John Doe,OU=,DC=example,DC=com'],                 // Empty value

			// Special attribute names
			['dc=example,dc=com'],
			['o=University of Michigan,c=US'],
			['cn=Steve Kille,o=ISODE Consortium,c=GB'],
			['l=Los Angeles, st=California, c=US'],                // With spaces

			// Internationalization / UTF-8
			['CN=Éric,CN=André,DC=example,DC=com'],
			/* ['CN=Klüger\\xC3\\x9F,OU=Users,DC=example,DC=com'],   // Mixed Unicode + escaped UTF-8 bytes */

			// Hex-encoded binary values
			['CN=# 040AFFFFFFFF'],                                 // Binary BER encoding format

			// Quoted strings (less common, allowed)
			['CN="Hello, World",OU=Test,DC=example,DC=com'],
			['CN="Escaped \\"Quote\\"",OU=Test,DC=example,DC=com'],

			// Complex nested escaping
			['CN=First\\, Last\\\\,OU=People,DC=example,DC=com'],  // Combination of escapes

			// Long DN with many RDNs
			[implode(',', array_fill(0, 100, 'OU=Level'))],       // Deep hierarchy

			// Empty DN string
			[''],
			['='],
			['=org'],
			['CN=John=Doe,OU=Users,DC=example,DC=com'],           // Invalid '=' in value without quotes or escape
		];
	}

	/**
	 * @dataProvider dataProvider
	 * @dataProvider dataProviderExtensive
	 */
	public function testParserMatchesLdapExplode(string $dn) {
		$dn_parser = new CDNParser();

		$parser_success = $dn_parser->parse($dn) == CParser::PARSE_SUCCESS;

		$library_result = ldap_explode_dn($dn, 0);
		$library_success = $library_result !== false;

		$message = $library_success ? "Library succeeded in parsing '$dn'." : "Library failed in parsing '$dn'.";
		$this->assertSame($parser_success, $library_success, $message);

		if (is_array($library_result)) {
			$result_count = count($dn_parser->result);
			$this->assertSame($result_count, $library_result['count'], sprintf(
				"Library found '%s' RDNs, parser found '%s' in DN '%s'.", $library_result['count'], $result_count, $dn
			));

			$library_result_objects = [];
			unset($library_result['count']);
			foreach ($library_result as $rdn) {
				[$name, $value] = explode('=', $rdn, 2);
				$value = self::replaceHexChars($value);
				$name = self::replaceHexChars($name);
				$library_result_objects[] = ['name' => $name, 'value' => $value];
			}

			$this->assertSame($dn_parser->result, $library_result_objects, "Issue with DN: '$dn'.");
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
