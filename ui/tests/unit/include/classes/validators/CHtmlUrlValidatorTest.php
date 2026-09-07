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

class CHtmlUrlValidatorTest extends TestCase {

	public function dataProviderValidateSameSiteURL() {
		return [
			['zabbix.php',									true],
			['zabbix.php?',									true],
			['zabbix.php?action=host.list',					true],
			['zabbix.php?action=item.list&context=host',	true],
			['zabbix.php?action=host.list#id=12345',		true],
			['zabbix.php?action=item.list&context=host&filter_hostids%5B%5D=10605',	true],

			['items1.php',								false],
			['items.html',								false],
			['zabbix.php&itemids=12345',				false],
			['http://www.zabbix.com/zabbix.php',		false],
			['http://www.zabbix.com',					false],
			['www.zabbix.com',							false],
			['zabbix.com',								false]
		];
	}

	/**
	 * @dataProvider dataProviderValidateSameSiteURL
	 */
	public function testValidateSameSiteURL($url, $expected) {
		$this->assertSame($expected, CHtmlUrlValidator::validateSameSite($url));
	}
}
