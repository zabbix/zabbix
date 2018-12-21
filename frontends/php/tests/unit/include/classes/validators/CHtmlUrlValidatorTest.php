<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			['http://zabbix.com',				true, false, true],
			['https://zabbix.com',				true, false, true],
			['zabbix.php?a=1',					true, false, true],
			['adm.images.php?a=1',				true, false, true],
			['chart_bar.php?a=1&b=2',			true, false, true],
			['mailto:example@example.com',		true, false, true],
			['file://localhost/path',			true, false, true],
			['ftp://user@host:21',				true, false, true],
			['tel:1-111-111-1111',				true, false, true],
			['ssh://username@hostname:/path ',	true, false, true],
			['{$USER_URL_MACRO}',				true, false, true],
			['{$USER_URL_MACRO}?a=1',			true, false, true],
			['http://{$USER_URL_MACRO}?a=1',	true, false, true],
			['http://{$USER_URL_MACRO}',		true, false, true],
			['ftp://user@host:21',				true, false, true],
			['{$USER_URL_MACRO}',				false, false, false],
			['protocol://{$INVALID!MACRO}',		true, false, false],
			['',								true, false, false],
			['javascript:alert(]',				true, false, false],
			['/chart_bar.php?a=1&b=2',			true, false, false],
			['ftp://user@host:port',			true, false, false],
			['vbscript:msgbox(]',				true, false, false],
			['../././not_so_zabbix',			true, false, false],
			['jav&#x09;ascript:alert(1];', 		true, false, false],
			['{INVENTORY.URL.A}',				false, true, true],
			['{INVENTORY.URL.A}',				false, false, false],
			['http://localhost?host={HOST.NAME}', false, false, true]
		];
	}

	/**
	 * @dataProvider providerValidateURL
	 */
	public function test_validateURL($url, $allow_user_macro, $allow_inventory_macro, $expected) {
		$this->assertEquals(CHtmlUrlValidator::validate($url, $allow_user_macro, $allow_inventory_macro), $expected);
	}
}
