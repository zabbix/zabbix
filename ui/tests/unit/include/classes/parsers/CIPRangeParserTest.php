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


/**
 * Class containing methods to test CIPRangeParser class functionality.
 */
use PHPUnit\Framework\TestCase;

class CIPRangeParserTest extends TestCase {

	public function dataProvider() {
		return [
			[
				'{$MACRO}', ['usermacros' => true], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				"0.0.0.0,255.255.255.255 \t\r\n,\t\r\n 192.168.1.0,2002:0:0:0:0:0:0:0,2002:0:0:0:0:0:ffff:ffff,www.zabbix.com", [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '0.0.0.0'
				]
			],
			[
				'www.zabbix.com', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'www.zabbix.com'
				]
			],
			[
				'www.zabbix.com,bad.dns-', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'www.zabbix.com'
				]
			],
			[
				'0.0.0.0/0', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '4294967296',
					'max_ip_range' => '0.0.0.0/0'
				]
			],
			[
				'0.0.0.0/30', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '4',
					'max_ip_range' => '0.0.0.0/30'
				]
			],
			[
				'192.168.255.0/30', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '4',
					'max_ip_range' => '192.168.255.0/30'
				]
			],
			[
				'192.168.0-255.0-255', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '192.168.0-255.0-255'
				]
			],
			[
				'0-255.0-255.0-255.0-255', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '4294967296',
					'max_ip_range' => '0-255.0-255.0-255.0-255'
				]
			],
			[
				'192.168.0.0/16,192.168.0.1', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '192.168.0.0/16'
				]
			],
			[
				'127.0.0.1', ['ranges' => false, 'dns' => false], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '127.0.0.1'
				]
			],
			[
				'192.168.0.1-127,127.0.0.1', ['ranges' => false, 'dns' => false], [
					'rc' => false,
					'error' => 'invalid address range "192.168.0.1-127"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0.1-127,192.168.2.1', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '127',
					'max_ip_range' => '192.168.0.1-127'
				]
			],
			[
				' 192.168.0.2 , 192.168.1-127.0  ,  192.168.255.0/16  ', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '192.168.255.0/16'
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0/128', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'fe80:0:0:0:0:0:c0a8:0/128'
				]
			],
			[
				'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '340282366920938463463374607431768211456',
					'max_ip_range' => 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0'
				]
			],
			[
				'fe80::c0a8:0/112', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => 'fe80::c0a8:0/112'
				]
			],
			[
				'fe80::c0a8:0/112', ['v6' => false], [
					'rc' => false,
					'error' => 'invalid address range "fe80::c0a8:0/112"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'fe80::c0a8:0/128', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => 'fe80::c0a8:0/128'
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0-ff', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '256',
					'max_ip_range' => 'fe80:0:0:0:0:0:c0a8:0-ff'
				]
			],
			[
				'fe80::c0a8:0-ff', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '256',
					'max_ip_range' => 'fe80::c0a8:0-ff'
				]
			],
			[
				'0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '340282366920938463463374607431768211456',
					'max_ip_range' => '0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff'
				]
			],
			[
				' fe80::c0a8:100 , fe80::c0a8:0-ff:1  ,  fe80::c0a8:0:1/112  ', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => 'fe80::c0a8:0:1/112'
				]
			],
			[
				'255.255.255.254/30', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '4',
					'max_ip_range' => '255.255.255.254/30'
				]
			],
			[
				'255.255.0.0/16', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => '255.255.0.0/16'
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0/112', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '65536',
					'max_ip_range' => 'fe80:0:0:0:0:0:c0a8:0/112'
				]
			],
			[
				'255.254.0.0/15', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '131072',
					'max_ip_range' => '255.254.0.0/15'
				]
			],
			[
				'255.252.0.0/14', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '262144',
					'max_ip_range' => '255.252.0.0/14'
				]
			],
			[
				'255.248.0.0/13', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '524288',
					'max_ip_range' => '255.248.0.0/13'
				]
			],
			[
				'255.240.0.0/12', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1048576',
					'max_ip_range' => '255.240.0.0/12'
				]
			],
			[
				'255.224.0.0/11', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '2097152',
					'max_ip_range' => '255.224.0.0/11'
				]
			],
			[
				'255.192.0.0/10', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '4194304',
					'max_ip_range' => '255.192.0.0/10'
				]
			],
			[
				'255.128.0.0/9', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '8388608',
					'max_ip_range' => '255.128.0.0/9'
				]
			],
			[
				'255.0.0.0/8', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '16777216',
					'max_ip_range' => '255.0.0.0/8'
				]
			],
			[
				'64.0.0.0/4', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '268435456',
					'max_ip_range' => '64.0.0.0/4'
				]
			],
			[
				'0.0.0.0/1', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '2147483648',
					'max_ip_range' => '0.0.0.0/1'
				]
			],
			[
				"192.168.1.1-2\t\r\n,\t\r\n192.168.1.2-3", [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '2',
					'max_ip_range' => '192.168.1.1-2'
				]
			],
			[
				'::000ff-ffff', [], [
					'rc' => false,
					'error' => 'invalid address range "::000ff-ffff"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'::ff-0ffff', [], [
					'rc' => false,
					'error' => 'invalid address range "::ff-0ffff"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0000-255', ['dns' => false], [
					'rc' => false,
					'error' => 'invalid address range "0.0.0.0000-255"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0-0255', ['dns' => false], [
					'rc' => false,
					'error' => 'invalid address range "0.0.0.0-0255"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'0.0.0.0/024', [], [
					'rc' => false,
					'error' => 'invalid address range "0.0.0.0/024"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0-255.0/30', [], [
					'rc' => false,
					'error' => 'invalid address range "192.168.0-255.0/30"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'192.168.0-255.0-255/16-30', [], [
					'rc' => false,
					'error' => 'invalid address range "192.168.0-255.0-255/16-30"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{$A}', [], [
					'rc' => false,
					'error' => 'invalid address range "{$A}"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'321.654.987.456', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '321.654.987.456'
				]
			],
			[
				'321.654.987.456', ['dns' => false], [
					'rc' => false,
					'error' => 'invalid address range "321.654.987.456"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'321.654.987.456-456', [], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '321.654.987.456-456'
				]
			],
			[
				'192.168.443.0/432', [], [
					'rc' => false,
					'error' => 'invalid address range "192.168.443.0/432"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'fe80:0:0:0:0:0:c0a8:0/129', [], [
					'rc' => false,
					'error' => 'invalid address range "fe80:0:0:0:0:0:c0a8:0/129"',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{HOST.HOST}', ['macros' => ['{HOST.HOST}']], [
					'rc' => true,
					'error' => '',
					'max_ip_count' => '0',
					'max_ip_range' => ''
				]
			],
			[
				'{HOST.HOST1}', ['macros' => ['{HOST.HOST}']], [
					'rc' => false,
					'error' => 'invalid address range "{HOST.HOST1}"',
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
					'rc' => true,
					'error' => '',
					'max_ip_count' => '1',
					'max_ip_range' => '0.0.0.0'
				]
			],
			[
				'{HOST.IP}', ['macros' => ['{HOST.DNS}']], [
					'rc' => false,
					'error' => 'invalid address range "{HOST.IP}"',
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
