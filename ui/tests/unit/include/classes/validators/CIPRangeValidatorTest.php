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
	public function dataProvider(): array {
		return [
			["0.0.0.0,255.255.255.255 \t\r\n,\t\r\n 192.168.1.0,2002:0:0:0:0:0:0:0,2002:0:0:0:0:0:ffff:ffff,www.zabbix.com", [],
				'incorrect address starting from "2002:0:0:0:0:0:0:0,2002:0:0:0:0:0:ffff:ffff,www.zabbix.com"'
			],
			['www.zabbix.com', [], 'incorrect address starting from "www.zabbix.com"'],
			['www.zabbix.com,bad.dns-', [], 'incorrect address starting from "www.zabbix.com,bad.dns-"'],
			['Zabbix server', [], 'incorrect address starting from "Zabbix server"'],
			['0.0.0.0/0', [], null],
			['0.0.0.0/30', [], null],
			['192.168.255.0/30', [], null],
			['192.168.0-255.0-255', [], null],
			['0-255.0-255.0-255.0-255', [], null],
			['192.168.0.0/16,192.168.0.1', [], null],
			['192.168.0.1-127,192.168.2.1', [], null],
			[' 192.168.0.2 , 192.168.1-127.0  ,  192.168.255.0/16  ', [], null],
			['2001:db8:3333:4444:CCCC:DDDD:EEEE:FFFF', [],
				'incorrect address starting from "2001:db8:3333:4444:CCCC:DDDD:EEEE:FFFF"'
			],
			['fe80:0:0:0:0:0:c0a8:0/128', [], 'incorrect address starting from "fe80:0:0:0:0:0:c0a8:0/128"'],
			['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0', [],
				'incorrect address starting from "ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/0"'
			],
			['::', [], 'incorrect address starting from "::"'],
			['fe80::c0a8:0/112', [], 'incorrect address starting from "fe80::c0a8:0/112"'],
			['fe80::c0a8:0/112', ['v6' => false], 'incorrect address starting from "fe80::c0a8:0/112"'],
			['fe80::c0a8:0/128', [], 'incorrect address starting from "fe80::c0a8:0/128"'],
			['fe80:0:0:0:0:0:c0a8:0-ff', [], 'incorrect address starting from "fe80:0:0:0:0:0:c0a8:0-ff"'],
			['fe80::c0a8:0-ff', [], 'incorrect address starting from "fe80::c0a8:0-ff"'],
			['0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff', [],
				'incorrect address starting from "0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff:0000-ffff"'
			],
			[' fe80::c0a8:100 , fe80::c0a8:0-ff:1  ,  fe80::c0a8:0:1/112  ', [],
				'incorrect address starting from "fe80::c0a8:100 , fe80::c0a8:0-ff:1  ,  fe80::c0a8:0:1/112  "'
			],
			['255.255.255.254/30', [], null],
			['255.255.0.0/16', [], null],
			['fe80:0:0:0:0:0:c0a8:0/112', [], 'incorrect address starting from "fe80:0:0:0:0:0:c0a8:0/112"'],
			['255.254.0.0/17', [], null],
			['255.254.0.0/16', [], null],
			['255.254.0.0/15', [], null],
			['255.252.0.0/14', [], null],
			['255.248.0.0/13', [], null],
			['255.240.0.0/12', [], null],
			['255.224.0.0/11', [], null],
			['255.192.0.0/10', [], null],
			['255.128.0.0/9', [], null],
			['255.0.0.0/8', [], null],
			['64.0.0.0/4', [], null],
			['0.0.0.0/1', [], null],
			["192.168.1.1-2\t\r\n,\t\r\n192.168.1.2-3", [], null],
			['::000ff-ffff', [], 'incorrect address starting from "::000ff-ffff"'],
			['::ff-0ffff', [], 'incorrect address starting from "::ff-0ffff"'],
			['0.0.0.0000-255', ['dns' => false], 'incorrect address starting from "0-255"'],
			['0.0.0.0-0255', ['dns' => false], 'incorrect address starting from "5"'],
			['0.0.0.0/024', [], 'incorrect address starting from "/024"'],
			['192.168.0-255.0/30', [], 'incorrect address starting from "/30"'],
			['192.168.0-255.0-255/16-30', [], 'incorrect address starting from "/16-30"'],
			['{$A}', [], 'incorrect address starting from "{$A}"'],
			['321.654.987.456', [], 'incorrect address starting from "321.654.987.456"'],
			['321.654.987.456-456', [], 'incorrect address starting from "321.654.987.456-456"'],
			['192.168.443.0/432', [], 'incorrect address starting from "192.168.443.0/432"'],
			['fe80:0:0:0:0:0:c0a8:0/129', [], 'incorrect address starting from "fe80:0:0:0:0:0:c0a8:0/129"'],
			['192.168.0.1-3', ['max' => 2], 'IP range "192.168.0.1-3" exceeds "2" address limit'],
			['192.168.0.1', ['max' => 1], null],
			['192.168.0.1-2', ['max' => 2], null],
			['192.168.0.1-3', ['max' => 2], 'IP range "192.168.0.1-3" exceeds "2" address limit'],
			['192.168.0.0/30', ['max' => 4], null],
			['192.168.0.0/30', ['max' => 3], 'IP range "192.168.0.0/30" exceeds "3" address limit'],
			['192.168.0.1-2,192.168.1.1-3', ['max' => 3], null],
			['192.168.0.1-2,192.168.1.1-4', ['max' => 3], 'IP range "192.168.1.1-4" exceeds "3" address limit'],
			['www.zabbix.com', ['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30],
				'incorrect address starting from "www.zabbix.com"'
			],
			['192.168.0.0/29', ['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => 7],
				'IP range "192.168.0.0/29" exceeds "7" address limit'
			],
			['192.168.0.0/30', ['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => 3],
				'IP range "192.168.0.0/30" exceeds "3" address limit'
			],
			['192.168.0.1-254',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				null
			],
			['192.168.0.156-155',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				'incorrect address starting from "192.168.0.156-155"'
			],
			['192.168.0.1-256',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				'incorrect address starting from "192.168.0.1-256"'
			],
			['192.168.0.1-254, 192.168.0.0-126, 192.168.0.1-2, 192.168.0.1-1, 192.168.172.1/18, 192.168.172.1/18',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				null
			],
			['192.168.0.1/31',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				'incorrect address starting from "/31"'
			],
			['192.168.0.1/30',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				null
			],
			['192.168.0.1/16',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				null
			],
			['192.168.0.1/15',
				['v6' => ZBX_HAVE_IPV6, 'dns' => false, 'max_ipv4_cidr' => 30, 'max' => ZBX_DISCOVERER_IPRANGE_LIMIT],
				'IP range "192.168.0.1/15" exceeds "65536" address limit'
			]
		];
	}

	/**
	 * @dataProvider dataProvider
	 *
	 * @param string $source
	 * @param array $options
	 * @param string|null $expected
	 */
	public function testValidate(string $source, array $options, ?string $expected): void {
		$validator = new CIPRangeValidator($options);

		$result = $validator->validate($source);

		$this->assertSame($expected === null, $result);
		$this->assertSame($expected, $validator->getError());
	}
}
