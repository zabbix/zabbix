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
 * Class containing methods to test CIPRangeParser class functionality.
 */
use PHPUnit\Framework\TestCase;

class CIPRangeParserTest extends TestCase {

	public function dataProvider() {
		return [
			[
				'{$MACRO}', ['usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{{$M}.regsub("^([0-9]+)", \1)}', ['usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				"0.0.0.0,255.255.255.255 \t\r\n,\t\r\n 192.168.1.0,2002:0:0:0:0:0:0:0,2002:0:0:0:0:0:ffff:ffff,www.zabbix.com", [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '0.0.0.0'
				]
			],
			[
				'www.zabbix.com', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'www.zabbix.com'
				]
			],
			[
				'www.zabbix.com,bad.dns-', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'www.zabbix.com'
				]
			],
			[
				'Zabbix server', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "server"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0/0', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '4294967296',
					'max_ip_range' => '0.0.0.0/0'
				]
			],
			[
				'0.0.0.0/30', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '4',
					'max_ip_range' => '0.0.0.0/30'
				]
			],
			[
				'192.168.255.0/30', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '4',
					'max_ip_range' => '192.168.255.0/30'
				]
			],
			[
				'192.168.0-255.0-255', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '192.168.0-255.0-255'
				]
			],
			[
				'0-255.0-255.0-255.0-255', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '4294967296',
					'max_ip_range' => '0-255.0-255.0-255.0-255'
				]
			],
			[
				'192.168.0.0/16,192.168.0.1', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '192.168.0.0/16'
				]
			],
			[
				'127.0.0.1', ['ranges' => false, 'dns' => false], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '127.0.0.1'
				]
			],
			[
				'{$M}', ['dns' => false, 'usermacros' => true], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0.1-127,127.0.0.1', ['ranges' => false, 'dns' => false], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "192.168.0.1-127,127.0.0.1"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0.1-127,192.168.2.1', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '127',
					'max_ip_range' => '192.168.0.1-127'
				]
			],
			[
				' 192.168.0.2 , 192.168.1-127.0  ,  192.168.255.0/16  ', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '192.168.255.0/16'
				]
			],
			[
				'2001:db8:3333:4444:CCCC:DDDD:EEEE:FFFF', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '2001:db8:3333:4444:CCCC:DDDD:EEEE:FFFF'
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0/128', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'fe80:0:0:0:0:0:c0a8:0/128'
				]
			],
			[
				'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '340282366920938463463374607431768211456',
					'max_ip_range' => 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0'
				]
			],
			[
				'::', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '::'
				]
			],
			[
				'fe80::c0a8:0/112', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => 'fe80::c0a8:0/112'
				]
			],
			[
				'fe80::c0a8:0/112', ['v6' => false], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "::c0a8:0/112"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'fe80::c0a8:0/128', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'fe80::c0a8:0/128'
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0-ff', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '256',
					'max_ip_range' => 'fe80:0:0:0:0:0:c0a8:0-ff'
				]
			],
			[
				'fe80::c0a8:0-ff', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '256',
					'max_ip_range' => 'fe80::c0a8:0-ff'
				]
			],
			[
				'0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '340282366920938463463374607431768211456',
					'max_ip_range' => '0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff'
				]
			],
			[
				' fe80::c0a8:100 , fe80::c0a8:0-ff:1  ,  fe80::c0a8:0:1/112  ', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => 'fe80::c0a8:0:1/112'
				]
			],
			[
				'255.255.255.254/30', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '4',
					'max_ip_range' => '255.255.255.254/30'
				]
			],
			[
				'255.255.0.0/16', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '255.255.0.0/16'
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0/112', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => 'fe80:0:0:0:0:0:c0a8:0/112'
				]
			],
			[
				'255.254.0.0/15', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '131072',
					'max_ip_range' => '255.254.0.0/15'
				]
			],
			[
				'255.252.0.0/14', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '262144',
					'max_ip_range' => '255.252.0.0/14'
				]
			],
			[
				'255.248.0.0/13', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '524288',
					'max_ip_range' => '255.248.0.0/13'
				]
			],
			[
				'255.240.0.0/12', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1048576',
					'max_ip_range' => '255.240.0.0/12'
				]
			],
			[
				'255.224.0.0/11', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '2097152',
					'max_ip_range' => '255.224.0.0/11'
				]
			],
			[
				'255.192.0.0/10', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '4194304',
					'max_ip_range' => '255.192.0.0/10'
				]
			],
			[
				'255.128.0.0/9', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '8388608',
					'max_ip_range' => '255.128.0.0/9'
				]
			],
			[
				'255.0.0.0/8', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '16777216',
					'max_ip_range' => '255.0.0.0/8'
				]
			],
			[
				'64.0.0.0/4', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '268435456',
					'max_ip_range' => '64.0.0.0/4'
				]
			],
			[
				'0.0.0.0/1', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '2147483648',
					'max_ip_range' => '0.0.0.0/1'
				]
			],
			[
				"192.168.1.1-2\t\r\n,\t\r\n192.168.1.2-3", [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '2',
					'max_ip_range' => '192.168.1.1-2'
				]
			],
			[
				'::000ff-ffff', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "f-ffff"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'::ff-0ffff', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "f"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0000-255', ['dns' => false], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "0-255"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0-0255', ['dns' => false], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "5"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0/024', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "/024"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0-255.0/30', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "/30"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0-255.0-255/16-30', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "/16-30"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{$A}', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "{$A}"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'321.654.987.456', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '321.654.987.456'
				]
			],
			[
				'321.654.987.456', ['dns' => false], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "321.654.987.456"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'321.654.987.456-456', [], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '321.654.987.456-456'
				]
			],
			[
				'192.168.443.0/432', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "/432"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0/129', [], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "/129"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{HOST.HOST}', ['macros' => ['{HOST.HOST}']], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{{HOST.HOST}.regsub("(\d+)", \1)}', ['macros' => ['{HOST.HOST}']], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{HOST.IP}', ['macros' => ['{HOST.IP}', '{HOST.HOST}']], [
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{HOST.HOST1}', ['macros' => ['{HOST.HOST}']], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "{HOST.HOST1}"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0,{HOST.IP},{HOST.DNS},1.1.1.1-2.2.2.2,{HOST.CONN},fe80::c0a8:100,{HOST.HOST},{HOST.NAME},{$MACRO}', [
					'usermacros' => true,
					'macros' => ['{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.HOST}', '{HOST.NAME}']
				],
				[
					'rc' => CParser::PARSE_SUCCESS,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '0.0.0.0'
				]
			],
			[
				'{HOST.IP}', ['macros' => ['{HOST.DNS}']], [
					'rc' => CParser::PARSE_FAIL,
					'error' => 'incorrect address starting from "{HOST.IP}"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
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
		$iprange_parser = new CIPRangeParser($options);

		$this->assertSame($expected, [
			'rc' => $iprange_parser->parse($source),
			'error' => $iprange_parser->getError(),
			'max_ip_count' => $iprange_parser->getMaxIPCount(),
			'max_ip_range' => $iprange_parser->getMaxIPRange()
		]);
	}

}
