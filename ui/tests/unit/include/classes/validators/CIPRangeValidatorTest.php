<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

class CIPRangeValidatorTest extends TestCase {
	public function dataProvider() {
		return [
			['{$MACRO}', ['usermacros' => true], true],
			['{{$M}.regsub("^([0-9]+)", \1)}', ['usermacros' => true], true],
			["0.0.0.0,255.255.255.255 \t\r\n,\t\r\n 192.168.1.0,2002:0:0:0:0:0:0:0,2002:0:0:0:0:0:ffff:ffff,www.zabbix.com", [], true],
			['www.zabbix.com', [], true],
			['www.zabbix.com,bad.dns-', [], true],
			['Zabbix server', [], false],
			['0.0.0.0/0', [], true],
			['0.0.0.0/30', [], true],
			['192.168.255.0/30', [], true],
			['192.168.0-255.0-255', [], true],
			['0-255.0-255.0-255.0-255', [], true],
			['192.168.0.0/16,192.168.0.1', [], true],
			['127.0.0.1', ['ranges' => false, 'dns' => false], true],
			['{$M}', ['dns' => false, 'usermacros' => true], true],
			['192.168.0.1-127,127.0.0.1', ['ranges' => false, 'dns' => false], false],
			['192.168.0.1-127,192.168.2.1', [], true],
			[' 192.168.0.2 , 192.168.1-127.0  ,  192.168.255.0/16  ', [], true],
			['2001:db8:3333:4444:CCCC:DDDD:EEEE:FFFF', [], true],
			['fe80:0:0:0:0:0:c0a8:0/128', [], true],
			['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0', [], true],
			['::', [], true],
			['fe80::c0a8:0/112', [], true],
			['fe80::c0a8:0/112', ['v6' => false], false],
			['fe80::c0a8:0/128', [], true],
			['fe80:0:0:0:0:0:c0a8:0-ff', [], true],
			['fe80::c0a8:0-ff', [], true],
			['0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff', [], true],
			[' fe80::c0a8:100 , fe80::c0a8:0-ff:1  ,  fe80::c0a8:0:1/112  ', [], true],
			['255.255.255.254/30', [], true],
			['255.255.0.0/16', [], true],
			['fe80:0:0:0:0:0:c0a8:0/112', [], true],
			['255.254.0.0/15', [], true],
			['255.252.0.0/14', [], true],
			['255.248.0.0/13', [], true],
			['255.240.0.0/12', [], true],
			['255.224.0.0/11', [], true],
			['255.192.0.0/10', [], true],
			['255.128.0.0/9', [], true],
			['255.0.0.0/8', [], true],
			['64.0.0.0/4', [], true],
			['0.0.0.0/1', [], true],
			["192.168.1.1-2\t\r\n,\t\r\n192.168.1.2-3", [], true],
			['::000ff-ffff', [], false],
			['::ff-0ffff', [], false],
			['0.0.0.0000-255', ['dns' => false], false],
			['0.0.0.0-0255', ['dns' => false], false],
			['0.0.0.0/024', [], false],
			['192.168.0-255.0/30', [], false],
			['192.168.0-255.0-255/16-30', [], false],
			['{$A}', [], false],
			['321.654.987.456', [], true],
			['321.654.987.456', ['dns' => false], false],
			['321.654.987.456-456', [], true],
			['192.168.443.0/432', [], false],
			['fe80:0:0:0:0:0:c0a8:0/129', [], false],
			['{HOST.HOST}', ['macros' => ['{HOST.HOST}']], true],
			['{{HOST.HOST}.regsub("(\d+)", \1)}', ['macros' => ['{HOST.HOST}']], true],
			['{HOST.IP}', ['macros' => ['{HOST.IP}', '{HOST.HOST}']], true],
			['{HOST.HOST1}', ['macros' => ['{HOST.HOST}']], false],
			['0.0.0.0,{HOST.IP},{HOST.DNS},1.1.1.1-2.2.2.2,{HOST.CONN},fe80::c0a8:100,{HOST.HOST},{HOST.NAME},{$MACRO}', [
				'usermacros' => true,
				'macros' => ['{HOST.IP}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.HOST}', '{HOST.NAME}']
			], true],
			['{HOST.IP}', ['macros' => ['{HOST.DNS}']], false],
			['192.168.0.1-3', ['max' => 2], false],
			['192.168.0.1', ['max' => 1], true],
			['192.168.0.1-2', ['max' => 2], true],
			['192.168.0.1-3', ['max' => 2], false],
			['192.168.0.0/30', ['max' => 4], true],
			['192.168.0.0/30', ['max' => 3], false],
			['192.168.0.1-2,192.168.1.1-3', ['max' => 3], true],
			['192.168.0.1-2,192.168.1.1-4', ['max' => 3], false],
			['www.zabbix.com', [
				'parser_options' => ['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30],
				'max' => 100
			], false],
			['192.168.0.0/29', [
				'parser_options' => ['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30],
				'max' => 7
			], false],
			['192.168.0.0/30', [
				'parser_options' => ['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30],
				'max' => 4
			], true]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param array  $options
	 * @param bool   $expected
	 */
	public function testValidate($source, $options, $expected): void {
		$validator = new CIPRangeValidator($options);

		$result = $validator->validate($source);
		$error = $validator->getError();

		$this->assertSame($expected, $result, (string) $error);
	}
}
