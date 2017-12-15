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

	// Expected results are defined assuming that VALIDATE_URI_SCHEMES is enabled (set to be true).
	public function providerValidateURL() {
		return [
			['http://zabbix.com',				true, true],
			['https://zabbix.com',				true, true],
			['zabbix.php?a=1',					true, true],
			['adm.images.php?a=1',				true, true],
			['chart_bar.php?a=1&b=2',			true, true],
			['mailto:example@example.com',		true, true],
			['file://localhost/path',			true, true],
			['ftp://user@host:21',				true, true],
			['tel:1-111-111-1111',				true, true],
			['ssh://username@hostname:/path ',	true, true],
			['{$USER_URL_MACRO}',				true, true],
			['{$USER_URL_MACRO}?a=1',			true, true],
			['http://{$USER_URL_MACRO}?a=1',	true, true],
			['http://{$USER_URL_MACRO}',		true, true],
			['ftp://user@host:21',				true, true],
			['{$USER_URL_MACRO}',				false, false],
			['protocol://{$INVALID!MACRO}',		true, false],
			['',								true, false],
			['javascript:alert(]',				true, false],
			['/chart_bar.php?a=1&b=2',			true, false],
			['ftp://user@host:port',			true, false],
			['vbscript:msgbox(]',				true, false],
			['../././not_so_zabbix',			true, false],
			['jav&#x09;ascript:alert(1];', 		true, false]
		];
	}

	/**
	 * @dataProvider providerValidateURL
	 */
	public function test_validateURL($url, $allow_user_macro, $expected) {
		$this->assertEquals(CHtmlUrlValidator::validate($url, $allow_user_macro), $expected);
	}
}
