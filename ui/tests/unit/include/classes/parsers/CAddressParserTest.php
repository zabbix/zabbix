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
 * Class containing tests for CAddressParser class functionality.
 */
use PHPUnit\Framework\TestCase;

class CAddressParserTest extends TestCase {

	public static function dataProvider() {
		return [
			// IPv4 tests - success.
			[
				'192.168.0.1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.0.1',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'192.168.0.1,192.168.1.10', 12, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.1.10',
					'type' => INTERFACE_USE_IP
				]
			],

			// IPv6 tests - success.
			[
				'::', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'::1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::1',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:0000:0001', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:0000:0001',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFF:FFFF', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:FFFF:FFFF',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '0000:0000:0000:0000:0000:0000:127.0.0.1',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'::FFFF:127.0.0.1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::FFFF:127.0.0.1',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:255.255.255.255',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'::127.0.0.1', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '::127.0.0.1',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{$MACRO}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO}',
					'type' => INTERFACE_USE_IP
				]
			],

			// Macros tests - success.
			[
				'{{$M}.regsub("^([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M}.regsub("^([0-9]+)", \1)}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{{$M: "context"}.regsub("^([0-9]+)", \1)}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{$M: "context"}.regsub("^([0-9]+)", \1)}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'text{$MACRO}', 4, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{$MACRO:"test"}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{$MACRO:"test"}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{#MACRO}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#MACRO}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'test{#MACRO}', 4, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{#MACRO}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{HOST.HOST}', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{{HOST.HOST}.func()}', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{HOST.HOST}.func()}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{{HOST.HOST}.func()}', 0, ['macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{{HOST.HOST}.func()}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'test{HOST.HOST}', 4, ['macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '{HOST.HOST}',
					'type' => INTERFACE_USE_IP
				]
			],

			// DNS tests - success (failed as IP addresses).
			[
				'192.168.3.256', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => '192.168.3.256',
					'type' => INTERFACE_USE_DNS
				]
			],

			// DNS tests - success (normal).
			[
				'dns.name', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'dns.name',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'www.zabbix.com-', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com-',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'a', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'a',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'com.', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'com.',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'a.root-servers.net', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'a.root-servers.net',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'x--ample.example.net', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'x--ample.example.net',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890_', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890_',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'127.0.0.1;www.zabbix.com.', 10, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com.',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'127.0.0.1;www.zabbix.com', 10, [],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'www.zabbix.com',
					'type' => INTERFACE_USE_DNS
				]
			],

			// DNS tests - success (with macros).
			[
				'&&&&zabbix.com{$MACRO2}', 4, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{$MACRO2}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'z{$A}{#B}{#B}i{$X}', 0, ['usermacros' => true, 'lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'z{$A}{#B}{#B}i{$X}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{HOST.HOST}', 0, ['macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{HOST.HOST}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{{HOST.HOST}.regsub("(\d+)", \1)}', 0, ['macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{{HOST.HOST}.regsub("(\d+)", \1)}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{HOST.HOST}dns{HOST.DNS}', 0, ['macros' => ['{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'zabbix.com{HOST.HOST}dns{HOST.DNS}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'testa{HOST.HOST}testb{$MACRO1}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => ['{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'match' => 'testa{HOST.HOST}testb{$MACRO1}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste',
					'type' => INTERFACE_USE_DNS
				]
			],

			// IPv4 tests - partial success.
			[
				'192.168.1.2,192.168.3.4', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.1.2',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'192.168.3.4,192.168.5.0/24', 12, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.5.0',
					'type' => INTERFACE_USE_IP
				]
			],

			// IPv6 tests - partial success.
			[
				'random text.....1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1....text', 16, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '1234:5678:90ab:cdef:ABCD:EF00:127.0.0.1',
					'type' => INTERFACE_USE_IP
				]
			],

			// Macros tests - partial success.
			[
				'{{$M}.regsub("^([0-9]+)", \1)}TEXT', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{{$M}.regsub("^([0-9]+)", \1)}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{$MACRO}text', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{$MACRO}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{#MACRO}test', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{#MACRO}',
					'type' => INTERFACE_USE_IP
				]
			],
			[
				'{HOST.HOST}test', 0, ['macros' => ['{HOST.NAME}', '{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '{HOST.HOST}',
					'type' => INTERFACE_USE_IP
				]
			],

			// DNS tests - partial success (failed IPv4 address).
			[
				'192.168..4', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '192.168.',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'2001:db8::8a2e:370:7334', 0, ['v6' => false],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '2001',
					'type' => INTERFACE_USE_DNS
				]
			],

			// DNS tests - partial success (failed IPv5 address).
			[
				'0000:0000:0000:0000:0000:0000:127.0.0.256', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0000',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'0000:0000:0000:0000:0000:0000:FFFG:FFFF', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => '0000',
					'type' => INTERFACE_USE_DNS
				]
			],

			// DNS tests - partial success (normal).
			[
				'com..', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'com.',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'abcdefghijklmnopqrstuvwxyz/.ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890', 0, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'abcdefghijklmnopqrstuvwxyz',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'127.0.0.1;www..zabbix.com', 10, [],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'www.',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{$MACRO3}test%%%', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com{$MACRO3}test',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{#MACRO6}test%%%', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{#MACRO7}test%%%', 0, ['lldmacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com{#MACRO7}test',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'z{$A}{#B}{#B}i{$X}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'z{$A}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'z{$A}%%i{$X}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'z{$A}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'zabbix.com{HOST.HOST}', 0, ['macros' => []],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'zabbix.com',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => ['{HOST.HOST}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}',
					'type' => INTERFACE_USE_DNS
				]
			],
			[
				'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste%%%%', 0, ['usermacros' => true, 'lldmacros' => true, 'macros' => ['{HOST.HOST}', '{HOST.DNS}']],
				[
					'rc' => CParser::PARSE_SUCCESS_CONT,
					'match' => 'testa{HOST.HOST}testb{$MACRO1}{{#M}.regsub("^([0-9]+)", "{#M}: \1")}{HOST.DNS}testc{$MACRO2}testd{#MACRO3}teste',
					'type' => INTERFACE_USE_DNS
				]
			],

			// IPv4 tests - fail.
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],

			// IPv6 tests - fail.
			[
				'::', 0, ['v6' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],

			// DNS tests - fail.
			[
				'', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'.a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'-a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'_a', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'%%%z{$A}bbi{$X}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => []],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],

			// Macros tests - fail.
			[
				'{#MACRO}', 0, ['usermacros' => true],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'{{#M}.regsub("^([0-9]+)", "{#M}: \1")}', 0, [],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => []],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			],
			[
				'{HOST.HOST}', 0, ['macros' => false],
				[
					'rc' => CParser::PARSE_FAIL,
					'match' => '',
					'type' => null
				]
			]
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
		$address_parser = new CAddressParser($options);

		$this->assertSame($expected, [
			'rc' => $address_parser->parse($source, $pos),
			'match' => $address_parser->getMatch(),
			'type' => $address_parser->getAddressType()
		]);
		$this->assertSame(strlen($expected['match']), $address_parser->getLength());
	}
}
