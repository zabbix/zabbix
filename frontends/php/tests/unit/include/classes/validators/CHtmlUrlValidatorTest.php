<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CHtmlUrlValidatorTest extends PHPUnit_Framework_TestCase {

	public function providerValidateURL() {
		return [
			['http://zabbix.com',				true],
			['https://zabbix.com',				true],
			['zabbix.php?a=1',					true],
			['adm.images.php?a=1',				true],
			['chart_bar.php?a=1&b=2',			true],
			['mailto:example@example.com',		true],
			['file://localhost/path',			true],
			['ftp://user@host:port',			true],
			['tel:1-111-111-1111',				true],
			['ssh://username@hostname:/path ',	true],
			['',								false],
			['javascript:alert(]',				false],
			['/chart_bar.php?a=1&b=2',			false],
			['vbscript:msgbox(]',				false],
			['../././not_so_zabbix',			false],
			['jav&#x09;ascript:alert(1];', 		false]
		];
	}

	/**
	 * @dataProvider providerValidateURL
	 */
	public function test_validateURL($url, $expected) {
		$this->assertEquals(CHtmlUrlValidator::validate($url), $expected);
	}
}
